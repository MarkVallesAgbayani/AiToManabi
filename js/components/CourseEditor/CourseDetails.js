import React, { useEffect, useRef, useState, useCallback } from 'react';

const CourseDetails = ({ course, setCourse, setIsDirty }) => {
    const editorRef = useRef(null);
    const [errors, setErrors] = useState({});
    const [editorInitialized, setEditorInitialized] = useState(false);
    const [fallbackMode, setFallbackMode] = useState(false);

    // Memoize handleChange to prevent unnecessary re-renders
    const handleChange = useCallback((field, value) => {
        const error = validateField(field, value);
        setErrors(prev => ({ ...prev, [field]: error }));
        setCourse(prev => ({ ...prev, [field]: value }));
        setIsDirty(true);
    }, [setCourse, setIsDirty]);

    useEffect(() => {
        let timeoutId;
        
        const initTinyMCE = async () => {
            // Check if TinyMCE is available
            if (!window.tinymce) {
                console.warn('TinyMCE not available, falling back to textarea');
                setFallbackMode(true);
                return;
            }

            // Wait a bit for the DOM element to be ready
            timeoutId = setTimeout(async () => {
                const element = document.getElementById('description');
                if (!element) {
                    console.warn('Description element not found, retrying...');
                    return;
                }

                try {
                    // Remove any existing editor instance
                    const existingEditor = window.tinymce.get('description');
                    if (existingEditor) {
                        existingEditor.remove();
                    }

                    await window.tinymce.init({
                        selector: '#description',
                        height: 300,
                        license_key: 'gpl',
                        promotion: false,
                        branding: false,
                        menubar: false,
                        statusbar: true,
                        resize: 'both',
                        plugins: [
                            'advlist', 'autolink', 'lists', 'link', 'image', 'charmap', 'preview',
                            'anchor', 'searchreplace', 'visualblocks', 'code', 'fullscreen',
                            'insertdatetime', 'media', 'table', 'help', 'wordcount'
                        ],
                        toolbar: 'undo redo | blocks | bold italic backcolor | ' +
                                'alignleft aligncenter alignright alignjustify | ' +
                                'bullist numlist outdent indent | link image | removeformat | help',
                        content_style: `
                            body { 
                                font-family: system-ui, -apple-system, sans-serif; 
                                font-size: 14px; 
                                line-height: 1.6; 
                                color: #374151;
                                margin: 16px;
                            }
                        `,
                        setup: (editor) => {
                            editorRef.current = editor;
                            
                            editor.on('init', () => {
                                console.log('TinyMCE initialized successfully');
                                setEditorInitialized(true);
                                
                                // Set initial content if it exists
                                if (course.description) {
                                    editor.setContent(course.description);
                                }
                            });
                            
                            // Handle content changes
                            editor.on('change keyup paste input', () => {
                                const content = editor.getContent();
                                // Use setTimeout to prevent React state update conflicts
                                setTimeout(() => {
                                    handleChange('description', content);
                                }, 0);
                            });

                            // Handle blur to ensure content is saved
                            editor.on('blur', () => {
                                const content = editor.getContent();
                                handleChange('description', content);
                            });
                        },
                        init_instance_callback: (editor) => {
                            editorRef.current = editor;
                            setEditorInitialized(true);
                        }
                    });
                } catch (error) {
                    console.error('TinyMCE initialization failed:', error);
                    setFallbackMode(true);
                    setEditorInitialized(false);
                }
            }, 100);
        };

        initTinyMCE();

        return () => {
            if (timeoutId) {
                clearTimeout(timeoutId);
            }
            
            // Cleanup TinyMCE
            if (editorRef.current) {
                try {
                    editorRef.current.remove();
                } catch (error) {
                    console.error('Error removing TinyMCE:', error);
                }
                editorRef.current = null;
            }
            
            // Alternative cleanup
            const editor = window.tinymce?.get('description');
            if (editor) {
                try {
                    editor.remove();
                } catch (error) {
                    console.error('Error in alternative cleanup:', error);
                }
            }
            
            setEditorInitialized(false);
        };
    }, []); // Empty dependency array - only run on mount/unmount

    // Update editor content when course.description changes externally
    useEffect(() => {
        if (editorInitialized && editorRef.current && course.description !== undefined) {
            const currentContent = editorRef.current.getContent();
            if (currentContent !== course.description) {
                editorRef.current.setContent(course.description || '');
            }
        }
    }, [course.description, editorInitialized]);

    const validateField = (name, value) => {
        switch(name) {
            case 'title':
                return !value?.trim() ? 'Course title is required' : 
                       value.length < 5 ? 'Title must be at least 5 characters' : '';
            case 'description':
                // Remove HTML tags for length validation
                const plainText = value?.replace(/<[^>]*>/g, '').trim() || '';
                return !plainText ? 'Course description is required' : 
                       plainText.length < 20 ? 'Description must be at least 20 characters' : '';
            case 'category':
                return !value?.trim() ? 'Category is required' : '';
            case 'price':
                return isNaN(value) || Number(value) < 0 ? 'Price must be a valid non-negative number' : '';
            default:
                return '';
        }
    };

    const handleImageChange = (e) => {
        const file = e.target.files[0];
        if (file) {
            // Validate file size (10MB limit)
            if (file.size > 10 * 1024 * 1024) {
                setErrors(prev => ({ ...prev, image: 'Image size must be less than 10MB' }));
                return;
            }

            // Validate file type
            if (!file.type.startsWith('image/')) {
                setErrors(prev => ({ ...prev, image: 'Please select a valid image file' }));
                return;
            }

            // Clear any previous image errors
            setErrors(prev => ({ ...prev, image: '' }));

            const reader = new FileReader();
            reader.onload = (e) => {
                handleChange('image_preview', e.target.result);
                handleChange('image_file', file);
            };
            reader.readAsDataURL(file);
        }
    };

    return (
        <div className="bg-white shadow rounded-lg p-6">
            <div className="flex items-center mb-4">
                <div className="w-8 h-8 rounded-full bg-red-600 text-white flex items-center justify-center mr-3">
                    1
                </div>
                <h2 className="text-2xl font-bold text-gray-900">Course Details</h2>
            </div>

            <div className="space-y-6">
                <div>
                    <label htmlFor="title" className="block text-sm font-medium text-gray-700">
                        Course Title <span className="text-red-500">*</span>
                    </label>
                    <input
                        type="text"
                        id="title"
                        value={course.title || ''}
                        onChange={(e) => handleChange('title', e.target.value)}
                        className={`mt-1 block w-full rounded-md shadow-sm focus:border-red-500 focus:ring-red-500 ${
                            errors.title ? 'border-red-500' : 'border-gray-300'
                        }`}
                        placeholder="Enter course title"
                        required
                    />
                    {errors.title && (
                        <p className="mt-1 text-sm text-red-600">{errors.title}</p>
                    )}
                </div>

                <div>
                    <label htmlFor="description" className="block text-sm font-medium text-gray-700">
                        Course Description <span className="text-red-500">*</span>
                    </label>
                    {fallbackMode ? (
                        <textarea
                            id="description"
                            value={course.description || ''}
                            onChange={(e) => handleChange('description', e.target.value)}
                            className={`mt-1 block w-full rounded-md shadow-sm focus:border-red-500 focus:ring-red-500 ${
                                errors.description ? 'border-red-500' : 'border-gray-300'
                            }`}
                            rows="6"
                            placeholder="Enter course description"
                            required
                        />
                    ) : (
                        <div className="mt-1">
                            <textarea
                                id="description"
                                defaultValue={course.description || ''}
                                className={`block w-full rounded-md shadow-sm focus:border-red-500 focus:ring-red-500 ${
                                    errors.description ? 'border-red-500' : 'border-gray-300'
                                }`}
                                rows="6"
                                placeholder="Enter course description"
                                style={{ display: editorInitialized ? 'none' : 'block' }}
                            />
                            {!editorInitialized && (
                                <div className="mt-2 text-sm text-gray-500 flex items-center">
                                    <svg className="animate-spin -ml-1 mr-2 h-4 w-4 text-gray-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                                        <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    Loading rich text editor...
                                </div>
                            )}
                        </div>
                    )}
                    {errors.description && (
                        <p className="mt-1 text-sm text-red-600">{errors.description}</p>
                    )}
                    <p className="mt-1 text-xs text-gray-500">
                        {fallbackMode ? 'Rich text editor unavailable - using plain text' : 'Use the toolbar above to format your text'}
                    </p>
                </div>

                <div>
                    <label htmlFor="category" className="block text-sm font-medium text-gray-700">
                        Course Category <span className="text-red-500">*</span>
                    </label>
                    <input
                        type="text"
                        id="category"
                        value={course.category || ''}
                        onChange={(e) => handleChange('category', e.target.value)}
                        className={`mt-1 block w-full rounded-md shadow-sm focus:border-red-500 focus:ring-red-500 ${
                            errors.category ? 'border-red-500' : 'border-gray-300'
                        }`}
                        list="category-suggestions"
                        placeholder="Select or enter category"
                        required
                    />
                    {errors.category && (
                        <p className="mt-1 text-sm text-red-600">{errors.category}</p>
                    )}
                    <datalist id="category-suggestions">
                        <option value="Japanese Language" />
                        <option value="Japanese Culture" />
                        <option value="Business Japanese" />
                        <option value="JLPT Preparation" />
                    </datalist>
                </div>

                <div>
                    <label htmlFor="price" className="block text-sm font-medium text-gray-700">
                        Course Price
                    </label>
                    <div className="mt-1 relative rounded-md shadow-sm">
                        <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <span className="text-gray-500 sm:text-sm">$</span>
                        </div>
                        <input
                            type="number"
                            id="price"
                            min="0"
                            step="0.01"
                            value={course.price || '0.00'}
                            onChange={(e) => handleChange('price', e.target.value)}
                            className={`pl-7 block w-full rounded-md shadow-sm focus:border-red-500 focus:ring-red-500 ${
                                errors.price ? 'border-red-500' : 'border-gray-300'
                            }`}
                            placeholder="0.00"
                        />
                    </div>
                    {errors.price && (
                        <p className="mt-1 text-sm text-red-600">{errors.price}</p>
                    )}
                    <p className="mt-1 text-sm text-gray-500">Enter 0 for free courses</p>
                </div>

                <div>
                    <label className="block text-sm font-medium text-gray-700">
                        Course Image
                    </label>
                    <div className="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-md hover:border-gray-400 transition-colors">
                        <div className="space-y-1 text-center">
                            {(course.image_path || course.image_preview) ? (
                                <div className="relative">
                                    <img
                                        src={course.image_preview || `../${course.image_path}`}
                                        alt="Course preview"
                                        className="mx-auto h-48 w-auto rounded-lg shadow-md"
                                    />
                                    <button
                                        type="button"
                                        onClick={() => {
                                            handleChange('image_preview', '');
                                            handleChange('image_file', null);
                                        }}
                                        className="absolute top-2 right-2 bg-red-500 text-white rounded-full p-1 hover:bg-red-600 transition-colors"
                                    >
                                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </div>
                            ) : (
                                <div className="mx-auto h-48 w-48 flex items-center justify-center">
                                    <svg
                                        className="mx-auto h-12 w-12 text-gray-400"
                                        stroke="currentColor"
                                        fill="none"
                                        viewBox="0 0 48 48"
                                    >
                                        <path
                                            d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02"
                                            strokeWidth={2}
                                            strokeLinecap="round"
                                            strokeLinejoin="round"
                                        />
                                    </svg>
                                </div>
                            )}
                            <div className="flex text-sm text-gray-600 justify-center">
                                <label className="relative cursor-pointer bg-white rounded-md font-medium text-red-600 hover:text-red-500 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-red-500">
                                    <span>{course.image_preview || course.image_path ? 'Change image' : 'Upload a file'}</span>
                                    <input
                                        type="file"
                                        className="sr-only"
                                        accept="image/*"
                                        onChange={handleImageChange}
                                    />
                                </label>
                            </div>
                            <p className="text-xs text-gray-500">PNG, JPG, GIF up to 10MB</p>
                            {errors.image && (
                                <p className="text-sm text-red-600">{errors.image}</p>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
};

export default CourseDetails;
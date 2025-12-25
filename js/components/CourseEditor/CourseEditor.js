// Import React and dependencies from window object since we're using UMD bundles
const { useState, useEffect } = React;

// Import DnD components from window object
const { 
    DndContext, 
    closestCenter, 
    KeyboardSensor, 
    PointerSensor, 
    useSensor, 
    useSensors,
    arrayMove,
    SortableContext,
    sortableKeyboardCoordinates,
    verticalListSortingStrategy,
    useSortable,
    restrictToVerticalAxis
} = window.DndKit;

// Navigation Component
const Navigation = ({ teacherName, isDirty, onSave, onPublish }) => {
    useEffect(() => {
        const handleBeforeUnload = (e) => {
            if (isDirty) {
                e.preventDefault();
                e.returnValue = '';
            }
        };

        window.addEventListener('beforeunload', handleBeforeUnload);
        return () => window.removeEventListener('beforeunload', handleBeforeUnload);
    }, [isDirty]);

    return (
        <nav className="bg-white shadow-lg">
            <div className="max-w-7xl mx-auto px-4">
                <div className="flex justify-between h-16">
                    <div className="flex">
                        <div className="flex-shrink-0 flex items-center">
                            <a href="teacher_courses.php" className="text-2xl font-bold text-red-600">
                                ← Back to Courses
                            </a>
                        </div>
                    </div>
                    <div className="flex items-center space-x-4">
                        <span className="text-gray-700">Welcome, {teacherName}</span>
                        {isDirty && (
                            <span className="text-yellow-600 text-sm">
                                You have unsaved changes
                            </span>
                        )}
                        <button
                            onClick={onSave}
                            className="px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50"
                        >
                            Save Draft
                        </button>
                        <button
                            onClick={onPublish}
                            className="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-red-600 hover:bg-red-700"
                        >
                            Publish
                        </button>
                    </div>
                </div>
            </div>
        </nav>
    );
};

// CourseDetails Component
const CourseDetails = ({ course, setCourse, setIsDirty }) => {
    const handleChange = (field, value) => {
        setCourse(prev => ({ ...prev, [field]: value }));
        setIsDirty(true);
    };

    const handleImageChange = (e) => {
        const file = e.target.files[0];
        if (file) {
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
                        Course Title
                    </label>
                    <input
                        type="text"
                        id="title"
                        value={course.title || ''}
                        onChange={(e) => handleChange('title', e.target.value)}
                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-red-500 focus:ring-red-500"
                        required
                    />
                </div>

                <div>
                    <label htmlFor="description" className="block text-sm font-medium text-gray-700 mb-2">
                        Course Description
                    </label>
                    <DescriptionBuilder
                        initialValue={course.description || ''}
                        onChange={(value) => handleChange('description', value)}
                    />
                </div>

                <div>
                    <label htmlFor="category" className="block text-sm font-medium text-gray-700">
                        Course Category
                    </label>
                    <input
                        type="text"
                        id="category"
                        value={course.category || ''}
                        onChange={(e) => handleChange('category', e.target.value)}
                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-red-500 focus:ring-red-500"
                        list="category-suggestions"
                        required
                    />
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
                            className="pl-7 block w-full rounded-md border-gray-300 shadow-sm focus:border-red-500 focus:ring-red-500"
                            required
                        />
                    </div>
                </div>

                <div>
                    <label className="block text-sm font-medium text-gray-700">
                        Course Image
                    </label>
                    <div className="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-md">
                        <div className="space-y-1 text-center">
                            {(course.image_path || course.image_preview) ? (
                                <img
                                    src={course.image_preview || `../${course.image_path}`}
                                    alt="Course preview"
                                    className="mx-auto h-48 w-auto"
                                />
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
                                    <span>Upload a file</span>
                                    <input
                                        type="file"
                                        className="sr-only"
                                        accept="image/*"
                                        onChange={handleImageChange}
                                    />
                                </label>
                            </div>
                            <p className="text-xs text-gray-500">PNG, JPG, GIF up to 10MB</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
};

// Main CourseEditor Component
const CourseEditor = ({ initialData }) => {
    const [course, setCourse] = useState(initialData.course || {});
    const [chapters, setChapters] = useState([]);
    const [sections, setSections] = useState([]);
    const [isDirty, setIsDirty] = useState(false);
    const [status, setStatus] = useState('draft');

    const sensors = useSensors(
        useSensor(PointerSensor),
        useSensor(KeyboardSensor, {
            coordinateGetter: sortableKeyboardCoordinates,
        })
    );

    useEffect(() => {
        if (initialData.mode === 'edit') {
            fetchCourseData();
        }
    }, []);

    const fetchCourseData = async () => {
        try {
            const response = await fetch(`api/courses/${initialData.courseId}`);
            const data = await response.json();
            setChapters(data.chapters);
            setSections(data.sections);
            setStatus(data.course.status);
        } catch (error) {
            console.error('Error fetching course data:', error);
        }
    };

    const handleSave = async (action = 'draft') => {
        try {
            const courseData = {
                ...course,
                chapters,
                sections,
                status: action
            };

            const method = initialData.mode === 'edit' ? 'PUT' : 'POST';
            const url = initialData.mode === 'edit' 
                ? `api/courses/${initialData.courseId}`
                : 'api/courses';

            const response = await fetch(url, {
                method,
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(courseData)
            });

            if (!response.ok) {
                throw new Error('Failed to save course');
            }

            setIsDirty(false);
            setStatus(action);

            if (action === 'publish') {
                window.location.href = 'teacher_courses.php?success=published';
            } else {
                window.location.href = 'teacher_courses.php?success=draft';
            }
        } catch (error) {
            console.error('Error saving course:', error);
            alert('Failed to save course. Please try again.');
        }
    };

    return (
        <div className="min-h-screen bg-gray-100">
            <Navigation 
                teacherName={initialData.teacherName}
                isDirty={isDirty}
                onSave={() => handleSave('draft')}
                onPublish={() => handleSave('publish')}
            />
            
            <main className="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
                <div className="px-4 py-6 sm:px-0">
                    <div className="mb-8">
                        <div className="flex items-center justify-between">
                            <h1 className="text-3xl font-bold text-gray-900">
                                {initialData.mode === 'edit' ? 'Edit Course' : 'Create Course'}
                            </h1>
                        </div>
                    </div>

                    <div className="space-y-6">
                        <CourseDetails
                            course={course}
                            setCourse={setCourse}
                            setIsDirty={setIsDirty}
                        />
                    </div>
                </div>
            </main>
        </div>
    );
};

// Export the component to the global scope
window.CourseEditor = CourseEditor; 
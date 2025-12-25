// TinyMCE Chapter Editor Configuration - License Issue COMPLETELY FIXED
// File: js/tinymce-chapter-editor.js

// Global TinyMCE configuration WITHOUT license key (we'll add it explicitly in each init call)
window.tinyMCEConfig = {
    height: 400,
    menubar: false,
    branding: false,
    statusbar: true,
    resize: 'both',
    toolbar_mode: 'sliding',
    plugins: [
        'advlist', 'autolink', 'lists', 'link', 'image', 'charmap', 'preview',
        'anchor', 'searchreplace', 'visualblocks', 'code', 'fullscreen',
        'insertdatetime', 'media', 'table', 'help', 'wordcount', 'emoticons',
        'paste', 'powerpaste'
    ],
    toolbar: [
        'undo redo | styles | bold italic underline strikethrough | alignleft aligncenter alignright alignjustify',
        'bullist numlist outdent indent | link image media table | paste pastePlainText | removeformat help'
    ],
    style_formats: [
        { title: 'Headings', items: [
            { title: 'Heading 1', format: 'h1' },
            { title: 'Heading 2', format: 'h2' },
            { title: 'Heading 3', format: 'h3' },
            { title: 'Heading 4', format: 'h4' }
        ]},
        { title: 'Inline', items: [
            { title: 'Bold', format: 'bold' },
            { title: 'Italic', format: 'italic' },
            { title: 'Underline', format: 'underline' },
            { title: 'Strikethrough', format: 'strikethrough' },
            { title: 'Code', format: 'code' }
        ]},
        { title: 'Blocks', items: [
            { title: 'Paragraph', format: 'p' },
            { title: 'Blockquote', format: 'blockquote' },
            { title: 'Div', format: 'div' },
            { title: 'Pre', format: 'pre' }
        ]}
    ],
    content_style: `
        body { 
            font-family: 'Inter', 'Noto Sans JP', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; 
            font-size: 14px; 
            line-height: 1.6; 
            color: #374151;
            background-color: #ffffff;
            margin: 16px;
        }
        h1, h2, h3, h4, h5, h6 { 
            color: #111827; 
            margin-top: 1.5em; 
            margin-bottom: 0.5em; 
            font-weight: 600;
        }
        h1 { font-size: 1.875rem; }
        h2 { font-size: 1.5rem; }
        h3 { font-size: 1.25rem; }
        h4 { font-size: 1.125rem; }
        p { margin-bottom: 1em; }
        ul, ol { padding-left: 1.5em; margin-bottom: 1em; }
        li { margin-bottom: 0.25em; }
        blockquote { 
            border-left: 4px solid #e5e7eb; 
            padding-left: 1em; 
            margin: 1em 0; 
            font-style: italic; 
            color: #6b7280;
        }
        code { 
            background-color: #f3f4f6; 
            padding: 0.125em 0.25em; 
            border-radius: 0.25em; 
            font-family: 'Consolas', 'Monaco', monospace; 
            font-size: 0.875em;
        }
        pre { 
            background-color: #f3f4f6; 
            padding: 1em; 
            border-radius: 0.5em; 
            overflow-x: auto; 
            margin: 1em 0;
        }
        img { 
            max-width: 100%; 
            height: auto; 
            border-radius: 0.5em; 
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        table {
            border-collapse: collapse;
            width: 100%;
            margin: 1em 0;
        }
        table, th, td {
            border: 1px solid #d1d5db;
        }
        th, td {
            padding: 0.75em;
            text-align: left;
        }
        th {
            background-color: #f9fafb;
            font-weight: 600;
        }
    `,
    setup: function(editor) {
        // Enhanced setup for better user experience
        editor.on('init', function() {
            console.log('TinyMCE editor initialized for chapter content');
            
            // Focus the editor after initialization
            setTimeout(() => {
                if (editor.getContainer()) {
                    editor.focus();
                }
            }, 100);
        });
        
        // Handle content changes
        editor.on('change keyup paste', function() {
            // Mark form as having unsaved changes
            if (typeof markAsChanged === 'function') {
                markAsChanged();
            }
            
            // Update character count if needed
            const content = editor.getContent();
            const wordCount = editor.plugins.wordcount ? editor.plugins.wordcount.getCount() : 0;
            
            // You can add custom handling here for word count display
            const statusBar = editor.getContainer().querySelector('.tox-statusbar__text-container');
            if (statusBar && wordCount > 0) {
                statusBar.innerHTML = `Words: ${wordCount}`;
            }
        });
        
        // Enhanced paste handling for various content types
        editor.on('paste', function(e) {
            const clipboardData = e.clipboardData || e.originalEvent.clipboardData;
            const items = clipboardData.items;
            
            // Handle image uploads
            for (let item of items) {
                if (item.type.indexOf('image') !== -1) {
                    e.preventDefault();
                    const file = item.getAsFile();
                    handleImageUpload(editor, file);
                    break;
                }
            }
            
            // Handle rich text content from various sources
            const htmlData = clipboardData.getData('text/html');
            const plainData = clipboardData.getData('text/plain');
            
            // Clean up content from Word, Google Docs, etc.
            if (htmlData) {
                // Remove unwanted Word/Office formatting
                let cleanHtml = htmlData
                    .replace(/<!--[\s\S]*?-->/g, '') // Remove comments
                    .replace(/<o:p\s*\/?>|<\/o:p>/g, '') // Remove Office paragraphs
                    .replace(/<w:[^>]*>|<\/w:[^>]*>/g, '') // Remove Word elements
                    .replace(/class="MsoNormal"|class="MsoListParagraph"/g, '') // Remove Office classes
                    .replace(/style="[^"]*mso-[^"]*"/g, '') // Remove Office styles
                    .replace(/<span[^>]*>\s*<\/span>/g, '') // Remove empty spans
                    .replace(/\s+class=""/g, '') // Remove empty class attributes
                    .replace(/\s+style=""/g, ''); // Remove empty style attributes
                
                // Insert cleaned content
                editor.insertContent(cleanHtml);
                e.preventDefault();
                return;
            }
            
            // Handle plain text with basic formatting preservation
            if (plainData && !htmlData) {
                // Convert line breaks to paragraphs
                const formattedText = plainData
                    .split('\n\n')
                    .map(paragraph => paragraph.trim())
                    .filter(paragraph => paragraph.length > 0)
                    .map(paragraph => `<p>${paragraph.replace(/\n/g, '<br>')}</p>`)
                    .join('');
                
                if (formattedText) {
                    editor.insertContent(formattedText);
                    e.preventDefault();
                    return;
                }
            }
        });
        
        // Custom toolbar buttons can be added here
        editor.ui.registry.addButton('customSave', {
            text: 'Save',
            icon: 'save',
            onAction: function() {
                // Trigger form save
                const saveBtn = document.getElementById('saveDraft');
                if (saveBtn) {
                    saveBtn.click();
                }
            }
        });
        
        // Custom paste as plain text button
        editor.ui.registry.addButton('pastePlainText', {
            text: 'Paste as Plain Text',
            icon: 'paste-plain-text',
            tooltip: 'Paste content as plain text without formatting',
            onAction: function() {
                navigator.clipboard.readText().then(function(text) {
                    if (text) {
                        // Convert plain text to formatted paragraphs
                        const formattedText = text
                            .split('\n\n')
                            .map(paragraph => paragraph.trim())
                            .filter(paragraph => paragraph.length > 0)
                            .map(paragraph => `<p>${paragraph.replace(/\n/g, '<br>')}</p>`)
                            .join('');
                        
                        editor.insertContent(formattedText);
                    }
                }).catch(function(err) {
                    console.error('Failed to read clipboard contents: ', err);
                    // Fallback: show a prompt
                    const text = prompt('Paste your text here:');
                    if (text) {
                        const formattedText = text
                            .split('\n\n')
                            .map(paragraph => paragraph.trim())
                            .filter(paragraph => paragraph.length > 0)
                            .map(paragraph => `<p>${paragraph.replace(/\n/g, '<br>')}</p>`)
                            .join('');
                        editor.insertContent(formattedText);
                    }
                });
            }
        });
    },
    // File picker for images and media
    file_picker_callback: function(callback, value, meta) {
        if (meta.filetype === 'image') {
            const input = document.createElement('input');
            input.setAttribute('type', 'file');
            input.setAttribute('accept', 'image/*');
            
            input.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    handleImageUpload(callback, file);
                }
            });
            
            input.click();
        }
    },
    // Enhanced image handling
    images_upload_handler: function(blobInfo, success, failure) {
        handleImageBlobUpload(blobInfo, success, failure);
    },
    // Enhanced paste configuration
    paste_data_images: true,
    paste_auto_cleanup_on_paste: true,
    paste_remove_styles_if_webkit: false,
    paste_merge_formats: true,
    paste_convert_word_fake_lists: true,
    paste_webkit_styles: 'all',
    paste_retain_style_properties: 'color font-size font-family background-color',
    paste_enable_default_filters: true,
    // PowerPaste configuration for better paste handling
    powerpaste_word_import: 'clean',
    powerpaste_html_import: 'clean',
    powerpaste_allow_local_images: true,
    // Accessibility improvements
    a11y_advanced_options: true,
    // Auto-save functionality
    autosave_ask_before_unload: true,
    autosave_interval: '30s',
    autosave_retention: '2m'
};

// Initialize TinyMCE editor - COMPLETELY FIXED VERSION
function initEditor(selector) {
    if (!selector) {
        console.error('No selector provided for TinyMCE initialization');
        return;
    }
    
    // Destroy existing editor if it exists
    if (window.tinymce && tinymce.get(selector)) {
        tinymce.get(selector).remove();
    }
    
    // Wait for DOM to be ready
    setTimeout(() => {
        const element = document.getElementById(selector) || document.querySelector(selector);
        if (!element) {
            console.error('TinyMCE target element not found:', selector);
            return;
        }
        
        // Check if TinyMCE is available
        if (!window.tinymce) {
            console.error('TinyMCE library not loaded');
            return;
        }
        
        // CRITICAL FIX: Create a clean config object with license_key FIRST
        const editorConfig = {
            license_key: 'gpl', // MUST be the very first property
            selector: `#${selector}`,
            // Now spread the rest of the config
            ...window.tinyMCEConfig,
            // Override the init_instance_callback to ensure it's set correctly
            init_instance_callback: function(editor) {
                console.log('TinyMCE editor initialized:', editor.id);
                
                // Custom initialization logic
                setTimeout(() => {
                    if (editor && !editor.removed) {
                        try {
                            editor.focus();
                        } catch (e) {
                            console.log('Could not focus editor:', e.message);
                        }
                    }
                }, 100);
                
                // Bind to form submission
                const form = editor.getContainer().closest('form');
                if (form) {
                    form.addEventListener('submit', function() {
                        if (editor && !editor.removed) {
                            editor.save(); // Ensure content is saved to textarea
                        }
                    });
                }
            }
        };
        
        // Initialize TinyMCE with the clean config
        tinymce.init(editorConfig).then(function(editors) {
            console.log('TinyMCE editors initialized successfully:', editors.length);
        }).catch(function(error) {
            console.error('TinyMCE initialization failed:', error);
            
            // Fallback to regular textarea
            element.style.display = 'block';
            element.style.minHeight = '400px';
            element.classList.add('form-textarea', 'border', 'border-gray-300', 'rounded', 'p-2');
            
            // Show error message to user
            const errorMsg = document.createElement('div');
            errorMsg.className = 'text-red-600 text-sm mt-1';
            errorMsg.textContent = 'Rich text editor failed to load. Using plain text mode.';
            element.parentNode.insertBefore(errorMsg, element.nextSibling);
        });
    }, 100);
}

// Alternative initialization method for when the main method fails
function initEditorSimple(selector) {
    if (!selector || !window.tinymce) {
        console.error('TinyMCE not available or no selector provided');
        return;
    }
    
    // Destroy existing editor if it exists
    if (tinymce.get(selector)) {
        tinymce.get(selector).remove();
    }
    
    // Minimal configuration with license key
    tinymce.init({
        license_key: 'gpl',
        selector: `#${selector}`,
        height: 400,
        menubar: false,
        branding: false,
        plugins: 'advlist autolink lists link code',
        toolbar: 'undo redo | bold italic | bullist numlist | link code',
        setup: function(editor) {
            editor.on('init', function() {
                console.log('TinyMCE simple editor initialized');
            });
        }
    }).catch(function(error) {
        console.error('Simple TinyMCE initialization also failed:', error);
    });
}

// Handle image upload for TinyMCE
function handleImageUpload(editor, file) {
    if (!file || !file.type.startsWith('image/')) {
        console.error('Invalid file type for image upload');
        return;
    }
    
    // Create FormData for upload
    const formData = new FormData();
    formData.append('image', file);
    formData.append('action', 'upload_chapter_image');
    
    // Show loading indicator
    if (typeof editor.windowManager !== 'undefined') {
        editor.windowManager.open({
            title: 'Uploading Image...',
            body: {
                type: 'panel',
                items: [{
                    type: 'htmlpanel',
                    html: '<div style="text-align: center; padding: 20px;"><div class="spinner"></div><p>Uploading image...</p></div>'
                }]
            },
            buttons: []
        });
    }
    
    // Upload image
    fetch('includes/upload_handler.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        // Close loading dialog
        if (editor.windowManager) {
            editor.windowManager.close();
        }
        
        if (data.success) {
            // Insert image into editor
            const img = `<img src="${data.url}" alt="${data.filename}" style="max-width: 100%; height: auto;" />`;
            editor.insertContent(img);
        } else {
            console.error('Image upload failed:', data.message);
            // Show error notification
            if (typeof showNotification === 'function') {
                showNotification('Error uploading image: ' + data.message, 'error');
            }
        }
    })
    .catch(error => {
        console.error('Image upload error:', error);
        // Close loading dialog
        if (editor.windowManager) {
            editor.windowManager.close();
        }
        
        // Show error notification
        if (typeof showNotification === 'function') {
            showNotification('Error uploading image', 'error');
        }
    });
}

// Handle blob upload for copy-paste images
function handleImageBlobUpload(blobInfo, success, failure) {
    const formData = new FormData();
    formData.append('image', blobInfo.blob(), blobInfo.filename());
    formData.append('action', 'upload_chapter_image');
    
    fetch('includes/upload_handler.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            success(data.url);
        } else {
            failure('Image upload failed: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Blob upload error:', error);
        failure('Image upload failed');
    });
}

// Destroy editor safely
function destroyEditor(selector) {
    if (!window.tinymce) return;
    
    const editor = tinymce.get(selector);
    if (editor) {
        try {
            editor.remove();
        } catch (error) {
            console.error('Error destroying editor:', error);
        }
    }
}

// Utility function to get editor content safely
function getEditorContent(selector) {
    if (!window.tinymce) return '';
    
    const editor = tinymce.get(selector);
    if (editor && !editor.removed) {
        try {
            return editor.getContent();
        } catch (error) {
            console.error('Error getting editor content:', error);
        }
    }
    
    // Fallback to textarea value
    const element = document.getElementById(selector);
    return element ? element.value : '';
}

// Utility function to set editor content safely
function setEditorContent(selector, content) {
    if (!window.tinymce) return;
    
    const editor = tinymce.get(selector);
    if (editor && !editor.removed) {
        try {
            editor.setContent(content || '');
            return;
        } catch (error) {
            console.error('Error setting editor content:', error);
        }
    }
    
    // Fallback to textarea
    const element = document.getElementById(selector);
    if (element) {
        element.value = content || '';
    }
}

// Check if editor is ready
function isEditorReady(selector) {
    if (!window.tinymce) return false;
    const editor = tinymce.get(selector);
    return editor && !editor.removed; // ✅ editor.removed is the correct property
}

// TinyMCE Chapter Editor object for structured access
window.tinyMCEChapterEditor = {
    currentEditor: null,
    
    initEditor: function(content = '') {
        const selector = 'chapterContent';
        this.destroyEditor();
        
        // Wait a bit for any existing editor to be fully destroyed
        setTimeout(() => {
            initEditor(selector);
            
            // Set content after initialization if provided
            if (content) {
                setTimeout(() => {
                    this.setContent(content);
                }, 500);
            }
        }, 100);
    },
    
    destroyEditor: function() {
        const selector = 'chapterContent';
        destroyEditor(selector);
        this.currentEditor = null;
    },
    
    getContent: function() {
        return getEditorContent('chapterContent');
    },
    
    setContent: function(content) {
        setEditorContent('chapterContent', content);
    },
    
    isActive: function() {
        return isEditorReady('chapterContent');
    }
};

// Export functions for global use
window.TinyMCEManager = {
    initEditor,
    initEditorSimple,
    destroyEditor,
    getEditorContent,
    setEditorContent,
    isEditorReady,
    config: window.tinyMCEConfig
};

// Safety override to catch any remaining init calls without license key
if (window.tinymce) {
    const originalInit = window.tinymce.init;
    window.tinymce.init = function(config) {
        if (!config.license_key) {
            console.warn('Adding missing license_key to TinyMCE init call');
            config.license_key = 'gpl';
        }
        return originalInit.call(this, config);
    };
}
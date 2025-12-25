const { useState, useRef, useEffect } = React;

const DescriptionBuilder = ({ initialValue = '', onChange }) => {
    const [content, setContent] = useState(initialValue);
    const editorRef = useRef(null);

    const handleChange = () => {
        const newContent = editorRef.current.innerHTML;
        setContent(newContent);
        onChange?.(newContent);
    };

    const execCommand = (command, value = null) => {
        document.execCommand(command, false, value);
        editorRef.current.focus();
        handleChange();
    };

    const handleKeyDown = (e) => {
        if (e.key === 'Tab') {
            e.preventDefault();
            execCommand('insertHTML', '&nbsp;&nbsp;&nbsp;&nbsp;');
        }
    };

    useEffect(() => {
        if (initialValue && editorRef.current) {
            editorRef.current.innerHTML = initialValue;
        }
    }, [initialValue]);

    return (
        <div className="description-builder border border-gray-300 rounded-md">
            <div className="toolbar bg-gray-50 p-2 border-b border-gray-300 flex flex-wrap gap-2">
                <button
                    onClick={() => execCommand('bold')}
                    className="px-2 py-1 rounded hover:bg-gray-200"
                    title="Bold"
                >
                    <strong>B</strong>
                </button>
                <button
                    onClick={() => execCommand('italic')}
                    className="px-2 py-1 rounded hover:bg-gray-200"
                    title="Italic"
                >
                    <em>I</em>
                </button>
                <button
                    onClick={() => execCommand('underline')}
                    className="px-2 py-1 rounded hover:bg-gray-200"
                    title="Underline"
                >
                    <u>U</u>
                </button>
                <div className="border-l border-gray-300 mx-2"></div>
                <button
                    onClick={() => execCommand('insertUnorderedList')}
                    className="px-2 py-1 rounded hover:bg-gray-200"
                    title="Bullet List"
                >
                    • List
                </button>
                <button
                    onClick={() => execCommand('insertOrderedList')}
                    className="px-2 py-1 rounded hover:bg-gray-200"
                    title="Numbered List"
                >
                    1. List
                </button>
                <div className="border-l border-gray-300 mx-2"></div>
                <button
                    onClick={() => execCommand('justifyLeft')}
                    className="px-2 py-1 rounded hover:bg-gray-200"
                    title="Align Left"
                >
                    ←
                </button>
                <button
                    onClick={() => execCommand('justifyCenter')}
                    className="px-2 py-1 rounded hover:bg-gray-200"
                    title="Align Center"
                >
                    ↔
                </button>
                <button
                    onClick={() => execCommand('justifyRight')}
                    className="px-2 py-1 rounded hover:bg-gray-200"
                    title="Align Right"
                >
                    →
                </button>
            </div>
            <div
                ref={editorRef}
                className="editor-content p-4 min-h-[300px] focus:outline-none"
                contentEditable={true}
                onInput={handleChange}
                onKeyDown={handleKeyDown}
                dangerouslySetInnerHTML={{ __html: content }}
            />
            <style>{`
                .description-builder {
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
                }
                .editor-content {
                    font-size: 16px;
                    line-height: 1.5;
                }
                .editor-content:empty:before {
                    content: 'Enter your course description here...';
                    color: #9CA3AF;
                }
                .editor-content ul, .editor-content ol {
                    margin-left: 1.5em;
                }
            `}</style>
        </div>
    );
};

// Export the component to the global scope
window.DescriptionBuilder = DescriptionBuilder; 
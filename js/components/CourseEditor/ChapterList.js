import React, { useState } from 'react';
import { useSortable } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { DndContext, closestCenter } from '@dnd-kit/core';
import { SortableContext, verticalListSortingStrategy } from '@dnd-kit/sortable';

const SortableSection = ({ section, onEdit, onDelete }) => {
    const {
        attributes,
        listeners,
        setNodeRef,
        transform,
        transition
    } = useSortable({ id: section.id });

    const style = {
        transform: CSS.Transform.toString(transform),
        transition
    };

    return (
        <div
            ref={setNodeRef}
            style={style}
            className="bg-white rounded-lg p-4 shadow-sm"
        >
            <div className="flex items-center justify-between">
                <div className="flex items-center">
                    <div {...attributes} {...listeners} className="cursor-move mr-3">
                        <svg className="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 8h16M4 16h16" />
                        </svg>
                    </div>
                    <div>
                        <h4 className="text-md font-medium text-gray-900">
                            {section.title}
                        </h4>
                        <span className="text-sm text-gray-500">
                            {section.content_type}
                        </span>
                    </div>
                </div>
                <div className="flex space-x-2">
                    <button
                        onClick={() => onEdit(section)}
                        className="text-red-600 hover:text-red-700"
                    >
                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                    </button>
                    <button
                        onClick={() => onDelete(section)}
                        className="text-red-600 hover:text-red-700"
                    >
                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    );
};

const ChapterList = ({ chapters, sections, setChapters, setSections, setIsDirty, onSectionDragEnd }) => {
    const [expandedChapter, setExpandedChapter] = useState(null);
    const [editingSection, setEditingSection] = useState(null);
    const [sectionErrors, setSectionErrors] = useState({});

    const handleAddChapter = () => {
        const newChapter = {
            id: `temp_${Date.now()}`,
            title: '',
            description: '',
            isNew: true
        };
        setChapters([...chapters, newChapter]);
        setIsDirty(true);
    };

    const handleChapterChange = (chapterId, field, value) => {
        setChapters(chapters.map(ch => 
            ch.id === chapterId ? { ...ch, [field]: value } : ch
        ));
        setIsDirty(true);
    };

    const handleAddSection = (chapterId) => {
        const newSection = {
            id: `temp_${Date.now()}`,
            chapter_id: chapterId,
            title: '',
            content_type: 'text',
            content: '',
            isNew: true
        };
        setSections([...sections, newSection]);
        setEditingSection(newSection);
        setIsDirty(true);
    };

    const validateSection = (section) => {
        const errors = {};
        if (!section.title?.trim()) {
            errors.title = 'Section title is required';
        }
        
        if (section.content_type === 'video') {
            if (!section.content?.trim()) {
                errors.content = 'Video URL is required';
            } else if (!isValidVideoUrl(section.content)) {
                errors.content = 'Please enter a valid YouTube or Vimeo URL';
            }
        }
        
        if (section.content_type === 'quiz' && (!section.questions || section.questions.length === 0)) {
            errors.questions = 'At least one quiz question is required';
        }
        
        return errors;
    };

    const isValidVideoUrl = (url) => {
        const youtubeRegex = /^(https?:\/\/)?(www\.)?(youtube\.com|youtu\.be)\/.+$/;
        const vimeoRegex = /^(https?:\/\/)?(www\.)?(vimeo\.com)\/.+$/;
        return youtubeRegex.test(url) || vimeoRegex.test(url);
    };

    const handleSectionChange = (sectionId, field, value) => {
        setEditingSection(prev => {
            const updated = { ...prev, [field]: value };
            const errors = validateSection(updated);
            setSectionErrors(errors);
            return updated;
        });
        setIsDirty(true);
    };

    const handleSectionSave = () => {
        const errors = validateSection(editingSection);
        setSectionErrors(errors);

        if (Object.keys(errors).length === 0) {
            if (editingSection.isNew) {
                setSections(prev => [...prev, { ...editingSection, id: Date.now().toString() }]);
            } else {
                setSections(prev => prev.map(s => 
                    s.id === editingSection.id ? editingSection : s
                ));
            }
            setEditingSection(null);
            setIsDirty(true);
        }
    };

    const handleDeleteSection = (sectionId) => {
        if (confirm('Are you sure you want to delete this section?')) {
            setSections(sections.filter(sec => sec.id !== sectionId));
            setIsDirty(true);
        }
    };

    return (
        <div className="space-y-6">
            <div className="flex items-center justify-between mb-4">
                <div className="flex items-center">
                    <div className="w-8 h-8 rounded-full bg-red-600 text-white flex items-center justify-center mr-3">
                        2
                    </div>
                    <h2 className="text-2xl font-bold text-gray-900">Chapters</h2>
                </div>
                <button
                    onClick={handleAddChapter}
                    className="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-red-600 hover:bg-red-700"
                >
                    Add Chapter
                </button>
            </div>

            <div className="space-y-4">
                {chapters.map(chapter => (
                    <div key={chapter.id} className="bg-gray-50 rounded-lg p-6">
                        <div className="flex items-center justify-between mb-4">
                            <input
                                type="text"
                                value={chapter.title}
                                onChange={(e) => handleChapterChange(chapter.id, 'title', e.target.value)}
                                placeholder="Chapter Title"
                                className="text-lg font-semibold text-gray-900 bg-transparent border-none focus:ring-0 w-full"
                            />
                            <button
                                onClick={() => setExpandedChapter(expandedChapter === chapter.id ? null : chapter.id)}
                                className="text-gray-400 hover:text-gray-500"
                            >
                                <svg className={`w-5 h-5 transform ${expandedChapter === chapter.id ? 'rotate-180' : ''}`} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>
                        </div>

                        <textarea
                            value={chapter.description}
                            onChange={(e) => handleChapterChange(chapter.id, 'description', e.target.value)}
                            placeholder="Chapter Description (optional)"
                            className="w-full bg-transparent border-none focus:ring-0 text-gray-600 mb-4"
                            rows="2"
                        />

                        {expandedChapter === chapter.id && (
                            <div className="space-y-4">
                                <DndContext
                                    collisionDetection={closestCenter}
                                    onDragEnd={(event) => onSectionDragEnd(chapter.id, event)}
                                >
                                    <SortableContext
                                        items={sections.filter(s => s.chapter_id === chapter.id).map(s => s.id)}
                                        strategy={verticalListSortingStrategy}
                                    >
                                        {sections
                                            .filter(section => section.chapter_id === chapter.id)
                                            .map(section => (
                                                <SortableSection
                                                    key={section.id}
                                                    section={section}
                                                    onEdit={() => setEditingSection(section)}
                                                    onDelete={() => handleDeleteSection(section.id)}
                                                />
                                            ))
                                        }
                                    </SortableContext>
                                </DndContext>

                                <button
                                    onClick={() => handleAddSection(chapter.id)}
                                    className="w-full py-3 border-2 border-dashed border-gray-300 rounded-lg text-gray-600 hover:text-gray-900 hover:border-gray-400 transition-colors"
                                >
                                    + Add Section
                                </button>
                            </div>
                        )}
                    </div>
                ))}
            </div>

            {editingSection && (
                <div className="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center">
                    <div className="bg-white rounded-lg p-6 max-w-2xl w-full mx-4">
                        <h3 className="text-lg font-medium text-gray-900 mb-4">
                            {editingSection.isNew ? 'Add Section' : 'Edit Section'}
                        </h3>
                        
                        <div className="space-y-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-700">
                                    Section Title
                                </label>
                                <input
                                    type="text"
                                    value={editingSection.title || ''}
                                    onChange={(e) => handleSectionChange(editingSection.id, 'title', e.target.value)}
                                    className={`mt-1 block w-full rounded-md shadow-sm focus:border-red-500 focus:ring-red-500 ${
                                        sectionErrors.title ? 'border-red-500' : 'border-gray-300'
                                    }`}
                                />
                                {sectionErrors.title && (
                                    <p className="mt-1 text-sm text-red-600">{sectionErrors.title}</p>
                                )}
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-gray-700">
                                    Content Type
                                </label>
                                <select
                                    value={editingSection.content_type || 'text'}
                                    onChange={(e) => handleSectionChange(editingSection.id, 'content_type', e.target.value)}
                                    className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-red-500 focus:ring-red-500"
                                >
                                    <option value="text">Text</option>
                                    <option value="video">Video</option>
                                    <option value="quiz">Quiz</option>
                                </select>
                            </div>

                            {editingSection.content_type === 'text' && (
                                <div>
                                    <label className="block text-sm font-medium text-gray-700">
                                        Content
                                    </label>
                                    <textarea
                                        value={editingSection.content}
                                        onChange={(e) => handleSectionChange(editingSection.id, 'content', e.target.value)}
                                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-red-500 focus:ring-red-500"
                                        rows="4"
                                    />
                                </div>
                            )}

                            {editingSection.content_type === 'video' && (
                                <div>
                                    <label className="block text-sm font-medium text-gray-700">
                                        Video URL
                                    </label>
                                    <input
                                        type="url"
                                        value={editingSection.content || ''}
                                        onChange={(e) => handleSectionChange(editingSection.id, 'content', e.target.value)}
                                        placeholder="Enter YouTube or Vimeo URL"
                                        className={`mt-1 block w-full rounded-md shadow-sm focus:border-red-500 focus:ring-red-500 ${
                                            sectionErrors.content ? 'border-red-500' : 'border-gray-300'
                                        }`}
                                    />
                                    {sectionErrors.content && (
                                        <p className="mt-1 text-sm text-red-600">{sectionErrors.content}</p>
                                    )}
                                </div>
                            )}

                            {editingSection.content_type === 'quiz' && (
                                <div>
                                    <label className="block text-sm font-medium text-gray-700">
                                        Quiz Questions
                                    </label>
                                    <QuizEditor
                                        questions={editingSection.questions || []}
                                        onChange={(questions) => handleSectionChange(editingSection.id, 'questions', questions)}
                                    />
                                    {sectionErrors.questions && (
                                        <p className="mt-1 text-sm text-red-600">{sectionErrors.questions}</p>
                                    )}
                                </div>
                            )}
                        </div>

                        <div className="mt-6 flex justify-end space-x-3">
                            <button
                                onClick={() => setEditingSection(null)}
                                className="px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50"
                            >
                                Cancel
                            </button>
                            <button
                                onClick={handleSectionSave}
                                className="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-red-600 hover:bg-red-700"
                            >
                                {editingSection.isNew ? 'Add Section' : 'Save Changes'}
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
};

export default ChapterList; 
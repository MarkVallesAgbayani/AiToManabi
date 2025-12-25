function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
import React, { useState } from 'react';
import { useSortable } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { DndContext, closestCenter } from '@dnd-kit/core';
import { SortableContext, verticalListSortingStrategy } from '@dnd-kit/sortable';
const SortableSection = ({
  section,
  onEdit,
  onDelete
}) => {
  const {
    attributes,
    listeners,
    setNodeRef,
    transform,
    transition
  } = useSortable({
    id: section.id
  });
  const style = {
    transform: CSS.Transform.toString(transform),
    transition
  };
  return /*#__PURE__*/React.createElement("div", {
    ref: setNodeRef,
    style: style,
    className: "bg-white rounded-lg p-4 shadow-sm"
  }, /*#__PURE__*/React.createElement("div", {
    className: "flex items-center justify-between"
  }, /*#__PURE__*/React.createElement("div", {
    className: "flex items-center"
  }, /*#__PURE__*/React.createElement("div", _extends({}, attributes, listeners, {
    className: "cursor-move mr-3"
  }), /*#__PURE__*/React.createElement("svg", {
    className: "w-5 h-5 text-gray-400",
    fill: "none",
    stroke: "currentColor",
    viewBox: "0 0 24 24"
  }, /*#__PURE__*/React.createElement("path", {
    strokeLinecap: "round",
    strokeLinejoin: "round",
    strokeWidth: 2,
    d: "M4 8h16M4 16h16"
  }))), /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement("h4", {
    className: "text-md font-medium text-gray-900"
  }, section.title), /*#__PURE__*/React.createElement("span", {
    className: "text-sm text-gray-500"
  }, section.content_type))), /*#__PURE__*/React.createElement("div", {
    className: "flex space-x-2"
  }, /*#__PURE__*/React.createElement("button", {
    onClick: () => onEdit(section),
    className: "text-red-600 hover:text-red-700"
  }, /*#__PURE__*/React.createElement("svg", {
    className: "w-5 h-5",
    fill: "none",
    stroke: "currentColor",
    viewBox: "0 0 24 24"
  }, /*#__PURE__*/React.createElement("path", {
    strokeLinecap: "round",
    strokeLinejoin: "round",
    strokeWidth: 2,
    d: "M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"
  }))), /*#__PURE__*/React.createElement("button", {
    onClick: () => onDelete(section),
    className: "text-red-600 hover:text-red-700"
  }, /*#__PURE__*/React.createElement("svg", {
    className: "w-5 h-5",
    fill: "none",
    stroke: "currentColor",
    viewBox: "0 0 24 24"
  }, /*#__PURE__*/React.createElement("path", {
    strokeLinecap: "round",
    strokeLinejoin: "round",
    strokeWidth: 2,
    d: "M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
  }))))));
};
const ChapterList = ({
  chapters,
  sections,
  setChapters,
  setSections,
  setIsDirty,
  onSectionDragEnd
}) => {
  const [expandedChapter, setExpandedChapter] = useState(null);
  const [editingSection, setEditingSection] = useState(null);
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
    setChapters(chapters.map(ch => ch.id === chapterId ? {
      ...ch,
      [field]: value
    } : ch));
    setIsDirty(true);
  };
  const handleAddSection = chapterId => {
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
  const handleSectionChange = (sectionId, field, value) => {
    setSections(sections.map(sec => sec.id === sectionId ? {
      ...sec,
      [field]: value
    } : sec));
    setIsDirty(true);
  };
  const handleDeleteSection = sectionId => {
    if (confirm('Are you sure you want to delete this section?')) {
      setSections(sections.filter(sec => sec.id !== sectionId));
      setIsDirty(true);
    }
  };
  return /*#__PURE__*/React.createElement("div", {
    className: "space-y-6"
  }, /*#__PURE__*/React.createElement("div", {
    className: "flex items-center justify-between mb-4"
  }, /*#__PURE__*/React.createElement("div", {
    className: "flex items-center"
  }, /*#__PURE__*/React.createElement("div", {
    className: "w-8 h-8 rounded-full bg-red-600 text-white flex items-center justify-center mr-3"
  }, "2"), /*#__PURE__*/React.createElement("h2", {
    className: "text-2xl font-bold text-gray-900"
  }, "Chapters")), /*#__PURE__*/React.createElement("button", {
    onClick: handleAddChapter,
    className: "px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-red-600 hover:bg-red-700"
  }, "Add Chapter")), /*#__PURE__*/React.createElement("div", {
    className: "space-y-4"
  }, chapters.map(chapter => /*#__PURE__*/React.createElement("div", {
    key: chapter.id,
    className: "bg-gray-50 rounded-lg p-6"
  }, /*#__PURE__*/React.createElement("div", {
    className: "flex items-center justify-between mb-4"
  }, /*#__PURE__*/React.createElement("input", {
    type: "text",
    value: chapter.title,
    onChange: e => handleChapterChange(chapter.id, 'title', e.target.value),
    placeholder: "Chapter Title",
    className: "text-lg font-semibold text-gray-900 bg-transparent border-none focus:ring-0 w-full"
  }), /*#__PURE__*/React.createElement("button", {
    onClick: () => setExpandedChapter(expandedChapter === chapter.id ? null : chapter.id),
    className: "text-gray-400 hover:text-gray-500"
  }, /*#__PURE__*/React.createElement("svg", {
    className: `w-5 h-5 transform ${expandedChapter === chapter.id ? 'rotate-180' : ''}`,
    fill: "none",
    stroke: "currentColor",
    viewBox: "0 0 24 24"
  }, /*#__PURE__*/React.createElement("path", {
    strokeLinecap: "round",
    strokeLinejoin: "round",
    strokeWidth: 2,
    d: "M19 9l-7 7-7-7"
  })))), /*#__PURE__*/React.createElement("textarea", {
    value: chapter.description,
    onChange: e => handleChapterChange(chapter.id, 'description', e.target.value),
    placeholder: "Chapter Description (optional)",
    className: "w-full bg-transparent border-none focus:ring-0 text-gray-600 mb-4",
    rows: "2"
  }), expandedChapter === chapter.id && /*#__PURE__*/React.createElement("div", {
    className: "space-y-4"
  }, /*#__PURE__*/React.createElement(DndContext, {
    collisionDetection: closestCenter,
    onDragEnd: event => onSectionDragEnd(chapter.id, event)
  }, /*#__PURE__*/React.createElement(SortableContext, {
    items: sections.filter(s => s.chapter_id === chapter.id).map(s => s.id),
    strategy: verticalListSortingStrategy
  }, sections.filter(section => section.chapter_id === chapter.id).map(section => /*#__PURE__*/React.createElement(SortableSection, {
    key: section.id,
    section: section,
    onEdit: () => setEditingSection(section),
    onDelete: () => handleDeleteSection(section.id)
  })))), /*#__PURE__*/React.createElement("button", {
    onClick: () => handleAddSection(chapter.id),
    className: "w-full py-3 border-2 border-dashed border-gray-300 rounded-lg text-gray-600 hover:text-gray-900 hover:border-gray-400 transition-colors"
  }, "+ Add Section"))))), editingSection && /*#__PURE__*/React.createElement("div", {
    className: "fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center"
  }, /*#__PURE__*/React.createElement("div", {
    className: "bg-white rounded-lg p-6 max-w-2xl w-full mx-4"
  }, /*#__PURE__*/React.createElement("h3", {
    className: "text-lg font-medium text-gray-900 mb-4"
  }, editingSection.isNew ? 'Add Section' : 'Edit Section'), /*#__PURE__*/React.createElement("div", {
    className: "space-y-4"
  }, /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement("label", {
    className: "block text-sm font-medium text-gray-700"
  }, "Section Title"), /*#__PURE__*/React.createElement("input", {
    type: "text",
    value: editingSection.title,
    onChange: e => handleSectionChange(editingSection.id, 'title', e.target.value),
    className: "mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-red-500 focus:ring-red-500"
  })), /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement("label", {
    className: "block text-sm font-medium text-gray-700"
  }, "Content Type"), /*#__PURE__*/React.createElement("select", {
    value: editingSection.content_type,
    onChange: e => handleSectionChange(editingSection.id, 'content_type', e.target.value),
    className: "mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-red-500 focus:ring-red-500"
  }, /*#__PURE__*/React.createElement("option", {
    value: "text"
  }, "Text"), /*#__PURE__*/React.createElement("option", {
    value: "video"
  }, "Video"), /*#__PURE__*/React.createElement("option", {
    value: "quiz"
  }, "Quiz"))), editingSection.content_type === 'text' && /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement("label", {
    className: "block text-sm font-medium text-gray-700"
  }, "Content"), /*#__PURE__*/React.createElement("textarea", {
    value: editingSection.content,
    onChange: e => handleSectionChange(editingSection.id, 'content', e.target.value),
    className: "mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-red-500 focus:ring-red-500",
    rows: "4"
  })), editingSection.content_type === 'video' && /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement("label", {
    className: "block text-sm font-medium text-gray-700"
  }, "Video URL"), /*#__PURE__*/React.createElement("input", {
    type: "url",
    value: editingSection.content,
    onChange: e => handleSectionChange(editingSection.id, 'content', e.target.value),
    placeholder: "Enter YouTube or Vimeo URL",
    className: "mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-red-500 focus:ring-red-500"
  })), editingSection.content_type === 'quiz' && /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement("label", {
    className: "block text-sm font-medium text-gray-700"
  }, "Quiz Questions"))), /*#__PURE__*/React.createElement("div", {
    className: "mt-6 flex justify-end space-x-3"
  }, /*#__PURE__*/React.createElement("button", {
    onClick: () => setEditingSection(null),
    className: "px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50"
  }, "Cancel"), /*#__PURE__*/React.createElement("button", {
    onClick: () => {
      setEditingSection(null);
      setIsDirty(true);
    },
    className: "px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-red-600 hover:bg-red-700"
  }, "Save Section")))));
};
export default ChapterList;
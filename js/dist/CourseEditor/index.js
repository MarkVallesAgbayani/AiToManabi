import React, { useState, useEffect } from 'react';
import { DndContext, closestCenter, KeyboardSensor, PointerSensor, useSensor, useSensors } from '@dnd-kit/core';
import { arrayMove, SortableContext, sortableKeyboardCoordinates, verticalListSortingStrategy } from '@dnd-kit/sortable';
import { restrictToVerticalAxis } from '@dnd-kit/modifiers';

// Components
import CourseDetails from './CourseDetails';
import ChapterList from './ChapterList';
import Navigation from './Navigation';
const CourseEditor = ({
  initialData
}) => {
  const [course, setCourse] = useState(initialData.course || {});
  const [chapters, setChapters] = useState([]);
  const [sections, setSections] = useState([]);
  const [activeStep, setActiveStep] = useState(1);
  const [isDirty, setIsDirty] = useState(false);
  const [status, setStatus] = useState('draft');
  const sensors = useSensors(useSensor(PointerSensor), useSensor(KeyboardSensor, {
    coordinateGetter: sortableKeyboardCoordinates
  }));
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
  const handleDragEnd = event => {
    const {
      active,
      over
    } = event;
    if (active.id !== over.id) {
      const oldIndex = chapters.findIndex(item => item.id === active.id);
      const newIndex = chapters.findIndex(item => item.id === over.id);
      setChapters(arrayMove(chapters, oldIndex, newIndex));
      setIsDirty(true);
    }
  };
  const handleSectionDragEnd = (chapterId, event) => {
    const {
      active,
      over
    } = event;
    if (active.id !== over.id) {
      const chapterSections = sections.filter(s => s.chapter_id === chapterId);
      const oldIndex = chapterSections.findIndex(item => item.id === active.id);
      const newIndex = chapterSections.findIndex(item => item.id === over.id);
      const newSections = [...sections];
      const sectionIds = chapterSections.map(s => s.id);
      const reorderedIds = arrayMove(sectionIds, oldIndex, newIndex);
      reorderedIds.forEach((id, index) => {
        const section = newSections.find(s => s.id === id);
        if (section) {
          section.order_index = index;
        }
      });
      setSections(newSections);
      setIsDirty(true);
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
      const url = initialData.mode === 'edit' ? `api/courses/${initialData.courseId}` : 'api/courses';
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
  return /*#__PURE__*/React.createElement("div", {
    className: "min-h-screen bg-gray-100"
  }, /*#__PURE__*/React.createElement(Navigation, {
    teacherName: initialData.teacherName,
    isDirty: isDirty,
    onSave: () => handleSave('draft'),
    onPublish: () => handleSave('publish')
  }), /*#__PURE__*/React.createElement("main", {
    className: "max-w-7xl mx-auto py-6 sm:px-6 lg:px-8"
  }, /*#__PURE__*/React.createElement("div", {
    className: "px-4 py-6 sm:px-0"
  }, /*#__PURE__*/React.createElement("div", {
    className: "mb-8"
  }, /*#__PURE__*/React.createElement("div", {
    className: "flex items-center justify-between"
  }, /*#__PURE__*/React.createElement("h1", {
    className: "text-3xl font-bold text-gray-900"
  }, initialData.mode === 'edit' ? 'Edit Course' : 'Create Course'), /*#__PURE__*/React.createElement("div", {
    className: "flex space-x-4"
  }, /*#__PURE__*/React.createElement("button", {
    onClick: () => handleSave('draft'),
    className: "px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50"
  }, "Save as Draft"), /*#__PURE__*/React.createElement("button", {
    onClick: () => handleSave('publish'),
    className: "px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-red-600 hover:bg-red-700"
  }, status === 'published' ? 'Update & Publish' : 'Publish Course')))), /*#__PURE__*/React.createElement("div", {
    className: "space-y-6"
  }, /*#__PURE__*/React.createElement(CourseDetails, {
    course: course,
    setCourse: setCourse,
    setIsDirty: setIsDirty
  }), /*#__PURE__*/React.createElement(DndContext, {
    sensors: sensors,
    collisionDetection: closestCenter,
    onDragEnd: handleDragEnd,
    modifiers: [restrictToVerticalAxis]
  }, /*#__PURE__*/React.createElement(ChapterList, {
    chapters: chapters,
    sections: sections,
    setChapters: setChapters,
    setSections: setSections,
    setIsDirty: setIsDirty,
    onSectionDragEnd: handleSectionDragEnd
  }))))));
};
export default CourseEditor;
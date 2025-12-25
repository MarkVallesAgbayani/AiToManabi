// Import React and dependencies from window object since we're using UMD bundles
const {
  useState,
  useEffect
} = React;

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
const Navigation = ({
  teacherName,
  isDirty,
  onSave,
  onPublish
}) => {
  useEffect(() => {
    const handleBeforeUnload = e => {
      if (isDirty) {
        e.preventDefault();
        e.returnValue = '';
      }
    };
    window.addEventListener('beforeunload', handleBeforeUnload);
    return () => window.removeEventListener('beforeunload', handleBeforeUnload);
  }, [isDirty]);
  return /*#__PURE__*/React.createElement("nav", {
    className: "bg-white shadow-lg"
  }, /*#__PURE__*/React.createElement("div", {
    className: "max-w-7xl mx-auto px-4"
  }, /*#__PURE__*/React.createElement("div", {
    className: "flex justify-between h-16"
  }, /*#__PURE__*/React.createElement("div", {
    className: "flex"
  }, /*#__PURE__*/React.createElement("div", {
    className: "flex-shrink-0 flex items-center"
  }, /*#__PURE__*/React.createElement("a", {
    href: "teacher_courses.php",
    className: "text-2xl font-bold text-red-600"
  }, "\u2190 Back to Courses"))), /*#__PURE__*/React.createElement("div", {
    className: "flex items-center space-x-4"
  }, /*#__PURE__*/React.createElement("span", {
    className: "text-gray-700"
  }, "Welcome, ", teacherName), isDirty && /*#__PURE__*/React.createElement("span", {
    className: "text-yellow-600 text-sm"
  }, "You have unsaved changes"), /*#__PURE__*/React.createElement("button", {
    onClick: onSave,
    className: "px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50"
  }, "Save Draft"), /*#__PURE__*/React.createElement("button", {
    onClick: onPublish,
    className: "px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-red-600 hover:bg-red-700"
  }, "Publish")))));
};

// CourseDetails Component
const CourseDetails = ({
  course,
  setCourse,
  setIsDirty
}) => {
  const handleChange = (field, value) => {
    setCourse(prev => ({
      ...prev,
      [field]: value
    }));
    setIsDirty(true);
  };
  const handleImageChange = e => {
    const file = e.target.files[0];
    if (file) {
      const reader = new FileReader();
      reader.onload = e => {
        handleChange('image_preview', e.target.result);
        handleChange('image_file', file);
      };
      reader.readAsDataURL(file);
    }
  };
  useEffect(() => {
    // Initialize TinyMCE
    if (window.tinymce) {
      window.tinymce.init({
        selector: '#description',
        height: 300,
        base_url: '../../assets/tinymce/tinymce/js/tinymce',
        suffix: '.min',
        license_key: 'gpl',
        promotion: false,
        setup: function (editor) {
          editor.on('change', function () {
            handleChange('description', editor.getContent());
          });
        },
        plugins: ['advlist', 'autolink', 'lists', 'link', 'image', 'charmap', 'preview', 'anchor', 'searchreplace', 'visualblocks', 'code', 'fullscreen', 'insertdatetime', 'media', 'table', 'help', 'wordcount'],
        toolbar: 'undo redo | blocks | bold italic backcolor | ' + 'alignleft aligncenter alignright alignjustify | ' + 'bullist numlist outdent indent | removeformat | help'
      });
    }
    return () => {
      if (window.tinymce) {
        window.tinymce.remove('#description');
      }
    };
  }, []);
  return /*#__PURE__*/React.createElement("div", {
    className: "bg-white shadow rounded-lg p-6"
  }, /*#__PURE__*/React.createElement("div", {
    className: "flex items-center mb-4"
  }, /*#__PURE__*/React.createElement("div", {
    className: "w-8 h-8 rounded-full bg-red-600 text-white flex items-center justify-center mr-3"
  }, "1"), /*#__PURE__*/React.createElement("h2", {
    className: "text-2xl font-bold text-gray-900"
  }, "Course Details")), /*#__PURE__*/React.createElement("div", {
    className: "space-y-6"
  }, /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement("label", {
    htmlFor: "title",
    className: "block text-sm font-medium text-gray-700"
  }, "Course Title"), /*#__PURE__*/React.createElement("input", {
    type: "text",
    id: "title",
    value: course.title || '',
    onChange: e => handleChange('title', e.target.value),
    className: "mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-red-500 focus:ring-red-500",
    required: true
  })), /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement("label", {
    htmlFor: "description",
    className: "block text-sm font-medium text-gray-700"
  }, "Course Description"), /*#__PURE__*/React.createElement("textarea", {
    id: "description",
    defaultValue: course.description || '',
    className: "hidden"
  })), /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement("label", {
    htmlFor: "category",
    className: "block text-sm font-medium text-gray-700"
  }, "Course Category"), /*#__PURE__*/React.createElement("input", {
    type: "text",
    id: "category",
    value: course.category || '',
    onChange: e => handleChange('category', e.target.value),
    className: "mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-red-500 focus:ring-red-500",
    list: "category-suggestions",
    required: true
  }), /*#__PURE__*/React.createElement("datalist", {
    id: "category-suggestions"
  }, /*#__PURE__*/React.createElement("option", {
    value: "Japanese Language"
  }), /*#__PURE__*/React.createElement("option", {
    value: "Japanese Culture"
  }), /*#__PURE__*/React.createElement("option", {
    value: "Business Japanese"
  }), /*#__PURE__*/React.createElement("option", {
    value: "JLPT Preparation"
  }))), /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement("label", {
    htmlFor: "price",
    className: "block text-sm font-medium text-gray-700"
  }, "Course Price"), /*#__PURE__*/React.createElement("div", {
    className: "mt-1 relative rounded-md shadow-sm"
  }, /*#__PURE__*/React.createElement("div", {
    className: "absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none"
  }, /*#__PURE__*/React.createElement("span", {
    className: "text-gray-500 sm:text-sm"
  }, "$")), /*#__PURE__*/React.createElement("input", {
    type: "number",
    id: "price",
    min: "0",
    step: "0.01",
    value: course.price || '0.00',
    onChange: e => handleChange('price', e.target.value),
    className: "pl-7 block w-full rounded-md border-gray-300 shadow-sm focus:border-red-500 focus:ring-red-500",
    required: true
  }))), /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement("label", {
    className: "block text-sm font-medium text-gray-700"
  }, "Course Image"), /*#__PURE__*/React.createElement("div", {
    className: "mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-md"
  }, /*#__PURE__*/React.createElement("div", {
    className: "space-y-1 text-center"
  }, course.image_path || course.image_preview ? /*#__PURE__*/React.createElement("img", {
    src: course.image_preview || `../${course.image_path}`,
    alt: "Course preview",
    className: "mx-auto h-48 w-auto"
  }) : /*#__PURE__*/React.createElement("div", {
    className: "mx-auto h-48 w-48 flex items-center justify-center"
  }, /*#__PURE__*/React.createElement("svg", {
    className: "mx-auto h-12 w-12 text-gray-400",
    stroke: "currentColor",
    fill: "none",
    viewBox: "0 0 48 48"
  }, /*#__PURE__*/React.createElement("path", {
    d: "M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02",
    strokeWidth: 2,
    strokeLinecap: "round",
    strokeLinejoin: "round"
  }))), /*#__PURE__*/React.createElement("div", {
    className: "flex text-sm text-gray-600 justify-center"
  }, /*#__PURE__*/React.createElement("label", {
    className: "relative cursor-pointer bg-white rounded-md font-medium text-red-600 hover:text-red-500 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-red-500"
  }, /*#__PURE__*/React.createElement("span", null, "Upload a file"), /*#__PURE__*/React.createElement("input", {
    type: "file",
    className: "sr-only",
    accept: "image/*",
    onChange: handleImageChange
  }))), /*#__PURE__*/React.createElement("p", {
    className: "text-xs text-gray-500"
  }, "PNG, JPG, GIF up to 10MB"))))));
};

// Main CourseEditor Component
const CourseEditor = ({
  initialData
}) => {
  const [course, setCourse] = useState(initialData.course || {});
  const [chapters, setChapters] = useState([]);
  const [sections, setSections] = useState([]);
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
  }, initialData.mode === 'edit' ? 'Edit Course' : 'Create Course'))), /*#__PURE__*/React.createElement("div", {
    className: "space-y-6"
  }, /*#__PURE__*/React.createElement(CourseDetails, {
    course: course,
    setCourse: setCourse,
    setIsDirty: setIsDirty
  })))));
};

// Export the component to the global scope
window.CourseEditor = CourseEditor;
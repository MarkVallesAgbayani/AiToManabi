import React, { useEffect, useRef } from 'react';
const CourseDetails = ({
  course,
  setCourse,
  setIsDirty
}) => {
  const editorRef = useRef(null);
  useEffect(() => {
    if (window.tinymce) {
      window.tinymce.init({
        selector: '#description',
        height: 300,
        base_url: '../../assets/tinymce/tinymce/js/tinymce',
        suffix: '.min',
        license_key: 'gpl',
        promotion: false,
        plugins: ['advlist', 'autolink', 'lists', 'link', 'image', 'charmap', 'preview', 'anchor', 'searchreplace', 'visualblocks', 'code', 'fullscreen', 'insertdatetime', 'media', 'table', 'help', 'wordcount'],
        toolbar: 'undo redo | blocks | bold italic backcolor | ' + 'alignleft aligncenter alignright alignjustify | ' + 'bullist numlist outdent indent | removeformat | help',
        setup: editor => {
          editorRef.current = editor;
          editor.on('change', () => {
            handleChange('description', editor.getContent());
          });
        }
      });
    }
    return () => {
      if (editorRef.current) {
        editorRef.current.destroy();
      }
    };
  }, []);
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
    className: "mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-red-500 focus:ring-red-500"
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
  })), /*#__PURE__*/React.createElement("p", {
    className: "mt-1 text-sm text-gray-500"
  }, "Enter 0 for free courses")), /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement("label", {
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
export default CourseDetails;
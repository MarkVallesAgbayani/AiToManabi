import React from 'react';
const Navigation = ({
  teacherName,
  isDirty,
  onSave,
  onPublish
}) => {
  const handleBeforeUnload = e => {
    if (isDirty) {
      e.preventDefault();
      e.returnValue = '';
    }
  };
  React.useEffect(() => {
    window.addEventListener('beforeunload', handleBeforeUnload);
    return () => {
      window.removeEventListener('beforeunload', handleBeforeUnload);
    };
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
export default Navigation;
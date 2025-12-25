// Shared notification system
function showNotification(message, type = 'info') {
    const existingNotifications = document.querySelectorAll('.notification');
    existingNotifications.forEach(notification => notification.remove());
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.textContent = message;
    document.body.appendChild(notification);
    setTimeout(() => notification.classList.add('show'), 10);
    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

// Modal helpers
function createModal(id, contentHtml) {
    const modal = document.createElement('div');
    modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
    modal.id = id;
    modal.innerHTML = contentHtml;
    document.body.appendChild(modal);
    return modal;
}
function closeModal(id) {
    const modal = document.getElementById(id);
    if (modal) modal.remove();
}

// Export for use in other scripts (if using modules)
// export { showNotification, createModal, closeModal }; 
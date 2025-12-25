// Function to initiate payment process
async function initiatePayment(courseId, userId) {
    try {
        const formData = new FormData();
        formData.append('course_id', courseId);
        formData.append('user_id', userId);

        const response = await fetch('../PaymentPayMongo/create_checkout_session.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();
        console.log('Payment response:', data);

        if (data.success) {
            // Show loading overlay
            showLoadingOverlay();
            // Redirect to PayMongo checkout page
            window.location.href = data.checkout_url;
        } else {
            hideLoadingOverlay();
            showError('Payment initialization failed: ' + (data.message || 'Unknown error'));
        }
    } catch (error) {
        console.error('Payment error:', error);
        hideLoadingOverlay();
        showError('An error occurred while processing your payment. Please try again.');
    }
}

// Function to handle payment button click
function handlePaymentButtonClick(event) {
    event.preventDefault(); // Prevent any default action
    
    const button = event.currentTarget;
    const courseId = button.getAttribute('data-course-id');
    const userId = button.getAttribute('data-user-id');

    console.log('Initiating payment for course:', courseId, 'user:', userId);

    if (!courseId || !userId) {
        showError('Missing course or user information');
        return;
    }

    showLoadingOverlay();
    initiatePayment(courseId, userId);
}

// Function to show loading overlay
function showLoadingOverlay() {
    // Create loading overlay if it doesn't exist
    let overlay = document.getElementById('loadingOverlay');
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.id = 'loadingOverlay';
        overlay.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
        overlay.innerHTML = `
            <div class="bg-white p-6 rounded-lg shadow-lg text-center">
                <div class="loading-spinner mb-4"></div>
                <p class="text-lg">Processing your payment...</p>
            </div>
        `;
        document.body.appendChild(overlay);

        // Add loading spinner styles
        const style = document.createElement('style');
        style.textContent = `
            .loading-spinner {
                width: 40px;
                height: 40px;
                border: 4px solid #f3f3f3;
                border-top: 4px solid #FF0000;
                border-radius: 50%;
                animation: spin 1s linear infinite;
                margin: 0 auto;
            }
            @keyframes spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
        `;
        document.head.appendChild(style);
    }
    overlay.style.display = 'flex';
}

// Function to hide loading overlay
function hideLoadingOverlay() {
    const overlay = document.getElementById('loadingOverlay');
    if (overlay) {
        overlay.style.display = 'none';
    }
}

// Function to show error message
function showError(message) {
    hideLoadingOverlay();
    
    // Create error toast if it doesn't exist
    let toast = document.getElementById('errorToast');
    if (!toast) {
        toast = document.createElement('div');
        toast.id = 'errorToast';
        toast.className = 'fixed bottom-4 right-4 bg-red-500 text-white px-6 py-3 rounded-lg shadow-lg z-50 transform translate-y-full opacity-0 transition-all duration-300';
        document.body.appendChild(toast);
    }
    
    toast.textContent = message;
    toast.style.transform = 'translateY(0)';
    toast.style.opacity = '1';
    
    // Hide toast after 3 seconds
    setTimeout(() => {
        toast.style.transform = 'translateY(full)';
        toast.style.opacity = '0';
    }, 3000);
} 
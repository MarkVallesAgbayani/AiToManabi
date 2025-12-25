/**
 * Reports Payment JavaScript
 * Handles payment invoice generation, preview, and download functionality
 */

class PaymentInvoiceManager {
    constructor() {
        this.currentPaymentId = null;
        this.currentUserId = null;
        this.isGenerating = false;
        this.init();
    }

    init() {
        this.bindEvents();
        this.initializePaymentData();
    }

    bindEvents() {
        // Download invoice button
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('download-invoice-btn')) {
                e.preventDefault();
                const paymentId = e.target.getAttribute('data-payment-id') || 
                                e.target.getAttribute('href')?.match(/payment_id=(\d+)/)?.[1];
                const userId = e.target.getAttribute('data-user-id') || 
                             this.getCurrentUserId();
                
                if (paymentId && userId) {
                    this.downloadInvoice(paymentId, userId);
                }
            }
        });

        // Print invoice button
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('btn-print-invoice')) {
                e.preventDefault();
                this.printInvoice();
            }
        });

        // Preview invoice button
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('btn-preview-invoice')) {
                e.preventDefault();
                const paymentId = e.target.getAttribute('data-payment-id');
                if (paymentId) {
                    this.previewInvoice(paymentId);
                }
            }
        });

        // Close preview modal
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('close-preview') || 
                e.target.classList.contains('preview-modal')) {
                this.closePreview();
            }
        });
    }

    initializePaymentData() {
        // Try to get payment data from page
        const paymentRows = document.querySelectorAll('[data-payment-id]');
        if (paymentRows.length > 0) {
            const firstRow = paymentRows[0];
            this.currentPaymentId = firstRow.getAttribute('data-payment-id');
        }

        // Get current user ID from session or data attribute
        this.currentUserId = this.getCurrentUserId();
    }

    getCurrentUserId() {
        // Try multiple ways to get user ID
        const userId = document.body.getAttribute('data-user-id') ||
                      document.querySelector('[data-user-id]')?.getAttribute('data-user-id') ||
                      window.currentUserId ||
                      this.extractUserIdFromUrl();
        
        return userId || null;
    }

    extractUserIdFromUrl() {
        // Extract user ID from current URL or referrer
        const urlParams = new URLSearchParams(window.location.search);
        return urlParams.get('user_id') || null;
    }

    async downloadInvoice(paymentId, userId) {
        if (this.isGenerating) {
            this.showNotification('Invoice generation in progress...', 'info');
            return;
        }

        this.isGenerating = true;
        this.showLoadingOverlay('Generating invoice...');

        try {
            // Validate inputs
            if (!paymentId || !userId) {
                throw new Error('Missing payment ID or user ID');
            }

            // Create download URL
            const downloadUrl = `reports-payment.php?payment_id=${paymentId}&user_id=${userId}`;
            
            // Create hidden iframe for download
            const iframe = document.createElement('iframe');
            iframe.style.display = 'none';
            iframe.src = downloadUrl;
            document.body.appendChild(iframe);

            // Clean up after download starts
            setTimeout(() => {
                document.body.removeChild(iframe);
                this.isGenerating = false;
                this.hideLoadingOverlay();
                this.showNotification('Invoice download started', 'success');
            }, 1000);

        } catch (error) {
            console.error('Download invoice error:', error);
            this.isGenerating = false;
            this.hideLoadingOverlay();
            this.showNotification('Failed to generate invoice: ' + error.message, 'error');
        }
    }

    async previewInvoice(paymentId) {
        if (this.isGenerating) {
            this.showNotification('Invoice generation in progress...', 'info');
            return;
        }

        this.isGenerating = true;
        this.showLoadingOverlay('Loading invoice preview...');

        try {
            // Get payment data for preview
            const paymentData = await this.getPaymentData(paymentId);
            
            if (!paymentData) {
                throw new Error('Payment data not found');
            }

            // Generate preview HTML
            const previewHTML = this.generatePreviewHTML(paymentData);
            
            // Show preview modal
            this.showPreviewModal(previewHTML);
            
            this.isGenerating = false;
            this.hideLoadingOverlay();

        } catch (error) {
            console.error('Preview invoice error:', error);
            this.isGenerating = false;
            this.hideLoadingOverlay();
            this.showNotification('Failed to load preview: ' + error.message, 'error');
        }
    }

    async getPaymentData(paymentId) {
        try {
            // This would typically make an AJAX request to get payment data
            // For now, we'll extract data from the current page
            const paymentRow = document.querySelector(`[data-payment-id="${paymentId}"]`);
            if (!paymentRow) {
                throw new Error('Payment row not found');
            }

            // Extract data from table row
            const cells = paymentRow.querySelectorAll('td');
            if (cells.length < 4) {
                throw new Error('Insufficient payment data');
            }

            return {
                id: paymentId,
                date: cells[0]?.textContent?.trim() || '',
                course: cells[1]?.textContent?.trim() || '',
                amount: cells[2]?.textContent?.trim() || '',
                status: cells[3]?.textContent?.trim() || '',
                invoice: cells[4]?.querySelector('a')?.href || ''
            };

        } catch (error) {
            console.error('Get payment data error:', error);
            return null;
        }
    }

    generatePreviewHTML(paymentData) {
        const currentDate = new Date().toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });

        return `
            <div class="invoice-preview-content">
                <!-- Invoice Header -->
                <div class="invoice-header-section">
                    <div class="company-name-preview">AiToManabi</div>
                    <div class="company-tagline-preview">Learning Management System</div>
                    <div class="company-tagline-preview">aitomanabilms@gmail.com</div>
                </div>
                
                <!-- Invoice Title -->
                <div class="invoice-title-preview">PAYMENT RECEIPT</div>
                
                <!-- Greeting Section -->
                <div class="greeting-section-preview">
                    <p class="greeting-text-preview">
                        <strong>Hi Student,</strong><br>
                        Thank you for your payment. Here's a copy of your receipt.
                    </p>
                </div>
                
                <!-- Order Details -->
                <div class="order-details-preview">
                    <h3 class="section-title-preview">Order Details</h3>
                    <div class="order-item-preview">
                        <div class="item-name-preview">${this.escapeHtml(paymentData.course)}</div>
                        <div class="item-details-preview">
                            <span class="item-quantity-preview">(${paymentData.amount}) x 1</span>
                            <span class="item-price-preview">${paymentData.amount}</span>
                        </div>
                    </div>
                </div>
                
                <!-- Amount Section -->
                <div class="amount-section-preview">
                    <div class="amount-label-preview">Amount Paid</div>
                    <div class="amount-value-preview">${paymentData.amount}</div>
                </div>
                
                <!-- Payment Info -->
                <div class="payment-info-preview">
                    <div class="info-row-preview">
                        <span class="info-label-preview">Payment Description:</span>
                        <span class="info-value-preview">AiToManabi Course</span>
                    </div>
                    <div class="info-row-preview">
                        <span class="info-label-preview">Payment Method:</span>
                        <span class="info-value-preview">Online Payment</span>
                    </div>
                    <div class="info-row-preview">
                        <span class="info-label-preview">Invoice ID:</span>
                        <span class="info-value-preview">INV-${paymentData.id.padStart(6, '0')}</span>
                    </div>
                    <div class="info-row-preview">
                        <span class="info-label-preview">Transaction ID:</span>
                        <span class="info-value-preview">TXN-${paymentData.id.padStart(6, '0')}</span>
                    </div>
                    <div class="info-row-preview">
                        <span class="info-label-preview">Date Paid:</span>
                        <span class="info-value-preview">${paymentData.date}</span>
                    </div>
                    <div class="info-row-preview">
                        <span class="info-label-preview">Payment Status:</span>
                        <span class="info-value-preview">${paymentData.status}</span>
                    </div>
                </div>
                
                <!-- Thank You Section -->
                <div class="thank-you-preview">
                    <div class="thank-you-text-preview">Thank you for choosing AiToManabi!</div>
                    <p class="support-text-preview">
                        For any questions or support, please contact us at aitomanabilms@gmail.com<br>
                        Visit us at www.aitomanabi.com
                    </p>
                </div>
            </div>
        `;
    }

    showPreviewModal(content) {
        // Remove existing modal if present
        this.closePreview();

        // Create modal
        const modal = document.createElement('div');
        modal.className = 'preview-modal fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
        modal.innerHTML = `
            <div class="bg-white rounded-lg shadow-xl max-w-4xl w-full mx-4 max-h-[90vh] overflow-y-auto">
                <div class="flex justify-between items-center p-4 border-b">
                    <h3 class="text-lg font-semibold">Invoice Preview</h3>
                    <button class="close-preview text-gray-500 hover:text-gray-700">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                <div class="p-6">
                    ${content}
                </div>
                <div class="flex justify-center gap-4 p-4 border-t">
                    <button class="btn-download-invoice" data-payment-id="${this.currentPaymentId}">
                        Download PDF
                    </button>
                    <button class="btn-print-invoice">
                        Print Invoice
                    </button>
                </div>
            </div>
        `;

        document.body.appendChild(modal);
        
        // Add CSS if not already present
        if (!document.querySelector('#reports-payment-css')) {
            const link = document.createElement('link');
            link.id = 'reports-payment-css';
            link.rel = 'stylesheet';
            link.href = 'css/reports-payment.css';
            document.head.appendChild(link);
        }
    }

    closePreview() {
        const modal = document.querySelector('.preview-modal');
        if (modal) {
            modal.remove();
        }
    }

    printInvoice() {
        // Get the preview content
        const previewContent = document.querySelector('.invoice-preview-content');
        if (!previewContent) {
            this.showNotification('No invoice content to print', 'error');
            return;
        }

        // Create print window
        const printWindow = window.open('', '_blank');
        printWindow.document.write(`
            <!DOCTYPE html>
            <html>
            <head>
                <title>Invoice - Print</title>
                <style>
                    body { font-family: Arial, sans-serif; margin: 20px; }
                    .invoice-header-section { text-align: center; margin-bottom: 30px; }
                    .company-name-preview { font-size: 24px; font-weight: bold; color: #1e40af; }
                    .company-tagline-preview { color: #64748b; margin: 5px 0; }
                    .invoice-title-preview { font-size: 18px; font-weight: bold; text-align: center; margin: 20px 0; border-bottom: 2px solid #e5e7eb; padding-bottom: 10px; }
                    .greeting-section-preview { background: #f0fdf4; padding: 15px; margin: 20px 0; border-radius: 8px; }
                    .order-details-preview { margin: 20px 0; }
                    .section-title-preview { font-size: 16px; font-weight: bold; color: #1e40af; margin-bottom: 15px; }
                    .order-item-preview { background: #f8fafc; padding: 15px; margin: 10px 0; border-radius: 8px; }
                    .amount-section-preview { background: #fef3c7; padding: 20px; margin: 20px 0; text-align: center; border-radius: 8px; }
                    .amount-value-preview { font-size: 24px; font-weight: bold; color: #b45309; }
                    .payment-info-preview { background: #f9fafb; padding: 15px; margin: 20px 0; border-radius: 8px; }
                    .info-row-preview { display: flex; justify-content: space-between; margin: 8px 0; padding: 5px 0; border-bottom: 1px solid #e5e7eb; }
                    .thank-you-preview { background: #f0fdf4; padding: 20px; margin: 20px 0; text-align: center; border-radius: 8px; }
                    @media print { body { margin: 0; } }
                </style>
            </head>
            <body>
                ${previewContent.outerHTML}
            </body>
            </html>
        `);
        
        printWindow.document.close();
        printWindow.focus();
        printWindow.print();
        
        // Close the print window after printing
        printWindow.addEventListener('afterprint', () => {
            printWindow.close();
        });
    }

    showLoadingOverlay(message = 'Loading...') {
        // Remove existing overlay
        this.hideLoadingOverlay();

        const overlay = document.createElement('div');
        overlay.className = 'loading-overlay';
        overlay.innerHTML = `
            <div class="bg-white rounded-lg shadow-lg p-6 flex flex-col items-center">
                <div class="loading-spinner mb-4"></div>
                <p class="text-gray-600 font-medium">${message}</p>
            </div>
        `;

        document.body.appendChild(overlay);
    }

    hideLoadingOverlay() {
        const overlay = document.querySelector('.loading-overlay');
        if (overlay) {
            overlay.remove();
        }
    }

    showNotification(message, type = 'info') {
        // Remove existing notifications
        const existingNotifications = document.querySelectorAll('.notification-toast');
        existingNotifications.forEach(notification => notification.remove());

        const notification = document.createElement('div');
        notification.className = `notification-toast fixed top-4 right-4 z-50 px-6 py-3 rounded-lg shadow-lg text-white font-medium transform transition-all duration-300 translate-x-full`;
        
        // Set background color based on type
        switch (type) {
            case 'success':
                notification.style.backgroundColor = '#10b981';
                break;
            case 'error':
                notification.style.backgroundColor = '#ef4444';
                break;
            case 'warning':
                notification.style.backgroundColor = '#f59e0b';
                break;
            default:
                notification.style.backgroundColor = '#3b82f6';
        }

        notification.textContent = message;
        document.body.appendChild(notification);

        // Animate in
        setTimeout(() => {
            notification.style.transform = 'translateX(0)';
        }, 100);

        // Auto remove after 3 seconds
        setTimeout(() => {
            notification.style.transform = 'translateX(full)';
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.remove();
                }
            }, 300);
        }, 3000);
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    // Initialize payment invoice manager
    window.paymentInvoiceManager = new PaymentInvoiceManager();
    
    // Update existing download buttons to use new system
    const downloadButtons = document.querySelectorAll('.download-invoice-btn');
    downloadButtons.forEach(button => {
        // Remove existing href to prevent default behavior
        if (button.tagName === 'A') {
            button.href = '#';
        }
        
        // Ensure button has proper classes
        button.classList.add('download-invoice-btn');
        
        // Add click handler if not already present
        if (!button.hasAttribute('data-payment-id')) {
            const href = button.getAttribute('href') || '';
            const paymentIdMatch = href.match(/payment_id=(\d+)/);
            if (paymentIdMatch) {
                button.setAttribute('data-payment-id', paymentIdMatch[1]);
            }
        }
    });
});

// Export for use in other modules
window.PaymentInvoiceManager = PaymentInvoiceManager;

// Main JavaScript for sellpincodes.com clone
const API_BASE_URL = 'backend/api';

document.addEventListener('DOMContentLoaded', function() {
    initializeApp();
});

function initializeApp() {
    // Load services data from backend
    loadServicesData();
    
    // Initialize form handlers
    initializeFormHandlers();
    
    // Initialize modal event listeners
    initializeModalEventListeners();
    
    // Initialize keyboard navigation
    initializeKeyboardNavigation();
}

// Load services data from backend
async function loadServicesData() {
    try {
        const response = await fetch(`${API_BASE_URL}/services.php`);
        const data = await response.json();
        
        if (data.success) {
            // Populate form dropdowns with real data
            populateServiceDropdowns(data.data);
        } else {
            console.error('Failed to load services data:', data.message);
        }
    } catch (error) {
        console.error('Error loading services data:', error);
    }
}

// Populate service dropdowns with backend data
function populateServiceDropdowns(servicesData) {
    const { services, momo_providers } = servicesData;
    
    // Populate WAEC exam types
    const waecService = services.find(s => s.code === 'WAEC');
    if (waecService) {
        populateExamTypes('waecType', waecService.exam_types);
        populatePricingTiers('waecQuantity', waecService.pricing_tiers);
    }
    
    // Populate SHS pricing (no exam types for SHS)
    const shsService = services.find(s => s.code === 'SHS');
    if (shsService) {
        populatePricingTiers('shsQuantity', shsService.pricing_tiers);
    }
    
    // Populate UCC form types
    const uccService = services.find(s => s.code === 'UCC');
    if (uccService) {
        populateUCCFormTypes('uccFormType', uccService.pricing_tiers);
    }
    
    // Populate mobile money providers for all forms
    populateMoMoProviders(momo_providers);
}

function populateExamTypes(selectId, examTypes) {
    const select = document.getElementById(selectId);
    if (!select) return;
    
    // Clear existing options except the first one
    select.innerHTML = '<option value="">select an option</option>';
    
    examTypes.forEach(examType => {
        const option = document.createElement('option');
        option.value = examType.id;
        option.textContent = examType.name;
        select.appendChild(option);
    });
}

function populatePricingTiers(selectId, pricingTiers) {
    const select = document.getElementById(selectId);
    if (!select) return;
    
    // Clear existing options except the first one
    select.innerHTML = '<option value="">select an option</option>';
    
    pricingTiers.forEach(tier => {
        const option = document.createElement('option');
        option.value = tier.min_quantity;
        option.textContent = tier.label;
        option.dataset.unitPrice = tier.unit_price;
        option.dataset.totalPrice = tier.total_price;
        select.appendChild(option);
    });
}

function populateUCCFormTypes(selectId, pricingTiers) {
    const select = document.getElementById(selectId);
    if (!select) return;
    
    // Clear existing options except the first one
    select.innerHTML = '<option value="">select UCC ADMISSION FORMS</option>';
    
    pricingTiers.forEach(tier => {
        const option = document.createElement('option');
        option.value = tier.min_quantity;
        option.textContent = tier.label;
        option.dataset.unitPrice = tier.unit_price;
        option.dataset.totalPrice = tier.total_price;
        select.appendChild(option);
    });
}

function populateMoMoProviders(providers) {
    const selects = ['waecMomo', 'shsMomo', 'uccMomo'];
    
    selects.forEach(selectId => {
        const select = document.getElementById(selectId);
        if (!select) return;
        
        // Clear existing options except the first one
        select.innerHTML = '<option value="">select an option</option>';
        
        providers.forEach(provider => {
            const option = document.createElement('option');
            option.value = provider.id;
            option.textContent = provider.name;
            select.appendChild(option);
        });
    });
}

// Modal Management
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('show');
        document.body.style.overflow = 'hidden';
        
        // Focus management for accessibility
        const firstInput = modal.querySelector('input, select, button');
        if (firstInput) {
            setTimeout(() => firstInput.focus(), 100);
        }
        
        // Clear any previous error messages
        clearErrorMessages(modalId);
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('show');
        document.body.style.overflow = 'auto';
        
        // Clear form data when closing
        const form = modal.querySelector('form');
        if (form) {
            form.reset();
            clearErrorMessages(modalId);
        }
    }
}

function initializeModalEventListeners() {
    // Close modal when clicking outside
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('modal')) {
            const modalId = e.target.id;
            closeModal(modalId);
        }
    });
}

function initializeKeyboardNavigation() {
    // Close modal with Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const openModal = document.querySelector('.modal.show');
            if (openModal) {
                closeModal(openModal.id);
            }
        }
    });
}

// Form Validation and Handling
function initializeFormHandlers() {
    // WAEC Form Handler
    const waecForm = document.getElementById('waecForm');
    if (waecForm) {
        waecForm.addEventListener('submit', function(e) {
            e.preventDefault();
            handleWaecFormSubmission(this);
        });
    }
    
    // SHS Form Handler
    const shsForm = document.getElementById('shsForm');
    if (shsForm) {
        shsForm.addEventListener('submit', function(e) {
            e.preventDefault();
            handleShsFormSubmission(this);
        });
    }
    
    // UCC Form Handler
    const uccForm = document.getElementById('uccForm');
    if (uccForm) {
        uccForm.addEventListener('submit', function(e) {
            e.preventDefault();
            handleUccFormSubmission(this);
        });
    }
    
    // Retrieve Form Handler
    const retrieveForm = document.getElementById('retrieveForm');
    if (retrieveForm) {
        retrieveForm.addEventListener('submit', function(e) {
            e.preventDefault();
            handleRetrieveFormSubmission(this);
        });
    }
    
    // Real-time validation
    addRealTimeValidation();
}

function addRealTimeValidation() {
    // Phone number validation
    const phoneInputs = document.querySelectorAll('input[type="tel"]');
    phoneInputs.forEach(input => {
        input.addEventListener('input', function() {
            validatePhoneNumber(this);
        });
    });
    
    // Select validation
    const selectInputs = document.querySelectorAll('select');
    selectInputs.forEach(select => {
        select.addEventListener('change', function() {
            validateSelect(this);
        });
    });
}

// Validation Functions
function validatePhoneNumber(input) {
    const phoneRegex = /^0[2-9]\d{8}$/; // Ghana phone number format
    const value = input.value.trim();
    const errorElement = document.getElementById(input.id + 'Error');
    
    if (!value) {
        showError(errorElement, 'Phone number is required');
        return false;
    } else if (!phoneRegex.test(value)) {
        showError(errorElement, 'Please enter a valid Ghana phone number (e.g., 0244123456)');
        return false;
    } else {
        clearError(errorElement);
        return true;
    }
}

function validateSelect(select) {
    const errorElement = document.getElementById(select.id + 'Error');
    
    if (!select.value) {
        showError(errorElement, 'Please select an option');
        return false;
    } else {
        clearError(errorElement);
        return true;
    }
}

function validateReferenceCode(input) {
    const value = input.value.trim().toUpperCase();
    const errorElement = document.getElementById(input.id + 'Error');
    
    if (!value) {
        showError(errorElement, 'Reference code is required');
        return false;
    } else if (!value.startsWith('QCG')) {
        showError(errorElement, 'Reference code must start with QCG');
        return false;
    } else if (value.length < 8) {
        showError(errorElement, 'Reference code must be at least 8 characters');
        return false;
    } else if (!/^[A-Z0-9]+$/.test(value)) {
        showError(errorElement, 'Reference code can only contain letters and numbers');
        return false;
    } else {
        clearError(errorElement);
        // Update input value to uppercase
        input.value = value;
        return true;
    }
}

function showError(errorElement, message) {
    if (errorElement) {
        errorElement.textContent = message;
        errorElement.style.display = 'block';
    }
}

function clearError(errorElement) {
    if (errorElement) {
        errorElement.textContent = '';
        errorElement.style.display = 'none';
    }
}

function clearErrorMessages(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        const errorElements = modal.querySelectorAll('.error-message');
        errorElements.forEach(element => {
            element.textContent = '';
            element.style.display = 'none';
        });
    }
}

// Form Submission Handlers
function handleWaecFormSubmission(form) {
    try {
        const formData = new FormData(form);
        const data = {
            type: formData.get('type'),
            quantity: formData.get('quantity'),
            momo: formData.get('momo'),
            phone: formData.get('phone')
        };
        
        // Validate all fields
        let isValid = true;
        
        if (!validateSelect(document.getElementById('waecType'))) isValid = false;
        if (!validateSelect(document.getElementById('waecQuantity'))) isValid = false;
        if (!validateSelect(document.getElementById('waecMomo'))) isValid = false;
        if (!validatePhoneNumber(document.getElementById('waecPhone'))) isValid = false;
        
        if (!isValid) {
            return;
        }
        
        // Simulate processing
        processPayment('WAEC Results Checker', data);
        
    } catch (error) {
        console.error('Error processing WAEC form:', error);
        showNotification('An error occurred. Please try again.', 'error');
    }
}

function handleShsFormSubmission(form) {
    try {
        const formData = new FormData(form);
        const data = {
            quantity: formData.get('quantity'),
            momo: formData.get('momo'),
            phone: formData.get('phone')
        };
        
        // Validate all fields
        let isValid = true;
        
        if (!validateSelect(document.getElementById('shsQuantity'))) isValid = false;
        if (!validateSelect(document.getElementById('shsMomo'))) isValid = false;
        if (!validatePhoneNumber(document.getElementById('shsPhone'))) isValid = false;
        
        if (!isValid) {
            return;
        }
        
        // Simulate processing
        processPayment('SHS Placement Checker', data);
        
    } catch (error) {
        console.error('Error processing SHS form:', error);
        showNotification('An error occurred. Please try again.', 'error');
    }
}

function handleUccFormSubmission(form) {
    try {
        const formData = new FormData(form);
        const data = {
            formType: formData.get('formType'),
            momo: formData.get('momo'),
            phone: formData.get('phone')
        };
        
        // Validate all fields
        let isValid = true;
        
        if (!validateSelect(document.getElementById('uccFormType'))) isValid = false;
        if (!validateSelect(document.getElementById('uccMomo'))) isValid = false;
        if (!validatePhoneNumber(document.getElementById('uccPhone'))) isValid = false;
        
        if (!isValid) {
            return;
        }
        
        // Simulate processing
        processPayment('UCC Admission Form', data);
        
    } catch (error) {
        console.error('Error processing UCC form:', error);
        showNotification('An error occurred. Please try again.', 'error');
    }
}

function handleRetrieveFormSubmission(form) {
    try {
        const formData = new FormData(form);
        const data = {
            phone_number: formData.get('phone'),
            reference_code: formData.get('referenceCode'),
            resend_sms: formData.get('resendSms') === 'on'
        };
        
        // Validate all fields
        let isValid = true;
        
        if (!validatePhoneNumber(document.getElementById('retrievePhone'))) isValid = false;
        if (!validateReferenceCode(document.getElementById('retrieveId'))) isValid = false;
        
        if (!isValid) {
            return;
        }
        
        // Process retrieval with backend
        processRetrievalBackend(data);
        
    } catch (error) {
        console.error('Error processing retrieval:', error);
        showNotification('An error occurred. Please try again.', 'error');
    }
}

// Backend Integration Functions
async function processPaymentBackend(data, modalId) {
    const submitBtn = event.target.querySelector('.submit-btn');
    
    // Disable submit button and show loading state
    submitBtn.disabled = true;
    submitBtn.textContent = 'Processing...';
    
    try {
        const response = await fetch(`${API_BASE_URL}/purchase.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (result.success) {
            showPaymentSuccessBackend(result, modalId);
            
            // Close modal after success
            setTimeout(() => {
                closeModal(modalId);
            }, 5000);
        } else {
            showPaymentErrorBackend(result.message);
        }
        
    } catch (error) {
        console.error('Payment processing error:', error);
        showPaymentErrorBackend('Network error. Please check your connection and try again.');
    } finally {
        // Re-enable submit button
        submitBtn.disabled = false;
        submitBtn.textContent = 'Pay now';
    }
}

async function processRetrievalBackend(data) {
    const submitBtn = event.target.querySelector('.submit-btn');
    
    // Disable submit button and show loading state
    submitBtn.disabled = true;
    submitBtn.textContent = 'Retrieving...';
    
    try {
        const response = await fetch(`${API_BASE_URL}/retrieve.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (result.success) {
            showRetrievalSuccessBackend(result);
            
            // Close modal after success
            setTimeout(() => {
                closeModal('retrieveModal');
            }, 5000);
        } else {
            showRetrievalErrorBackend(result.message);
        }
        
    } catch (error) {
        console.error('Retrieval processing error:', error);
        showRetrievalErrorBackend('Network error. Please check your connection and try again.');
    } finally {
        // Re-enable submit button
        submitBtn.disabled = false;
        submitBtn.textContent = 'Submit';
    }
}

// Backend Success/Error Message Functions
function showPaymentSuccessBackend(result, modalId) {
    const checkers = result.checkers;
    let checkersHtml = '';
    
    if (checkers && checkers.checkers) {
        checkersHtml = '<div class="checkers-list" style="margin-top: 20px; max-height: 200px; overflow-y: auto;">';
        checkers.checkers.forEach((checker, index) => {
            checkersHtml += `
                <div style="border: 1px solid #e5e7eb; padding: 10px; margin-bottom: 10px; border-radius: 5px;">
                    <strong>Checker ${index + 1}:</strong><br>
                    Serial: ${checker.serial_number}<br>
                    PIN: ${checker.pin_code}
                    ${checker.voucher_code ? '<br>Voucher: ' + checker.voucher_code : ''}
                </div>
            `;
        });
        checkersHtml += '</div>';
    }
    
    const pdfDownloadBtn = result.pdf_download_url ? 
        `<button type="button" class="submit-btn" onclick="window.open('${result.pdf_download_url}', '_blank')" style="margin-right: 10px;">Download PDF</button>` : '';
    
    const message = `
        <div class="success-message">
            <h4>Purchase Successful!</h4>
            <p><strong>Service:</strong> ${checkers?.service_name || 'Service'}</p>
            <p><strong>Purchase Reference:</strong> ${result.purchase_reference}</p>
            <p><strong>Transaction ID:</strong> ${result.transaction_id}</p>
            <p><strong>Print ID:</strong> ${result.print_id}</p>
            <p><strong>Quantity:</strong> ${checkers?.quantity || 0} checkers</p>
            <p><strong>SMS Status:</strong> ${result.sms_sent ? 'Sent successfully' : 'Failed to send'}</p>
            <p><strong>PDF Receipt:</strong> ${result.pdf_generated ? 'Generated successfully' : 'Failed to generate'}</p>
            ${checkersHtml}
            <p style="margin-top: 15px;">Your checkers have been ${result.sms_sent ? 'sent to your phone via SMS' : 'generated'}. Please save your <strong>Purchase Reference Code: ${result.purchase_reference}</strong> for future retrieval.</p>
            <div style="margin-top: 20px;">
                ${pdfDownloadBtn}
                <button type="button" class="submit-btn" onclick="window.print()" style="margin-right: 10px;">Print Checkers</button>
                <button type="button" class="submit-btn" onclick="closeModal('${modalId}')">Close</button>
            </div>
        </div>
    `;
    
    const modalContent = event.target.closest('.modal-content');
    const modalForm = modalContent.querySelector('.modal-form');
    modalForm.innerHTML = message;
}

function showPaymentErrorBackend(errorMessage) {
    const message = `
        <div class="error-alert">
            <h4>Payment Failed</h4>
            <p>${errorMessage}</p>
            <button type="button" class="submit-btn" onclick="location.reload()">Try Again</button>
        </div>
    `;
    
    const modalContent = event.target.closest('.modal-content');
    const modalForm = modalContent.querySelector('.modal-form');
    modalForm.innerHTML = message;
}

function showRetrievalSuccessBackend(result) {
    const transaction = result.transaction;
    const checkers = result.checkers;
    
    let checkersHtml = '';
    if (checkers && checkers.checkers) {
        checkersHtml = '<div class="checkers-list" style="margin-top: 20px; max-height: 200px; overflow-y: auto;">';
        checkers.checkers.forEach((checker, index) => {
            checkersHtml += `
                <div style="border: 1px solid #e5e7eb; padding: 10px; margin-bottom: 10px; border-radius: 5px;">
                    <strong>Checker ${index + 1}:</strong><br>
                    Serial: ${checker.serial_number}<br>
                    PIN: ${checker.pin_code}
                    ${checker.voucher_code ? '<br>Voucher: ' + checker.voucher_code : ''}
                </div>
            `;
        });
        checkersHtml += '</div>';
    }
    
    const pdfDownloadBtn = result.pdf_download_url ? 
        `<button type="button" class="submit-btn" onclick="window.open('${result.pdf_download_url}', '_blank')" style="margin-right: 10px;">Download PDF</button>` : '';
    
    const message = `
        <div class="success-message">
            <h4>Checkers Retrieved Successfully!</h4>
            <p><strong>Service:</strong> ${transaction.service_name}</p>
            <p><strong>Purchase Reference:</strong> ${transaction.reference_code}</p>
            <p><strong>Transaction ID:</strong> ${transaction.transaction_id}</p>
            <p><strong>Print ID:</strong> ${transaction.print_id}</p>
            <p><strong>Quantity:</strong> ${transaction.quantity} checkers</p>
            <p><strong>Amount Paid:</strong> Gh¢ ${parseFloat(transaction.total_amount).toFixed(2)}</p>
            <p><strong>PDF Receipt:</strong> ${result.pdf_generated ? 'Generated successfully' : 'Failed to generate'}</p>
            ${result.sms_resent ? `<p><strong>SMS Status:</strong> ${result.sms_message}</p>` : ''}
            ${checkersHtml}
            <div style="margin-top: 20px;">
                ${pdfDownloadBtn}
                <button type="button" class="submit-btn" onclick="window.print()" style="margin-right: 10px;">Print Checkers</button>
                <button type="button" class="submit-btn" onclick="closeModal('retrieveModal')">Close</button>
            </div>
        </div>
    `;
    
    const modalContent = document.querySelector('#retrieveModal .modal-form');
    modalContent.innerHTML = message;
}

function showRetrievalErrorBackend(errorMessage) {
    const message = `
        <div class="error-alert">
            <h4>Retrieval Failed</h4>
            <p>${errorMessage}</p>
            <button type="button" class="submit-btn" onclick="location.reload()">Try Again</button>
        </div>
    `;
    
    const modalContent = document.querySelector('#retrieveModal .modal-form');
    modalContent.innerHTML = message;
}

// Legacy functions for backward compatibility
function showPaymentSuccess(serviceType, data, transactionId) {
    // Kept for backward compatibility - redirects to backend version
    showPaymentSuccessBackend({
        transaction_id: transactionId,
        print_id: 'PRT' + transactionId.slice(-6),
        checkers: { service_name: serviceType, quantity: data.quantity || 1 },
        sms_sent: true
    }, 'modal');
}

function showPaymentError() {
    showPaymentErrorBackend('There was an error processing your payment. Please check your mobile money account and try again.');
}

function showRetrievalSuccess(data) {
    showRetrievalSuccessBackend({
        transaction: {
            service_name: 'Service',
            transaction_id: data.transactionId,
            print_id: 'PRT' + data.transactionId.slice(-6),
            quantity: 1,
            total_amount: '0.00'
        },
        checkers: { checkers: [] }
    });
}

function showRetrievalError() {
    showRetrievalErrorBackend('Could not find checkers with the provided information. Please check your Transaction ID and phone number.');
}

// Utility Functions
function generateTransactionId() {
    const timestamp = Date.now().toString();
    const random = Math.random().toString(36).substring(2, 8).toUpperCase();
    return `TXN${timestamp.slice(-6)}${random}`;
}

function showNotification(message, type = 'info') {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
        <div class="notification-content">
            <span>${message}</span>
            <button class="notification-close" onclick="this.parentElement.parentElement.remove()">&times;</button>
        </div>
    `;
    
    // Add notification styles if not already present
    if (!document.querySelector('#notification-styles')) {
        const styles = document.createElement('style');
        styles.id = 'notification-styles';
        styles.textContent = `
            .notification {
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 10000;
                max-width: 400px;
                padding: 16px;
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                animation: slideInRight 0.3s ease;
            }
            .notification-info { background: #3b82f6; color: white; }
            .notification-success { background: #10b981; color: white; }
            .notification-error { background: #ef4444; color: white; }
            .notification-content { display: flex; justify-content: space-between; align-items: center; }
            .notification-close { background: none; border: none; color: inherit; font-size: 20px; cursor: pointer; }
            @keyframes slideInRight { from { transform: translateX(100%); } to { transform: translateX(0); } }
        `;
        document.head.appendChild(styles);
    }
    
    // Add to page
    document.body.appendChild(notification);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        if (notification.parentElement) {
            notification.remove();
        }
    }, 5000);
}

// Initialize price calculation for quantity changes
document.addEventListener('change', function(e) {
    if (e.target.tagName === 'SELECT' && e.target.name === 'quantity') {
        updatePriceDisplay(e.target);
    }
});

function updatePriceDisplay(selectElement) {
    const selectedOption = selectElement.options[selectElement.selectedIndex];
    if (selectedOption && selectedOption.value) {
        // Price is already included in the option text
        console.log('Selected quantity:', selectedOption.text);
    }
}

// Smooth scrolling for internal links
document.addEventListener('click', function(e) {
    if (e.target.tagName === 'A' && e.target.getAttribute('href')?.startsWith('#')) {
        e.preventDefault();
        const targetId = e.target.getAttribute('href').substring(1);
        const targetElement = document.getElementById(targetId);
        if (targetElement) {
            targetElement.scrollIntoView({ behavior: 'smooth' });
        }
    }
});

// Form input formatting
document.addEventListener('input', function(e) {
    if (e.target.type === 'tel') {
        // Format phone number as user types
        let value = e.target.value.replace(/\D/g, ''); // Remove non-digits
        if (value.length > 10) {
            value = value.substring(0, 10);
        }
        e.target.value = value;
    }
});

// Prevent form submission on Enter key in select elements
document.addEventListener('keydown', function(e) {
    if (e.key === 'Enter' && e.target.tagName === 'SELECT') {
        e.preventDefault();
    }
});

// Add loading states to buttons
function addLoadingState(button, loadingText = 'Loading...') {
    const originalText = button.textContent;
    button.disabled = true;
    button.textContent = loadingText;
    
    return function removeLoadingState() {
        button.disabled = false;
        button.textContent = originalText;
    };
}

// Error boundary for unhandled errors
window.addEventListener('error', function(e) {
    console.error('Unhandled error:', e.error);
    showNotification('An unexpected error occurred. Please refresh the page and try again.', 'error');
});

// Service worker registration for offline functionality (optional)
if ('serviceWorker' in navigator) {
    window.addEventListener('load', function() {
        // Uncomment to enable service worker
        // navigator.serviceWorker.register('/sw.js')
        //     .then(registration => console.log('SW registered'))
        //     .catch(error => console.log('SW registration failed'));
    });
}

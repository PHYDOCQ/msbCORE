/**
 * BENGKEL MANAGEMENT PRO - MAIN APPLICATION SCRIPT
 * Version: 3.1.0
 * Author: Professional Development Team
 */

class BengkelApp {
    constructor() {
        this.init();
        this.setupEventListeners();
        this.setupAjaxDefaults();
        this.startPeriodicTasks();
        
        if (window.APP_CONFIG.debug) {
            console.log('🚗 Bengkel Management Pro initialized');
            this.enableDebugMode();
        }
    }
    
    init() {
        // Initialize tooltips
        this.initializeTooltips();
        
        // Initialize popovers
        this.initializePopovers();
        
        // Initialize sidebar toggle
        this.initializeSidebar();
        
        // Initialize form validation
        this.initializeFormValidation();
        
        // Initialize file uploads
        this.initializeFileUploads();
        
        // Initialize data tables
        this.initializeDataTables();
        
        // Initialize notifications
        this.initializeNotifications();
        
        // Initialize modals
        this.initializeModals();
        
        // Initialize auto-save
        this.initializeAutoSave();
    }
    
    setupEventListeners() {
        // Global click handler
        document.addEventListener('click', this.handleGlobalClick.bind(this));
        
        // Global form submit handler
        document.addEventListener('submit', this.handleFormSubmit.bind(this));
        
        // Global key press handler
        document.addEventListener('keydown', this.handleKeyPress.bind(this));
        
        // Window resize handler
        window.addEventListener('resize', this.handleResize.bind(this));
        
        // Before unload handler
        window.addEventListener('beforeunload', this.handleBeforeUnload.bind(this));
        
        // Online/offline handlers
        window.addEventListener('online', this.handleOnline.bind(this));
        window.addEventListener('offline', this.handleOffline.bind(this));
    }
    
    setupAjaxDefaults() {
        // Set default CSRF token for all AJAX requests
        const token = document.querySelector('meta[name="csrf-token"]');
        if (token) {
            XMLHttpRequest.prototype.setRequestHeader = function(header, value) {
                if (header.toLowerCase() === 'x-csrf-token') {
                    value = token.getAttribute('content');
                }
                return XMLHttpRequest.prototype.setRequestHeader.call(this, header, value);
            };
        }
        
        // Global AJAX error handler
        document.addEventListener('ajaxError', (event) => {
            const xhr = event.detail.xhr;
            if (xhr.status === 401) {
                this.handleUnauthorized();
            } else if (xhr.status === 403) {
                this.showToast('Access denied', 'error');
            } else if (xhr.status >= 500) {
                this.showToast('Server error occurred', 'error');
                if (window.APP_CONFIG.debug) {
                    console.error('Server error:', xhr.responseText);
                }
            }
        });
    }
    
    startPeriodicTasks() {
        // Check for notifications every 60 seconds
        setInterval(() => {
            this.loadNotifications();
        }, 60000);
        
        // Auto-save forms every 30 seconds
        setInterval(() => {
            this.autoSaveForms();
        }, 30000);
        
        // Check connection status every 10 seconds
        setInterval(() => {
            this.checkConnectionStatus();
        }, 10000);
    }
    
    // ========================================
    // INITIALIZATION METHODS
    // ========================================
    
    initializeTooltips() {
        const tooltipElements = document.querySelectorAll('[data-bs-toggle="tooltip"]');
        tooltipElements.forEach(element => {
            new bootstrap.Tooltip(element);
        });
    }
    
    initializePopovers() {
        const popoverElements = document.querySelectorAll('[data-bs-toggle="popover"]');
        popoverElements.forEach(element => {
            new bootstrap.Popover(element);
        });
    }
    
    initializeSidebar() {
        const sidebarToggle = document.querySelector('#sidebarToggle');
        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', (e) => {
                e.preventDefault();
                document.body.classList.toggle('sb-sidenav-toggled');
                localStorage.setItem('sb|sidebar-toggle', document.body.classList.contains('sb-sidenav-toggled'));
            });
        }
        
        // Restore sidebar state
        if (localStorage.getItem('sb|sidebar-toggle') === 'true') {
            document.body.classList.add('sb-sidenav-toggled');
        }
        
        // Auto-collapse sidebar on mobile
        const sidenavLinks = document.querySelectorAll('.sb-sidenav .nav-link');
        sidenavLinks.forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth < 992) {
                    document.body.classList.add('sb-sidenav-toggled');
                }
            });
        });
    }
    
    initializeFormValidation() {
        const forms = document.querySelectorAll('.needs-validation');
        forms.forEach(form => {
            form.addEventListener('submit', (event) => {
                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                    this.showFirstValidationError(form);
                }
                form.classList.add('was-validated');
            });
        });
        
        // Real-time validation
        const inputs = document.querySelectorAll('input, textarea, select');
        inputs.forEach(input => {
            input.addEventListener('blur', () => {
                this.validateField(input);
            });
            
            input.addEventListener('input', () => {
                if (input.classList.contains('is-invalid')) {
                    this.validateField(input);
                }
            });
        });
    }
    
    initializeFileUploads() {
        const fileUploadAreas = document.querySelectorAll('.file-upload-area');
        fileUploadAreas.forEach(area => {
            const input = area.querySelector('input[type="file"]');
            
            // Click to upload
            area.addEventListener('click', () => {
                input.click();
            });
            
            // Drag and drop
            area.addEventListener('dragover', (e) => {
                e.preventDefault();
                area.classList.add('dragover');
            });
            
            area.addEventListener('dragleave', () => {
                area.classList.remove('dragover');
            });
            
            area.addEventListener('drop', (e) => {
                e.preventDefault();
                area.classList.remove('dragover');
                const files = e.dataTransfer.files;
                this.handleFileUpload(files, input);
            });
            
            // File input change
            input.addEventListener('change', () => {
                this.handleFileUpload(input.files, input);
            });
        });
    }
    
    initializeDataTables() {
        const tables = document.querySelectorAll('.data-table');
        tables.forEach(table => {
            this.enhanceDataTable(table);
        });
    }
    
    initializeNotifications() {
        // Load initial notifications
        this.loadNotifications();
        
        // Mark notifications as read when dropdown is opened
        const notificationDropdown = document.getElementById('notificationDropdown');
        if (notificationDropdown) {
            notificationDropdown.addEventListener('shown.bs.dropdown', () => {
                this.markNotificationsAsRead();
            });
        }
    }
    
    initializeModals() {
        // Auto focus first input in modals
        const modals = document.querySelectorAll('.modal');
        modals.forEach(modal => {
            modal.addEventListener('shown.bs.modal', () => {
                const firstInput = modal.querySelector('input, select, textarea');
                if (firstInput) {
                    firstInput.focus();
                }
            });
        });
    }
    
    initializeAutoSave() {
        const autoSaveForms = document.querySelectorAll('[data-auto-save]');
        autoSaveForms.forEach(form => {
            const inputs = form.querySelectorAll('input, textarea, select');
            inputs.forEach(input => {
                input.addEventListener('input', () => {
                    clearTimeout(input.autoSaveTimeout);
                    input.autoSaveTimeout = setTimeout(() => {
                        this.autoSaveForm(form);
                    }, 2000);
                });
            });
        });
    }
    
    // ========================================
    // EVENT HANDLERS
    // ========================================
    
    handleGlobalClick(event) {
        const target = event.target;
        
        // Handle confirm actions
        if (target.dataset.confirm) {
            event.preventDefault();
            this.showConfirmDialog(target.dataset.confirm, () => {
                if (target.href) {
                    window.location.href = target.href;
                } else if (target.onclick) {
                    target.onclick();
                }
            });
        }
        
        // Handle AJAX links
        if (target.dataset.ajax) {
            event.preventDefault();
            this.handleAjaxRequest(target.href || target.dataset.url, {
                method: target.dataset.method || 'GET',
                callback: target.dataset.callback
            });
        }
        
        // Handle delete actions
        if (target.classList.contains('delete-btn') || target.dataset.action === 'delete') {
            event.preventDefault();
            this.handleDeleteAction(target);
        }
    }
    
    handleFormSubmit(event) {
        const form = event.target;
        
        // Handle AJAX forms
        if (form.dataset.ajax !== undefined) {
            event.preventDefault();
            this.submitFormAjax(form);
        }
        
        // Prevent double submission
        if (form.dataset.submitting === 'true') {
            event.preventDefault();
            return;
        }
        
        form.dataset.submitting = 'true';
        setTimeout(() => {
            form.dataset.submitting = 'false';
        }, 5000);
    }
    
    handleKeyPress(event) {
        // Ctrl+S to save forms
        if (event.ctrlKey && event.key === 's') {
            event.preventDefault();
            const form = document.querySelector('form');
            if (form) {
                form.submit();
            }
        }
        
        // Escape to close modals
        if (event.key === 'Escape') {
            const openModal = document.querySelector('.modal.show');
            if (openModal) {
                const modal = bootstrap.Modal.getInstance(openModal);
                modal.hide();
            }
        }
    }
    
    handleResize() {
        // Auto-collapse sidebar on mobile
        if (window.innerWidth < 992 && !document.body.classList.contains('sb-sidenav-toggled')) {
            document.body.classList.add('sb-sidenav-toggled');
        }
    }
    
    handleBeforeUnload(event) {
        const dirtyForms = document.querySelectorAll('form[data-dirty="true"]');
        if (dirtyForms.length > 0) {
            event.preventDefault();
            event.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
            return event.returnValue;
        }
    }
    
    handleOnline() {
        this.showToast('Connection restored', 'success');
        document.body.classList.remove('offline');
    }
    
    handleOffline() {
        this.showToast('Connection lost. Working in offline mode.', 'warning');
        document.body.classList.add('offline');
    }
    
    handleUnauthorized() {
        this.showToast('Session expired. Please login again.', 'error');
        setTimeout(() => {
            window.location.href = '/login.php';
        }, 2000);
    }
    
    // ========================================
    // ENHANCED NOTIFICATION SYSTEM
    // ========================================
    
    showToast(message, type = 'info', options = {}) {
        const defaultOptions = {
            title: null,
            duration: 5000,
            closable: true,
            progress: true,
            sound: false,
            position: 'top-right'
        };
        
        const config = { ...defaultOptions, ...options };
        
        // Create toast container if it doesn't exist
        let container = document.querySelector('.toast-container');
        if (!container) {
            container = document.createElement('div');
            container.className = 'toast-container';
            document.body.appendChild(container);
        }
        
        // Create toast element
        const toast = document.createElement('div');
        toast.className = `toast-notification ${type}`;
        
        // Generate unique ID
        const toastId = 'toast_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
        toast.id = toastId;
        
        // Get icon based on type
        const icons = {
            success: 'fas fa-check-circle',
            error: 'fas fa-exclamation-circle',
            warning: 'fas fa-exclamation-triangle',
            info: 'fas fa-info-circle'
        };
        
        // Build toast content
        let toastContent = `
            <div class="toast-content">
                <div class="toast-icon ${type}">
                    <i class="${icons[type] || icons.info}"></i>
                </div>
                <div class="toast-message">
                    ${config.title ? `<div class="toast-title">${config.title}</div>` : ''}
                    ${message}
                </div>
            </div>
        `;
        
        // Add close button if closable
        if (config.closable) {
            toastContent += `
                <button class="toast-close" onclick="bengkelApp.closeToast('${toastId}')">
                    <i class="fas fa-times"></i>
                </button>
            `;
        }
        
        // Add progress bar if enabled
        if (config.progress && config.duration > 0) {
            toastContent += `
                <div class="toast-progress">
                    <div class="toast-progress-bar ${type}"></div>
                </div>
            `;
        }
        
        toast.innerHTML = toastContent;
        
        // Add to container
        container.appendChild(toast);
        
        // Trigger show animation
        setTimeout(() => {
            toast.classList.add('show');
        }, 10);
        
        // Setup progress bar animation
        if (config.progress && config.duration > 0) {
            const progressBar = toast.querySelector('.toast-progress-bar');
            if (progressBar) {
                progressBar.style.transform = 'scaleX(0)';
                progressBar.style.transition = `transform ${config.duration}ms linear`;
                setTimeout(() => {
                    progressBar.style.transform = 'scaleX(1)';
                }, 50);
            }
        }
        
        // Auto dismiss
        if (config.duration > 0) {
            setTimeout(() => {
                this.closeToast(toastId);
            }, config.duration);
        }
        
        // Play sound if enabled
        if (config.sound) {
            this.playNotificationSound(type);
        }
        
        // Add click to dismiss
        toast.addEventListener('click', (e) => {
            if (!e.target.closest('.toast-close')) {
                this.closeToast(toastId);
            }
        });
        
        return toastId;
    }
    
    closeToast(toastId) {
        const toast = document.getElementById(toastId);
        if (toast) {
            toast.classList.add('hide');
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.parentNode.removeChild(toast);
                }
            }, 400);
        }
    }
    
    closeAllToasts() {
        const toasts = document.querySelectorAll('.toast-notification');
        toasts.forEach(toast => {
            this.closeToast(toast.id);
        });
    }
    
    playNotificationSound(type) {
        // Create audio context for notification sounds
        if (typeof AudioContext !== 'undefined' || typeof webkitAudioContext !== 'undefined') {
            const audioContext = new (AudioContext || webkitAudioContext)();
            const oscillator = audioContext.createOscillator();
            const gainNode = audioContext.createGain();
            
            oscillator.connect(gainNode);
            gainNode.connect(audioContext.destination);
            
            // Different frequencies for different types
            const frequencies = {
                success: 800,
                error: 400,
                warning: 600,
                info: 500
            };
            
            oscillator.frequency.setValueAtTime(frequencies[type] || 500, audioContext.currentTime);
            oscillator.type = 'sine';
            
            gainNode.gain.setValueAtTime(0.1, audioContext.currentTime);
            gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.3);
            
            oscillator.start(audioContext.currentTime);
            oscillator.stop(audioContext.currentTime + 0.3);
        }
    }
    
    // Enhanced error handling methods
    showError(message, options = {}) {
        return this.showToast(message, 'error', {
            title: 'Error',
            duration: 7000,
            sound: true,
            ...options
        });
    }
    
    showSuccess(message, options = {}) {
        return this.showToast(message, 'success', {
            title: 'Success',
            duration: 4000,
            sound: true,
            ...options
        });
    }
    
    showWarning(message, options = {}) {
        return this.showToast(message, 'warning', {
            title: 'Warning',
            duration: 6000,
            ...options
        });
    }
    
    showInfo(message, options = {}) {
        return this.showToast(message, 'info', {
            title: 'Information',
            duration: 5000,
            ...options
        });
    }
    
    // Form validation with enhanced feedback
    validateForm(form) {
        let isValid = true;
        const fields = form.querySelectorAll('input, select, textarea');
        
        fields.forEach(field => {
            this.validateField(field);
            if (field.classList.contains('is-invalid')) {
                isValid = false;
            }
        });
        
        return isValid;
    }
    
    validateField(field) {
        const value = field.value.trim();
        const type = field.type;
        const required = field.hasAttribute('required');
        let isValid = true;
        let message = '';
        
        // Remove existing validation classes
        field.classList.remove('is-valid', 'is-invalid');
        
        // Remove existing feedback
        const existingFeedback = field.parentNode.querySelector('.invalid-feedback, .valid-feedback');
        if (existingFeedback) {
            existingFeedback.remove();
        }
        
        // Required field validation
        if (required && !value) {
            isValid = false;
            message = 'This field is required';
        }
        
        // Type-specific validation
        if (value && isValid) {
            switch (type) {
                case 'email':
                    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    if (!emailRegex.test(value)) {
                        isValid = false;
                        message = 'Please enter a valid email address';
                    }
                    break;
                    
                case 'tel':
                    const phoneRegex = /^[\+]?[1-9][\d]{0,15}$/;
                    if (!phoneRegex.test(value.replace(/\s/g, ''))) {
                        isValid = false;
                        message = 'Please enter a valid phone number';
                    }
                    break;
                    
                case 'password':
                    if (value.length < 6) {
                        isValid = false;
                        message = 'Password must be at least 6 characters long';
                    }
                    break;
                    
                case 'number':
                    if (isNaN(value)) {
                        isValid = false;
                        message = 'Please enter a valid number';
                    }
                    break;
            }
        }
        
        // Custom validation patterns
        const pattern = field.getAttribute('pattern');
        if (pattern && value && isValid) {
            const regex = new RegExp(pattern);
            if (!regex.test(value)) {
                isValid = false;
                message = field.getAttribute('data-pattern-message') || 'Invalid format';
            }
        }
        
        // Apply validation result
        if (isValid && value) {
            field.classList.add('is-valid');
            this.addFeedback(field, 'Looks good!', 'valid');
        } else if (!isValid) {
            field.classList.add('is-invalid');
            this.addFeedback(field, message, 'invalid');
        }
        
        return isValid;
    }
    
    addFeedback(field, message, type) {
        const feedback = document.createElement('div');
        feedback.className = `${type}-feedback`;
        feedback.textContent = message;
        field.parentNode.appendChild(feedback);
    }
    
    // Enhanced loading states
    showLoading(show = true, message = 'Loading...') {
        let overlay = document.querySelector('.loading-overlay');
        
        if (show) {
            if (!overlay) {
                overlay = document.createElement('div');
                overlay.className = 'loading-overlay';
                overlay.innerHTML = `
                    <div class="loading-content">
                        <div class="loading-spinner"></div>
                        <div class="loading-message">${message}</div>
                    </div>
                `;
                document.body.appendChild(overlay);
            }
            
            overlay.classList.add('show');
            document.body.style.overflow = 'hidden';
        } else {
            if (overlay) {
                overlay.classList.remove('show');
                document.body.style.overflow = '';
                setTimeout(() => {
                    if (overlay.parentNode) {
                        overlay.parentNode.removeChild(overlay);
                    }
                }, 300);
            }
        }
    }
    
    // ========================================
    // AJAX METHODS
    // ========================================
    
    async makeRequest(url, options = {}) {
        const defaultOptions = {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-Token': window.APP_CONFIG.csrf_token
            }
        };
        
        const mergedOptions = { ...defaultOptions, ...options };
        
        // Add CSRF token to form data if it's a POST request
        if (mergedOptions.method !== 'GET' && mergedOptions.body instanceof FormData) {
            mergedOptions.body.append('csrf_token', window.APP_CONFIG.csrf_token);
        }
        
        this.showLoading(true, 'Processing request...');
        
        try {
            const response = await fetch(url, mergedOptions);
            
            if (!response.ok) {
                let errorMessage = `HTTP error! status: ${response.status}`;
                
                // Try to get error message from response
                try {
                    const errorData = await response.json();
                    if (errorData.message) {
                        errorMessage = errorData.message;
                    }
                } catch (e) {
                    // If response is not JSON, use status text
                    errorMessage = response.statusText || errorMessage;
                }
                
                // Show appropriate error message
                if (response.status === 401) {
                    this.showError('Session expired. Please login again.');
                    setTimeout(() => {
                        window.location.href = '/login.php';
                    }, 2000);
                } else if (response.status === 403) {
                    this.showError('Access denied. You don't have permission to perform this action.');
                } else if (response.status === 404) {
                    this.showError('The requested resource was not found.');
                } else if (response.status >= 500) {
                    this.showError('Server error occurred. Please try again later.');
                } else {
                    this.showError(errorMessage);
                }
                
                throw new Error(errorMessage);
            }
            
            const contentType = response.headers.get('content-type');
            let data;
            
            if (contentType && contentType.includes('application/json')) {
                data = await response.json();
            } else {
                data = await response.text();
            }
            
            this.debugLog('AJAX Response:', { url, data });
            
            // Show success message if provided in response
            if (data && typeof data === 'object' && data.success && data.message) {
                this.showSuccess(data.message);
            }
            
            return data;
            
        } catch (error) {
            this.debugLog('AJAX Error:', { url, error: error.message });
            
            // Only show error if it wasn't already shown above
            if (!error.message.includes('HTTP error!')) {
                this.showError('Network error occurred. Please check your connection.');
            }
            
            throw error;
        } finally {
            this.showLoading(false);
        }
    }
    
    // ========================================
    // ENHANCED FORM HANDLING
    // ========================================
    
    initializeFormValidation() {
        // Real-time validation for all forms
        document.addEventListener('input', (e) => {
            if (e.target.matches('input, select, textarea')) {
                this.validateField(e.target);
            }
        });
        
        // Enhanced form submission
        document.addEventListener('submit', (e) => {
            const form = e.target;
            if (form.dataset.validate !== 'false') {
                if (!this.validateForm(form)) {
                    e.preventDefault();
                    this.showError('Please correct the errors in the form before submitting.');
                    
                    // Focus on first invalid field
                    const firstInvalid = form.querySelector('.is-invalid');
                    if (firstInvalid) {
                        firstInvalid.focus();
                    }
                }
            }
        });
    }
    
    // ========================================
    // UTILITY METHODS
    // ========================================
    
    debugLog(message, data = null) {
        if (window.APP_CONFIG.debug) {
            console.log(`[BengkelApp] ${message}`, data);
        }
    }
    
    // Format currency
    formatCurrency(amount, currency = 'IDR') {
        return new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: currency
        }).format(amount);
    }
    
    // Format date
    formatDate(date, options = {}) {
        const defaultOptions = {
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        };
        
        return new Intl.DateTimeFormat('id-ID', { ...defaultOptions, ...options }).format(new Date(date));
    }
    
    // Debounce function
    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
    
    // Throttle function
    throttle(func, limit) {
        let inThrottle;
        return function() {
            const args = arguments;
            const context = this;
            if (!inThrottle) {
                func.apply(context, args);
                inThrottle = true;
                setTimeout(() => inThrottle = false, limit);
            }
        };
    }
}

// Initialize the application
const bengkelApp = new BengkelApp();

// Global utility functions for backward compatibility
function showToast(message, type = 'info', options = {}) {
    return bengkelApp.showToast(message, type, options);
}

function showError(message, options = {}) {
    return bengkelApp.showError(message, options);
}

function showSuccess(message, options = {}) {
    return bengkelApp.showSuccess(message, options);
}

function showWarning(message, options = {}) {
    return bengkelApp.showWarning(message, options);
}

function showInfo(message, options = {}) {
    return bengkelApp.showInfo(message, options);
}

// Export for module usage
if (typeof module !== 'undefined' && module.exports) {
    module.exports = BengkelApp;
}

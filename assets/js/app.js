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
        
        this.showLoading(true);
        
        try {
            const response = await fetch(url, mergedOptions);
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const contentType = response.headers.get('content-type');
            let data;
            
            if (contentType && contentType.includes('application/json')) {
                data = await response.json();
            } else {
                data = await response.text();
            }
            
            this.debugLog('AJAX Response:', { url, data });
            return data;
            
        } catch (error) {
            this.debugLog('AJAX Error:', { url, error: error.message });
            throw error;
        } finally {
            this.showLoading(false);
        }
    }

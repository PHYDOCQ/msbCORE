/**
 * BENGKEL MANAGEMENT PRO - VALIDATION MODULE
 * Version: 3.1.0
 * Advanced Form Validation with Custom Rules
 */

class BengkelValidation {
    constructor() {
        this.rules = new Map();
        this.messages = new Map();
        this.validators = new Map();
        
        this.init();
    }
    
    init() {
        this.registerDefaultValidators();
        this.registerDefaultMessages();
        this.setupEventListeners();
    }
    
    registerDefaultValidators() {
        // Required validator
        this.addValidator('required', (value, params) => {
            if (Array.isArray(value)) {
                return value.length > 0;
            }
            return value !== null && value !== undefined && String(value).trim() !== '';
        });
        
        // Email validator
        this.addValidator('email', (value) => {
            if (!value) return true; // Optional field
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(value);
        });
        
        // Phone validator
        this.addValidator('phone', (value) => {
            if (!value) return true;
            const phoneRegex = /^[\+]?[0-9\s\-\(\)]{10,}$/;
            return phoneRegex.test(value);
        });
        
        // URL validator
        this.addValidator('url', (value) => {
            if (!value) return true;
            try {
                new URL(value);
                return true;
            } catch {
                return false;
            }
        });
        
        // Minimum length validator
        this.addValidator('min_length', (value, params) => {
            if (!value) return true;
            const minLength = parseInt(params[0]);
            return String(value).length >= minLength;
        });
        
        // Maximum length validator
        this.addValidator('max_length', (value, params) => {
            if (!value) return true;
            const maxLength = parseInt(params[0]);
            return String(value).length <= maxLength;
        });
        
        // Minimum value validator
        this.addValidator('min', (value, params) => {
            if (!value) return true;
            const minValue = parseFloat(params[0]);
            return parseFloat(value) >= minValue;
        });
        
        // Maximum value validator
        this.addValidator('max', (value, params) => {
            if (!value) return true;
            const maxValue = parseFloat(params[0]);
            return parseFloat(value) <= maxValue;
        });
        
        // Numeric validator
        this.addValidator('numeric', (value) => {
            if (!value) return true;
            return !isNaN(value) && !isNaN(parseFloat(value));
        });
        
        // Integer validator
        this.addValidator('integer', (value) => {
            if (!value) return true;
            return Number.isInteger(Number(value));
        });
        
        // Alpha validator (letters only)
        this.addValidator('alpha', (value) => {
            if (!value) return true;
            const alphaRegex = /^[a-zA-Z\s]+$/;
            return alphaRegex.test(value);
        });
        
        // Alphanumeric validator
        this.addValidator('alphanumeric', (value) => {
            if (!value) return true;
            const alphanumRegex = /^[a-zA-Z0-9\s]+$/;
            return alphanumRegex.test(value);
        });
        
        // Date validator
        this.addValidator('date', (value) => {
            if (!value) return true;
            const date = new Date(value);
            return date instanceof Date && !isNaN(date);
        });
        
        // Date after validator
        this.addValidator('date_after', (value, params) => {
            if (!value) return true;
            const date = new Date(value);
            const afterDate = new Date(params[0]);
            return date > afterDate;
        });
        
        // Date before validator
        this.addValidator('date_before', (value, params) => {
            if (!value) return true;
            const date = new Date(value);
            const beforeDate = new Date(params[0]);
            return date < beforeDate;
        });
        
        // Confirmed validator (password confirmation)
        this.addValidator('confirmed', (value, params, element) => {
            if (!value) return true;
            const confirmField = element.form.querySelector(`[name="${params[0]}"]`);
            return confirmField && value === confirmField.value;
        });
        
        // In validator (value must be in list)
        this.addValidator('in', (value, params) => {
            if (!value) return true;
            return params.includes(value);
        });
        
        // Not in validator (value must not be in list)
        this.addValidator('not_in', (value, params) => {
            if (!value) return true;
            return !params.includes(value);
        });
        
        // Regex validator
        this.addValidator('regex', (value, params) => {
            if (!value) return true;
            const regex = new RegExp(params[0]);
            return regex.test(value);
        });
        
        // Custom business validators
        this.addValidator('license_plate', (value) => {
            if (!value) return true;
            // Indonesian license plate format
            const plateRegex = /^[A-Z]{1,2}\s?\d{1,4}\s?[A-Z]{1,3}$/i;
            return plateRegex.test(value);
        });
        
        this.addValidator('currency', (value) => {
            if (!value) return true;
            const currencyRegex = /^\d+(\.\d{1,2})?$/;
            return currencyRegex.test(value);
        });
        
        this.addValidator('vin', (value) => {
            if (!value) return true;
            // Vehicle Identification Number
            const vinRegex = /^[A-HJ-NPR-Z0-9]{17}$/i;
            return vinRegex.test(value);
        });
        
        this.addValidator('unique', async (value, params, element) => {
            if (!value) return true;
            
            const table = params[0];
            const column = params[1];
            const excludeId = params[2] || null;
            
            try {
                const response = await fetch('/api/validation.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': window.APP_CONFIG.csrf_token
                    },
                    body: JSON.stringify({
                        action: 'check_unique',
                        table: table,
                        column: column,
                        value: value,
                        exclude_id: excludeId
                    })
                });
                
                const data = await response.json();
                return data.is_unique;
            } catch (error) {
                console.error('Unique validation error:', error);
                return true; // Assume valid on error
            }
        });
    }
    
    registerDefaultMessages() {
        this.addMessage('required', 'This field is required.');
        this.addMessage('email', 'Please enter a valid email address.');
        this.addMessage('phone', 'Please enter a valid phone number.');
        this.addMessage('url', 'Please enter a valid URL.');
        this.addMessage('min_length', 'This field must be at least {0} characters long.');
        this.addMessage('max_length', 'This field must not exceed {0} characters.');
        this.addMessage('min', 'This field must be at least {0}.');
        this.addMessage('max', 'This field must not exceed {0}.');
        this.addMessage('numeric', 'This field must be a number.');
        this.addMessage('integer', 'This field must be an integer.');
        this.addMessage('alpha', 'This field may only contain letters and spaces.');
        this.addMessage('alphanumeric', 'This field may only contain letters, numbers, and spaces.');
        this.addMessage('date', 'Please enter a valid date.');
        this.addMessage('date_after', 'This date must be after {0}.');
        this.addMessage('date_before', 'This date must be before {0}.');
        this.addMessage('confirmed', 'The confirmation does not match.');
        this.addMessage('in', 'The selected value is invalid.');
        this.addMessage('not_in', 'The selected value is invalid.');
        this.addMessage('regex', 'The format is invalid.');
        this.addMessage('license_plate', 'Please enter a valid license plate number.');
        this.addMessage('currency', 'Please enter a valid amount.');
        this.addMessage('vin', 'Please enter a valid VIN number.');
        this.addMessage('unique', 'This value is already taken.');
    }
    
    setupEventListeners() {
        document.addEventListener('input', (event) => {
            const element = event.target;
            if (this.hasValidationRules(element)) {
                this.debounce(() => {
                    this.validateField(element);
                }, 300)();
            }
        });
        
        document.addEventListener('blur', (event) => {
            const element = event.target;
            if (this.hasValidationRules(element)) {
                this.validateField(element);
            }
        });
        
        document.addEventListener('submit', (event) => {
            const form = event.target;
            if (form.tagName === 'FORM' && form.hasAttribute('data-validate')) {
                event.preventDefault();
                this.validateForm(form).then(isValid => {
                    if (isValid) {
                        form.submit();
                    }
                });
            }
        });
    }
    
    // ========================================
    // VALIDATION METHODS
    // ========================================
    
    async validateField(element) {
        const rules = this.getFieldRules(element);
        if (!rules || rules.length === 0) {
            return true;
        }
        
        const value = this.getFieldValue(element);
        let isValid = true;
        let errorMessage = '';
        
        for (const rule of rules) {
            const [ruleName, ...params] = rule.split(':');
            const ruleParams = params.length > 0 ? params[0].split(',') : [];
            
            const validator = this.validators.get(ruleName);
            if (!validator) {
                console.warn(`Validator '${ruleName}' not found`);
                continue;
            }
            
            try {
                const result = await validator(value, ruleParams, element);
                if (!result) {
                    isValid = false;
                    errorMessage = this.getErrorMessage(ruleName, ruleParams, element);
                    break;
                }
            } catch (error) {
                console.error(`Validation error for rule '${ruleName}':`, error);
                isValid = false;
                errorMessage = 'Validation error occurred';
                break;
            }
        }
        
        this.updateFieldValidationState(element, isValid, errorMessage);
        return isValid;
    }
    
    async validateForm(form) {
        const fields = form.querySelectorAll('[data-rules], [required]');
        const validationPromises = Array.from(fields).map(field => this.validateField(field));
        
        const results = await Promise.all(validationPromises);
        const isValid = results.every(result => result === true);
        
        if (!isValid) {
            this.focusFirstInvalidField(form);
        }
        
        return isValid;
    }
    
    validateValue(value, rules) {
        if (typeof rules === 'string') {
            rules = rules.split('|');
        }
        
        for (const rule of rules) {
            const [ruleName, ...params] = rule.split(':');
            const ruleParams = params.length > 0 ? params[0].split(',') : [];
            
            const validator = this.validators.get(ruleName);
            if (!validator) {
                console.warn(`Validator '${ruleName}' not found`);
                continue;
            }
            
            if (!validator(value, ruleParams)) {
                return {
                    valid: false,
                    message: this.getErrorMessage(ruleName, ruleParams)
                };
            }
        }
        
        return { valid: true };
    }
    
    // ========================================
    // HELPER METHODS
    // ========================================
    
    hasValidationRules(element) {
        return element.hasAttribute('data-rules') || 
               element.hasAttribute('required') ||
               element.type === 'email' ||
               element.type === 'url' ||
               element.type === 'tel';
    }
    
    getFieldRules(element) {
        let rules = [];
        
        // Get rules from data-rules attribute
        if (element.hasAttribute('data-rules')) {
            rules = element.getAttribute('data-rules').split('|');
        }
        
        // Add HTML5 validation rules
        if (element.hasAttribute('required')) {
            rules.push('required');
        }
        
        if (element.type === 'email') {
            rules.push('email');
        }
        
        if (element.type === 'url') {
            rules.push('url');
        }
        
        if (element.type === 'tel') {
            rules.push('phone');
        }
        
        if (element.hasAttribute('min')) {
            rules.push(`min:${element.getAttribute('min')}`);
        }
        
        if (element.hasAttribute('max')) {
            rules.push(`max:${element.getAttribute('max')}`);
        }
        
        if (element.hasAttribute('minlength')) {
            rules.push(`min_length:${element.getAttribute('minlength')}`);
        }
        
        if (element.hasAttribute('maxlength')) {
            rules.push(`max_length:${element.getAttribute('maxlength')}`);
        }
        
        if (element.hasAttribute('pattern')) {
            rules.push(`regex:${element.getAttribute('pattern')}`);
        }
        
        return rules;
    }
    
    getFieldValue(element) {
        if (element.type === 'checkbox') {
            return element.checked;
        } else if (element.type === 'radio') {
            const form = element.form;
            const radioGroup = form.querySelectorAll(`[name="${element.name}"]`);
            for (const radio of radioGroup) {
                if (radio.checked) {
                    return radio.value;
                }
            }
            return null;
        } else if (element.tagName === 'SELECT' && element.multiple) {
            return Array.from(element.selectedOptions).map(option => option.value);
        } else {
            return element.value;
        }
    }
    
    getErrorMessage(ruleName, params = [], element = null) {
        let message = this.messages.get(ruleName) || 'Invalid value';
        
        // Replace parameter placeholders
        params.forEach((param, index) => {
            message = message.replace(`{${index}}`, param);
        });
        
        // Custom message from element
        if (element && element.hasAttribute(`data-${ruleName}-message`)) {
            message = element.getAttribute(`data-${ruleName}-message`);
        }
        
        return message;
    }
    
    updateFieldValidationState(element, isValid, errorMessage = '') {
        element.classList.remove('is-valid', 'is-invalid');
        
        if (isValid) {
            element.classList.add('is-valid');
            this.removeFieldError(element);
        } else {
            element.classList.add('is-invalid');
            this.showFieldError(element, errorMessage);
        }
        
        // Trigger custom event
        element.dispatchEvent(new CustomEvent('validation', {
            detail: { isValid, errorMessage }
        }));
    }
    
    showFieldError(element, message) {
        this.removeFieldError(element);
        
        const errorElement = document.createElement('div');
        errorElement.className = 'invalid-feedback';
        errorElement.textContent = message;
        errorElement.setAttribute('data-validation-error', 'true');
        
        // Insert after the element or its parent container
        const container = element.closest('.form-group') || element.closest('.input-group') || element.parentNode;
        container.appendChild(errorElement);
    }
    
    removeFieldError(element) {
        const container = element.closest('.form-group') || element.closest('.input-group') || element.parentNode;
        const errorElements = container.querySelectorAll('[data-validation-error="true"]');
        errorElements.forEach(errorElement => errorElement.remove());
    }
    
    focusFirstInvalidField(form) {
        const firstInvalidField = form.querySelector('.is-invalid');
        if (firstInvalidField) {
            firstInvalidField.focus();
            firstInvalidField.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }
    
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
    
    // ========================================
    // PUBLIC API METHODS
    // ========================================
    
    addValidator(name, validator) {
        this.validators.set(name, validator);
    }
    
    addMessage(ruleName, message) {
        this.messages.set(ruleName, message);
    }
    
    removeValidator(name) {
        this.validators.delete(name);
    }
    
    removeMessage(ruleName) {
        this.messages.delete(ruleName);
    }
    
    async validate(element) {
        return await this.validateField(element);
    }
    
    async validateFormAsync(form) {
        return await this.validateForm(form);
    }
    
    clearValidation(element) {
        element.classList.remove('is-valid', 'is-invalid');
        this.removeFieldError(element);
    }
    
    clearFormValidation(form) {
        const fields = form.querySelectorAll('.is-valid, .is-invalid');
        fields.forEach(field => this.clearValidation(field));
    }
    
    setFieldValid(element) {
        this.updateFieldValidationState(element, true);
    }
    
    setFieldInvalid(element, message) {
        this.updateFieldValidationState(element, false, message);
    }
    
    // ========================================
    // UTILITY METHODS
    // ========================================
    
    isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }
    
    isValidPhone(phone) {
        const phoneRegex = /^[\+]?[0-9\s\-\(\)]{10,}$/;
        return phoneRegex.test(phone);
    }
    
    isValidUrl(url) {
        try {
            new URL(url);
            return true;
        } catch {
            return false;
        }
    }
    
    isValidLicensePlate(plate) {
        const plateRegex = /^[A-Z]{1,2}\s?\d{1,4}\s?[A-Z]{1,3}$/i;
        return plateRegex.test(plate);
    }
    
    isValidCurrency(amount) {
        const currencyRegex = /^\d+(\.\d{1,2})?$/;
        return currencyRegex.test(amount);
    }
    
    isValidVIN(vin) {
        const vinRegex = /^[A-HJ-NPR-Z0-9]{17}$/i;
        return vinRegex.test(vin);
    }
}

// Initialize validation when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.bengkelValidation = new BengkelValidation();
});

// Export for module usage
if (typeof module !== 'undefined' && module.exports) {
    module.exports = BengkelValidation;
}

/**
 * ENHANCED FORM VALIDATION SYSTEM
 * Version: 2.0.0
 * Author: Professional Development Team
 */

class FormValidator {
    constructor() {
        this.rules = {};
        this.messages = {};
        this.init();
    }
    
    init() {
        // Initialize default validation rules
        this.setupDefaultRules();
        this.setupDefaultMessages();
        this.bindEvents();
    }
    
    setupDefaultRules() {
        this.rules = {
            required: (value) => value.trim() !== '',
            email: (value) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value),
            phone: (value) => /^[\+]?[1-9][\d]{0,15}$/.test(value.replace(/\s/g, '')),
            password: (value) => value.length >= 6,
            number: (value) => !isNaN(value) && value !== '',
            min: (value, min) => parseFloat(value) >= parseFloat(min),
            max: (value, max) => parseFloat(value) <= parseFloat(max),
            minLength: (value, length) => value.length >= parseInt(length),
            maxLength: (value, length) => value.length <= parseInt(length),
            pattern: (value, pattern) => new RegExp(pattern).test(value),
            url: (value) => {
                try {
                    new URL(value);
                    return true;
                } catch {
                    return false;
                }
            },
            date: (value) => !isNaN(Date.parse(value)),
            time: (value) => /^([0-1]?[0-9]|2[0-3]):[0-5][0-9]$/.test(value),
            alphanumeric: (value) => /^[a-zA-Z0-9]+$/.test(value),
            alpha: (value) => /^[a-zA-Z]+$/.test(value),
            numeric: (value) => /^[0-9]+$/.test(value),
            licensePlate: (value) => /^[A-Z]{1,2}\s?[0-9]{1,4}\s?[A-Z]{1,3}$/i.test(value),
            vin: (value) => /^[A-HJ-NPR-Z0-9]{17}$/i.test(value),
            currency: (value) => /^\d+(\.\d{1,2})?$/.test(value)
        };
    }
    
    setupDefaultMessages() {
        this.messages = {
            required: 'Field ini wajib diisi',
            email: 'Format email tidak valid',
            phone: 'Format nomor telepon tidak valid',
            password: 'Password minimal 6 karakter',
            number: 'Harus berupa angka',
            min: 'Nilai minimal {min}',
            max: 'Nilai maksimal {max}',
            minLength: 'Minimal {length} karakter',
            maxLength: 'Maksimal {length} karakter',
            pattern: 'Format tidak sesuai',
            url: 'Format URL tidak valid',
            date: 'Format tanggal tidak valid',
            time: 'Format waktu tidak valid (HH:MM)',
            alphanumeric: 'Hanya boleh huruf dan angka',
            alpha: 'Hanya boleh huruf',
            numeric: 'Hanya boleh angka',
            licensePlate: 'Format plat nomor tidak valid',
            vin: 'Format VIN tidak valid (17 karakter)',
            currency: 'Format mata uang tidak valid'
        };
    }
    
    bindEvents() {
        // Real-time validation on input
        document.addEventListener('input', (e) => {
            if (e.target.matches('input, select, textarea')) {
                this.validateField(e.target);
            }
        });
        
        // Validation on blur
        document.addEventListener('blur', (e) => {
            if (e.target.matches('input, select, textarea')) {
                this.validateField(e.target);
            }
        }, true);
        
        // Form submission validation
        document.addEventListener('submit', (e) => {
            const form = e.target;
            if (form.dataset.validate !== 'false') {
                if (!this.validateForm(form)) {
                    e.preventDefault();
                    this.showFormErrors(form);
                }
            }
        });
    }
    
    validateField(field) {
        const value = field.value.trim();
        const rules = this.getFieldRules(field);
        let isValid = true;
        let errorMessage = '';
        
        // Clear previous validation state
        this.clearFieldValidation(field);
        
        // Skip validation if field is empty and not required
        if (!value && !rules.required) {
            return true;
        }
        
        // Validate each rule
        for (const [ruleName, ruleValue] of Object.entries(rules)) {
            if (!this.validateRule(value, ruleName, ruleValue)) {
                isValid = false;
                errorMessage = this.getErrorMessage(ruleName, ruleValue);
                break;
            }
        }
        
        // Apply validation result
        if (isValid && value) {
            this.setFieldValid(field);
        } else if (!isValid) {
            this.setFieldInvalid(field, errorMessage);
        }
        
        return isValid;
    }
    
    validateForm(form) {
        const fields = form.querySelectorAll('input, select, textarea');
        let isValid = true;
        
        fields.forEach(field => {
            if (!this.validateField(field)) {
                isValid = false;
            }
        });
        
        return isValid;
    }
    
    validateRule(value, ruleName, ruleValue) {
        const rule = this.rules[ruleName];
        if (!rule) return true;
        
        if (typeof ruleValue === 'boolean' && ruleValue) {
            return rule(value);
        } else if (ruleValue !== false) {
            return rule(value, ruleValue);
        }
        
        return true;
    }
    
    getFieldRules(field) {
        const rules = {};
        
        // Required
        if (field.hasAttribute('required')) {
            rules.required = true;
        }
        
        // Type-based rules
        switch (field.type) {
            case 'email':
                rules.email = true;
                break;
            case 'tel':
                rules.phone = true;
                break;
            case 'password':
                rules.password = true;
                break;
            case 'number':
                rules.number = true;
                break;
            case 'url':
                rules.url = true;
                break;
            case 'date':
                rules.date = true;
                break;
            case 'time':
                rules.time = true;
                break;
        }
        
        // Attribute-based rules
        if (field.hasAttribute('min')) {
            rules.min = field.getAttribute('min');
        }
        
        if (field.hasAttribute('max')) {
            rules.max = field.getAttribute('max');
        }
        
        if (field.hasAttribute('minlength')) {
            rules.minLength = field.getAttribute('minlength');
        }
        
        if (field.hasAttribute('maxlength')) {
            rules.maxLength = field.getAttribute('maxlength');
        }
        
        if (field.hasAttribute('pattern')) {
            rules.pattern = field.getAttribute('pattern');
        }
        
        // Custom validation rules from data attributes
        Object.keys(field.dataset).forEach(key => {
            if (key.startsWith('validate')) {
                const ruleName = key.replace('validate', '').toLowerCase();
                if (this.rules[ruleName]) {
                    rules[ruleName] = field.dataset[key] === 'true' ? true : field.dataset[key];
                }
            }
        });
        
        return rules;
    }
    
    getErrorMessage(ruleName, ruleValue) {
        let message = this.messages[ruleName] || 'Invalid input';
        
        // Replace placeholders
        if (typeof ruleValue !== 'boolean') {
            message = message.replace(`{${ruleName}}`, ruleValue);
            message = message.replace('{min}', ruleValue);
            message = message.replace('{max}', ruleValue);
            message = message.replace('{length}', ruleValue);
        }
        
        return message;
    }
    
    clearFieldValidation(field) {
        field.classList.remove('is-valid', 'is-invalid');
        
        const feedback = field.parentNode.querySelector('.invalid-feedback, .valid-feedback');
        if (feedback) {
            feedback.remove();
        }
    }
    
    setFieldValid(field) {
        field.classList.add('is-valid');
        field.classList.remove('is-invalid');
        
        const feedback = document.createElement('div');
        feedback.className = 'valid-feedback';
        feedback.textContent = 'Looks good!';
        field.parentNode.appendChild(feedback);
    }
    
    setFieldInvalid(field, message) {
        field.classList.add('is-invalid');
        field.classList.remove('is-valid');
        
        const feedback = document.createElement('div');
        feedback.className = 'invalid-feedback';
        feedback.textContent = message;
        field.parentNode.appendChild(feedback);
    }
    
    showFormErrors(form) {
        const invalidFields = form.querySelectorAll('.is-invalid');
        
        if (invalidFields.length > 0) {
            // Show toast notification
            if (typeof showError === 'function') {
                showError(`Mohon perbaiki ${invalidFields.length} kesalahan pada form`, {
                    title: 'Validasi Gagal',
                    duration: 5000
                });
            }
            
            // Focus on first invalid field
            invalidFields[0].focus();
            
            // Scroll to first invalid field
            invalidFields[0].scrollIntoView({
                behavior: 'smooth',
                block: 'center'
            });
        }
    }
    
    // Custom validation methods
    addRule(name, validator, message) {
        this.rules[name] = validator;
        this.messages[name] = message;
    }
    
    addMessage(ruleName, message) {
        this.messages[ruleName] = message;
    }
    
    // Utility methods for specific validations
    validateLicensePlate(value) {
        // Indonesian license plate format
        const patterns = [
            /^[A-Z]{1,2}\s?[0-9]{1,4}\s?[A-Z]{1,3}$/i, // Standard format
            /^[0-9]{1,4}\s?[A-Z]{1,3}\s?[0-9]{1,4}$/i  // Alternative format
        ];
        
        return patterns.some(pattern => pattern.test(value));
    }
    
    validateIndonesianPhone(value) {
        // Indonesian phone number formats
        const cleanValue = value.replace(/[\s\-\(\)]/g, '');
        const patterns = [
            /^(\+62|62|0)[0-9]{8,13}$/, // Indonesian format
            /^08[0-9]{8,11}$/           // Mobile format
        ];
        
        return patterns.some(pattern => pattern.test(cleanValue));
    }
    
    validateCurrency(value, currency = 'IDR') {
        // Remove currency symbols and spaces
        const cleanValue = value.replace(/[^\d.,]/g, '');
        
        // Check if it's a valid number
        const numValue = parseFloat(cleanValue.replace(',', '.'));
        
        return !isNaN(numValue) && numValue >= 0;
    }
    
    // Real-time formatting methods
    formatCurrency(input) {
        let value = input.value.replace(/[^\d]/g, '');
        
        if (value) {
            // Format as Indonesian Rupiah
            const formatted = new Intl.NumberFormat('id-ID').format(value);
            input.value = 'Rp ' + formatted;
        }
    }
    
    formatPhone(input) {
        let value = input.value.replace(/\D/g, '');
        
        if (value.startsWith('0')) {
            // Format: 0812-3456-7890
            value = value.replace(/(\d{4})(\d{4})(\d{4})/, '$1-$2-$3');
        } else if (value.startsWith('62')) {
            // Format: +62 812-3456-7890
            value = value.replace(/(\d{2})(\d{3})(\d{4})(\d{4})/, '+$1 $2-$3-$4');
        }
        
        input.value = value;
    }
    
    formatLicensePlate(input) {
        let value = input.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
        
        // Format: AB 1234 CD
        if (value.length > 2) {
            value = value.replace(/([A-Z]{1,2})([0-9]{1,4})([A-Z]{0,3})/, '$1 $2 $3');
        }
        
        input.value = value.trim();
    }
}

// Initialize form validator
const formValidator = new FormValidator();

// Add custom Indonesian-specific validations
formValidator.addRule('indonesianPhone', (value) => {
    return formValidator.validateIndonesianPhone(value);
}, 'Format nomor telepon Indonesia tidak valid');

formValidator.addRule('indonesianLicensePlate', (value) => {
    return formValidator.validateLicensePlate(value);
}, 'Format plat nomor Indonesia tidak valid');

formValidator.addRule('rupiah', (value) => {
    return formValidator.validateCurrency(value, 'IDR');
}, 'Format mata uang Rupiah tidak valid');

// Export for global usage
window.FormValidator = FormValidator;
window.formValidator = formValidator;

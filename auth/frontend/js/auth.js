/**
 * Authentication JavaScript Module
 * Handles common authentication functionality
 */

class AuthManager {
    constructor(options = {}) {
        this.apiBase = options.apiBase || '/auth/backend/controllers';
        // Allow setting via <meta name="api-base" content="https://your-php-host/auth/backend/controllers">
        const metaApi = document.querySelector('meta[name="api-base"]');
        if (metaApi && metaApi.content) {
            this.apiBase = metaApi.content.replace(/\/$/, '');
        }
        this.csrfToken = this.getCSRFToken();
        this.init();
    }

    init() {
        this.setupCSRF();
        this.setupPasswordToggles();
        this.setupFormValidation();
    }

    /**
     * Get CSRF token from meta tag
     */
    getCSRFToken() {
        const metaTag = document.querySelector('meta[name="csrf-token"]');
        return metaTag ? metaTag.getAttribute('content') : '';
    }

    /**
     * Setup CSRF token in forms
     */
    async setupCSRF() {
        try {
            // Fetch token from server if not present
            if (!this.csrfToken) {
                const res = await fetch(`${this.apiBase}/CsrfController.php`, {
                    method: 'GET',
                    credentials: 'include'
                });
                const text = await res.text();
                let data;
                try {
                    data = JSON.parse(text);
                } catch (e) {
                    console.warn('CSRF response not JSON, got:', text.slice(0, 120));
                    throw e;
                }
                if (data.success) {
                    this.csrfToken = data.token;
                }
            }
        } catch (e) {
            console.warn('CSRF token fetch failed', e);
        }

        const csrfInputs = document.querySelectorAll('input[name="_token"]');
        csrfInputs.forEach(input => {
            input.value = this.csrfToken;
        });
        const meta = document.querySelector('meta[name="csrf-token"]');
        if (meta) meta.setAttribute('content', this.csrfToken);
    }

    /**
     * Setup password toggle functionality
     */
    setupPasswordToggles() {
        const toggles = document.querySelectorAll('.password-toggle');
        
        toggles.forEach(toggle => {
            toggle.addEventListener('click', (e) => {
                e.preventDefault();
                const input = toggle.parentElement.querySelector('input');
                const icon = toggle.querySelector('i');
                
                if (input.type === 'password') {
                    input.type = 'text';
                    icon.classList.remove('fa-eye');
                    icon.classList.add('fa-eye-slash');
                } else {
                    input.type = 'password';
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                }
            });
        });
    }

    /**
     * Setup real-time form validation
     */
    setupFormValidation() {
        const inputs = document.querySelectorAll('.form-input');
        
        inputs.forEach(input => {
            input.addEventListener('blur', () => this.validateField(input));
            input.addEventListener('input', () => this.clearFieldError(input));
        });
    }

    /**
     * Validate individual field
     */
    validateField(input) {
        const value = input.value.trim();
        const fieldName = input.name;
        let isValid = true;
        let errorMessage = '';

        // Clear previous validation state
        this.clearFieldError(input);

        // Required field validation
        if (input.hasAttribute('required') && !value) {
            isValid = false;
            errorMessage = 'Toto pole je povinné';
        }

        // Specific field validations
        switch (fieldName) {
            case 'email':
                if (value && !this.isValidEmail(value)) {
                    isValid = false;
                    errorMessage = 'Neplatný formát e-mailu';
                }
                break;

            case 'username':
                if (value && !this.isValidUsername(value)) {
                    isValid = false;
                    errorMessage = 'Uživatelské jméno může obsahovat pouze písmena, čísla a podtržítka';
                }
                break;

            case 'password':
                if (value && !this.isValidPassword(value)) {
                    isValid = false;
                    errorMessage = 'Heslo musí mít alespoň 8 znaků a obsahovat velké písmeno, malé písmeno a číslo';
                }
                this.updatePasswordStrength(input, value);
                break;

            case 'password_confirm':
                const passwordField = document.querySelector('input[name="password"]');
                if (value && passwordField && value !== passwordField.value) {
                    isValid = false;
                    errorMessage = 'Hesla se neshodují';
                }
                break;
        }

        if (!isValid) {
            this.showFieldError(input, errorMessage);
        } else {
            this.showFieldSuccess(input);
        }

        return isValid;
    }

    /**
     * Email validation
     */
    isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }

    /**
     * Username validation
     */
    isValidUsername(username) {
        const usernameRegex = /^[a-zA-Z0-9_]+$/;
        return usernameRegex.test(username) && username.length >= 3;
    }

    /**
     * Password validation
     */
    isValidPassword(password) {
        const minLength = password.length >= 8;
        const hasUpper = /[A-Z]/.test(password);
        const hasLower = /[a-z]/.test(password);
        const hasNumber = /\d/.test(password);
        
        return minLength && hasUpper && hasLower && hasNumber;
    }

    /**
     * Update password strength indicator
     */
    updatePasswordStrength(input, password) {
        const strengthElement = document.getElementById('passwordStrength');
        if (!strengthElement) return;

        const strength = this.calculatePasswordStrength(password);
        
        strengthElement.className = 'password-strength';
        
        if (password.length === 0) {
            strengthElement.textContent = '';
            return;
        }

        switch (strength) {
            case 'weak':
                strengthElement.classList.add('weak');
                strengthElement.textContent = 'Slabé heslo';
                break;
            case 'medium':
                strengthElement.classList.add('medium');
                strengthElement.textContent = 'Střední heslo';
                break;
            case 'strong':
                strengthElement.classList.add('strong');
                strengthElement.textContent = 'Silné heslo';
                break;
        }
    }

    /**
     * Calculate password strength
     */
    calculatePasswordStrength(password) {
        let score = 0;
        
        if (password.length >= 8) score++;
        if (/[a-z]/.test(password)) score++;
        if (/[A-Z]/.test(password)) score++;
        if (/\d/.test(password)) score++;
        if (/[^a-zA-Z0-9]/.test(password)) score++;
        
        if (score < 3) return 'weak';
        if (score < 5) return 'medium';
        return 'strong';
    }

    /**
     * Show field error
     */
    showFieldError(input, message) {
        input.classList.remove('success');
        input.classList.add('error');
        
        const errorElement = document.getElementById(input.name + 'Error');
        if (errorElement) {
            errorElement.textContent = message;
            errorElement.classList.add('show');
        }
    }

    /**
     * Show field success
     */
    showFieldSuccess(input) {
        input.classList.remove('error');
        input.classList.add('success');
        
        const errorElement = document.getElementById(input.name + 'Error');
        if (errorElement) {
            errorElement.classList.remove('show');
        }
    }

    /**
     * Clear field error
     */
    clearFieldError(input) {
        input.classList.remove('error', 'success');
        
        const errorElement = document.getElementById(input.name + 'Error');
        if (errorElement) {
            errorElement.classList.remove('show');
        }
    }

    /**
     * Validate entire form
     */
    validateForm(form) {
        const inputs = form.querySelectorAll('.form-input[required]');
        let isValid = true;

        inputs.forEach(input => {
            if (!this.validateField(input)) {
                isValid = false;
            }
        });

        return isValid;
    }

    /**
     * Show alert message
     */
    showAlert(message, type = 'error') {
        const alertElement = document.getElementById(type + 'Alert');
        if (alertElement) {
            alertElement.textContent = message;
            alertElement.classList.add('show');
            
            // Auto-hide after 5 seconds
            setTimeout(() => {
                alertElement.classList.remove('show');
            }, 5000);
        }
    }

    /**
     * Hide all alerts
     */
    hideAlerts() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => alert.classList.remove('show'));
    }

    /**
     * Make API request
     */
    async makeRequest(endpoint, data = {}, method = 'POST') {
        try {
            const response = await fetch(`${this.apiBase}/${endpoint}`, {
                method: method,
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-Token': this.csrfToken
                },
                credentials: 'include',
                body: method !== 'GET' ? new URLSearchParams(data) : undefined
            });

            const result = await response.json();
            
            if (!response.ok) {
                throw new Error(result.error || 'Request failed');
            }

            return result;
        } catch (error) {
            console.error('API Request failed:', error);
            throw error;
        }
    }

    /**
     * Set loading state for button
     */
    setButtonLoading(button, loading = true) {
        const spinner = button.querySelector('.loading-spinner');
        const text = button.querySelector('[id$="BtnText"]');
        
        if (loading) {
            button.disabled = true;
            if (spinner) spinner.style.display = 'inline-block';
            if (text) text.textContent = 'Zpracovávám...';
        } else {
            button.disabled = false;
            if (spinner) spinner.style.display = 'none';
            if (text) text.textContent = button.id.includes('login') ? 'Přihlásit se' : 'Vytvořit účet';
        }
    }

    /**
     * Get form data as object
     */
    getFormData(form) {
        const formData = new FormData(form);
        const data = {};
        
        for (let [key, value] of formData.entries()) {
            data[key] = value;
        }
        
        return data;
    }
}

// Initialize AuthManager when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.authManager = new AuthManager();
});

// Export for use in other modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = AuthManager;
}

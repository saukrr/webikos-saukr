/**
 * Registration Page JavaScript
 * Handles registration form submission and validation
 */

class RegisterManager {
    constructor() {
        this.form = document.getElementById('registerForm');
        this.registerBtn = document.getElementById('registerBtn');
        this.googleBtn = document.getElementById('googleRegister');
        this.discordBtn = document.getElementById('discordRegister');
        
        this.init();
    }

    init() {
        this.setupEventListeners();
        this.setupPasswordValidation();
        this.setupUsernameValidation();
    }

    setupEventListeners() {
        // Form submission
        if (this.form) {
            this.form.addEventListener('submit', (e) => this.handleRegistration(e));
        }

        // Social registration buttons
        if (this.googleBtn) {
            this.googleBtn.addEventListener('click', () => this.handleSocialRegistration('google'));
        }

        if (this.discordBtn) {
            this.discordBtn.addEventListener('click', () => this.handleSocialRegistration('discord'));
        }

        // Real-time validation
        this.setupRealTimeValidation();
    }

    /**
     * Setup real-time validation for specific fields
     */
    setupRealTimeValidation() {
        const emailField = document.getElementById('email');
        const usernameField = document.getElementById('username');
        const passwordField = document.getElementById('password');
        const passwordConfirmField = document.getElementById('passwordConfirm');

        // Email availability check
        if (emailField) {
            let emailTimeout;
            emailField.addEventListener('input', () => {
                clearTimeout(emailTimeout);
                emailTimeout = setTimeout(() => {
                    this.checkEmailAvailability(emailField.value);
                }, 500);
            });
        }

        // Username availability check
        if (usernameField) {
            let usernameTimeout;
            usernameField.addEventListener('input', () => {
                clearTimeout(usernameTimeout);
                usernameTimeout = setTimeout(() => {
                    this.checkUsernameAvailability(usernameField.value);
                }, 500);
            });
        }

        // Password confirmation matching
        if (passwordConfirmField) {
            passwordConfirmField.addEventListener('input', () => {
                this.validatePasswordMatch();
            });
        }

        if (passwordField) {
            passwordField.addEventListener('input', () => {
                this.validatePasswordMatch();
            });
        }
    }

    /**
     * Setup password validation and strength indicator
     */
    setupPasswordValidation() {
        const passwordField = document.getElementById('password');
        if (!passwordField) return;

        passwordField.addEventListener('input', (e) => {
            const password = e.target.value;
            window.authManager.updatePasswordStrength(passwordField, password);
        });
    }

    /**
     * Setup username validation
     */
    setupUsernameValidation() {
        const usernameField = document.getElementById('username');
        if (!usernameField) return;

        usernameField.addEventListener('input', (e) => {
            const username = e.target.value;
            
            // Convert to lowercase and remove invalid characters
            const cleaned = username.toLowerCase().replace(/[^a-z0-9_]/g, '');
            if (cleaned !== username) {
                e.target.value = cleaned;
            }
        });
    }

    /**
     * Handle registration form submission
     */
    async handleRegistration(e) {
        e.preventDefault();

        // Hide previous alerts
        window.authManager.hideAlerts();

        // Validate form
        if (!this.validateRegistrationForm()) {
            window.authManager.showAlert('Prosím opravte chyby ve formuláři', 'error');
            return;
        }

        // Set loading state
        window.authManager.setButtonLoading(this.registerBtn, true);

        try {
            // Get form data
            const formData = window.authManager.getFormData(this.form);
            
            // Add CSRF token
            formData._token = window.authManager.csrfToken;

            // Make registration request
            const response = await this.makeRegistrationRequest(formData);

            if (response.success) {
                window.authManager.showAlert(
                    'Registrace úspěšná! Zkontrolujte svůj e-mail pro ověření účtu.', 
                    'success'
                );
                
                // Clear form
                this.form.reset();
                
                // Redirect to login after delay
                setTimeout(() => {
                    window.location.href = 'login.html?message=' + 
                        encodeURIComponent('Registrace dokončena. Prosím přihlaste se.');
                }, 3000);
            } else {
                throw new Error(response.error || 'Registrace se nezdařila');
            }

        } catch (error) {
            console.error('Registration error:', error);
            
            // Handle validation errors
            if (error.details) {
                this.showValidationErrors(error.details);
            } else {
                window.authManager.showAlert(error.message, 'error');
            }
        } finally {
            window.authManager.setButtonLoading(this.registerBtn, false);
        }
    }

    /**
     * Make registration API request
     */
    async makeRegistrationRequest(data) {
        const response = await fetch('/auth/backend/controllers/AuthController.php?action=register', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: new URLSearchParams(data)
        });

        const result = await response.json();

        if (!response.ok) {
            const error = new Error(result.error || `HTTP ${response.status}`);
            error.details = result.details;
            throw error;
        }

        return result;
    }

    /**
     * Validate registration form
     */
    validateRegistrationForm() {
        let isValid = true;

        // Basic form validation
        if (!window.authManager.validateForm(this.form)) {
            isValid = false;
        }

        // Additional validations
        if (!this.validatePasswordMatch()) {
            isValid = false;
        }

        if (!this.validateTermsAccepted()) {
            isValid = false;
        }

        return isValid;
    }

    /**
     * Validate password match
     */
    validatePasswordMatch() {
        const password = document.getElementById('password').value;
        const passwordConfirm = document.getElementById('passwordConfirm').value;
        const confirmField = document.getElementById('passwordConfirm');

        if (passwordConfirm && password !== passwordConfirm) {
            window.authManager.showFieldError(confirmField, 'Hesla se neshodují');
            return false;
        } else if (passwordConfirm) {
            window.authManager.showFieldSuccess(confirmField);
        }

        return true;
    }

    /**
     * Validate terms acceptance
     */
    validateTermsAccepted() {
        const termsCheckbox = document.getElementById('termsAccepted');
        
        if (!termsCheckbox.checked) {
            window.authManager.showAlert('Musíte souhlasit s podmínkami použití', 'error');
            return false;
        }

        return true;
    }

    /**
     * Check email availability
     */
    async checkEmailAvailability(email) {
        if (!email || !window.authManager.isValidEmail(email)) {
            return;
        }

        try {
            const response = await fetch('/auth/backend/controllers/AuthController.php?action=checkEmail', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: new URLSearchParams({ email: email })
            });

            const result = await response.json();
            const emailField = document.getElementById('email');

            if (result.exists) {
                window.authManager.showFieldError(emailField, 'Tento e-mail je již registrován');
            } else {
                window.authManager.showFieldSuccess(emailField);
            }
        } catch (error) {
            console.error('Email check failed:', error);
        }
    }

    /**
     * Check username availability
     */
    async checkUsernameAvailability(username) {
        if (!username || !window.authManager.isValidUsername(username)) {
            return;
        }

        try {
            const response = await fetch('/auth/backend/controllers/AuthController.php?action=checkUsername', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: new URLSearchParams({ username: username })
            });

            const result = await response.json();
            const usernameField = document.getElementById('username');

            if (result.exists) {
                window.authManager.showFieldError(usernameField, 'Toto uživatelské jméno je již obsazeno');
            } else {
                window.authManager.showFieldSuccess(usernameField);
            }
        } catch (error) {
            console.error('Username check failed:', error);
        }
    }

    /**
     * Show validation errors
     */
    showValidationErrors(errors) {
        Object.keys(errors).forEach(fieldName => {
            const field = document.querySelector(`[name="${fieldName}"]`);
            if (field) {
                window.authManager.showFieldError(field, errors[fieldName]);
            }
        });
    }

    /**
     * Handle social registration
     */
    handleSocialRegistration(provider) {
        // Show loading state
        const button = provider === 'google' ? this.googleBtn : this.discordBtn;
        const originalText = button.innerHTML;
        
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Přesměrovávám...';
        button.disabled = true;

        // Store registration intent
        sessionStorage.setItem('social_auth_intent', 'register');

        // Redirect to social auth endpoint
        const redirectUrl = `/auth/backend/controllers/SocialAuthController.php?provider=${provider}&intent=register`;
        
        // Store current page for redirect after auth
        sessionStorage.setItem('auth_redirect', window.location.href);
        
        // Redirect to social auth
        window.location.href = redirectUrl;
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    // Wait for authManager to be available
    const initRegister = () => {
        if (window.authManager) {
            window.registerManager = new RegisterManager();
        } else {
            setTimeout(initRegister, 100);
        }
    };
    
    initRegister();
});

// Handle browser back button
window.addEventListener('popstate', () => {
    // Clear any loading states
    const registerBtn = document.getElementById('registerBtn');
    if (registerBtn && window.authManager) {
        window.authManager.setButtonLoading(registerBtn, false);
    }
});

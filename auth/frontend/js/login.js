/**
 * Login Page JavaScript
 * Handles login form submission and social authentication
 */

class LoginManager {
    constructor() {
        this.form = document.getElementById('loginForm');
        this.loginBtn = document.getElementById('loginBtn');
        this.googleBtn = document.getElementById('googleLogin');
        this.discordBtn = document.getElementById('discordLogin');
        
        this.init();
    }

    init() {
        this.setupEventListeners();
        this.checkForRedirectMessage();
    }

    setupEventListeners() {
        // Form submission
        if (this.form) {
            this.form.addEventListener('submit', (e) => this.handleLogin(e));
        }

        // Social login buttons
        if (this.googleBtn) {
            this.googleBtn.addEventListener('click', () => this.handleSocialLogin('google'));
        }

        if (this.discordBtn) {
            this.discordBtn.addEventListener('click', () => this.handleSocialLogin('discord'));
        }

        // Enter key on password field
        const passwordField = document.getElementById('password');
        if (passwordField) {
            passwordField.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    this.form.dispatchEvent(new Event('submit'));
                }
            });
        }
    }

    /**
     * Handle login form submission
     */
    async handleLogin(e) {
        e.preventDefault();

        // Hide previous alerts
        window.authManager.hideAlerts();

        // Validate form
        if (!window.authManager.validateForm(this.form)) {
            window.authManager.showAlert('Prosím opravte chyby ve formuláři', 'error');
            return;
        }

        // Set loading state
        window.authManager.setButtonLoading(this.loginBtn, true);

        try {
            // Get form data
            const formData = window.authManager.getFormData(this.form);
            
            // Add CSRF token
            formData._token = window.authManager.csrfToken;

            // Make login request
            const response = await this.makeLoginRequest(formData);

            if (response.success) {
                window.authManager.showAlert('Přihlášení úspěšné! Přesměrovávám...', 'success');
                
                // Redirect after successful login
                setTimeout(() => {
                    this.redirectAfterLogin();
                }, 1500);
            } else {
                throw new Error(response.error || 'Přihlášení se nezdařilo');
            }

        } catch (error) {
            console.error('Login error:', error);
            window.authManager.showAlert(error.message, 'error');
        } finally {
            window.authManager.setButtonLoading(this.loginBtn, false);
        }
    }

    /**
     * Make login API request
     */
    async makeLoginRequest(data) {
        const apiBase = (window.authManager && window.authManager.apiBase) || '/auth/backend/controllers';
        const response = await fetch(`${apiBase}/AuthController.php?action=login`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'include',
            body: new URLSearchParams(data)
        });

        if (!response.ok) {
            const errorData = await response.json().catch(() => ({}));
            throw new Error(errorData.error || `HTTP ${response.status}`);
        }

        return await response.json();
    }

    /**
     * Handle social login
     */
    handleSocialLogin(provider) {
        // Show loading state
        const button = provider === 'google' ? this.googleBtn : this.discordBtn;
        const originalText = button.innerHTML;
        
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Přesměrovávám...';
        button.disabled = true;

        // Redirect to social auth endpoint
        const apiBase = (window.authManager && window.authManager.apiBase) || '/auth/backend/controllers';
        const redirectUrl = `${apiBase}/SocialAuthController.php?provider=${provider}`;
        
        // Store current page for redirect after auth
        sessionStorage.setItem('auth_redirect', window.location.href);
        
        // Redirect to social auth
        window.location.href = redirectUrl;
    }

    /**
     * Check for redirect messages (from social auth or other sources)
     */
    checkForRedirectMessage() {
        const urlParams = new URLSearchParams(window.location.search);
        const error = urlParams.get('error');
        const success = urlParams.get('success');
        const message = urlParams.get('message');

        if (error && message) {
            window.authManager.showAlert(decodeURIComponent(message), 'error');
            // Clean URL
            this.cleanUrl();
        } else if (success && message) {
            window.authManager.showAlert(decodeURIComponent(message), 'success');
            // Clean URL
            this.cleanUrl();
        }

        // Check for flash messages from session
        this.checkFlashMessages();
    }

    /**
     * Check for flash messages
     */
    async checkFlashMessages() {
        try {
            const apiBase = (window.authManager && window.authManager.apiBase) || '/auth/backend/controllers';
            const response = await fetch(`${apiBase}/AuthController.php?action=getFlashMessage`, {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'include'
            });

            if (response.ok) {
                const data = await response.json();
                if (data.message) {
                    window.authManager.showAlert(data.message, data.type || 'info');
                }
            }
        } catch (error) {
            console.error('Error checking flash messages:', error);
        }
    }

    /**
     * Clean URL parameters
     */
    cleanUrl() {
        const url = new URL(window.location);
        url.search = '';
        window.history.replaceState({}, document.title, url);
    }

    /**
     * Redirect after successful login
     */
    redirectAfterLogin() {
        // Check for intended redirect
        const redirectUrl = sessionStorage.getItem('auth_redirect') || 
                           new URLSearchParams(window.location.search).get('redirect') ||
                           '/dashboard.php';

        // Clean up
        sessionStorage.removeItem('auth_redirect');

        // Redirect
        window.location.href = redirectUrl;
    }

    /**
     * Handle remember me functionality
     */
    setupRememberMe() {
        const rememberCheckbox = document.getElementById('rememberMe');
        const savedEmail = localStorage.getItem('remembered_email');

        if (savedEmail && rememberCheckbox) {
            document.getElementById('email').value = savedEmail;
            rememberCheckbox.checked = true;
        }

        // Save email when remember me is checked
        if (this.form) {
            this.form.addEventListener('submit', () => {
                const email = document.getElementById('email').value;
                const remember = document.getElementById('rememberMe').checked;

                if (remember && email) {
                    localStorage.setItem('remembered_email', email);
                } else {
                    localStorage.removeItem('remembered_email');
                }
            });
        }
    }

    /**
     * Setup forgot password functionality
     */
    setupForgotPassword() {
        const forgotLink = document.querySelector('.forgot-password');
        if (forgotLink) {
            forgotLink.addEventListener('click', (e) => {
                e.preventDefault();
                
                const email = document.getElementById('email').value;
                if (email) {
                    // Pre-fill email in forgot password form
                    sessionStorage.setItem('forgot_password_email', email);
                }
                
                window.location.href = 'forgot-password.html';
            });
        }
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    // Wait for authManager to be available
    const initLogin = () => {
        if (window.authManager) {
            window.loginManager = new LoginManager();
        } else {
            setTimeout(initLogin, 100);
        }
    };
    
    initLogin();
});

// Handle browser back button
window.addEventListener('popstate', () => {
    // Clear any loading states
    const loginBtn = document.getElementById('loginBtn');
    if (loginBtn && window.authManager) {
        window.authManager.setButtonLoading(loginBtn, false);
    }
});

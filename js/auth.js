// Authentication Module
class AuthManager {
    constructor(supabase) {
        this.supabase = supabase;
        this.currentUser = null;
        this.rememberMe = false;
        this.init();
    }

    init() {
        this.setupEventListeners();
        this.checkExistingSession();
        this.setupAuthStateListener();
    }

    setupEventListeners() {
        // Login form
        document.getElementById('login-form').addEventListener('submit', (e) => {
            e.preventDefault();
            this.handleLogin();
        });

        // Register form
        document.getElementById('register-form').addEventListener('submit', (e) => {
            e.preventDefault();
            this.handleRegister();
        });

        // Tab switching
        document.querySelectorAll('.tab-button').forEach(button => {
            button.addEventListener('click', (e) => {
                this.switchTab(e.target.textContent.includes('Přihlášení') ? 'login' : 'register');
            });
        });

        // Remember me checkbox
        const rememberCheckbox = document.getElementById('remember-me');
        if (rememberCheckbox) {
            rememberCheckbox.addEventListener('change', (e) => {
                this.rememberMe = e.target.checked;
                localStorage.setItem('webikos_remember_me', this.rememberMe);
            });
        }
    }

    async checkExistingSession() {
        try {
            // Check if remember me was enabled
            const rememberedState = localStorage.getItem('webikos_remember_me');
            this.rememberMe = rememberedState === 'true';

            // Check for stored session info
            const storedSession = localStorage.getItem('webikos_user_session');
            if (storedSession && this.rememberMe) {
                const sessionData = JSON.parse(storedSession);
                const sessionAge = Date.now() - sessionData.timestamp;

                // If session is older than 30 days, clear it
                if (sessionAge > 30 * 24 * 60 * 60 * 1000) {
                    this.clearStoredSession();
                }
            }

            // Get current session
            const { data: { session }, error } = await this.supabase.auth.getSession();

            if (error) throw error;

            if (session && session.user) {
                this.currentUser = session.user;

                // Update stored session if remember me is enabled
                if (this.rememberMe) {
                    localStorage.setItem('webikos_user_session', JSON.stringify({
                        timestamp: Date.now(),
                        userId: session.user.id,
                        email: session.user.email
                    }));
                }

                await window.app.showDashboard(session.user);
            } else {
                // If no active session but remember me is enabled, try to restore
                if (this.rememberMe && storedSession) {
                    // Session expired but user wants to stay logged in
                    // Show auth form but keep remember me checked
                    this.showAuthSection();
                    const rememberCheckbox = document.getElementById('remember-me');
                    if (rememberCheckbox) {
                        rememberCheckbox.checked = true;
                    }
                } else {
                    this.showAuthSection();
                }
            }
        } catch (error) {
            console.error('Error checking session:', error);
            this.showAuthSection();
        }
    }

    setupAuthStateListener() {
        this.supabase.auth.onAuthStateChange(async (event, session) => {
            console.log('Auth state changed:', event, session);
            
            if (event === 'SIGNED_IN' && session) {
                this.currentUser = session.user;
                
                // Store session info if remember me is enabled
                if (this.rememberMe) {
                    localStorage.setItem('webikos_user_session', JSON.stringify({
                        timestamp: Date.now(),
                        userId: session.user.id
                    }));
                }
                
                await window.app.showDashboard(session.user);
            } else if (event === 'SIGNED_OUT') {
                this.currentUser = null;
                this.clearStoredSession();
                this.showAuthSection();
            } else if (event === 'TOKEN_REFRESHED' && session) {
                this.currentUser = session.user;
            }
        });
    }

    async handleLogin() {
        const email = document.getElementById('login-email').value.trim();
        const password = document.getElementById('login-password').value;
        const rememberMe = document.getElementById('remember-me')?.checked || false;
        
        this.clearMessages();
        this.setLoading('login', true);

        try {
            const { data, error } = await this.supabase.auth.signInWithPassword({
                email: email,
                password: password
            });

            if (error) throw error;

            this.rememberMe = rememberMe;
            localStorage.setItem('webikos_remember_me', rememberMe);

            this.showMessage('login-success', 'Úspěšně přihlášen!');
            
            // Dashboard will be shown by auth state listener
            
        } catch (error) {
            console.error('Login error:', error);
            this.showMessage('login-error', this.getErrorMessage(error));
        } finally {
            this.setLoading('login', false);
        }
    }

    async handleRegister() {
        const username = document.getElementById('register-username').value.replace('@', '').trim();
        const displayName = document.getElementById('register-display-name').value.trim();
        const email = document.getElementById('register-email').value.trim();
        const password = document.getElementById('register-password').value;
        const confirmPassword = document.getElementById('register-confirm').value;

        this.clearMessages();
        this.setLoading('register', true);

        // Validation
        if (password !== confirmPassword) {
            this.showMessage('register-error', 'Hesla se neshodují!');
            this.setLoading('register', false);
            return;
        }

        if (username.length < 3 || username.length > 20) {
            this.showMessage('register-error', 'Uživatelské jméno musí mít 3-20 znaků!');
            this.setLoading('register', false);
            return;
        }

        if (!/^[a-zA-Z0-9_]+$/.test(username)) {
            this.showMessage('register-error', 'Uživatelské jméno může obsahovat pouze písmena, čísla a podtržítka!');
            this.setLoading('register', false);
            return;
        }

        try {
            // Check if username is available
            const { data: existingUser } = await this.supabase
                .from('user_profiles')
                .select('username')
                .eq('username', username)
                .single();

            if (existingUser) {
                this.showMessage('register-error', 'Uživatelské jméno již existuje!');
                this.setLoading('register', false);
                return;
            }

            const { data, error } = await this.supabase.auth.signUp({
                email: email,
                password: password,
                options: {
                    data: {
                        username: username,
                        display_name: displayName || username
                    }
                }
            });

            if (error) throw error;

            this.showMessage('register-success', 'Registrace úspěšná! Zkontrolujte email pro potvrzení.');
            
            // Clear form
            document.getElementById('register-form').reset();
            
        } catch (error) {
            console.error('Registration error:', error);
            this.showMessage('register-error', this.getErrorMessage(error));
        } finally {
            this.setLoading('register', false);
        }
    }

    async signInWithGoogle() {
        try {
            const { data, error } = await this.supabase.auth.signInWithOAuth({
                provider: 'google',
                options: {
                    redirectTo: window.location.origin
                }
            });
            if (error) throw error;
        } catch (error) {
            console.error('Google login error:', error);
            this.showMessage('login-error', 'Chyba při přihlášení přes Google');
        }
    }

    async signInWithGitHub() {
        try {
            const { data, error } = await this.supabase.auth.signInWithOAuth({
                provider: 'github',
                options: {
                    redirectTo: window.location.origin
                }
            });
            if (error) throw error;
        } catch (error) {
            console.error('GitHub login error:', error);
            this.showMessage('login-error', 'Chyba při přihlášení přes GitHub');
        }
    }

    async signOut() {
        console.log('🚪 Signing out user...');
        try {
            const { error } = await this.supabase.auth.signOut();
            if (error) {
                console.error('❌ Sign out error:', error);
                throw error;
            }
            console.log('✅ User signed out successfully');
            this.clearStoredSession();
        } catch (error) {
            console.error('💥 Critical sign out error:', error);
            // Force logout even if there's an error
            this.clearStoredSession();
            this.showAuthSection();
        }
    }

    switchTab(tab) {
        document.querySelectorAll('.tab-button').forEach(btn => btn.classList.remove('active'));
        document.querySelectorAll('.auth-form').forEach(form => form.classList.remove('active'));
        
        const activeButton = Array.from(document.querySelectorAll('.tab-button'))
            .find(btn => btn.textContent.includes(tab === 'login' ? 'Přihlášení' : 'Registrace'));
        
        if (activeButton) {
            activeButton.classList.add('active');
        }
        
        document.getElementById(tab + '-form').classList.add('active');
        this.clearMessages();
    }

    showAuthSection() {
        document.getElementById('app-section')?.classList.remove('active');
        document.getElementById('auth-section').style.display = 'block';
        document.body.className = 'auth-page';
        
        // Clear forms
        document.querySelectorAll('input').forEach(input => {
            if (input.type !== 'checkbox') input.value = '';
        });
        this.clearMessages();
    }

    clearStoredSession() {
        localStorage.removeItem('webikos_user_session');
        localStorage.removeItem('webikos_remember_me');
    }

    setLoading(form, isLoading) {
        const button = document.querySelector(`#${form}-form button[type="submit"]`);
        if (button) {
            button.disabled = isLoading;
            if (isLoading) {
                button.innerHTML = '<span class="loading-spinner"></span> Načítání...';
            } else {
                button.innerHTML = form === 'login' ? 'Přihlásit se' : 'Registrovat se';
            }
        }
    }

    showMessage(elementId, message) {
        const element = document.getElementById(elementId);
        if (element) {
            element.textContent = message;
            element.style.display = 'block';
            
            // Auto-hide success messages
            if (elementId.includes('success')) {
                setTimeout(() => {
                    element.style.display = 'none';
                }, 5000);
            }
        }
    }

    clearMessages() {
        document.querySelectorAll('.error, .success').forEach(el => {
            el.style.display = 'none';
            el.textContent = '';
        });
    }

    getErrorMessage(error) {
        const errorMessages = {
            'Invalid login credentials': 'Neplatné přihlašovací údaje',
            'Email not confirmed': 'Email nebyl potvrzen',
            'User already registered': 'Uživatel je již registrován',
            'Password should be at least 6 characters': 'Heslo musí mít alespoň 6 znaků',
            'Invalid email': 'Neplatný email',
            'Signup is disabled': 'Registrace je zakázána'
        };

        return errorMessages[error.message] || error.message || 'Došlo k neočekávané chybě';
    }

    getCurrentUser() {
        return this.currentUser;
    }
}

// Global functions for backward compatibility
window.signInWithGoogle = () => window.auth.signInWithGoogle();
window.signInWithGitHub = () => window.auth.signInWithGitHub();
window.signOut = () => window.auth.signOut();
window.switchTab = (tab) => window.auth.switchTab(tab);

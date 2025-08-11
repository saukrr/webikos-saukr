// Profile Management Module
class ProfileManager {
    constructor(supabase, app) {
        this.supabase = supabase;
        this.app = app;
        this.currentProfile = null;
        this.isModalOpen = false;
    }

    openEditModal() {
        if (this.isModalOpen) return;
        
        this.isModalOpen = true;
        this.createModal();
        this.populateForm();
    }

    createModal() {
        // Create modal backdrop
        const backdrop = document.createElement('div');
        backdrop.className = 'modal-backdrop';
        backdrop.id = 'profile-modal-backdrop';
        
        // Create modal content
        const modal = document.createElement('div');
        modal.className = 'profile-modal';
        modal.innerHTML = `
            <div class="modal-header">
                <h2>Upravit profil</h2>
                <button class="modal-close" onclick="window.profileManager.closeModal()">×</button>
            </div>
            <form id="profile-edit-form" class="modal-body">
                <div class="profile-avatar-section">
                    <div class="current-avatar" id="modal-avatar">
                        ${this.app.currentProfile ? (this.app.currentProfile.display_name || this.app.currentProfile.username).charAt(0).toUpperCase() : 'U'}
                    </div>
                    <button type="button" class="change-avatar-btn" onclick="window.profileManager.changeAvatar()">
                        Změnit avatar
                    </button>
                    <input type="file" id="avatar-upload" accept="image/*" style="display: none;">
                </div>
                
                <div class="form-group">
                    <label for="edit-display-name">Zobrazované jméno</label>
                    <input type="text" id="edit-display-name" maxlength="50" placeholder="Vaše zobrazované jméno">
                </div>
                
                <div class="form-group">
                    <label for="edit-username">Uživatelské jméno</label>
                    <div class="username-input">
                        <span class="username-prefix">@</span>
                        <input type="text" id="edit-username" maxlength="20" pattern="[a-zA-Z0-9_]+" placeholder="username">
                    </div>
                    <small class="form-help">Pouze písmena, čísla a podtržítka</small>
                </div>
                
                <div class="form-group">
                    <label for="edit-bio">Bio</label>
                    <textarea id="edit-bio" maxlength="160" rows="3" placeholder="Napište něco o sobě..."></textarea>
                    <div class="char-counter">
                        <span id="bio-char-count">160</span> znaků zbývá
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="window.profileManager.closeModal()">
                        Zrušit
                    </button>
                    <button type="submit" class="btn btn-primary">
                        Uložit změny
                    </button>
                </div>
            </form>
        `;
        
        backdrop.appendChild(modal);
        document.body.appendChild(backdrop);
        
        // Add event listeners
        this.setupModalEventListeners();
        
        // Animate in
        setTimeout(() => {
            backdrop.classList.add('active');
        }, 10);
    }

    setupModalEventListeners() {
        // Form submission
        document.getElementById('profile-edit-form').addEventListener('submit', (e) => {
            e.preventDefault();
            this.saveProfile();
        });

        // Bio character counter
        const bioTextarea = document.getElementById('edit-bio');
        const charCount = document.getElementById('bio-char-count');
        
        bioTextarea.addEventListener('input', () => {
            const remaining = 160 - bioTextarea.value.length;
            charCount.textContent = remaining;
            charCount.parentElement.classList.toggle('warning', remaining < 20);
        });

        // Avatar upload
        document.getElementById('avatar-upload').addEventListener('change', (e) => {
            const file = e.target.files[0];
            if (file) {
                this.handleAvatarUpload(file);
            }
        });

        // Close on backdrop click
        document.getElementById('profile-modal-backdrop').addEventListener('click', (e) => {
            if (e.target.id === 'profile-modal-backdrop') {
                this.closeModal();
            }
        });

        // Username validation
        document.getElementById('edit-username').addEventListener('input', (e) => {
            const value = e.target.value;
            const isValid = /^[a-zA-Z0-9_]*$/.test(value);
            e.target.classList.toggle('invalid', !isValid && value.length > 0);
        });
    }

    populateForm() {
        if (!this.app.currentProfile) return;

        const profile = this.app.currentProfile;
        
        document.getElementById('edit-display-name').value = profile.display_name || '';
        document.getElementById('edit-username').value = profile.username || '';
        document.getElementById('edit-bio').value = profile.bio || '';
        
        // Update character counter
        const bioLength = (profile.bio || '').length;
        document.getElementById('bio-char-count').textContent = 160 - bioLength;
        
        // Update avatar
        this.updateModalAvatar(profile);
    }

    updateModalAvatar(profile) {
        const avatarEl = document.getElementById('modal-avatar');
        if (profile.avatar_url) {
            avatarEl.style.backgroundImage = `url(${profile.avatar_url})`;
            avatarEl.style.backgroundSize = 'cover';
            avatarEl.style.backgroundPosition = 'center';
            avatarEl.textContent = '';
        } else {
            avatarEl.textContent = (profile.display_name || profile.username).charAt(0).toUpperCase();
            avatarEl.style.backgroundImage = '';
        }
    }

    changeAvatar() {
        document.getElementById('avatar-upload').click();
    }

    async handleAvatarUpload(file) {
        if (!file.type.startsWith('image/')) {
            this.app.showNotification('Prosím vyberte obrázek', 'error');
            return;
        }

        if (file.size > 2 * 1024 * 1024) { // 2MB limit for avatars
            this.app.showNotification('Obrázek je příliš velký (max 2MB)', 'error');
            return;
        }

        try {
            // Show loading state
            const avatarEl = document.getElementById('modal-avatar');
            const originalContent = avatarEl.innerHTML;
            avatarEl.innerHTML = '<div class="loading-spinner"></div>';

            // Upload to Imgur
            const imageUrl = await this.uploadToImgur(file);
            
            // Update preview
            avatarEl.style.backgroundImage = `url(${imageUrl})`;
            avatarEl.style.backgroundSize = 'cover';
            avatarEl.style.backgroundPosition = 'center';
            avatarEl.innerHTML = '';
            
            // Store for saving
            this.pendingAvatarUrl = imageUrl;
            
            this.app.showNotification('Avatar nahrán!', 'success');
        } catch (error) {
            console.error('Avatar upload error:', error);
            this.app.showNotification('Chyba při nahrávání avataru', 'error');
            
            // Restore original content
            document.getElementById('modal-avatar').innerHTML = originalContent;
        }
    }

    async uploadToImgur(file) {
        const formData = new FormData();
        formData.append('image', file);

        const response = await fetch('https://api.imgur.com/3/image', {
            method: 'POST',
            headers: {
                'Authorization': 'Client-ID 546c25a59c58ad7' // Public Imgur client ID
            },
            body: formData
        });

        if (!response.ok) {
            throw new Error('Upload failed');
        }

        const data = await response.json();
        return data.data.link;
    }

    async saveProfile() {
        const displayName = document.getElementById('edit-display-name').value.trim();
        const username = document.getElementById('edit-username').value.trim();
        const bio = document.getElementById('edit-bio').value.trim();

        // Validation
        if (!username) {
            this.app.showNotification('Uživatelské jméno je povinné', 'error');
            return;
        }

        if (username.length < 3) {
            this.app.showNotification('Uživatelské jméno musí mít alespoň 3 znaky', 'error');
            return;
        }

        if (!/^[a-zA-Z0-9_]+$/.test(username)) {
            this.app.showNotification('Uživatelské jméno může obsahovat pouze písmena, čísla a podtržítka', 'error');
            return;
        }

        try {
            // Check if username is taken (if changed)
            if (username !== this.app.currentProfile.username) {
                const { data: existingUser } = await this.supabase
                    .from('user_profiles')
                    .select('username')
                    .eq('username', username)
                    .single();

                if (existingUser) {
                    this.app.showNotification('Uživatelské jméno již existuje', 'error');
                    return;
                }
            }

            // Prepare update data
            const updateData = {
                display_name: displayName || username,
                username: username,
                bio: bio
            };

            if (this.pendingAvatarUrl) {
                updateData.avatar_url = this.pendingAvatarUrl;
            }

            // Update profile
            const { data, error } = await this.supabase
                .from('user_profiles')
                .update(updateData)
                .eq('id', this.app.currentUser.id)
                .select()
                .single();

            if (error) throw error;

            // Update app state
            this.app.currentProfile = data;
            this.app.updateUserInterface(data);

            this.app.showNotification('Profil aktualizován!', 'success');
            this.closeModal();

        } catch (error) {
            console.error('Error saving profile:', error);
            this.app.showNotification('Chyba při ukládání profilu', 'error');
        }
    }

    closeModal() {
        const backdrop = document.getElementById('profile-modal-backdrop');
        if (backdrop) {
            backdrop.classList.remove('active');
            setTimeout(() => {
                if (backdrop.parentNode) {
                    backdrop.parentNode.removeChild(backdrop);
                }
                this.isModalOpen = false;
                this.pendingAvatarUrl = null;
            }, 300);
        }
    }
}

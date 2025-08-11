// Main Application Module
class WebikosApp {
    constructor(supabase) {
        this.supabase = supabase;
        this.currentUser = null;
        this.currentProfile = null;
        this.posts = [];
        this.mediaUpload = null;
    }

    async showDashboard(user) {
        this.currentUser = user;
        
        // Hide auth section and show app
        document.getElementById('auth-section').style.display = 'none';
        document.getElementById('app-section').classList.add('active');
        document.body.className = 'app-page';
        
        // Load user profile
        await this.loadUserProfile(user);
        
        // Initialize app components after a short delay to ensure DOM is ready
        setTimeout(() => {
            this.initializeApp();
        }, 100);
    }

    async loadUserProfile(user) {
        console.log('Loading user profile for:', user);
        try {
            let { data: profile, error } = await this.supabase
                .from('user_profiles')
                .select('*')
                .eq('id', user.id)
                .single();

            console.log('Profile query result:', { profile, error });

            if (error && error.code === 'PGRST116') {
                // Profile doesn't exist, create it
                console.log('Profile not found, creating new profile...');
                const username = user.user_metadata?.username || user.email.split('@')[0];
                const displayName = user.user_metadata?.display_name || username;

                console.log('Creating profile with:', { username, displayName });

                const { data: newProfile, error: insertError } = await this.supabase
                    .from('user_profiles')
                    .insert([{
                        id: user.id,
                        username: username,
                        display_name: displayName,
                        bio: 'Nový uživatel na Webikos! 🎉'
                    }])
                    .select()
                    .single();

                console.log('Profile creation result:', { newProfile, insertError });

                if (insertError) throw insertError;
                profile = newProfile;
            }

            if (profile) {
                console.log('Setting current profile:', profile);
                this.currentProfile = profile;

                // Update ProfileManager with current profile
                if (window.profileManager) {
                    window.profileManager.currentProfile = profile;
                }

                this.updateUserInterface(profile);
            } else {
                console.warn('No profile data available');
            }
        } catch (error) {
            console.error('Error loading profile:', error);
            // Show error message to user
            this.showNotification('Chyba při načítání profilu', 'error');
        }
    }

    updateUserInterface(profile) {
        console.log('Updating user interface with profile:', profile);

        // Update header
        const currentUsername = document.getElementById('current-username');
        console.log('Current username element:', currentUsername);
        if (currentUsername) {
            currentUsername.textContent = `@${profile.username}`;
            console.log('Updated header username to:', `@${profile.username}`);
        }

        // Update sidebar profile
        const displayName = document.getElementById('user-display-name');
        const usernameDisplay = document.getElementById('user-username-display');
        const userBio = document.getElementById('user-bio');

        console.log('Profile elements:', { displayName, usernameDisplay, userBio });

        if (displayName) {
            displayName.textContent = profile.display_name || profile.username;
            console.log('Updated display name to:', profile.display_name || profile.username);
        }
        if (usernameDisplay) {
            usernameDisplay.textContent = `@${profile.username}`;
            console.log('Updated username display to:', `@${profile.username}`);
        }
        if (userBio) {
            userBio.textContent = profile.bio || 'Žádné bio';
            console.log('Updated bio to:', profile.bio || 'Žádné bio');
        }

        // Set avatars
        this.updateAvatars(profile);
    }

    updateAvatars(profile) {
        const avatarElements = document.querySelectorAll('.avatar-placeholder');
        const initial = (profile.display_name || profile.username).charAt(0).toUpperCase();
        
        avatarElements.forEach(el => {
            if (profile.avatar_url) {
                el.style.backgroundImage = `url(${profile.avatar_url})`;
                el.style.backgroundSize = 'cover';
                el.style.backgroundPosition = 'center';
                el.textContent = '';
            } else {
                el.textContent = initial;
                el.style.backgroundImage = '';
            }
        });
    }

    initializeApp() {
        this.setupComposeTweet();
        this.setupMediaUpload();
        this.setupProfileEdit();
        this.setupFollowButtons();
        this.loadPosts();
        this.setupInfiniteScroll();
    }

    setupComposeTweet() {
        const tweetContent = document.getElementById('tweet-content');
        const charCount = document.querySelector('.char-count');
        const tweetBtn = document.querySelector('.tweet-btn');
        const composeForm = document.getElementById('compose-form');

        if (!tweetContent || !charCount || !tweetBtn || !composeForm) return;

        // Character counter
        tweetContent.addEventListener('input', () => {
            const remaining = 280 - tweetContent.value.length;
            charCount.textContent = remaining;
            
            charCount.classList.remove('warning', 'danger');
            if (remaining < 20) {
                charCount.classList.add('warning');
            }
            if (remaining < 0) {
                charCount.classList.add('danger');
            }
            
            tweetBtn.disabled = remaining < 0 || tweetContent.value.trim().length === 0;
        });

        // Auto-resize textarea
        tweetContent.addEventListener('input', () => {
            tweetContent.style.height = 'auto';
            tweetContent.style.height = Math.min(tweetContent.scrollHeight, 200) + 'px';
        });

        // Submit tweet
        composeForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const content = tweetContent.value.trim();
            const imageUrl = this.mediaUpload?.getCurrentImage();
            await this.postTweet(content, imageUrl);
        });

        // Initial state
        tweetBtn.disabled = true;
    }

    setupMediaUpload() {
        this.mediaUpload = new MediaUploadManager(this.supabase);
        
        const mediaBtn = document.querySelector('.media-upload-btn');
        if (mediaBtn) {
            mediaBtn.addEventListener('click', () => {
                this.mediaUpload.openFileDialog();
            });
        }
    }

    setupProfileEdit() {
        console.log('Setting up profile edit button...');
        const editBtn = document.querySelector('.edit-profile-btn');
        console.log('Edit button found:', editBtn);
        if (editBtn) {
            editBtn.addEventListener('click', () => {
                console.log('Edit button clicked!');
                this.openProfileEditModal();
            });
            console.log('Event listener added to edit button');
        } else {
            console.warn('Edit profile button not found in DOM');
        }
    }

    setupFollowButtons() {
        console.log('Setting up follow buttons...');
        const followBtns = document.querySelectorAll('.follow-btn');
        console.log('Follow buttons found:', followBtns.length);

        followBtns.forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                console.log('Follow button clicked');
                this.handleFollowClick(btn);
            });
        });
    }

    handleFollowClick(button) {
        const isFollowing = button.textContent.trim() === 'Sledováno';

        if (isFollowing) {
            button.textContent = 'Sledovat';
            button.style.background = 'var(--twitter-blue)';
            this.showNotification('Přestali jste sledovat uživatele', 'info');
        } else {
            button.textContent = 'Sledováno';
            button.style.background = 'var(--twitter-green)';
            this.showNotification('Nyní sledujete uživatele', 'success');
        }
    }

    async postTweet(content, imageUrl = null) {
        if (!content && !imageUrl) return;

        const tweetBtn = document.querySelector('.tweet-btn');
        const tweetContent = document.getElementById('tweet-content');
        
        try {
            // Set loading state
            tweetBtn.disabled = true;
            tweetBtn.innerHTML = '<span class="loading-spinner"></span> Odesílání...';

            const postData = {
                user_id: this.currentUser.id,
                content: content
            };

            if (imageUrl) {
                postData.image_url = imageUrl;
            }

            const { data, error } = await this.supabase
                .from('posts')
                .insert([postData])
                .select(`
                    *,
                    user_profiles (
                        username,
                        display_name,
                        avatar_url
                    )
                `);

            if (error) throw error;

            // Clear form
            tweetContent.value = '';
            tweetContent.style.height = 'auto';
            document.querySelector('.char-count').textContent = '280';
            document.querySelector('.char-count').classList.remove('warning', 'danger');
            
            // Clear image preview
            this.mediaUpload?.clearPreview();

            // Add new post to timeline with animation
            if (data && data[0]) {
                // Add the user profile data to the new post
                data[0].user_profiles = {
                    username: this.currentProfile.username,
                    display_name: this.currentProfile.display_name,
                    avatar_url: this.currentProfile.avatar_url
                };

                this.posts.unshift(data[0]);
                this.renderPosts();

                // Highlight the new post
                setTimeout(() => {
                    const newPost = document.querySelector(`[data-post-id="${data[0].id}"]`);
                    if (newPost) {
                        newPost.classList.add('new-post');
                        setTimeout(() => {
                            newPost.classList.remove('new-post');
                        }, 2000);
                    }
                }, 100);
            }

            this.showNotification('Tweet odeslán!', 'success');

        } catch (error) {
            console.error('Error posting tweet:', error);
            this.showNotification('Chyba při odesílání tweetu', 'error');
        } finally {
            // Reset button
            tweetBtn.disabled = true;
            tweetBtn.innerHTML = 'Tweetovat';
        }
    }

    async loadPosts(offset = 0, limit = 20) {
        console.log('Loading posts...', { offset, limit });
        try {
            const { data: posts, error } = await this.supabase
                .from('posts')
                .select(`
                    *,
                    user_profiles (
                        username,
                        display_name,
                        avatar_url
                    )
                `)
                .order('created_at', { ascending: false })
                .range(offset, offset + limit - 1);

            console.log('Posts query result:', { posts, error });

            if (error) throw error;

            if (offset === 0) {
                this.posts = posts || [];
            } else {
                this.posts = [...this.posts, ...(posts || [])];
            }

            console.log('Total posts loaded:', this.posts.length);
            this.renderPosts();
        } catch (error) {
            console.error('Error loading posts:', error);
            this.showNotification('Chyba při načítání postů', 'error');
        }
    }

    renderPosts() {
        console.log('Rendering posts...', this.posts.length, 'posts');
        const container = document.getElementById('posts-container');
        console.log('Posts container found:', container);
        if (!container) {
            console.error('Posts container not found!');
            return;
        }

        if (this.posts.length === 0) {
            console.log('No posts to display, showing empty message');
            container.innerHTML = '<div class="loading">Zatím žádné posty... Buďte první, kdo něco napíše! 🎉</div>';
            return;
        }

        console.log('Rendering', this.posts.length, 'posts');
        container.innerHTML = this.posts.map(post => this.renderPost(post)).join('');

        // Setup post interactions
        this.setupPostInteractions();
    }

    renderPost(post) {
        const profile = post.user_profiles;
        const avatar = profile?.avatar_url 
            ? `<img src="${profile.avatar_url}" alt="Avatar" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">` 
            : (profile?.display_name || profile?.username || 'U').charAt(0).toUpperCase();

        const imageHtml = post.image_url 
            ? `<div class="post-image">
                 <img src="${post.image_url}" alt="Post image" onclick="openImageModal('${post.image_url}')">
               </div>` 
            : '';

        return `
            <div class="post" data-post-id="${post.id}">
                <div class="post-header">
                    <div class="post-avatar">${avatar}</div>
                    <div class="post-user-info">
                        <h4>${profile?.display_name || profile?.username || 'Uživatel'}</h4>
                        <span class="post-username">@${profile?.username || 'unknown'}</span>
                        <span class="post-time">${this.formatTime(post.created_at)}</span>
                    </div>
                </div>
                <div class="post-content">${this.escapeHtml(post.content)}</div>
                ${imageHtml}
                <div class="post-actions">
                    <div class="post-action like-action" data-post-id="${post.id}">
                        <span>❤️</span>
                        <span class="like-count">${post.likes_count || 0}</span>
                    </div>
                    <div class="post-action">
                        <span>🔄</span>
                        <span>${post.retweets_count || 0}</span>
                    </div>
                    <div class="post-action">
                        <span>💬</span>
                        <span>${post.replies_count || 0}</span>
                    </div>
                </div>
            </div>
        `;
    }

    setupPostInteractions() {
        // Like buttons
        document.querySelectorAll('.like-action').forEach(button => {
            button.addEventListener('click', (e) => {
                e.stopPropagation();
                const postId = button.dataset.postId;
                this.toggleLike(postId);
            });
        });
    }

    async toggleLike(postId) {
        if (!this.currentUser) return;

        try {
            // Check if already liked
            const { data: existingLike } = await this.supabase
                .from('post_likes')
                .select('id')
                .eq('user_id', this.currentUser.id)
                .eq('post_id', postId)
                .single();

            if (existingLike) {
                // Unlike
                await this.supabase
                    .from('post_likes')
                    .delete()
                    .eq('user_id', this.currentUser.id)
                    .eq('post_id', postId);
            } else {
                // Like
                await this.supabase
                    .from('post_likes')
                    .insert([{
                        user_id: this.currentUser.id,
                        post_id: postId
                    }]);
            }

            // Update UI optimistically
            const likeButton = document.querySelector(`[data-post-id="${postId}"]`);
            const likeCount = likeButton?.querySelector('.like-count');
            
            if (likeButton && likeCount) {
                const currentCount = parseInt(likeCount.textContent) || 0;
                likeCount.textContent = existingLike ? currentCount - 1 : currentCount + 1;
                likeButton.classList.toggle('liked', !existingLike);
            }

        } catch (error) {
            console.error('Error toggling like:', error);
            this.showNotification('Chyba při označování příspěvku', 'error');
        }
    }

    setupInfiniteScroll() {
        let isLoading = false;
        
        window.addEventListener('scroll', async () => {
            if (isLoading) return;
            
            const { scrollTop, scrollHeight, clientHeight } = document.documentElement;
            
            if (scrollTop + clientHeight >= scrollHeight - 1000) {
                isLoading = true;
                await this.loadPosts(this.posts.length);
                isLoading = false;
            }
        });
    }

    openProfileEditModal() {
        console.log('Opening profile edit modal...');
        console.log('ProfileManager available:', window.profileManager);
        if (window.profileManager) {
            console.log('Calling profileManager.openEditModal()');
            window.profileManager.openEditModal();
        } else {
            console.error('ProfileManager not available!');
            this.showNotification('Chyba: ProfileManager není dostupný', 'error');
        }
    }

    showNotification(message, type = 'info') {
        // Create notification element
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.textContent = message;
        
        // Style the notification
        Object.assign(notification.style, {
            position: 'fixed',
            top: '20px',
            right: '20px',
            padding: '1rem 1.5rem',
            borderRadius: '8px',
            color: 'white',
            fontWeight: '600',
            zIndex: '10000',
            transform: 'translateX(100%)',
            transition: 'transform 0.3s ease-in-out'
        });

        // Set background color based on type
        const colors = {
            success: '#17bf63',
            error: '#e0245e',
            info: '#1da1f2',
            warning: '#ff7a00'
        };
        notification.style.background = colors[type] || colors.info;

        // Add to DOM
        document.body.appendChild(notification);

        // Animate in
        setTimeout(() => {
            notification.style.transform = 'translateX(0)';
        }, 100);

        // Remove after delay
        setTimeout(() => {
            notification.style.transform = 'translateX(100%)';
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 300);
        }, 3000);
    }

    formatTime(timestamp) {
        const date = new Date(timestamp);
        const now = new Date();
        const diff = now - date;
        
        if (diff < 60000) return 'právě teď';
        if (diff < 3600000) return `${Math.floor(diff / 60000)}m`;
        if (diff < 86400000) return `${Math.floor(diff / 3600000)}h`;
        if (diff < 604800000) return `${Math.floor(diff / 86400000)}d`;
        
        return date.toLocaleDateString('cs-CZ', {
            day: 'numeric',
            month: 'short'
        });
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

// Media Upload Manager
class MediaUploadManager {
    constructor(supabase) {
        this.supabase = supabase;
        this.currentImage = null;
        this.setupFileInput();
    }

    setupFileInput() {
        // Create hidden file input
        this.fileInput = document.createElement('input');
        this.fileInput.type = 'file';
        this.fileInput.accept = 'image/*';
        this.fileInput.style.display = 'none';
        document.body.appendChild(this.fileInput);

        this.fileInput.addEventListener('change', (e) => {
            const file = e.target.files[0];
            if (file) {
                this.handleFileSelect(file);
            }
        });
    }

    openFileDialog() {
        this.fileInput.click();
    }

    async handleFileSelect(file) {
        // Validate file
        if (!file.type.startsWith('image/')) {
            window.app.showNotification('Prosím vyberte obrázek', 'error');
            return;
        }

        if (file.size > 5 * 1024 * 1024) { // 5MB limit
            window.app.showNotification('Obrázek je příliš velký (max 5MB)', 'error');
            return;
        }

        try {
            // Show preview
            this.showPreview(file);

            // Upload to Imgur
            const imageUrl = await this.uploadToImgur(file);
            this.currentImage = imageUrl;

            window.app.showNotification('Obrázek nahrán!', 'success');
        } catch (error) {
            console.error('Upload error:', error);
            window.app.showNotification('Chyba při nahrávání obrázku', 'error');
            this.clearPreview();
        }
    }

    showPreview(file) {
        const reader = new FileReader();
        reader.onload = (e) => {
            let preview = document.querySelector('.image-preview');
            if (!preview) {
                preview = document.createElement('div');
                preview.className = 'image-preview';
                preview.innerHTML = `
                    <img class="preview-image" alt="Preview">
                    <button class="remove-image" onclick="window.app.mediaUpload.clearPreview()">×</button>
                `;

                const composeFooter = document.querySelector('.compose-footer');
                composeFooter.parentNode.insertBefore(preview, composeFooter);
            }

            preview.classList.add('active');
            preview.querySelector('.preview-image').src = e.target.result;
        };
        reader.readAsDataURL(file);
    }

    clearPreview() {
        const preview = document.querySelector('.image-preview');
        if (preview) {
            preview.classList.remove('active');
        }
        this.currentImage = null;
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

    getCurrentImage() {
        return this.currentImage;
    }
}

// Global functions
window.openImageModal = (imageUrl) => {
    // Simple image modal implementation
    const modal = document.createElement('div');
    modal.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.9);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 10000;
        cursor: pointer;
    `;

    const img = document.createElement('img');
    img.src = imageUrl;
    img.style.cssText = `
        max-width: 90%;
        max-height: 90%;
        object-fit: contain;
    `;

    modal.appendChild(img);
    document.body.appendChild(modal);

    modal.addEventListener('click', () => {
        document.body.removeChild(modal);
    });
};

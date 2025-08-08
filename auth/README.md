# Webikos Authentication System

A complete, secure authentication system with modern design and social login integration.

## 🚀 Features

- **Modern UI/UX**: Contemporary design with responsive layout
- **Secure Authentication**: Password hashing, CSRF protection, rate limiting
- **Social Login**: Google and Discord OAuth integration
- **Session Management**: Secure session handling with remember me functionality
- **Email Verification**: User email verification system
- **Password Reset**: Secure password reset functionality
- **Rate Limiting**: Protection against brute force attacks
- **Mobile Responsive**: Works perfectly on all devices

## 📁 Project Structure

```
auth/
├── backend/
│   ├── config/
│   │   ├── config.php          # Main configuration
│   │   └── database.php        # Database connection
│   ├── controllers/
│   │   ├── AuthController.php  # Authentication logic
│   │   └── SocialAuthController.php # Social auth
│   ├── middleware/
│   │   ├── Auth.php            # Authentication middleware
│   │   ├── CSRF.php            # CSRF protection
│   │   └── RateLimit.php       # Rate limiting
│   └── models/
│       ├── User.php            # User model
│       └── Session.php         # Session model
├── frontend/
│   ├── css/
│   │   └── auth.css            # Authentication styles
│   ├── js/
│   │   ├── auth.js             # Core auth functionality
│   │   ├── login.js            # Login page logic
│   │   └── register.js         # Registration logic
│   └── pages/
│       ├── login.html          # Login page
│       ├── register.html       # Registration page
│       └── forgot-password.html # Password reset
├── database/
│   └── schema.sql              # Database schema
└── assets/
    ├── images/                 # Image assets
    └── icons/                  # Icon assets
```

## 🛠️ Installation & Setup

### 1. Database Setup

#### Using phpMyAdmin:

1. **Access phpMyAdmin**:
   - URL: `https://cpanel.infinityfree.com/phpmyadmin/`
   - Username: `if0_39199715`
   - Password: `Danecek202020`

2. **Create Database**:
   - Click "Databases" tab
   - Create new database: `if0_39199715_auth`
   - Select UTF8 collation

3. **Import Schema**:
   - Select the database
   - Click "Import" tab
   - Upload `auth/database/schema.sql`
   - Click "Go"

#### Manual Database Creation:

```sql
-- Run the contents of auth/database/schema.sql
-- This creates all necessary tables with proper indexes
```

### 2. Configuration

#### Database Configuration:
Edit `auth/backend/config/database.php` if needed:

```php
private const DB_HOST = 'sql309.infinityfree.com';
private const DB_PORT = 3306;
private const DB_NAME = 'if0_39199715_auth';
private const DB_USER = 'if0_39199715';
private const DB_PASS = 'Danecek202020';
```

#### Social Authentication Setup:

1. **Google OAuth**:
   - Go to [Google Cloud Console](https://console.cloud.google.com/)
   - Create new project or select existing
   - Enable Google+ API
   - Create OAuth 2.0 credentials
   - Add redirect URI: `https://webikos-saukr.vercel.app/auth/backend/controllers/SocialAuthController.php?action=callback&provider=google`
   - Update `auth/backend/config/config.php`:

```php
define('GOOGLE_CLIENT_ID', 'your-google-client-id');
define('GOOGLE_CLIENT_SECRET', 'your-google-client-secret');
```

2. **Discord OAuth**:
   - Go to [Discord Developer Portal](https://discord.com/developers/applications)
   - Create new application
   - Go to OAuth2 section
   - Add redirect URI: `https://webikos-saukr.vercel.app/auth/backend/controllers/SocialAuthController.php?action=callback&provider=discord`
   - Update `auth/backend/config/config.php`:

```php
define('DISCORD_CLIENT_ID', 'your-discord-client-id');
define('DISCORD_CLIENT_SECRET', 'your-discord-client-secret');
```

### 3. File Permissions

Ensure proper permissions for upload directories:

```bash
chmod 755 auth/assets/uploads/
chmod 644 auth/backend/config/*.php
```

### 4. Testing the Installation

1. **Test Database Connection**:
   ```php
   <?php
   require_once 'auth/backend/config/database.php';
   $test = testDatabaseConnection();
   var_dump($test);
   ?>
   ```

2. **Access Login Page**:
   - Navigate to: `https://webikos-saukr.vercel.app/auth/frontend/pages/login.html`

3. **Test Registration**:
   - Use the registration form
   - Check database for new user entry

## 🔐 Security Features

### Password Security
- Minimum 8 characters
- Must contain uppercase, lowercase, and number
- Bcrypt hashing with cost factor 12
- Password strength indicator

### Session Security
- Secure session tokens (64 characters)
- CSRF protection on all forms
- Session expiration (24 hours default)
- IP address validation
- User agent validation

### Rate Limiting
- Login attempts: 5 per 5 minutes
- Registration attempts: 3 per 5 minutes
- Automatic IP blocking for suspicious activity

### Input Validation
- Server-side validation for all inputs
- XSS protection with htmlspecialchars
- SQL injection prevention with prepared statements
- Email format validation

## 📱 Usage

### User Registration
1. Navigate to registration page
2. Fill in required information
3. Accept terms and conditions
4. Submit form
5. Check email for verification link

### User Login
1. Navigate to login page
2. Enter email and password
3. Optionally check "Remember me"
4. Submit form or use social login

### Social Authentication
1. Click Google or Discord button
2. Authorize application
3. Automatic account creation/login

### Password Reset
1. Click "Forgot Password" on login page
2. Enter email address
3. Check email for reset link
4. Follow link to set new password

## 🔧 API Endpoints

### Authentication
- `POST /auth/backend/controllers/AuthController.php?action=login`
- `POST /auth/backend/controllers/AuthController.php?action=register`
- `POST /auth/backend/controllers/AuthController.php?action=logout`

### User Management
- `GET /auth/backend/controllers/AuthController.php?action=getCurrentUser`
- `POST /auth/backend/controllers/AuthController.php?action=checkEmail`
- `POST /auth/backend/controllers/AuthController.php?action=checkUsername`

### Social Authentication
- `GET /auth/backend/controllers/SocialAuthController.php?provider=google`
- `GET /auth/backend/controllers/SocialAuthController.php?provider=discord`

## 🎨 Customization

### Styling
Edit `auth/frontend/css/auth.css` to customize:
- Colors (CSS custom properties in `:root`)
- Typography
- Layout and spacing
- Responsive breakpoints

### Branding
- Update logo in auth header
- Modify color scheme
- Change application name in config

### Functionality
- Add new social providers
- Implement additional security measures
- Extend user profile fields

## 🐛 Troubleshooting

### Common Issues

1. **Database Connection Failed**:
   - Check credentials in `database.php`
   - Verify database exists
   - Check server connectivity

2. **CSRF Token Mismatch**:
   - Ensure sessions are working
   - Check session configuration
   - Verify CSRF token generation

3. **Social Login Not Working**:
   - Verify OAuth credentials
   - Check redirect URIs
   - Ensure HTTPS is used

4. **Email Not Sending**:
   - Configure SMTP settings
   - Check email provider settings
   - Verify firewall rules

### Debug Mode
Enable debug mode in `config.php`:

```php
error_reporting(E_ALL);
ini_set('display_errors', 1);
```

## 📊 Database Schema

### Users Table
- `id`: Primary key
- `username`: Unique username
- `email`: Unique email address
- `password_hash`: Bcrypt hashed password
- `email_verified`: Email verification status
- `created_at`: Registration timestamp

### Sessions Table
- `id`: Primary key
- `user_id`: Foreign key to users
- `session_token`: Unique session identifier
- `csrf_token`: CSRF protection token
- `expires_at`: Session expiration

### Social Providers Table
- `id`: Primary key
- `user_id`: Foreign key to users
- `provider_name`: OAuth provider (google, discord)
- `provider_id`: Provider user ID
- `provider_data`: JSON data from provider

## 🚀 Deployment

### Vercel Deployment
1. Push code to GitHub repository
2. Connect Vercel to GitHub
3. Configure environment variables
4. Deploy automatically on push

### Environment Variables
Set in Vercel dashboard:
- `DB_HOST`: Database hostname
- `DB_NAME`: Database name
- `DB_USER`: Database username
- `DB_PASS`: Database password
- `GOOGLE_CLIENT_ID`: Google OAuth client ID
- `GOOGLE_CLIENT_SECRET`: Google OAuth secret
- `DISCORD_CLIENT_ID`: Discord OAuth client ID
- `DISCORD_CLIENT_SECRET`: Discord OAuth secret

## 📝 License

This project is licensed under the MIT License.

## 🤝 Support

For support and questions:
- Check troubleshooting section
- Review error logs
- Contact development team

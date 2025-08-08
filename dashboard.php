<?php
/**
 * Dashboard Page
 * Protected page that requires authentication
 */

require_once __DIR__ . '/auth/backend/config/config.php';

// Check authentication
Auth::require();

$user = Auth::user();
$sessionInfo = Auth::getSessionInfo();
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Webikos</title>
    <link rel="stylesheet" href="auth/frontend/css/auth.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: var(--bg-secondary);
            min-height: 100vh;
            padding: 0;
            margin: 0;
        }
        
        .dashboard-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        .dashboard-header {
            background: var(--bg-primary);
            border-radius: var(--border-radius);
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-md);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .user-info h1 {
            margin: 0 0 0.5rem 0;
            color: var(--text-primary);
        }
        
        .user-info p {
            margin: 0;
            color: var(--text-secondary);
        }
        
        .dashboard-actions {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }
        
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }
        
        .dashboard-card {
            background: var(--bg-primary);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            box-shadow: var(--shadow-md);
        }
        
        .dashboard-card h3 {
            margin: 0 0 1rem 0;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .dashboard-card h3 i {
            color: var(--primary-color);
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            color: var(--primary-color);
            margin: 0.5rem 0;
        }
        
        .session-info {
            font-size: 0.9rem;
            color: var(--text-secondary);
        }
        
        .session-info strong {
            color: var(--text-primary);
        }
        
        @media (max-width: 768px) {
            .dashboard-container {
                padding: 1rem;
            }
            
            .dashboard-header {
                flex-direction: column;
                text-align: center;
            }
            
            .dashboard-actions {
                justify-content: center;
            }
            
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <div class="dashboard-header">
            <div class="user-info">
                <h1>Vítejte, <?php echo htmlspecialchars($user['first_name'] ?: $user['username']); ?>!</h1>
                <p>Přihlášeni jako: <?php echo htmlspecialchars($user['email']); ?></p>
            </div>
            <div class="dashboard-actions">
                <a href="auth/frontend/pages/profile.html" class="btn btn-primary">
                    <i class="fas fa-user"></i> Profil
                </a>
                <button onclick="logout()" class="btn" style="background: var(--error-color); color: white;">
                    <i class="fas fa-sign-out-alt"></i> Odhlásit se
                </button>
            </div>
        </div>

        <div class="dashboard-grid">
            <div class="dashboard-card">
                <h3><i class="fas fa-user-check"></i> Stav účtu</h3>
                <div class="stat-value"><?php echo $user['email_verified'] ? 'Ověřen' : 'Neověřen'; ?></div>
                <p>E-mail: <?php echo $user['email_verified'] ? 'Ověřen' : 'Čeká na ověření'; ?></p>
                <p>Účet: <?php echo $user['is_active'] ? 'Aktivní' : 'Neaktivní'; ?></p>
            </div>

            <div class="dashboard-card">
                <h3><i class="fas fa-clock"></i> Poslední aktivita</h3>
                <div class="stat-value">
                    <?php 
                    if ($user['last_login']) {
                        echo date('d.m.Y H:i', strtotime($user['last_login']));
                    } else {
                        echo 'Nikdy';
                    }
                    ?>
                </div>
                <p>Registrace: <?php echo date('d.m.Y', strtotime($user['created_at'])); ?></p>
            </div>

            <div class="dashboard-card">
                <h3><i class="fas fa-shield-alt"></i> Bezpečnost</h3>
                <div class="session-info">
                    <p><strong>IP adresa:</strong> <?php echo htmlspecialchars($sessionInfo['ip_address'] ?? 'Neznámá'); ?></p>
                    <p><strong>Relace vyprší:</strong> <?php echo date('d.m.Y H:i', strtotime($sessionInfo['expires_at'] ?? 'now')); ?></p>
                    <p><strong>Neúspěšné pokusy:</strong> <?php echo $user['failed_login_attempts']; ?></p>
                </div>
            </div>

            <div class="dashboard-card">
                <h3><i class="fas fa-cog"></i> Rychlé akce</h3>
                <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                    <a href="auth/frontend/pages/change-password.html" class="btn" style="background: var(--warning-color); color: white; text-decoration: none; text-align: center;">
                        <i class="fas fa-key"></i> Změnit heslo
                    </a>
                    <a href="auth/frontend/pages/profile.html" class="btn" style="background: var(--primary-color); color: white; text-decoration: none; text-align: center;">
                        <i class="fas fa-edit"></i> Upravit profil
                    </a>
                </div>
            </div>
        </div>

        <div class="dashboard-card">
            <h3><i class="fas fa-home"></i> Navigace</h3>
            <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                <a href="index.html" class="btn" style="background: var(--secondary-color); color: white; text-decoration: none;">
                    <i class="fas fa-arrow-left"></i> Zpět na hlavní stránku
                </a>
                <a href="#" class="btn" style="background: var(--success-color); color: white; text-decoration: none;">
                    <i class="fas fa-chart-bar"></i> Statistiky
                </a>
                <a href="#" class="btn" style="background: var(--primary-color); color: white; text-decoration: none;">
                    <i class="fas fa-bell"></i> Oznámení
                </a>
            </div>
        </div>
    </div>

    <script>
        async function logout() {
            if (confirm('Opravdu se chcete odhlásit?')) {
                try {
                    const response = await fetch('/auth/backend/controllers/AuthController.php?action=logout', {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });

                    const result = await response.json();

                    if (result.success) {
                        window.location.href = '/auth/frontend/pages/login.html?message=' + 
                            encodeURIComponent('Úspěšně odhlášeni');
                    } else {
                        alert('Chyba při odhlašování: ' + result.error);
                    }
                } catch (error) {
                    console.error('Logout error:', error);
                    alert('Chyba při odhlašování');
                }
            }
        }

        // Auto-refresh session
        setInterval(async () => {
            try {
                await fetch('/auth/backend/controllers/AuthController.php?action=getCurrentUser', {
                    method: 'GET',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
            } catch (error) {
                console.error('Session refresh failed:', error);
            }
        }, 300000); // Every 5 minutes
    </script>
</body>
</html>

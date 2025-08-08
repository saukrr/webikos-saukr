<?php
/**
 * Authentication System Setup Script
 * Helps with initial setup and configuration testing
 */

// Prevent direct access in production
if ($_SERVER['SERVER_NAME'] !== 'localhost' && $_SERVER['SERVER_NAME'] !== '127.0.0.1') {
    die('Setup script is only available on localhost');
}

require_once __DIR__ . '/backend/config/config.php';

$step = $_GET['step'] ?? 'welcome';
$action = $_POST['action'] ?? '';

// Handle AJAX requests
if ($action) {
    header('Content-Type: application/json');
    
    switch ($action) {
        case 'test_database':
            echo json_encode(testDatabaseConnection());
            exit;
            
        case 'create_tables':
            echo json_encode(createDatabaseTables());
            exit;
            
        case 'test_social_auth':
            $provider = $_POST['provider'] ?? '';
            echo json_encode(testSocialAuthConfig($provider));
            exit;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Unknown action']);
            exit;
    }
}

function createDatabaseTables() {
    try {
        $db = Database::getInstance();
        $sql = file_get_contents(__DIR__ . '/database/schema.sql');
        
        // Split SQL into individual statements
        $statements = array_filter(array_map('trim', explode(';', $sql)));
        
        foreach ($statements as $statement) {
            if (!empty($statement)) {
                $db->execute($statement);
            }
        }
        
        return ['success' => true, 'message' => 'Database tables created successfully'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Failed to create tables: ' . $e->getMessage()];
    }
}

function testSocialAuthConfig($provider) {
    switch ($provider) {
        case 'google':
            $clientId = GOOGLE_CLIENT_ID;
            $clientSecret = GOOGLE_CLIENT_SECRET;
            $redirectUri = GOOGLE_REDIRECT_URI;
            break;
        case 'discord':
            $clientId = DISCORD_CLIENT_ID;
            $clientSecret = DISCORD_CLIENT_SECRET;
            $redirectUri = DISCORD_REDIRECT_URI;
            break;
        default:
            return ['success' => false, 'message' => 'Unknown provider'];
    }
    
    $configured = !empty($clientId) && !empty($clientSecret);
    
    return [
        'success' => $configured,
        'message' => $configured ? 
            ucfirst($provider) . ' OAuth is configured' : 
            ucfirst($provider) . ' OAuth is not configured',
        'details' => [
            'client_id' => !empty($clientId),
            'client_secret' => !empty($clientSecret),
            'redirect_uri' => $redirectUri
        ]
    ];
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Webikos Auth Setup</title>
    <link rel="stylesheet" href="frontend/css/auth.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .setup-container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 2rem;
        }
        
        .setup-step {
            background: var(--bg-primary);
            border-radius: var(--border-radius);
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-md);
        }
        
        .setup-step h2 {
            color: var(--primary-color);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .test-result {
            padding: 1rem;
            border-radius: var(--border-radius);
            margin: 1rem 0;
            font-family: monospace;
        }
        
        .test-result.success {
            background: rgba(72, 187, 120, 0.1);
            border: 1px solid var(--success-color);
            color: var(--success-color);
        }
        
        .test-result.error {
            background: rgba(245, 101, 101, 0.1);
            border: 1px solid var(--error-color);
            color: var(--error-color);
        }
        
        .test-button {
            margin: 0.5rem 0.5rem 0.5rem 0;
        }
        
        .config-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem 0;
            border-bottom: 1px solid var(--border-color);
        }
        
        .config-item:last-child {
            border-bottom: none;
        }
        
        .status-icon {
            font-size: 1.2rem;
        }
        
        .status-icon.success {
            color: var(--success-color);
        }
        
        .status-icon.error {
            color: var(--error-color);
        }
        
        .navigation {
            display: flex;
            justify-content: space-between;
            margin-top: 2rem;
        }
    </style>
</head>
<body>
    <div class="setup-container">
        <div class="auth-header" style="margin-bottom: 2rem;">
            <div class="auth-logo">
                <i class="fas fa-cog"></i>
            </div>
            <h1 class="auth-title">Webikos Auth Setup</h1>
            <p class="auth-subtitle">Průvodce nastavením autentifikačního systému</p>
        </div>

        <?php if ($step === 'welcome'): ?>
        <div class="setup-step">
            <h2><i class="fas fa-home"></i> Vítejte v průvodci nastavením</h2>
            <p>Tento průvodce vám pomůže nastavit autentifikační systém Webikos.</p>
            <p>Projdeme následující kroky:</p>
            <ul>
                <li>Test připojení k databázi</li>
                <li>Vytvoření databázových tabulek</li>
                <li>Konfigurace sociálního přihlašování</li>
                <li>Test funkcionalit</li>
            </ul>
            <div class="navigation">
                <div></div>
                <a href="?step=database" class="btn btn-primary">Začít <i class="fas fa-arrow-right"></i></a>
            </div>
        </div>

        <?php elseif ($step === 'database'): ?>
        <div class="setup-step">
            <h2><i class="fas fa-database"></i> Databáze</h2>
            <p>Nejprve otestujeme připojení k databázi a vytvoříme potřebné tabulky.</p>
            
            <div class="config-item">
                <span>Hostname:</span>
                <span><?php echo DB_HOST ?? 'Není nastaveno'; ?></span>
            </div>
            <div class="config-item">
                <span>Database:</span>
                <span><?php echo DB_NAME ?? 'Není nastaveno'; ?></span>
            </div>
            <div class="config-item">
                <span>Username:</span>
                <span><?php echo DB_USER ?? 'Není nastaveno'; ?></span>
            </div>
            
            <button onclick="testDatabase()" class="btn btn-primary test-button">
                <i class="fas fa-plug"></i> Test připojení
            </button>
            
            <button onclick="createTables()" class="btn btn-secondary test-button">
                <i class="fas fa-table"></i> Vytvořit tabulky
            </button>
            
            <div id="databaseResult" class="test-result" style="display: none;"></div>
            
            <div class="navigation">
                <a href="?step=welcome" class="btn">← Zpět</a>
                <a href="?step=social" class="btn btn-primary">Pokračovat →</a>
            </div>
        </div>

        <?php elseif ($step === 'social'): ?>
        <div class="setup-step">
            <h2><i class="fas fa-share-alt"></i> Sociální přihlašování</h2>
            <p>Zkontrolujeme konfiguraci OAuth pro Google a Discord.</p>
            
            <h3>Google OAuth</h3>
            <div class="config-item">
                <span>Client ID:</span>
                <span class="status-icon <?php echo !empty(GOOGLE_CLIENT_ID) ? 'success' : 'error'; ?>">
                    <i class="fas <?php echo !empty(GOOGLE_CLIENT_ID) ? 'fa-check' : 'fa-times'; ?>"></i>
                </span>
            </div>
            <div class="config-item">
                <span>Client Secret:</span>
                <span class="status-icon <?php echo !empty(GOOGLE_CLIENT_SECRET) ? 'success' : 'error'; ?>">
                    <i class="fas <?php echo !empty(GOOGLE_CLIENT_SECRET) ? 'fa-check' : 'fa-times'; ?>"></i>
                </span>
            </div>
            
            <button onclick="testSocialAuth('google')" class="btn btn-primary test-button">
                <i class="fab fa-google"></i> Test Google OAuth
            </button>
            
            <h3>Discord OAuth</h3>
            <div class="config-item">
                <span>Client ID:</span>
                <span class="status-icon <?php echo !empty(DISCORD_CLIENT_ID) ? 'success' : 'error'; ?>">
                    <i class="fas <?php echo !empty(DISCORD_CLIENT_ID) ? 'fa-check' : 'fa-times'; ?>"></i>
                </span>
            </div>
            <div class="config-item">
                <span>Client Secret:</span>
                <span class="status-icon <?php echo !empty(DISCORD_CLIENT_SECRET) ? 'success' : 'error'; ?>">
                    <i class="fas <?php echo !empty(DISCORD_CLIENT_SECRET) ? 'fa-check' : 'fa-times'; ?>"></i>
                </span>
            </div>
            
            <button onclick="testSocialAuth('discord')" class="btn btn-primary test-button">
                <i class="fab fa-discord"></i> Test Discord OAuth
            </button>
            
            <div id="socialResult" class="test-result" style="display: none;"></div>
            
            <div class="navigation">
                <a href="?step=database" class="btn">← Zpět</a>
                <a href="?step=complete" class="btn btn-primary">Pokračovat →</a>
            </div>
        </div>

        <?php elseif ($step === 'complete'): ?>
        <div class="setup-step">
            <h2><i class="fas fa-check-circle"></i> Nastavení dokončeno</h2>
            <p>Gratulujeme! Autentifikační systém je připraven k použití.</p>
            
            <h3>Další kroky:</h3>
            <ul>
                <li>Otestujte registraci nového uživatele</li>
                <li>Otestujte přihlašování</li>
                <li>Zkontrolujte sociální přihlašování</li>
                <li>Smažte tento setup soubor v produkci</li>
            </ul>
            
            <div style="margin: 2rem 0;">
                <a href="frontend/pages/register.html" class="btn btn-primary" target="_blank">
                    <i class="fas fa-user-plus"></i> Test registrace
                </a>
                <a href="frontend/pages/login.html" class="btn btn-secondary" target="_blank">
                    <i class="fas fa-sign-in-alt"></i> Test přihlášení
                </a>
            </div>
            
            <div class="alert alert-warning">
                <strong>Bezpečnost:</strong> Nezapomeňte smazat tento setup.php soubor před nasazením do produkce!
            </div>
            
            <div class="navigation">
                <a href="?step=social" class="btn">← Zpět</a>
                <a href="../index.html" class="btn btn-primary">Dokončit</a>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script>
        async function testDatabase() {
            const result = document.getElementById('databaseResult');
            result.style.display = 'block';
            result.textContent = 'Testování připojení...';
            result.className = 'test-result';
            
            try {
                const response = await fetch('setup.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=test_database'
                });
                
                const data = await response.json();
                
                result.className = `test-result ${data.success ? 'success' : 'error'}`;
                result.textContent = data.message;
            } catch (error) {
                result.className = 'test-result error';
                result.textContent = 'Chyba při testování: ' + error.message;
            }
        }
        
        async function createTables() {
            const result = document.getElementById('databaseResult');
            result.style.display = 'block';
            result.textContent = 'Vytváření tabulek...';
            result.className = 'test-result';
            
            try {
                const response = await fetch('setup.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=create_tables'
                });
                
                const data = await response.json();
                
                result.className = `test-result ${data.success ? 'success' : 'error'}`;
                result.textContent = data.message;
            } catch (error) {
                result.className = 'test-result error';
                result.textContent = 'Chyba při vytváření tabulek: ' + error.message;
            }
        }
        
        async function testSocialAuth(provider) {
            const result = document.getElementById('socialResult');
            result.style.display = 'block';
            result.textContent = `Testování ${provider} OAuth...`;
            result.className = 'test-result';
            
            try {
                const response = await fetch('setup.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=test_social_auth&provider=${provider}`
                });
                
                const data = await response.json();
                
                result.className = `test-result ${data.success ? 'success' : 'error'}`;
                result.innerHTML = `
                    <strong>${data.message}</strong><br>
                    Client ID: ${data.details.client_id ? '✓' : '✗'}<br>
                    Client Secret: ${data.details.client_secret ? '✓' : '✗'}<br>
                    Redirect URI: ${data.details.redirect_uri}
                `;
            } catch (error) {
                result.className = 'test-result error';
                result.textContent = 'Chyba při testování: ' + error.message;
            }
        }
    </script>
</body>
</html>

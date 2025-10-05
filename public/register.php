<?php
require_once 'php/auth.php';

// Debug: Reset-Parameter verarbeiten (nur für Entwicklung)
if (isset($_GET['reset']) && $_GET['reset'] === 'true') {
    try {
        global $pdo;
        $pdo->prepare("DELETE FROM app_data WHERE setting_key IN ('username', 'password', 'master_key')")->execute();
        $success = 'Alle Benutzerdaten wurden zurückgesetzt. Du kannst jetzt einen neuen Account erstellen.';
    } catch (Exception $e) {
        error_log("Reset error: " . $e->getMessage());
        $error = 'Fehler beim Zurücksetzen der Daten.';
    }
}

// Versuche die Datenbank zu initialisieren falls nötig
try {
    require_once 'php/setup_db.php';
} catch (Exception $e) {
    // Falls Setup fehlschlägt, nur eine Warnung loggen
    error_log("Database setup warning: " . $e->getMessage());
}

// Wenn bereits ein User existiert, zum Login weiterleiten
if (!isset($_GET['reset']) && userExists()) {
    header('Location: /login.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    if (empty($username) || empty($password) || empty($confirmPassword)) {
        $error = 'Bitte fülle alle Felder aus.';
    } else {
        // Username-Validierung
        $usernameErrors = validateUsername($username);
        if (!empty($usernameErrors)) {
            $error = implode('<br>', $usernameErrors);
        } else {
            // Passwort-Validierung
            $passwordErrors = validatePassword($password);
            if (!empty($passwordErrors)) {
                $error = implode('<br>', $passwordErrors);
            } elseif ($password !== $confirmPassword) {
                $error = 'Die Passwörter stimmen nicht überein.';
            } else {
                // Debug: Prüfen ob bereits ein User existiert
                try {
                    if (userExists()) {
                        $error = 'Es existiert bereits ein Benutzer. Bitte verwende die <a href="/login.php" class="auth-link auth-link-primary">Anmeldeseite</a>.';
                    } else {
                        // Versuche den User zu erstellen
                        $result = createUser($username, $password);
                        if ($result) {
                            login($username);
                            header('Location: /index.php');
                            exit;
                        } else {
                            $error = 'Fehler beim Erstellen des Accounts. Möglicherweise ist die Datenbank nicht verfügbar oder es gibt ein Problem mit der Verschlüsselung. Bitte versuche es erneut oder kontaktiere den Administrator.';
                        }
                    }
                } catch (Exception $e) {
                    error_log("Registration error: " . $e->getMessage());
                    $error = 'Ein technischer Fehler ist aufgetreten. Bitte stelle sicher, dass die Datenbank verfügbar ist und versuche es erneut. Fehlerdetails wurden protokolliert.';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <!-- Verhindert Suchmaschinen-Indexierung -->
    <meta name="robots" content="noindex, nofollow, noarchive, nosnippet, noimageindex, notranslate">
    <meta name="googlebot" content="noindex, nofollow, noarchive, nosnippet, noimageindex">
    <meta name="bingbot" content="noindex, nofollow, noarchive, nosnippet">
    <meta name="slurp" content="noindex, nofollow, noarchive, nosnippet">
    <meta name="referrer" content="no-referrer">
    
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrierung - Tagebuch</title>
    
    <!-- Favicons -->
    <link rel="icon" type="image/x-icon" href="img/favicons/favicon-light.ico">
    <link rel="icon" type="image/x-icon" href="img/favicons/favicon-dark.ico" media="(prefers-color-scheme: light)">
    <link rel="icon" type="image/svg+xml" href="img/favicons/favicon-light.svg">
    <link rel="icon" type="image/svg+xml" href="img/favicons/favicon-dark.svg" media="(prefers-color-scheme: light)">
    <link rel="icon" type="image/png" sizes="16x16" href="img/favicons/favicon-light-16x16.png">
    <link rel="icon" type="image/png" sizes="16x16" href="img/favicons/favicon-dark-16x16.png" media="(prefers-color-scheme: light)">
    <link rel="icon" type="image/png" sizes="32x32" href="img/favicons/favicon-light-32x32.png">
    <link rel="icon" type="image/png" sizes="32x32" href="img/favicons/favicon-dark32x32.png" media="(prefers-color-scheme: light)">
    <link rel="apple-touch-icon" sizes="180x180" href="img/favicons/favicon-light-180x180.png">
    <link rel="apple-touch-icon" sizes="180x180" href="img/favicons/favicon-dark-180x180.png" media="(prefers-color-scheme: light)">
    <link rel="icon" type="image/png" sizes="512x512" href="img/favicons/favicon-light-512x512.png">
    <link rel="icon" type="image/png" sizes="512x512" href="img/favicons/favicon-dark-512x512.png" media="(prefers-color-scheme: light)">
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
    <style>
        /* CSS Custom Properties (Variables) */
        :root {
            /* Warme Pastell-Farben für cozy Design */
            --color-primary: #f4a4a4;          /* Sanftes Korallrosa */
            --color-secondary: #f7c5a4;        /* Warmes Pfirsich */
            --color-success: #b8d4a8;          /* Sanftes Salbeigrün */
            --color-warning: #f7d794;          /* Warmes Cremgelb */
            --color-error: #f2a8a8;            /* Sanftes Rosarot */
            --color-info: #a8c5f2;             /* Sanftes Himmelblau */
            --color-lime: #c8e6a0;             /* Sanftes Lindgrün */
            
            /* Warme Graustufen */
            --color-white: #fefefe;
            --color-cream: #faf8f5;            /* Warmes Creme statt reines Weiß */
            --color-gray-50: #f8f6f3;          /* Warmes Off-White */
            --color-gray-100: #f2ede8;         /* Sanftes Beige */
            --color-gray-150: #ebe4dc;         /* Zusätzlicher Grauton */
            --color-gray-200: #e8ddd4;         /* Warmes Hellgrau */
            --color-gray-300: #d4c4b8;         /* Sanftes Taupe */
            --color-gray-400: #b8a69a;         /* Warmes Mittelgrau */
            --color-gray-500: #9c8a7e;         /* Warmes Grau */
            --color-gray-600: #7d6b5f;         /* Warmes Dunkelgrau */
            --color-gray-700: #5e4c40;         /* Sanftes Braun */
            --color-gray-800: #3f2e24;         /* Warmes Dunkelbraun */
            --color-gray-900: #2a1b12;         /* Sanftes Dunkelbraun */
            
            /* Sanfte Border Colors */
            --border-default: var(--color-gray-200);
            --border-light: var(--color-gray-300);
            --border-focus: var(--color-primary);
            
            /* Warme Text Colors */
            --text-primary: var(--color-gray-800);
            --text-secondary: var(--color-gray-600);
            --text-muted: var(--color-gray-500);
            --text-white: var(--color-cream);
            --text-link: var(--color-primary);
            
            /* Font Size Variables */
            --font-size-xs: 0.75rem;    /* 12px */
            --font-size-sm: 0.875rem;   /* 14px */
            --font-size-base: 1rem;     /* 16px */
            --font-size-lg: 1.125rem;   /* 18px */
            --font-size-xl: 1.25rem;    /* 20px */
            --font-size-2xl: 1.5rem;    /* 24px */
            --font-size-3xl: 1.875rem;  /* 30px */
            --font-size-4xl: 2.25rem;   /* 36px */
            
            /* Font Families */
            --font-family-primary: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            --font-family-fallback: Arial, Helvetica, sans-serif;
        }

        body {
            font-family: var(--font-family-primary);
            background: linear-gradient(135deg, var(--color-gray-50) 0%, var(--color-gray-100) 30%, var(--color-cream) 100%);
            background-image: 
                radial-gradient(circle at 20% 30%, rgba(244, 164, 164, 0.3) 0%, transparent 60%),
                radial-gradient(circle at 80% 70%, rgba(247, 197, 164, 0.3) 0%, transparent 60%),
                radial-gradient(circle at 40% 80%, rgba(184, 212, 168, 0.2) 0%, transparent 50%);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 20px;
        }
        
        .auth-container {
            background: linear-gradient(145deg, var(--color-cream) 0%, rgba(255, 255, 255, 0.95) 50%, var(--color-gray-50) 100%);
            border-radius: 30px;
            box-shadow: 
                0 25px 35px -10px rgba(159, 122, 102, 0.15),
                0 15px 20px -8px rgba(159, 122, 102, 0.1);
            backdrop-filter: blur(15px);
            border: 3px solid rgba(244, 164, 164, 0.2);
            width: 100%;
            max-width: 400px;
            padding: 1.5rem;
        }
        
        .auth-header {
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: .5rem;
            margin-bottom: 1rem;
        }

        .auth-title,
        .auth-subtitle {
            margin: 0;
        }

        .auth-icon {
            background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-secondary) 100%);
            border-radius: 50%;
            font-size: var(--font-size-2xl);
            color: var(--color-cream);
            box-shadow: 0 12px 20px -5px rgba(244, 164, 164, 0.4);
            width: 80px;
            height: 80px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 3px solid rgba(255, 255, 255, 0.3);
        }
        
        .auth-header h1 {
            color: var(--text-primary);
            font-size: var(--font-size-2xl);
            font-weight: 700;
            letter-spacing: -0.025em;
        }
        
        .auth-header p {
            color: var(--text-secondary);
            font-size: var(--font-size-base);
            font-weight: 400;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--color-gray-700);
            font-weight: 500;
            font-size: var(--font-size-sm);
        }
        
        .form-group input {
            width: 100%;
            border: 3px solid var(--border-default);
            border-radius: 20px;
            font-size: var(--font-size-base);
            font-family: inherit;
            transition: all 0.4s ease-in-out;
            background: linear-gradient(135deg, var(--color-cream) 0%, rgba(255, 255, 255, 0.8) 100%);
            padding: 12px 20px;
            box-shadow: inset 0 2px 4px rgba(159, 122, 102, 0.1);
            box-sizing: border-box;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: var(--color-primary);
            box-shadow: 0 0 0 4px rgba(244, 164, 164, 0.3), 0 8px 16px -2px rgba(159, 122, 102, 0.2);
            transform: translateY(-3px) scale(1.02);
            transition: all 0.3s ease-in-out;
        }

        .form-group input:hover {
            border-color: var(--color-primary);
            box-shadow: 0 6px 12px -2px rgba(159, 122, 102, 0.2);
            transform: translateY(-1px);
        }
        
        .form-group input.valid {
            border-color: var(--color-success) !important;
        }
        
        .form-group input.invalid {
            border-color: var(--color-error) !important;
        }
        
        .btn {
            width: 100%;
            border: none;
            border-radius: 25px;
            font-size: var(--font-size-base);
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            transition: all 0.4s ease-in-out;
            text-decoration: none;
            padding: 12px 25px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-secondary) 100%);
            color: var(--color-cream);
            box-shadow: 0 6px 12px -2px rgba(244, 164, 164, 0.3);
            border: 2px solid rgba(255, 255, 255, 0.2);
        }
        
        .btn:hover {
            transform: scale(1.03);
            box-shadow: 0 15px 30px -5px rgba(244, 164, 164, 0.5);
            background: linear-gradient(135deg, var(--color-secondary) 0%, var(--color-primary) 100%);
            transition: all 0.3s ease-in-out;
        }

        .btn:active {
            transform: translateY(-2px) scale(1.01);
            box-shadow: 0 10px 20px -3px rgba(244, 164, 164, 0.4);
            transition: all 0.15s ease-in-out;
        }
        
        .error {
            background: linear-gradient(135deg, #fdf2f2 0%, #fce8e8 100%);
            color: var(--color-error);
            border-radius: 20px;
            border: 2px solid rgba(242, 168, 168, 0.3);
            font-size: var(--font-size-sm);
            font-weight: 500;
            padding: 12px 20px;
            box-shadow: 0 4px 8px -2px rgba(242, 168, 168, 0.2);
            backdrop-filter: blur(5px);
            margin-bottom: 1rem;
            line-height: 1.5;
        }
        
        .success {
            background: linear-gradient(135deg, #f2f7ed 0%, #e6f2dc 100%);
            color: var(--color-success);
            border-radius: 20px;
            border: 2px solid rgba(184, 212, 168, 0.3);
            font-size: var(--font-size-sm);
            font-weight: 500;
            padding: 12px 20px;
            box-shadow: 0 4px 8px -2px rgba(184, 212, 168, 0.2);
            backdrop-filter: blur(5px);
            margin-bottom: 1rem;
        }
        
        .password-requirements {
            font-size: var(--font-size-xs);
            color: var(--text-secondary);
            margin-top: 0.5rem;
            line-height: 1.4;
            background: linear-gradient(135deg, rgba(248, 246, 243, 0.7) 0%, rgba(255, 255, 255, 0.5) 100%);
            border-radius: 15px;
            padding: 12px 16px;
            border: 2px solid rgba(244, 164, 164, 0.1);
        }
        
        .password-requirements p {
            margin: 0 0 0.5rem 0;
            font-weight: 500;
            color: var(--text-primary);
        }
        
        .password-requirements ul {
            margin: 0;
            padding: 0;
            list-style: none;
        }
        
        .password-requirements li {
            margin-bottom: 0.4rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.4s ease-in-out;
            padding: 2px 0;
            border-radius: 8px;
        }
        
        .requirement-icon {
            width: 16px;
            height: 16px;
            font-size: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }
        
        .requirement-icon.unchecked {
            color: var(--color-error);
        }
        
        .requirement-icon.checked {
            color: var(--color-success);
        }
        
        .password-requirements li.checked {
            color: var(--color-success);
            background: linear-gradient(135deg, rgba(184, 212, 168, 0.1) 0%, rgba(184, 212, 168, 0.05) 100%);
            border-radius: 10px;
        }
        
        .password-requirements li.unchecked {
            color: var(--color-error);
        }
        
        .password-strength-bar {
            width: 100%;
            height: 6px;
            background: linear-gradient(135deg, var(--color-gray-200) 0%, var(--color-gray-300) 100%);
            border-radius: 10px;
            margin-top: 0.5rem;
            overflow: hidden;
            box-shadow: inset 0 2px 4px rgba(159, 122, 102, 0.1);
        }
        
        .password-strength-fill {
            height: 100%;
            width: 0%;
            transition: all 0.4s ease-in-out;
            border-radius: 10px;
        }
        
        .password-strength-text {
            font-size: var(--font-size-xs);
            margin-top: 0.25rem;
            font-weight: 500;
        }
        
        .strength-weak {
            background: linear-gradient(135deg, var(--color-error) 0%, #e89b9b 100%);
            color: var(--color-error);
        }
        
        .strength-fair {
            background: linear-gradient(135deg, var(--color-warning) 0%, #f5d679 100%);
            color: var(--color-warning);
        }
        
        .strength-good {
            background: linear-gradient(135deg, var(--color-lime) 0%, #c2e391 100%);
            color: var(--color-lime);
        }
        
        .strength-strong {
            background: linear-gradient(135deg, var(--color-success) 0%, #a8c99c 100%);
            color: var(--color-success);
        }
        
        .password-input-container {
            position: relative;
            display: flex;
            align-items: center;
        }
        
        .password-input-container input {
            padding-right: 3rem;
        }
        
        .password-toggle {
            position: absolute;
            right: 0.75rem;
            background: linear-gradient(135deg, var(--color-gray-100) 0%, var(--color-gray-150) 100%);
            border: 2px solid var(--border-default);
            border-radius: 50%;
            color: var(--text-secondary);
            cursor: pointer;
            padding: 0.4rem;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            width: 32px;
            height: 32px;
            box-shadow: 0 2px 4px rgba(159, 122, 102, 0.1);
        }
        
        .password-toggle:hover {
            color: var(--color-primary);
            background: linear-gradient(135deg, var(--color-gray-150) 0%, var(--color-gray-200) 100%);
            transform: scale(1.1);
            box-shadow: 0 4px 8px rgba(159, 122, 102, 0.2);
        }
        
        .password-toggle:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(244, 164, 164, 0.3);
            border-color: var(--color-primary);
        }
        
        .password-match-indicator {
            font-size: var(--font-size-xs);
            margin-top: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 8px 12px;
            border-radius: 12px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .password-match-indicator.match {
            color: var(--color-success);
            background: linear-gradient(135deg, rgba(184, 212, 168, 0.1) 0%, rgba(184, 212, 168, 0.05) 100%);
            border: 2px solid rgba(184, 212, 168, 0.2);
        }
        
        .password-match-indicator.no-match {
            color: var(--color-error);
            background: linear-gradient(135deg, rgba(242, 168, 168, 0.1) 0%, rgba(242, 168, 168, 0.05) 100%);
            border: 2px solid rgba(242, 168, 168, 0.2);
        }
        
        /* Smooth transitions for all interactive elements */
        * {
            transition: all 0.3s ease;
        }

        /* Focus states for better accessibility */
        .btn:focus-visible {
            outline: 3px solid rgba(244, 164, 164, 0.5);
            outline-offset: 2px;
        }

        .form-group input:focus-visible {
            outline: none;
            box-shadow: 0 0 0 4px rgba(244, 164, 164, 0.3);
        }

        /* Loading state for auth button */
        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        /* Animation for page load */
        @keyframes authSlideIn {
            0% {
                opacity: 0;
                transform: translateY(40px) scale(0.85);
            }
            70% {
                opacity: 0.8;
                transform: translateY(-5px) scale(1.02);
            }
            100% {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .auth-container {
            animation: authSlideIn 0.8s ease-out;
        }
    </style>
</head>
<body class="auth-page-body">
    <div class="auth-container auth-card">
        <div class="auth-header">
            <div class="auth-icon">
                <i class="fa fa-book-open"></i>
            </div>
            <h1 class="auth-title">Tagebuch</h1>
            <p class="auth-subtitle">Erstelle Deinen Account</p>
        </div>
        
        <?php if ($error): ?>
            <div class="error auth-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="success auth-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label for="username" class="auth-label">Benutzername:</label>
                <input type="text" id="username" name="username" class="auth-input"
                       value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" 
                       required minlength="3">
            </div>
            
            <div class="form-group">
                <label for="password" class="auth-label">Passwort:</label>
                <div class="password-input-container">
                    <input type="password" id="password" name="password" class="auth-input" required minlength="12">
                    <button type="button" class="password-toggle" id="password-toggle">
                        <i class="fa-solid fa-eye" id="password-toggle-icon"></i>
                    </button>
                </div>
                <div class="password-strength-bar">
                    <div class="password-strength-fill" id="password-strength-fill"></div>
                </div>
                <div class="password-strength-text" id="password-strength-text"></div>
                <div class="password-requirements">
                    <p>Das Passwort muss enthalten:</p>
                    <ul id="password-checklist">
                        <li id="check-length">
                            <i class="fa-solid fa-times requirement-icon unchecked"></i>
                            <span>Mindestens 12 Zeichen</span>
                        </li>
                        <li id="check-uppercase">
                            <i class="fa-solid fa-times requirement-icon unchecked"></i>
                            <span>Mindestens einen Großbuchstaben (A-Z)</span>
                        </li>
                        <li id="check-lowercase">
                            <i class="fa-solid fa-times requirement-icon unchecked"></i>
                            <span>Mindestens einen Kleinbuchstaben (a-z)</span>
                        </li>
                        <li id="check-number">
                            <i class="fa-solid fa-times requirement-icon unchecked"></i>
                            <span>Mindestens eine Zahl (0-9)</span>
                        </li>
                        <li id="check-special">
                            <i class="fa-solid fa-times requirement-icon unchecked"></i>
                            <span>Mindestens ein Sonderzeichen (!@#$%^&*)</span>
                        </li>
                        <li id="check-pattern">
                            <i class="fa-solid fa-times requirement-icon unchecked"></i>
                            <span>Keine unsicheren Muster</span>
                        </li>
                    </ul>
                </div>
            </div>
            
            <div class="form-group">
                <label for="confirm_password" class="auth-label">Passwort bestätigen:</label>
                <div class="password-input-container">
                    <input type="password" id="confirm_password" name="confirm_password" class="auth-input" required minlength="12">
                    <button type="button" class="password-toggle" id="confirm-password-toggle">
                        <i class="fa-solid fa-eye" id="confirm-password-toggle-icon"></i>
                    </button>
                </div>
                <div class="password-match-indicator" id="password-match-indicator"></div>
            </div>
            
            <button type="submit" class="btn auth-btn auth-btn-primary">Account erstellen</button>
        </form>
        
        <!-- Debug-Informationen (nur für Entwicklung) -->
        <div style="margin-top: 2rem; padding: 1rem; background: rgba(168, 197, 242, 0.1); border-radius: 15px; border: 2px solid rgba(168, 197, 242, 0.2); font-size: 0.75rem; color: var(--text-secondary);">
            <h4 style="margin: 0 0 0.5rem 0; color: var(--color-info);">Debug-Informationen:</h4>
            <p><strong>Benutzer existiert:</strong> <?php echo userExists() ? 'Ja' : 'Nein'; ?></p>
            <p><strong>Datenbank-Verbindung:</strong> <?php 
                try { 
                    global $pdo; 
                    $pdo->query("SELECT 1"); 
                    echo "Aktiv"; 
                } catch (Exception $e) { 
                    echo "Fehler: " . htmlspecialchars($e->getMessage()); 
                } 
            ?></p>
            <?php if (userExists()): ?>
                <p style="margin-top: 1rem;">
                    <a href="?reset=true" style="color: var(--color-error); text-decoration: underline; font-weight: 500;" 
                       onclick="return confirm('Alle Benutzerdaten werden gelöscht. Fortfahren?')">
                        Benutzerdaten zurücksetzen
                    </a>
                </p>
            <?php endif; ?>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const passwordInput = document.getElementById('password');
            const confirmPasswordInput = document.getElementById('confirm_password');
            
            // Passwort-Validierungsfunktionen
            function validatePasswordRequirements(password) {
                const requirements = {
                    length: password.length >= 12,
                    uppercase: /[A-Z]/.test(password),
                    lowercase: /[a-z]/.test(password),
                    number: /[0-9]/.test(password),
                    special: /[^A-Za-z0-9]/.test(password),
                    pattern: !hasUnsafePatterns(password)
                };
                
                return requirements;
            }
            
            function hasUnsafePatterns(password) {
                const unsafePatterns = [
                    /(.)\1{3,}/,     // 4 oder mehr gleiche Zeichen
                    /123456/,        // Aufeinanderfolgende Zahlen
                    /abcdef/,        // Aufeinanderfolgende Buchstaben
                    /qwerty/i,       // Keyboard-Muster
                    /password/i,     // Das Wort "password"
                    /tagebuch/i      // Das Wort "tagebuch"
                ];
                
                return unsafePatterns.some(pattern => pattern.test(password));
            }
            
            function updateRequirementStatus(elementId, isValid) {
                const element = document.getElementById(elementId);
                const icon = element.querySelector('.requirement-icon');
                
                if (isValid) {
                    icon.className = 'fa-solid fa-check requirement-icon checked';
                    element.className = 'checked';
                } else {
                    icon.className = 'fa-solid fa-times requirement-icon unchecked';
                    element.className = 'unchecked';
                }
            }
            
            function calculatePasswordStrength(password) {
                if (password.length === 0) return { score: 0, text: '', className: '' };
                
                let score = 0;
                const requirements = validatePasswordRequirements(password);
                
                // Base score from requirements
                score += requirements.length ? 20 : 0;
                score += requirements.uppercase ? 15 : 0;
                score += requirements.lowercase ? 15 : 0;
                score += requirements.number ? 15 : 0;
                score += requirements.special ? 15 : 0;
                score += requirements.pattern ? 20 : 0;
                
                // Additional points for longer passwords
                if (password.length >= 16) score += 10;
                if (password.length >= 20) score += 10;
                
                // Determine strength level
                let text, className;
                if (score < 40) {
                    text = 'Schwach';
                    className = 'strength-weak';
                } else if (score < 70) {
                    text = 'Mäßig';
                    className = 'strength-fair';
                } else if (score < 90) {
                    text = 'Gut';
                    className = 'strength-good';
                } else {
                    text = 'Stark';
                    className = 'strength-strong';
                }
                
                return { score, text, className };
            }
            
            function updatePasswordStrength(password) {
                const strength = calculatePasswordStrength(password);
                const strengthFill = document.getElementById('password-strength-fill');
                const strengthText = document.getElementById('password-strength-text');
                
                // Update progress bar
                strengthFill.style.width = `${strength.score}%`;
                strengthFill.className = `password-strength-fill ${strength.className}`;
                
                // Update text
                strengthText.textContent = password.length > 0 ? `Passwort-Stärke: ${strength.text}` : '';
                strengthText.className = `password-strength-text ${strength.className}`;
            }
            
            function validatePassword() {
                const password = passwordInput.value;
                const requirements = validatePasswordRequirements(password);
                
                // Update visual indicators
                updateRequirementStatus('check-length', requirements.length);
                updateRequirementStatus('check-uppercase', requirements.uppercase);
                updateRequirementStatus('check-lowercase', requirements.lowercase);
                updateRequirementStatus('check-number', requirements.number);
                updateRequirementStatus('check-special', requirements.special);
                updateRequirementStatus('check-pattern', requirements.pattern);
                
                // Update password strength
                updatePasswordStrength(password);
                
                // Check if all requirements are met
                const allValid = Object.values(requirements).every(req => req);
                
                // Update password field border color
                if (password.length === 0) {
                    passwordInput.style.borderColor = 'var(--border-default)';
                } else if (allValid) {
                    passwordInput.style.borderColor = 'var(--color-success)';
                } else {
                    passwordInput.style.borderColor = 'var(--color-error)';
                }
                
                return allValid;
            }
            
            function validatePasswordMatch() {
                const password = passwordInput.value;
                const confirmPassword = confirmPasswordInput.value;
                const matchIndicator = document.getElementById('password-match-indicator');
                
                if (confirmPassword.length === 0) {
                    confirmPasswordInput.style.borderColor = 'var(--border-default)';
                    matchIndicator.textContent = '';
                    matchIndicator.className = 'password-match-indicator';
                    return true;
                }
                
                const matches = password === confirmPassword;
                confirmPasswordInput.style.borderColor = matches ? 'var(--color-success)' : 'var(--color-error)';
                
                if (matches) {
                    matchIndicator.innerHTML = '<i class="fa-solid fa-check"></i> Passwörter stimmen überein';
                    matchIndicator.className = 'password-match-indicator match';
                } else {
                    matchIndicator.innerHTML = '<i class="fa-solid fa-times"></i> Passwörter stimmen nicht überein';
                    matchIndicator.className = 'password-match-indicator no-match';
                }
                
                return matches;
            }
            
            // Password visibility toggle functions
            function setupPasswordToggle(inputId, toggleId, iconId) {
                const input = document.getElementById(inputId);
                const toggle = document.getElementById(toggleId);
                const icon = document.getElementById(iconId);
                
                toggle.addEventListener('click', function() {
                    const isPassword = input.type === 'password';
                    input.type = isPassword ? 'text' : 'password';
                    icon.className = isPassword ? 'fa-solid fa-eye-slash' : 'fa-solid fa-eye';
                });
            }
            
            // Setup password toggles
            setupPasswordToggle('password', 'password-toggle', 'password-toggle-icon');
            setupPasswordToggle('confirm_password', 'confirm-password-toggle', 'confirm-password-toggle-icon');
            
            // Event Listeners
            passwordInput.addEventListener('input', function() {
                validatePassword();
                if (confirmPasswordInput.value.length > 0) {
                    validatePasswordMatch();
                }
            });
            
            confirmPasswordInput.addEventListener('input', validatePasswordMatch);
            
            // Form submission validation
            document.querySelector('form').addEventListener('submit', function(e) {
                const passwordValid = validatePassword();
                const passwordsMatch = validatePasswordMatch();
                
                if (!passwordValid || !passwordsMatch) {
                    e.preventDefault();
                    
                    if (!passwordValid) {
                        alert('Bitte erfülle alle Passwort-Anforderungen.');
                    } else if (!passwordsMatch) {
                        alert('Die Passwörter stimmen nicht überein.');
                    }
                }
            });
            
            // Initial validation on page load
            if (passwordInput.value.length > 0) {
                validatePassword();
            }
        });
    </script>
</body>
</html>
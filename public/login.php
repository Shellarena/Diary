<?php
require_once 'php/auth.php';
require_once 'php/db_config.php';
require_once 'php/encryption.php';

// Funktion zum Abrufen des gespeicherten Styles
function getSavedStyle() {
    global $pdo;
    try {
        $pdo->exec("USE diary_db");
        $stmt = $pdo->prepare("SELECT setting_value FROM app_data WHERE setting_key = 'style'");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            $style = DataEncryption::decrypt($result['setting_value']);
            return $style ?: 'standard';
        }
    } catch (Exception $e) {
        // Falls ein Fehler auftritt, Standard-Style verwenden
    }
    return 'standard';
}

// Gespeicherten Style abrufen
$savedStyle = getSavedStyle();

// Wenn kein User existiert, zur Registrierung weiterleiten
if (!userExists()) {
    header('Location: /register.php');
    exit;
}

// Wenn bereits eingeloggt, zur Hauptseite weiterleiten
if (isLoggedIn()) {
    header('Location: /index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Rate Limiting - einfache Session-basierte Implementierung
    if (!isset($_SESSION['login_attempts'])) {
        $_SESSION['login_attempts'] = 0;
        $_SESSION['last_attempt'] = 0;
    }
    
    $now = time();
    $timeSinceLastAttempt = $now - $_SESSION['last_attempt'];
    
    // Reset attempts nach 15 Minuten
    if ($timeSinceLastAttempt > 900) {
        $_SESSION['login_attempts'] = 0;
    }
    
    // Zu viele Versuche
    if ($_SESSION['login_attempts'] >= 5 && $timeSinceLastAttempt < 900) {
        $waitTime = 900 - $timeSinceLastAttempt;
        $waitMinutes = ceil($waitTime / 60);
        $error = "Zu viele fehlgeschlagene Login-Versuche. Bitte warte {$waitMinutes} Minuten.";
    } elseif (empty($username) || empty($password)) {
        $error = 'Bitte fülle alle Felder aus.';
    } elseif (strlen($username) > 50) {
        $error = 'Ungültige Eingabe.';
    } else {
        // Login-Versuch
        if (verifyLogin($username, $password)) {
            // Erfolgreicher Login - Reset attempts
            $_SESSION['login_attempts'] = 0;
            login($username);
            header('Location: /index.php');
            exit;
        } else {
            // Fehlgeschlagener Login
            $_SESSION['login_attempts']++;
            $_SESSION['last_attempt'] = $now;
            
            // Kleine Verzögerung gegen Timing-Angriffe
            usleep(rand(100000, 500000)); // 0.1-0.5 Sekunden
            
            $remainingAttempts = 5 - $_SESSION['login_attempts'];
            if ($remainingAttempts > 0) {
                $error = "Ungültiger Benutzername oder Passwort. Noch {$remainingAttempts} Versuche übrig.";
            } else {
                $error = 'Zu viele fehlgeschlagene Versuche. Account temporär gesperrt.';
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
    <title>Login - Tagebuch</title>
    
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
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="css/layout.css" rel="stylesheet">
    <?php
    // Style basierend auf gespeicherten Einstellungen laden
    $styleFile = 'css/pastel.css'; // Standard
    if ($savedStyle === 'hell') {
        $styleFile = 'css/light.css';
    } elseif ($savedStyle === 'dunkel') {
        $styleFile = 'css/dark.css';
    }
    echo '<link href="' . $styleFile . '" rel="stylesheet">';
    ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
</head>
<body class="auth-page-body">
    <div class="auth-page-container">
        <div class="auth-card">
            <div class="auth-header">
                <div class="auth-icon">
                    <i class="fas fa-book-open"></i>
                </div>
                <h1 class="auth-title">Tagebuch</h1>
                <p class="auth-subtitle">Melden Dich an</p>
            </div>
            
            <?php if ($error): ?>
                <div class="auth-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <form method="POST" class="auth-form">
                <div class="auth-form-group">
                    <label for="username" class="auth-label">Benutzername:</label>
                    <input type="text" id="username" name="username" class="auth-input"
                           value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" 
                           required>
                </div>
                
                <div class="auth-form-group">
                    <label for="password" class="auth-label">Passwort:</label>
                    <input type="password" id="password" name="password" class="auth-input" required>
                </div>
                
                <button type="submit" class="auth-btn auth-btn-primary">
                    <i class="fas fa-sign-in-alt mr-2"></i>
                    Anmelden
                </button>
            </form>
        </div>
    </div>
</body>
</html>
<?php
// auth.php - Authentication functions (with encryption)
require_once 'db_config.php';
require_once 'encryption.php';

session_start();

function userExists() {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM app_data WHERE setting_key = 'username'");
        $stmt->execute();
        return $stmt->fetchColumn() > 0;
    } catch (PDOException $e) {
        error_log("Error checking if user exists: " . $e->getMessage());
        return false;
    }
}

function getUserCredentials() {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT 
                u.setting_value as username, 
                p.setting_value as password 
            FROM app_data u 
            JOIN app_data p ON u.setting_key = 'username' AND p.setting_key = 'password'
        ");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            // Decrypt username (password stays hashed)
            $result['username'] = DataEncryption::decrypt($result['username']);
        }
        
        return $result;
    } catch (Exception $e) {
        error_log("Error getting user credentials: " . $e->getMessage());
        return false;
    }
}

function createUser($username, $password) {
    global $pdo;
    try {
        // Check if user already exists
        if (userExists()) {
            return false;
        }
        
        $pdo->beginTransaction();
        
        // Store username encrypted
        $encryptedUsername = DataEncryption::encrypt($username);
        $stmt = $pdo->prepare("INSERT INTO app_data (setting_key, setting_value) VALUES ('username', ?)");
        $stmt->execute([$encryptedUsername]);
        
        // Store password hashed (hashing + encryption for double security)
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO app_data (setting_key, setting_value) VALUES ('password', ?)");
        $stmt->execute([$hashedPassword]);
        
        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error creating user: " . $e->getMessage());
        return false;
    }
}

function verifyLogin($username, $password) {
    $credentials = getUserCredentials();
    if (!$credentials) {
        return false;
    }
    
    return $credentials['username'] === $username && 
           password_verify($password, $credentials['password']);
}

function isLoggedIn() {
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

function login($username) {
    $_SESSION['logged_in'] = true;
    $_SESSION['username'] = $username;
    $_SESSION['login_time'] = time();
}

function logout() {
    session_destroy();
}

function requireAuth() {
    // Check if user exists
    if (!userExists()) {
        // No user present -> to registration
        header('Location: /register.php');
        exit;
    }
    
    // User exists but not logged in -> to login
    if (!isLoggedIn()) {
        header('Location: /login.php');
        exit;
    }
}

function validatePassword($password) {
    $errors = [];
    
    // Minimum length
    if (strlen($password) < 12) {
        $errors[] = 'Das Passwort muss mindestens 12 Zeichen lang sein.';
    }
    
    // Maximum length (for security against DoS attacks)
    if (strlen($password) > 128) {
        $errors[] = 'Das Passwort darf maximal 128 Zeichen lang sein.';
    }
    
    // At least one uppercase letter
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'Das Passwort muss mindestens einen Großbuchstaben enthalten.';
    }
    
    // At least one lowercase letter
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = 'Das Passwort muss mindestens einen Kleinbuchstaben enthalten.';
    }
    
    // At least one number
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'Das Passwort muss mindestens eine Zahl enthalten.';
    }
    
    // At least one special character
    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        $errors[] = 'Das Passwort muss mindestens ein Sonderzeichen enthalten.';
    }
    
    // No common password patterns
    $commonPatterns = [
        '/(.)\1{3,}/',  // 4 or more identical characters in a row
        '/123456/',     // Sequential numbers
        '/abcdef/',     // Sequential letters
        '/qwerty/i',    // Keyboard patterns
        '/password/i',  // The word "password"
        '/tagebuch/i',  // The word "tagebuch" (app-specific)
    ];
    
    foreach ($commonPatterns as $pattern) {
        if (preg_match($pattern, $password)) {
            $errors[] = 'Das Passwort enthält unsichere Muster oder häufig verwendete Begriffe.';
            break;
        }
    }
    
    return $errors;
}

function validateUsername($username) {
    $errors = [];
    
    if (strlen($username) < 3) {
        $errors[] = 'Der Benutzername muss mindestens 3 Zeichen lang sein.';
    }
    
    if (strlen($username) > 50) {
        $errors[] = 'Der Benutzername darf maximal 50 Zeichen lang sein.';
    }
    
    if (!preg_match('/^[a-zA-Z0-9_-]+$/', $username)) {
        $errors[] = 'Der Benutzername darf nur Buchstaben, Zahlen, Unterstriche und Bindestriche enthalten.';
    }
    
    return $errors;
}
?>
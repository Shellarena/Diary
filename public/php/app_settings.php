<?php
// app_settings.php - Manage app settings (with encryption)
require_once 'db_config.php';
require_once 'encryption.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

$method = $_SERVER['REQUEST_METHOD'];

// Define which settings should be encrypted
$encryptedSettings = ['app_name', 'theme', 'username', 'style']; // 'password' and 'master_key' are handled specially
$nonEncryptedSettings = ['password', 'master_key']; // These are NOT encrypted

try {
    $pdo->exec("USE diary_db");
    
    if ($method === 'GET') {
        // Retrieve settings
        $key = $_GET['key'] ?? null;
        
        if ($key) {
            // Retrieve single setting
            $stmt = $pdo->prepare("SELECT setting_value FROM app_data WHERE setting_key = ?");
            $stmt->execute([$key]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $value = null;
            if ($result) {
                // Decrypt the setting if necessary
                if (in_array($key, $encryptedSettings)) {
                    $value = DataEncryption::decrypt($result['setting_value']);
                } else {
                    $value = $result['setting_value'];
                }
            }
            
            echo json_encode([
                'success' => true,
                'value' => $value
            ]);
        } else {
            // Retrieve all settings (except sensitive ones)
            $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM app_data WHERE setting_key NOT IN ('password', 'master_key')");
            $stmt->execute();
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $settings = [];
            foreach ($results as $row) {
                $key = $row['setting_key'];
                $value = $row['setting_value'];
                
                // Decrypt the setting if necessary
                if (in_array($key, $encryptedSettings)) {
                    $value = DataEncryption::decrypt($value);
                }
                
                $settings[$key] = $value;
            }
            
            echo json_encode([
                'success' => true,
                'settings' => $settings
            ]);
        }
        
    } else if ($method === 'POST') {
        // Save/update setting
        $input = json_decode(file_get_contents('php://input'), true);
        $key = $input['key'] ?? null;
        $value = $input['value'] ?? null;
        
        if (!$key) {
            throw new Exception('Setting key ist erforderlich');
        }
        
        // Prevent direct saving of sensitive settings
        if (in_array($key, $nonEncryptedSettings)) {
            throw new Exception('Diese Einstellung kann nicht direkt geändert werden');
        }
        
        // Encrypt the setting if necessary
        if (in_array($key, $encryptedSettings)) {
            $value = DataEncryption::encrypt($value);
        }
        
        // INSERT ... ON DUPLICATE KEY UPDATE for MySQL
        $stmt = $pdo->prepare("INSERT INTO app_data (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = CURRENT_TIMESTAMP");
        $stmt->execute([$key, $value, $value]);
        
        echo json_encode(['success' => true]);
        
    } else if ($method === 'DELETE') {
        // Delete setting
        $input = json_decode(file_get_contents('php://input'), true);
        $key = $input['key'] ?? null;
        
        if (!$key) {
            throw new Exception('Setting key ist erforderlich');
        }
        
        $stmt = $pdo->prepare("DELETE FROM app_data WHERE setting_key = ?");
        $stmt->execute([$key]);
        
        echo json_encode(['success' => true]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
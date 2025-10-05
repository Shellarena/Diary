<?php
// key_management.php - Key management and security functions
require_once 'db_config.php';
require_once 'encryption.php';

header('Content-Type: application/json');

// Only executable via CLI or with admin authorization
$isAuthorized = (php_sapi_name() === 'cli') || (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true);

if (!$isAuthorized) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Nicht autorisiert']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'rotate_key':
            // Rotate the master key
            DataEncryption::rotateMasterKey();
            echo json_encode([
                'success' => true,
                'message' => 'Hauptschlüssel erfolgreich rotiert'
            ]);
            break;
            
        case 'check_encryption':
            // Check encryption status
            $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM diary_entries");
            $stmt->execute();
            $totalEntries = $stmt->fetchColumn();
            
            // Check how many entries are encrypted
            $stmt = $pdo->prepare("SELECT content FROM diary_entries LIMIT 10");
            $stmt->execute();
            $sampleEntries = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $encryptedCount = 0;
            foreach ($sampleEntries as $entry) {
                try {
                    DataEncryption::decrypt($entry['content']);
                    $encryptedCount++;
                } catch (Exception $e) {
                    // Not encrypted
                }
            }
            
            $encryptionPercentage = $totalEntries > 0 ? ($encryptedCount / min(10, $totalEntries)) * 100 : 0;
            
            echo json_encode([
                'success' => true,
                'total_entries' => $totalEntries,
                'sample_encrypted_count' => $encryptedCount,
                'estimated_encryption_percentage' => round($encryptionPercentage, 1)
            ]);
            break;
            
        case 'backup_key':
            // Create backup of master key (for emergencies only)
            $stmt = $pdo->prepare("SELECT setting_value FROM app_data WHERE setting_key = 'master_key'");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                $keyBackup = [
                    'key' => $result['setting_value'],
                    'created' => date('Y-m-d H:i:s'),
                    'warning' => 'DIESER SCHLÜSSEL ERMÖGLICHT ZUGRIFF AUF ALLE VERSCHLÜSSELTEN DATEN!'
                ];
                
                // Save backup in secure file
                $backupFile = __DIR__ . '/../../backups/key_backup_' . date('Y-m-d_H-i-s') . '.json';
                @mkdir(dirname($backupFile), 0700, true);
                file_put_contents($backupFile, json_encode($keyBackup, JSON_PRETTY_PRINT));
                chmod($backupFile, 0600); // Only readable by owner
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Schlüssel-Backup erstellt',
                    'backup_file' => $backupFile
                ]);
            } else {
                throw new Exception('Kein Hauptschlüssel gefunden');
            }
            break;
            
        case 'verify_data_integrity':
            // Check data integrity
            $corruptEntries = 0;
            $totalChecked = 0;
            
            $stmt = $pdo->prepare("SELECT id, content, music_title FROM diary_entries");
            $stmt->execute();
            $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($entries as $entry) {
                $totalChecked++;
                try {
                    DataEncryption::decrypt($entry['content']);
                    DataEncryption::decrypt($entry['music_title']);
                } catch (Exception $e) {
                    $corruptEntries++;
                    error_log("Corrupt entry ID " . $entry['id'] . ": " . $e->getMessage());
                }
            }
            
            echo json_encode([
                'success' => true,
                'total_entries_checked' => $totalChecked,
                'corrupt_entries' => $corruptEntries,
                'integrity_percentage' => $totalChecked > 0 ? round((($totalChecked - $corruptEntries) / $totalChecked) * 100, 1) : 0
            ]);
            break;
            
        default:
            throw new Exception('Unbekannte Aktion');
    }
    
} catch (Exception $e) {
    error_log("Key management error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
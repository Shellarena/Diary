<?php
// encryption.php - Encryption functions for sensitive data
require_once 'db_config.php';

class DataEncryption {
    private static $cipher = 'AES-256-GCM';
    private static $keyLength = 32; // 256 bits
    
    /**
     * Generates or loads the master key for encryption
     */
    private static function getMasterKey() {
        global $pdo;
        
        try {
            // Try to load key from database
            $stmt = $pdo->prepare("SELECT setting_value FROM app_data WHERE setting_key = 'master_key'");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                return base64_decode($result['setting_value']);
            }
            
            // No key present, create a new one
            $masterKey = random_bytes(self::$keyLength);
            $encodedKey = base64_encode($masterKey);
            
            $stmt = $pdo->prepare("INSERT INTO app_data (setting_key, setting_value) VALUES ('master_key', ?)");
            $stmt->execute([$encodedKey]);
            
            return $masterKey;
            
        } catch (PDOException $e) {
            error_log("Error managing master key: " . $e->getMessage());
            throw new Exception("Verschlüsselungsschlüssel konnte nicht verwaltet werden");
        }
    }
    
    /**
     * Encrypts a text
     */
    public static function encrypt($plaintext) {
        if (empty($plaintext)) {
            return '';
        }
        
        try {
            $masterKey = self::getMasterKey();
            
            // Generate random IV (Initialization Vector)
            $ivLength = openssl_cipher_iv_length(self::$cipher);
            $iv = random_bytes($ivLength);
            
            // Encrypt the data
            $encrypted = openssl_encrypt($plaintext, self::$cipher, $masterKey, OPENSSL_RAW_DATA, $iv, $tag);
            
            if ($encrypted === false) {
                throw new Exception("Verschlüsselung fehlgeschlagen");
            }
            
            // Combine IV, tag and encrypted data
            $result = base64_encode($iv . $tag . $encrypted);
            
            return $result;
            
        } catch (Exception $e) {
            error_log("Encryption error: " . $e->getMessage());
            throw new Exception("Daten konnten nicht verschlüsselt werden");
        }
    }
    
    /**
     * Decrypts a text
     */
    public static function decrypt($encryptedData) {
        if (empty($encryptedData)) {
            return '';
        }
        
        try {
            $masterKey = self::getMasterKey();
            
            // Decode Base64 data
            $data = base64_decode($encryptedData);
            if ($data === false) {
                throw new Exception("Ungültige Base64-Daten");
            }
            
            // Extract IV, tag and encrypted data
            $ivLength = openssl_cipher_iv_length(self::$cipher);
            $tagLength = 16; // GCM tag is always 16 bytes
            
            if (strlen($data) < $ivLength + $tagLength) {
                throw new Exception("Verschlüsselte Daten zu kurz");
            }
            
            $iv = substr($data, 0, $ivLength);
            $tag = substr($data, $ivLength, $tagLength);
            $encrypted = substr($data, $ivLength + $tagLength);
            
            // Decrypt the data
            $decrypted = openssl_decrypt($encrypted, self::$cipher, $masterKey, OPENSSL_RAW_DATA, $iv, $tag);
            
            if ($decrypted === false) {
                throw new Exception("Entschlüsselung fehlgeschlagen - möglicherweise wurden die Daten manipuliert");
            }
            
            return $decrypted;
            
        } catch (Exception $e) {
            error_log("Decryption error: " . $e->getMessage());
            throw new Exception("Daten konnten nicht entschlüsselt werden");
        }
    }
    
    /**
     * Encrypts sensitive settings
     */
    public static function encryptSetting($value) {
        return self::encrypt($value);
    }
    
    /**
     * Decrypts sensitive settings
     */
    public static function decryptSetting($encryptedValue) {
        return self::decrypt($encryptedValue);
    }
    
    /**
     * Rotates the master key (for security updates)
     */
    public static function rotateMasterKey() {
        global $pdo;
        
        try {
            $pdo->beginTransaction();
            
            // Load all encrypted data
            $stmt = $pdo->prepare("SELECT * FROM diary_entries");
            $stmt->execute();
            $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $stmt = $pdo->prepare("SELECT * FROM app_data WHERE setting_key != 'master_key'");
            $stmt->execute();
            $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Decrypt all data with old key
            $decryptedEntries = [];
            foreach ($entries as $entry) {
                $decryptedEntries[] = [
                    'id' => $entry['id'],
                    'entry_date' => $entry['entry_date'],
                    'content' => self::decrypt($entry['content']),
                    'mood' => $entry['mood'], // Mood is not encrypted (only number)
                    'music_title' => self::decrypt($entry['music_title'])
                ];
            }
            
            $decryptedSettings = [];
            foreach ($settings as $setting) {
                if (in_array($setting['setting_key'], ['username', 'app_name', 'theme'])) {
                    $decryptedSettings[] = [
                        'setting_key' => $setting['setting_key'],
                        'setting_value' => self::decrypt($setting['setting_value'])
                    ];
                } else {
                    // Password stays hashed, is not encrypted
                    $decryptedSettings[] = $setting;
                }
            }
            
            // Delete old key
            $stmt = $pdo->prepare("DELETE FROM app_data WHERE setting_key = 'master_key'");
            $stmt->execute();
            
            // Create new key (automatically generated in getMasterKey())
            $newKey = self::getMasterKey();
            
            // Encrypt all data with new key
            foreach ($decryptedEntries as $entry) {
                $stmt = $pdo->prepare("UPDATE diary_entries SET content = ?, music_title = ? WHERE id = ?");
                $stmt->execute([
                    self::encrypt($entry['content']),
                    self::encrypt($entry['music_title']),
                    $entry['id']
                ]);
            }
            
            foreach ($decryptedSettings as $setting) {
                if (in_array($setting['setting_key'], ['username', 'app_name', 'theme'])) {
                    $stmt = $pdo->prepare("UPDATE app_data SET setting_value = ? WHERE setting_key = ?");
                    $stmt->execute([
                        self::encrypt($setting['setting_value']),
                        $setting['setting_key']
                    ]);
                }
            }
            
            $pdo->commit();
            return true;
            
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Key rotation error: " . $e->getMessage());
            throw new Exception("Schlüsselrotation fehlgeschlagen");
        }
    }
}
?>
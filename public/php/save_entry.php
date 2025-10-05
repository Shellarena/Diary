<?php
// save_entry.php - Save entry (with encryption)
header('Content-Type: application/json');
require_once 'db_config.php';
require_once 'encryption.php';

// Read mood value and music data
$data = json_decode(file_get_contents('php://input'), true);
$date = $data['date'];
$content = $data['content'];
$mood = isset($data['mood']) ? intval($data['mood']) : 1;
$musicTitle = isset($data['music_title']) ? $data['music_title'] : null;
$musicApi = isset($data['music_api']) ? $data['music_api'] : null;
$musicId = isset($data['music_id']) ? $data['music_id'] : null;
$musicArtist = isset($data['music_artist']) ? $data['music_artist'] : null;
$musicUrl = isset($data['music_url']) ? $data['music_url'] : null;

try {
    // Encrypt sensitive data before saving
    $encryptedContent = DataEncryption::encrypt($content);
    $encryptedMusicTitle = DataEncryption::encrypt($musicTitle);
    $encryptedMusicArtist = DataEncryption::encrypt($musicArtist);
    $encryptedMusicUrl = DataEncryption::encrypt($musicUrl);
    
    $stmt = $pdo->prepare("INSERT INTO diary_entries (entry_date, content, mood, music_title, music_api, music_id, music_artist, music_url) VALUES (?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE content = ?, mood = ?, music_title = ?, music_api = ?, music_id = ?, music_artist = ?, music_url = ?, updated_at = CURRENT_TIMESTAMP");
    $stmt->execute([$date, $encryptedContent, $mood, $encryptedMusicTitle, $musicApi, $musicId, $encryptedMusicArtist, $encryptedMusicUrl, $encryptedContent, $mood, $encryptedMusicTitle, $musicApi, $musicId, $encryptedMusicArtist, $encryptedMusicUrl]);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    error_log("Save entry error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Fehler beim Speichern des Eintrags']);
}
?>

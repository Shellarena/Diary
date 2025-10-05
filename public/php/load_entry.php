<?php
// load_entry.php - Load entry (with decryption)
header('Content-Type: application/json');
require_once 'db_config.php';
require_once 'encryption.php';

$date = $_GET['date'];

try {
    $stmt = $pdo->prepare("SELECT content, mood, music_title, music_api, music_id, music_artist, music_url FROM diary_entries WHERE entry_date = ?");
    $stmt->execute([$date]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        // Decrypt sensitive data
        $decryptedContent = DataEncryption::decrypt($result['content']);
        $decryptedMusicTitle = DataEncryption::decrypt($result['music_title']);
        $decryptedMusicArtist = DataEncryption::decrypt($result['music_artist']);
        $decryptedMusicUrl = DataEncryption::decrypt($result['music_url']);
        
        echo json_encode([
            'success' => true,
            'content' => $decryptedContent,
            'mood' => $result['mood'], // Mood is not encrypted
            'music_title' => $decryptedMusicTitle,
            'music_api' => $result['music_api'],
            'music_id' => $result['music_id'],
            'music_artist' => $decryptedMusicArtist,
            'music_url' => $decryptedMusicUrl
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'content' => '',
            'mood' => 1,
            'music_title' => '',
            'music_api' => null,
            'music_id' => null,
            'music_artist' => null,
            'music_url' => null
        ]);
    }
} catch (Exception $e) {
    error_log("Load entry error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Fehler beim Laden des Eintrags']);
}
?>

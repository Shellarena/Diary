<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

function searchLocalMusic($query) {
    $jsonFile = __DIR__ . '/../music/local_music.json';
    
    if (!file_exists($jsonFile)) {
        return [
            'status' => 'error',
            'message' => 'Lokale Musik-Datei nicht gefunden',
            'results' => []
        ];
    }
    
    $size = @filesize($jsonFile);
    if ($size === false) {
        return [
            'status' => 'error',
            'message' => 'Dateigröße konnte nicht ermittelt werden',
            'results' => []
        ];
    }
    // Protection: better move very large JSON files to DB
    if ($size > 50 * 1024 * 1024) { // >50MB
        return [
            'status' => 'error',
            'message' => 'Die lokale Musik-Datei ist zu groß. Bitte DB-Modus verwenden oder Datei verkleinern.',
            'results' => []
        ];
    }

    $jsonContent = file_get_contents($jsonFile);
    $musicData = json_decode($jsonContent, true, 512, JSON_INVALID_UTF8_IGNORE);
    
    if (!$musicData || !is_array($musicData)) {
        return [
            'status' => 'error',
            'message' => 'Fehler beim Laden der Musik-Daten: ' . json_last_error_msg(),
            'results' => []
        ];
    }
    
    $query = strtolower(trim((string)$query));
    $results = [];
    
    if (empty($query)) {
        // If no search query, show first 10 titles
        $results = array_slice($musicData, 0, 10);
    } else {
        // Search in title, artist, album and genre
        foreach ($musicData as $track) {
            if (!is_array($track)) { continue; }
            $title = isset($track['title']) ? (string)$track['title'] : '';
            $artist = isset($track['artist']) ? (string)$track['artist'] : '';
            $album = isset($track['album']) ? (string)$track['album'] : '';
            $genre = isset($track['genre']) ? (string)$track['genre'] : '';
            $mood = isset($track['mood']) ? (string)$track['mood'] : '';

            $titleMatch = $title !== '' && stripos($title, $query) !== false;
            $artistMatch = $artist !== '' && stripos($artist, $query) !== false;
            $albumMatch = $album !== '' && stripos($album, $query) !== false;
            $genreMatch = $genre !== '' && stripos($genre, $query) !== false;
            $moodMatch = $mood !== '' && stripos($mood, $query) !== false;

            if ($titleMatch || $artistMatch || $albumMatch || $genreMatch || $moodMatch) {
                $results[] = $track;
            }
        }
    }
    
    // Format results for frontend display
    $formattedResults = [];
    foreach ($results as $track) {
        if (!is_array($track)) { continue; }
        $formattedResults[] = [
            'id' => $track['id'] ?? null,
            'title' => isset($track['title']) ? (string)$track['title'] : '',
            'artist' => isset($track['artist']) ? (string)$track['artist'] : '',
            'album' => isset($track['album']) ? (string)$track['album'] : '',
            'duration' => isset($track['duration']) ? $track['duration'] : null,
            'genre' => isset($track['genre']) ? (string)$track['genre'] : '',
            'mood' => isset($track['mood']) ? (string)$track['mood'] : '',
            'source' => 'local'
        ];
    }
    
    return [
        'status' => 'success',
        'message' => count($formattedResults) . ' Titel gefunden',
        'results' => $formattedResults,
        'total' => count($formattedResults)
    ];
}

// Main logic
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $query = $_GET['q'] ?? '';
    
    $response = searchLocalMusic($query);
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} else {
    http_response_code(405);
    echo json_encode([
        'status' => 'error',
        'message' => 'Nur GET-Anfragen erlaubt'
    ]);
}
?>
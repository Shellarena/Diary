<?php
// load_month_moods.php
// Returns mood information for each day of a month
require_once 'db_config.php';
header('Content-Type: application/json');

$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
$month = isset($_GET['month']) ? intval($_GET['month']) : date('n');

// Format: YYYY-MM
$monthStr = sprintf('%04d-%02d', $year, $month);

// Use PDO like in save_entry.php
$sql = "SELECT entry_date, mood FROM diary_entries WHERE entry_date LIKE CONCAT(:monthStr, '%')";
$stmt = $pdo->prepare($sql);
$stmt->execute(['monthStr' => $monthStr]);
$moods = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $moods[$row['entry_date']] = $row['mood'] !== null ? intval($row['mood']) : null;
}
echo json_encode(['success' => true, 'moods' => $moods]);

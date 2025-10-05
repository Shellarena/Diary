<?php
// setup_db.php - Create database and tables
require_once 'db_config.php';

try {
    // Create database if it doesn't exist
    $pdo->exec("CREATE DATABASE IF NOT EXISTS diary_db");
    $pdo->exec("USE diary_db");

    // Create table for diary entries
    $sql = "CREATE TABLE IF NOT EXISTS diary_entries (
        id INT AUTO_INCREMENT PRIMARY KEY,
        entry_date DATE NOT NULL UNIQUE,
        content MEDIUMTEXT,
        mood TINYINT UNSIGNED DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    $pdo->exec($sql);

    // Add 'mood' column if missing
    $columns = $pdo->query("SHOW COLUMNS FROM diary_entries LIKE 'mood'")->fetchAll();
    if (count($columns) === 0) {
        $pdo->exec("ALTER TABLE diary_entries ADD COLUMN mood TINYINT UNSIGNED DEFAULT 2");
    }

    // Add 'music_title' column if missing
    $music_columns = $pdo->query("SHOW COLUMNS FROM diary_entries LIKE 'music_title'")->fetchAll();
    if (count($music_columns) === 0) {
        $pdo->exec("ALTER TABLE diary_entries ADD COLUMN music_title TEXT");
    }

    // Add columns for complete music data
    $music_api_columns = $pdo->query("SHOW COLUMNS FROM diary_entries LIKE 'music_api'")->fetchAll();
    if (count($music_api_columns) === 0) {
        $pdo->exec("ALTER TABLE diary_entries ADD COLUMN music_api VARCHAR(20)");
    }

    $music_id_columns = $pdo->query("SHOW COLUMNS FROM diary_entries LIKE 'music_id'")->fetchAll();
    if (count($music_id_columns) === 0) {
        $pdo->exec("ALTER TABLE diary_entries ADD COLUMN music_id TEXT");
    }

    $music_artist_columns = $pdo->query("SHOW COLUMNS FROM diary_entries LIKE 'music_artist'")->fetchAll();
    if (count($music_artist_columns) === 0) {
        $pdo->exec("ALTER TABLE diary_entries ADD COLUMN music_artist TEXT");
    }

    $music_url_columns = $pdo->query("SHOW COLUMNS FROM diary_entries LIKE 'music_url'")->fetchAll();
    if (count($music_url_columns) === 0) {
        $pdo->exec("ALTER TABLE diary_entries ADD COLUMN music_url TEXT");
    }

    // Create table for app data/settings
    $sql_app_data = "CREATE TABLE IF NOT EXISTS app_data (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(100) NOT NULL UNIQUE,
        setting_value TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    $pdo->exec($sql_app_data);

    // Success - no output needed in include context
    return true;
} catch (PDOException $e) {
    error_log("Database setup error: " . $e->getMessage());
    return false;
}
?>

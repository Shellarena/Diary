<?php
// db_config.php - Database configuration
$host = getenv('DB_HOST') ?: 'db'; // 'db' for Docker, 'localhost' for local development
$dbname = getenv('DB_NAME') ?: 'diary_db';
$username = getenv('DB_USER') ?: 'diary_user'; // Change this according to your MySQL configuration
$password = getenv('DB_PASSWORD') ?: 'diary_password'; // Change this according to your MySQL configuration

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>

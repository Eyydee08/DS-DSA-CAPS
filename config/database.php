<?php
$host = '127.0.0.1';
$database = 'website_archive';
$username = 'root';
$password = '';

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$database;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $exception) {
    http_response_code(500);
    exit(
        'Could not connect to the archive database. Start MySQL in XAMPP, ' .
        'import schema.sql in phpMyAdmin, and check the settings in config/database.php. ' .
        'Database message: ' . htmlspecialchars($exception->getMessage(), ENT_QUOTES, 'UTF-8')
    );
}

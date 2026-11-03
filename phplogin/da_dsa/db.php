<?php
// Database connection settings for the default XAMPP MySQL installation.
$host = '127.0.0.1';
$database = 'website_archive';
$username = 'root';
$password = '';

// Connect PHP to MySQL with PDO so database errors are reported clearly
// and user input can be safely passed through prepared statements.
try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$database;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $exception) {
    http_response_code(500);
    exit(
        'Could not connect to the archive database. Start MySQL in XAMPP, ' .
        'import database.sql in phpMyAdmin, and check the settings in db.php. ' .
        'Database message: ' . htmlspecialchars($exception->getMessage(), ENT_QUOTES, 'UTF-8')
    );
}

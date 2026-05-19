<?php
date_default_timezone_set('Asia/Kathmandu');

$host = 'localhost';
$database   = 'foodbyte';
$username = 'root';
$password = '';

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES => true,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
];

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$database",
        $username,
        $password,
        $options
    );
    $pdo->exec("SET time_zone = '+05:45'");
} catch (PDOException $e) {
    die('Database connection failed.');
}
?>
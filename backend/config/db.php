<?php
$host = 'localhost';
$user = 'root';
$pass = '';
$dbname = 'pet_shelter_db';

// Ensure session is started with loose cookie settings for local dev
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '', 
        'secure' => false,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

try {
    $dsn = "mysql:host=$host;charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname`");
    $pdo->exec("USE `$dbname`");
} catch (PDOException $e) {
    http_response_code(500);
    die(json_encode(["error" => "Database connection failed: " . $e->getMessage()]));
}

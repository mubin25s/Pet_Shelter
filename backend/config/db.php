<?php
$host = 'localhost';
$user = 'root';
$pass = '';
$dbname = 'pet_shelter_db';

// Ensure session is started with loose cookie settings for local dev
if (session_status() === PHP_SESSION_NONE) {
    try {
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '', 
            'secure' => false,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
        session_start();
    } catch (Exception $e) {
        // Session might have already started or headers sent, ignore to prevent crash
    }
}

try {
    $dsn = "mysql:host=$host;charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC); // Good practice
    
    // Check if database exists by trying to select it or create it
    // Note: Creating DB inside common connect script is risky for production but fine for this dev setup
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname`");
    $pdo->exec("USE `$dbname`");
    
} catch (PDOException $e) {
    // If the request is an AJAX/API request, return JSON
    if ((isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) || 
        (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false)) {
            
        header('Content-Type: application/json');
        http_response_code(500);
        die(json_encode([
            "success" => false, 
            "error" => "Database connection failed: " . $e->getMessage(),
            "code" => "DB_CONNECTION_ERROR"
        ]));
    } else {
        // Plain text for direct access
        die("Database connection failed: " . $e->getMessage());
    }
}

<?php
$host = 'db.gviwyujcdrzcsnvifkzz.supabase.co'; 
$port = '5432'; 
$dbname = 'postgres'; // Supabase default
$user = 'postgres';
$pass = 'proportial duck'; 

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
    // PGSQL Connection
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
    $pdo = new PDO($dsn, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    // Postgres doesn't need "USE db", it connects directly to the db name in DSN.
    // Also removed CREATE DATABASE as Supabase manages that.
    
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

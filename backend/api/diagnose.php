<?php
// Simple Diagnostic Script
header("Access-Control-Allow-Origin: *");
header('Content-Type: application/json');

$results = [
    'php_version' => phpversion(),
    'session_status' => session_status(),
    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
    'db_connection' => false,
    'db_error' => null,
    'tables' => []
];

// 1. Test Database
require_once '../config/db.php'; // This will attempt connection

if (isset($pdo)) {
    $results['db_connection'] = true;
    try {
        $stmt = $pdo->query("SHOW TABLES");
        $results['tables'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        $results['db_error'] = "Connected but failed to list tables: " . $e->getMessage();
    }
} else {
    $results['db_error'] = "PDO variable not set. Connection likely failed in db.php";
}

echo json_encode($results, JSON_PRETTY_PRINT);
?>

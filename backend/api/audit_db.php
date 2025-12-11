<?php
header('Content-Type: text/plain');
require_once '../config/db.php';

try {
    $userCount = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $petCount = $pdo->query("SELECT COUNT(*) FROM pets")->fetchColumn();
    
    echo "DB STATUS: OK\n";
    echo "Users: $userCount\n";
    echo "Pets: $petCount\n";
    
    if ($userCount == 0) echo "WARNING: No users found. Login impossible.\n";
    if ($petCount == 0) echo "WARNING: No pets found.\n";
    
} catch (Exception $e) {
    echo "DB ERROR: " . $e->getMessage();
}
?>

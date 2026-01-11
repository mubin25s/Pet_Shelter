<?php
require_once '../config/db.php';
header('Content-Type: text/plain');

echo "--- Supabase Connection Diagnostic ---\n";
echo "Host: " . $host . "\n";
echo "DB Name: " . $dbname . "\n";
echo "User: " . $user . "\n";

try {
    $stmt = $pdo->query("SELECT CURRENT_TIMESTAMP");
    $result = $stmt->fetch();
    echo "\n✅ SUCCESS: Connected to Supabase!\n";
    echo "Current Server Time: " . $result[0] . "\n";
    
    // Check tables
    $tables = $pdo->query("SELECT tablename FROM pg_catalog.pg_tables WHERE schemaname = 'public'")->fetchAll(PDO::FETCH_COLUMN);
    echo "\nTables found: " . implode(", ", $tables) . "\n";
    
    if (empty($tables)) {
        echo "⚠️ WARNING: No tables found. Did you run the SQL script in Supabase SQL Editor?\n";
    }

} catch (PDOException $e) {
    echo "\n❌ FAILURE: Could not connect to Supabase.\n";
    echo "Error Error: " . $e->getMessage() . "\n";
    echo "\nChecklist:\n";
    echo "1. Is the password in backend/config/db.php correct?\n";
    echo "2. Is the host correct?\n";
    echo "3. Is your IP allowed in Supabase? (Check Project Settings -> API -> CIDR/CORS)\n";
}

<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once 'config/db.php';

echo "Applying Schema Updates...\n";

try {
    // 1. Users Age
    try {
        echo "Adding 'age' to users... ";
        $pdo->exec("ALTER TABLE users ADD COLUMN age INT");
        echo "Done.\n";
    } catch (PDOException $e) {
        echo "Error (likely exists): " . $e->getMessage() . "\n";
    }

    // 2. Users Gender
    try {
        echo "Adding 'gender' to users... ";
        $pdo->exec("ALTER TABLE users ADD COLUMN gender VARCHAR(20)");
        echo "Done.\n";
    } catch (PDOException $e) {
        echo "Error (likely exists): " . $e->getMessage() . "\n";
    }

    // 3. Fix Role
    try {
        echo "Modifying 'role' column... ";
        $pdo->exec("ALTER TABLE users MODIFY COLUMN role VARCHAR(50) DEFAULT 'user'");
        echo "Done.\n";
    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }

    // 4. Expenses columns
    try {
        echo "Adding 'status' to expenses... ";
        $pdo->exec("ALTER TABLE expenses ADD COLUMN status VARCHAR(20) DEFAULT 'approved'");
        echo "Done.\n";
    } catch (PDOException $e) { echo "Skip: " . $e->getMessage() . "\n"; }
    
    try {
        echo "Adding 'requested_by' to expenses... ";
        $pdo->exec("ALTER TABLE expenses ADD COLUMN requested_by INT");
        echo "Done.\n";
    } catch (PDOException $e) { echo "Skip: " . $e->getMessage() . "\n"; }

     // 5. Activity Logs
    try {
        echo "Creating activity_logs... ";
        $pdo->exec("CREATE TABLE IF NOT EXISTS activity_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT,
            action VARCHAR(50),
            details TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        echo "Done.\n";
    } catch (PDOException $e) { echo "Error: " . $e->getMessage() . "\n"; }

} catch (Exception $e) {
    echo "General Error: " . $e->getMessage();
}
?>

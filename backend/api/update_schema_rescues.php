<?php
require_once '../config/db.php';

try {
    // Add columns if they don't exist
    $columns = [
        "ADD COLUMN name VARCHAR(100) AFTER user_id",
        "ADD COLUMN type VARCHAR(50) AFTER name",
        "ADD COLUMN image VARCHAR(255) AFTER type"
    ];

    foreach ($columns as $col) {
        try {
            $pdo->exec("ALTER TABLE rescues $col");
            echo "Added column: $col <br>";
        } catch (PDOException $e) {
            // Ignore if column likely exists
            echo "Column might already exist or error: " . $e->getMessage() . "<br>";
        }
    }
    echo "Schema update finished.";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>

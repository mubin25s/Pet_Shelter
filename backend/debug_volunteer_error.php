<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/db.php';

echo "--- Table: users structure ---\n";
$stmt = $pdo->query("DESCRIBE users");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

echo "\n--- Attempting Dummy Insert ---\n";
$name = 'Debug Vol';
$email = 'debug_vol_' . time() . '@test.com';
$password = '123';
$role = 'volunteer';
$age = 25;
$gender = 'Male';

$sql = "INSERT INTO users (name, email, password, role, age, gender) VALUES (?, ?, ?, ?, ?, ?)";
$stmt = $pdo->prepare($sql);

if ($stmt->execute([$name, $email, $password, $role, $age, $gender])) {
    echo "SUCCESS: Inserted user ID: " . $pdo->lastInsertId();
    // Clean up
    $pdo->exec("DELETE FROM users WHERE id = " . $pdo->lastInsertId());
} else {
    echo "FAILURE: Could not insert.\n";
    print_r($stmt->errorInfo());
}
?>

<?php
// backend/api/test_db.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h3>Database Connection Test</h3>";

$host = 'localhost';
$user = 'root';
$pass = '';
$dbname = 'pet_shelter_db';

echo "<p>Attempting to connect to <strong>$dbname</strong> on <strong>$host</strong> with user <strong>$user</strong>...</p>";

try {
    $dsn = "mysql:host=$host;charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "<p style='color:green'>✅ Connection to MySQL Server success!</p>";
    
    // Check if database exists
    $stmt = $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '$dbname'");
    if ($stmt->fetchColumn()) {
        echo "<p style='color:green'>✅ Database '$dbname' exists.</p>";
    } else {
        echo "<p style='color:orange'>⚠️ Database '$dbname' does NOT exist. Attempting creation...</p>";
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname`");
        echo "<p style='color:green'>✅ Database created.</p>";
    }

    $pdo->exec("USE `$dbname`");
    echo "<p style='color:green'>✅ Selected database '$dbname'.</p>";

    // List tables
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "<p>Tables found: " . implode(", ", $tables) . "</p>";

} catch (PDOException $e) {
    echo "<p style='color:red'>❌ Connection Failed: " . $e->getMessage() . "</p>";
    echo "<hr>";
    echo "Debugging Tips:<br>";
    echo "1. Is XAMPP MySQL running? (Green in Control Panel)<br>";
    echo "2. Does your root user have a password? (Default is empty)<br>";
    echo "3. Is the port 3306? (Default)<br>";
}
?>

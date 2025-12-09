<?php
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '', // specific domain can cause issues on localhost
    'secure' => false,
    'httponly' => true,
    'samesite' => 'Lax'
]);
session_start();
$origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
header("Access-Control-Allow-Origin: $origin");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

error_reporting(E_ALL);
ini_set('display_errors', 0);

// Buffer output to catch unnecessary warnings
ob_start();

require_once '../config/db.php';

// Clear the buffer before sending fresh JSON
ob_clean();

$action = $_GET['action'] ?? '';

if ($action == 'register' && $_SERVER['REQUEST_METHOD'] == 'POST') {
    // Handling new user registration
    $data = json_decode(file_get_contents("php://input"), true);
    $name = $data['name'];
    $email = $data['email'];
    $password = password_hash($data['password'], PASSWORD_BCRYPT);
    
    try {
        $stmt = $pdo->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)");
        $stmt->execute([$name, $email, $password]);
        echo json_encode(["success" => true]);
    } catch (Exception $e) {
        echo json_encode(["success" => false, "error" => $e->getMessage()]);
    }
}
elseif ($action == 'login' && $_SERVER['REQUEST_METHOD'] == 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    $email = $data['email'];
    $password = $data['password'];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user'] = [
            'id' => $user['id'],
            'name' => $user['name'],
            'role' => $user['role']
        ];
        echo json_encode(["success" => true, "role" => $user['role']]);
    } else {
        echo json_encode(["success" => false, "error" => "Invalid credentials"]);
    }
}
elseif ($action == 'check_session') {
    if (isset($_SESSION['user'])) {
        echo json_encode(["loggedIn" => true, "user" => $_SESSION['user']]);
    } else {
        echo json_encode(["loggedIn" => false]);
    }
}
elseif ($action == 'logout') {
    session_destroy();
    echo json_encode(["success" => true]);
}
else {
    echo json_encode(["error" => "Invalid action"]);
}

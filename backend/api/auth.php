<?php
session_start();
error_reporting(0);
ini_set('display_errors', 0);
require_once '../config/db.php';
header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

if ($action == 'register' && $_SERVER['REQUEST_METHOD'] == 'POST') {
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

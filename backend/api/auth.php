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
$allowed_origins = [
    'http://localhost',
    'http://localhost:3000',
    'http://localhost:5500',
    'http://127.0.0.1:5500',
    'http://127.0.0.1:8080' // Common VS Code Live Server ports
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowed_origins)) {
    header("Access-Control-Allow-Origin: $origin");
} else {
    // Fallback for non-browser or other tools, but be careful
    header("Access-Control-Allow-Origin: *"); 
}

header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

error_reporting(E_ALL);
ini_set('display_errors', 0);

// Buffer output to catch unnecessary warnings
ob_start();
require_once '../config/db.php';
// Define logActivity if not included (Auth doesn't include misc.php, so we duplicate or move. 
// Ideally move to db.php. For now, I will add it here safely check exists)
if (!function_exists('logActivity')) {
    function logActivity($pdo, $user_id, $action, $details = '') {
        try {
            $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, details) VALUES (?, ?, ?)");
            $stmt->execute([$user_id, $action, $details]);
        } catch (Exception $e) {}
    }
}
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

    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            echo json_encode(["success" => false, "error" => "User not registered", "code" => "USER_NOT_FOUND"]);
        } elseif (password_verify($password, $user['password'])) {
            $_SESSION['user'] = [
                'id' => $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'role' => $user['role']
            ];
            logActivity($pdo, $user['id'], 'login', 'User logged in');
            echo json_encode(["success" => true, "role" => $user['role']]);
            http_response_code(200);
        } else {
            echo json_encode(["success" => false, "error" => "Incorrect password", "code" => "INVALID_PASSWORD"]);
        }
    } catch (Exception $e) {
        $msg = $e->getMessage();
        $code = "DB_ERROR";
        // Check for missing table error
        if (strpos($msg, "Base table or view not found") !== false) {
             $msg = "Database tables are missing. Please run the provided SQL script in your Supabase SQL Editor.";
             $code = "MISSING_TABLES";
        }
        http_response_code(500);
        echo json_encode(["success" => false, "error" => $msg, "code" => $code]);
    }
}
elseif ($action == 'check_session') {
    if (isset($_SESSION['user'])) {
        http_response_code(200);
        echo json_encode(["loggedIn" => true, "user" => $_SESSION['user']]);
    } else {
        echo json_encode(["loggedIn" => false]);
    }
}
elseif ($action == 'logout') {
    if (isset($_SESSION['user'])) {
        logActivity($pdo, $_SESSION['user']['id'], 'logout', 'User logged out');
        session_destroy();
    }
    echo json_encode(["success" => true]);
}
else {
    echo json_encode(["error" => "Invalid action"]);
}

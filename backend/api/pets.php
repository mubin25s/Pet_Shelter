<?php
session_start();
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS");
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

ob_start();
require_once '../config/db.php';
ob_clean();

$method = $_SERVER['REQUEST_METHOD'];

if ($method == 'GET') {
    // List Pets
    $stmt = $pdo->query("SELECT * FROM pets WHERE status = 'available' ORDER BY created_at DESC");
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
}
elseif ($method == 'POST') {
    // Add Pet (With Image Upload)
    if (!isset($_SESSION['user'])) {
        echo json_encode(["error" => "Unauthorized"]);
        exit;
    }

    $name = $_POST['name'];
    $type = $_POST['type'];
    $history = $_POST['history'];
    $health = $_POST['health_status'];
    $vaccine = $_POST['vaccine_status'];
    $food = $_POST['food_habit'];
    $desc = $_POST['description'];
    $user_id = $_SESSION['user']['id'];

    // Image Upload
    $target_dir = "../uploads/"; // Relative to api folder -> backend/uploads
    if (!file_exists($target_dir)) mkdir($target_dir, 0777, true);
    
    $filename = uniqid() . "_" . basename($_FILES["image"]["name"]);
    $target_file = $target_dir . $filename;
    
    if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
        // Frontend accesses via ../backend/uploads/filename
        $db_image_path = "uploads/" . $filename;
        $stmt = $pdo->prepare("INSERT INTO pets (name, type, image, history, health_status, vaccine_status, food_habit, description, added_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$name, $type, $db_image_path, $history, $health, $vaccine, $food, $desc, $user_id]);
        echo json_encode(["success" => true]);
    } else {
        echo json_encode(["success" => false, "error" => "Image upload failed"]);
    }
}
elseif ($method == 'DELETE') {
    // Admin Delete
    if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'admin') {
        echo json_encode(["error" => "Unauthorized"]);
        exit;
    }
    $id = $_GET['id'];
    $pdo->prepare("DELETE FROM pets WHERE id = ?")->execute([$id]);
    echo json_encode(["success" => true]);
}
?>

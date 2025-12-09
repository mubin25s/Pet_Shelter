<?php
session_start();
$origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
header("Access-Control-Allow-Origin: $origin");
header("Access-Control-Allow-Credentials: true");
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
    // Show all the available pets
    $stmt = $pdo->query("SELECT * FROM pets WHERE status = 'available' ORDER BY created_at DESC");
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
}
elseif ($method == 'POST') {
    // Add a new pet or Update an existing one
    if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
         // Allow regular users to POST new pets? Original code allowed any logged in user to POST.
         // But for UPDATE, only admin. 
         // Let's stick to original restriction for add (any user?) -> check logic
         // Original: if (!isset($_SESSION['user'])) ...
         
         if (!isset($_SESSION['user'])) {
             echo json_encode(["error" => "Unauthorized"]);
             exit;
         }
    }

    $action = $_GET['action'] ?? null;

    if ($action === 'update') {
        // UPDATE PET
        if ($_SESSION['user']['role'] !== 'admin') {
            echo json_encode(["error" => "Unauthorized: Admins only"]);
            exit;
        }

        $id = $_POST['id'];
        $name = $_POST['name'];
        $type = $_POST['type'];
        $health = $_POST['health_status'];
        $vaccine = $_POST['vaccine_status'];
        $desc = $_POST['description'];
        
        // Optional fields if you have them in DB
        // $history = $_POST['history'] ?? '';
        // $food = $_POST['food_habit'] ?? '';

        // Check if image is being updated
        $imageUpdateSql = "";
        $params = [$name, $type, $health, $vaccine, $desc];

        if (isset($_FILES['image']) && $_FILES['image']['size'] > 0) {
            $target_dir = "../uploads/";
            if (!file_exists($target_dir)) mkdir($target_dir, 0777, true);
            $filename = uniqid() . "_" . basename($_FILES["image"]["name"]);
            $target_file = $target_dir . $filename;
            if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
                $db_image_path = "uploads/" . $filename;
                $imageUpdateSql = ", image = ?";
                $params[] = $db_image_path;
            }
        }

        $params[] = $id; // For WHERE clause

        $sql = "UPDATE pets SET name = ?, type = ?, health_status = ?, vaccine_status = ?, description = ? $imageUpdateSql WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        
        if ($stmt->execute($params)) {
            echo json_encode(["success" => true]);
        } else {
            echo json_encode(["success" => false, "error" => "Database Update Failed"]);
        }

    } else {
        // ADD NEW PET (Original Logic with slight cleanup)
        $name = $_POST['name'];
        $type = $_POST['type'];
        $history = $_POST['history'];
        $health = $_POST['health_status'];
        $vaccine = $_POST['vaccine_status'];
        $food = $_POST['food_habit'];
        $desc = $_POST['description'];
        $user_id = $_SESSION['user']['id'];
    
        // Handle the image upload
        $target_dir = "../uploads/"; // Moving it to backend/uploads
        if (!file_exists($target_dir)) mkdir($target_dir, 0777, true);
        
        $filename = uniqid() . "_" . basename($_FILES["image"]["name"]);
        $target_file = $target_dir . $filename;
        
        if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
            // Frontend finds it here: ../backend/uploads/filename
            $db_image_path = "uploads/" . $filename;
            $stmt = $pdo->prepare("INSERT INTO pets (name, type, image, history, health_status, vaccine_status, food_habit, description, added_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $type, $db_image_path, $history, $health, $vaccine, $food, $desc, $user_id]);
            echo json_encode(["success" => true]);
        } else {
            echo json_encode(["success" => false, "error" => "Image upload failed"]);
        }
    }
}
elseif ($method == 'DELETE') {
    // Admins can remove pets
    if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'admin') {
        echo json_encode(["error" => "Unauthorized"]);
        exit;
    }
    $id = $_GET['id'];
    $pdo->prepare("DELETE FROM pets WHERE id = ?")->execute([$id]);
    echo json_encode(["success" => true]);
}
?>

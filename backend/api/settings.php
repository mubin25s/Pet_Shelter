<?php
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

ob_start();
require_once '../config/db.php';
ob_clean();

// Ensure settings table exists
$pdo->exec("CREATE TABLE IF NOT EXISTS settings (
    id INT PRIMARY KEY DEFAULT 1,
    contact_email VARCHAR(255),
    contact_phone VARCHAR(50),
    shelter_location TEXT,
    about_text TEXT,
    founder_name VARCHAR(255),
    founder_image VARCHAR(255)
)");

// Insert default row if not exists
$stmt = $pdo->query("SELECT COUNT(*) FROM settings");
if ($stmt->fetchColumn() == 0) {
    $pdo->exec("INSERT INTO settings (id, contact_email, contact_phone, shelter_location, about_text, founder_name, founder_image) 
                VALUES (1, 'petshelter@gmail.com', '+088 0130 2910017', 'Ashulia, Savar,Dhaka', 'Welcome to Paws Shelter.', 'K.M. Fathum Mubin Sachcha', 'images/founder.png')");
}

if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    $stmt = $pdo->query("SELECT * FROM settings WHERE id = 1");
    echo json_encode($stmt->fetch(PDO::FETCH_ASSOC));
}
elseif ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'admin') {
        echo json_encode(["success" => false, "error" => "Unauthorized"]);
        exit;
    }

    // Check if content type is JSON
    if (strpos($_SERVER["CONTENT_TYPE"] ?? '', 'application/json') !== false) {
        $data = json_decode(file_get_contents("php://input"), true);
    } else {
        $data = $_POST;
    }

    $sql = "UPDATE settings SET contact_email = ?, contact_phone = ?, shelter_location = ?, about_text = ?, founder_name = ?";
    $params = [
        $data['contact_email'] ?? '',
        $data['contact_phone'] ?? '',
        $data['shelter_location'] ?? '',
        $data['about_text'] ?? '',
        $data['founder_name'] ?? ''
    ];

    $sql .= " WHERE id = 1";
    
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute($params);

    echo json_encode(["success" => $result]);
}
?>

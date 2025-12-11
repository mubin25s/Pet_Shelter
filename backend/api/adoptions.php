<?php
session_start();
$origin = $_SERVER['HTTP_ORIGIN'] ?? 'http://localhost';
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

$action = $_GET['action'] ?? '';

if ($action == 'submit' && $_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!isset($_SESSION['user'])) exit(json_encode(["error" => "Unauthorized"]));
    
    $data = json_decode(file_get_contents("php://input"), true);
    $stmt = $pdo->prepare("INSERT INTO adoptions (user_id, pet_id, experience, other_pets, financial_status) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([
        $_SESSION['user']['id'], 
        $data['pet_id'], 
        $data['experience'], 
        $data['other_pets'], 
        $data['financial_status']
    ]);
    echo json_encode(["success" => true]);
}
elseif ($action == 'list_pending') {
    if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'admin') exit;
    
    $sql = "SELECT a.*, u.name as user_name, u.email, p.name as pet_name FROM adoptions a JOIN users u ON a.user_id = u.id JOIN pets p ON a.pet_id = p.id WHERE a.adoption_status = 'pending'";
    echo json_encode($pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC));
}
elseif ($action == 'approve') {
    if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'admin') exit;
    $data = json_decode(file_get_contents("php://input"), true);
    
    $id = $data['id'];
    $pet_id = $data['pet_id'];

    $pdo->prepare("UPDATE adoptions SET adoption_status = 'approved' WHERE id = ?")->execute([$id]);
    $pdo->prepare("UPDATE pets SET status = 'adopted' WHERE id = ?")->execute([$pet_id]);
    // Reject others
    $pdo->prepare("UPDATE adoptions SET adoption_status = 'rejected' WHERE pet_id = ? AND id != ?")->execute([$pet_id, $id]);
    
    echo json_encode(["success" => true]);
}
elseif ($action == 'reject') {
    if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'admin') exit;
    $data = json_decode(file_get_contents("php://input"), true);
    $pdo->prepare("UPDATE adoptions SET adoption_status = 'rejected' WHERE id = ?")->execute([$data['id']]);
    echo json_encode(["success" => true]);
}
?>

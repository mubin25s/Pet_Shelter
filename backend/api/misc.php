<?php
session_start();
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

ob_start();
require_once '../config/db.php';
ob_clean();

$type = $_GET['type'] ?? '';

$type = $_GET['type'] ?? '';

if ($type == 'rescue') {
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $data = json_decode(file_get_contents("php://input"), true);
        $stmt = $pdo->prepare("INSERT INTO rescues (user_id, location, description, condition_desc) VALUES (?, ?, ?, ?)");
        $stmt->execute([$_SESSION['user']['id'], $data['location'], $data['description'], $data['condition']]);
        echo json_encode(["success" => true]);
    } 
    elseif ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_SESSION['user']) && $_SESSION['user']['role'] == 'admin') {
        echo json_encode($pdo->query("SELECT * FROM rescues ORDER BY report_date DESC")->fetchAll(PDO::FETCH_ASSOC));
    }
}
elseif ($type == 'donation') {
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $data = json_decode(file_get_contents("php://input"), true);
        $stmt = $pdo->prepare("INSERT INTO donations (user_id, amount) VALUES (?, ?)");
        $stmt->execute([$_SESSION['user']['id'], $data['amount']]);
        echo json_encode(["success" => true]);
    }
    elseif ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_SESSION['user']) && $_SESSION['user']['role'] == 'admin') {
        // Banking Data
        $donations = $pdo->query("SELECT SUM(amount) FROM donations")->fetchColumn() ?: 0;
        $expenses = $pdo->query("SELECT SUM(amount) FROM expenses")->fetchColumn() ?: 0;
        $recent_donations = $pdo->query("SELECT d.*, u.name FROM donations d JOIN users u ON d.user_id = u.id ORDER BY d.donation_date DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
        $recent_expenses = $pdo->query("SELECT * FROM expenses ORDER BY expense_date DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            "balance" => $donations - $expenses,
            "total_donations" => $donations,
            "total_expenses" => $expenses,
            "recent_donations" => $recent_donations,
            "recent_expenses" => $recent_expenses
        ]);
    }
}
elseif ($type == 'expense' && $_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'admin') {
        echo json_encode(["success" => false, "error" => "Unauthorized"]);
        exit;
    }
    
    $data = json_decode(file_get_contents("php://input"), true);
    $amount = $data['amount'];
    
    // Calculate current balance
    $donations = $pdo->query("SELECT SUM(amount) FROM donations")->fetchColumn() ?: 0;
    $expenses = $pdo->query("SELECT SUM(amount) FROM expenses")->fetchColumn() ?: 0;
    $balance = $donations - $expenses;
    
    if ($balance < $amount) {
        echo json_encode(["success" => false, "error" => "Insufficient Fund"]);
    } else {
        $pdo->prepare("INSERT INTO expenses (description, amount) VALUES (?, ?)")->execute([$data['description'], $amount]);
        echo json_encode(["success" => true, "message" => "Your expense is successful"]);
    }
}
?>

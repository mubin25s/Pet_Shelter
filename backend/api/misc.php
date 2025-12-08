<?php
session_start();
require_once '../config/db.php';
header('Content-Type: application/json');

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
    if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'admin') exit;
    $data = json_decode(file_get_contents("php://input"), true);
    $pdo->prepare("INSERT INTO expenses (description, amount) VALUES (?, ?)")->execute([$data['description'], $data['amount']]);
    echo json_encode(["success" => true]);
}
?>

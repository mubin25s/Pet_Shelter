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

$type = $_GET['type'] ?? '';

$type = $_GET['type'] ?? '';

if ($type == 'rescue') {
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $action = $_GET['action'] ?? 'report';
        
        if ($action == 'report') {
            // Reporting a rescue
            $name = $_POST['name'] ?? 'Unknown';
            $type = $_POST['type'] ?? 'Unknown';
            $location = $_POST['location'];
            $description = $_POST['description'];
            $image = '';

            // Handle Image Upload
            if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
                $uploadDir = '../uploads/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                
                $fileName = time() . '_' . basename($_FILES['image']['name']);
                $uploadFile = $uploadDir . $fileName;
                
                if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadFile)) {
                    $image = 'uploads/' . $fileName;
                }
            }

            $stmt = $pdo->prepare("INSERT INTO rescues (user_id, name, type, image, location, description, condition_desc) VALUES (?, ?, ?, ?, ?, ?, ?)");
           
            $stmt->execute([$_SESSION['user']['id'], $name, $type, $image, $location, $description, 'See description']);
            echo json_encode(["success" => true]);
        }
        elseif ($action == 'approve') {
            // Admin approving a rescue
            if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'admin') {
                echo json_encode(["error" => "Unauthorized"]);
                exit;
            }
            
            $id = $_POST['id'];
            $rescue = $pdo->query("SELECT * FROM rescues WHERE id = $id")->fetch(PDO::FETCH_ASSOC);
            
            if ($rescue) {
                // Insert into PETS
                $stmt = $pdo->prepare("INSERT INTO pets (name, type, image, description, health_status, status, added_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
                // Default health to 'red' (Critical/Rescued) or 'yellow'. Let's say 'yellow'.
                $stmt->execute([$rescue['name'], $rescue['type'], $rescue['image'], $rescue['description'], 'yellow', 'available', $_SESSION['user']['id']]);
                
                // Update Rescue Status
                $pdo->exec("UPDATE rescues SET status = 'rescued' WHERE id = $id");
                
                echo json_encode(["success" => true, "message" => "Rescue approved and pet profile created!"]);
            } else {
                echo json_encode(["success" => false, "error" => "Rescue not found"]);
            }
        }
        elseif ($action == 'reject') {
             if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'admin') exit;
             $id = $_POST['id'];
             $pdo->exec("DELETE FROM rescues WHERE id = $id"); // Simply delete for rejection
             echo json_encode(["success" => true, "message" => "Rescue report rejected."]);
        }
    } 
    elseif ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_SESSION['user']) && $_SESSION['user']['role'] == 'admin') {
        // Only show 'reported' (pending) rescues
        echo json_encode($pdo->query("SELECT * FROM rescues WHERE status = 'reported' ORDER BY report_date DESC")->fetchAll(PDO::FETCH_ASSOC));
    }
}
elseif ($type == 'payment_methods') {
    // Lazy Schema: Ensure table exists
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS user_payment_methods (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            type VARCHAR(50),
            provider VARCHAR(50),
            display_info VARCHAR(100),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
    } catch (Exception $e) {}

    if ($_SERVER['REQUEST_METHOD'] == 'GET') {
        if (!isset($_SESSION['user'])) exit;
        $methods = $pdo->query("SELECT * FROM user_payment_methods WHERE user_id = " . $_SESSION['user']['id'] . " ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($methods);
    }
    elseif ($_SERVER['REQUEST_METHOD'] == 'POST') {
        if (!isset($_SESSION['user'])) exit;
        $data = json_decode(file_get_contents("php://input"), true);
        
        $stmt = $pdo->prepare("INSERT INTO user_payment_methods (user_id, type, provider, display_info) VALUES (?, ?, ?, ?)");
        $stmt->execute([$_SESSION['user']['id'], $data['type'], $data['provider'], $data['display_info']]);
        
        echo json_encode(["success" => true, "id" => $pdo->lastInsertId()]);
    }
    elseif ($_SERVER['REQUEST_METHOD'] == 'DELETE') {
         if (!isset($_SESSION['user'])) exit;
         $id = $_GET['id'];
         $pdo->prepare("DELETE FROM user_payment_methods WHERE id = ? AND user_id = ?")->execute([$id, $_SESSION['user']['id']]);
         echo json_encode(["success" => true]);
    }
}
elseif ($type == 'donation') {
    // Lazy Schema Migration: Ensure payment_method column exists
    try {
        $check = $pdo->query("SHOW COLUMNS FROM donations LIKE 'payment_method'");
        if ($check->rowCount() == 0) {
            $pdo->exec("ALTER TABLE donations ADD COLUMN payment_method VARCHAR(50) DEFAULT 'Manual'");
        }
    } catch (Exception $e) { /* Ignore if already exists/error */ }

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $data = json_decode(file_get_contents("php://input"), true);
        $method = $data['payment_method'] ?? 'Manual';
        $stmt = $pdo->prepare("INSERT INTO donations (user_id, amount, payment_method) VALUES (?, ?, ?)");
        $stmt->execute([$_SESSION['user']['id'], $data['amount'], $method]);
        echo json_encode(["success" => true]);
    }
    elseif ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_SESSION['user']) && $_SESSION['user']['role'] == 'admin') {
        // Banking Data for the dashboard
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
    
    // Check if we have enough money
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

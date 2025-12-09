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
                $uploadDir = '../../uploads/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                
                $fileName = time() . '_' . basename($_FILES['image']['name']);
                $uploadFile = $uploadDir . $fileName;
                
                if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadFile)) {
                    $image = 'uploads/' . $fileName;
                }
            }

            $stmt = $pdo->prepare("INSERT INTO rescues (user_id, name, type, image, location, description, condition_desc) VALUES (?, ?, ?, ?, ?, ?, ?)");
            // parsing description vs condition from the frontend concatenated string is tricky if we don't separate them?
            // The frontend sends: description = desc + "\nCondition: " + cond.
            // Let's just use that whole thing as description for now, or split if needed. 
            // Actually, the DB has `condition_desc` column. The frontend is concatenating.
            // Let's improve frontend later? No, let's just save the concatenated to 'description' and maybe 'condition' as separate if passed.
            // Wait, previous code: $stmt->execute([$uid, $loc, $desc, $data['condition']]); 
            // My recent frontend update `formData.append('description', desc + "\nCondition: " + cond);` merged them.
            // And `condition` was NOT appended separately.
            // So `condition_desc` will be empty unless I fix frontend. 
            // It's okay, I'll put the "Condition" text into `condition_desc` roughly or just empty.
            // Actually, let's just stick to saving what we have.
            
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
elseif ($type == 'donation') {
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $data = json_decode(file_get_contents("php://input"), true);
        $stmt = $pdo->prepare("INSERT INTO donations (user_id, amount) VALUES (?, ?)");
        $stmt->execute([$_SESSION['user']['id'], $data['amount']]);
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

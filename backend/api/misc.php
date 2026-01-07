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

// --- Lazy Schema Updates Removed (Using Supabase SQL Schema) ---

function logActivity($pdo, $user_id, $action, $details = '') {
    try {
        $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, details) VALUES (?, ?, ?)");
        $stmt->execute([$user_id, $action, $details]);
    } catch (Exception $e) {}
}


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
            // Admin OR Volunteer approving a rescue
            if (!isset($_SESSION['user']) || ($_SESSION['user']['role'] != 'admin' && $_SESSION['user']['role'] != 'volunteer')) {
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
             if (!isset($_SESSION['user']) || ($_SESSION['user']['role'] != 'admin' && $_SESSION['user']['role'] != 'volunteer')) exit;
             $id = $_POST['id'];
             $pdo->exec("DELETE FROM rescues WHERE id = $id"); // Simply delete for rejection
             echo json_encode(["success" => true, "message" => "Rescue report rejected."]);
        }
    } 
    elseif ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_SESSION['user']) && ($_SESSION['user']['role'] == 'admin' || $_SESSION['user']['role'] == 'volunteer')) {
        // Only show 'reported' (pending) rescues
        echo json_encode($pdo->query("SELECT * FROM rescues WHERE status = 'reported' ORDER BY report_date DESC")->fetchAll(PDO::FETCH_ASSOC));
    }
}
elseif ($type == 'payment_methods') {
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
elseif ($type == 'create_volunteer') {
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'admin') {
            echo json_encode(["success" => false, "error" => "Unauthorized"]);
            exit;
        }

        $data = json_decode(file_get_contents("php://input"), true);
        $name = $data['name'];
        $email = $data['email'];
        $password = password_hash($data['password'], PASSWORD_BCRYPT);
        $age = $data['age'] ?? 0;
        $gender = $data['gender'] ?? 'Not Specified';

        // Check exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            echo json_encode(["success" => false, "error" => "Email already exists"]);
            exit;
        }

        $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, age, gender) VALUES (?, ?, ?, 'volunteer', ?, ?)");
        if ($stmt->execute([$name, $email, $password, $age, $gender])) {
            logActivity($pdo, $_SESSION['user']['id'], 'create_volunteer', "Created volunteer: $name ($email)");
            echo json_encode(["success" => true]);
        } else {
            echo json_encode(["success" => false, "error" => "Database error"]);
        }
    }
    elseif ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_SESSION['user']) && $_SESSION['user']['role'] == 'admin') {
        try {
            $stmt = $pdo->query("SELECT id, name, email, age, gender, created_at FROM users WHERE role = 'volunteer'");
            if ($stmt) {
                echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            } else {
                echo json_encode(["error" => "Database Query Failed: " . implode(" ", $pdo->errorInfo())]);
            }
        } catch (Exception $e) {
            echo json_encode(["error" => "Exception: " . $e->getMessage()]);
        }
    }
    elseif ($_SERVER['REQUEST_METHOD'] == 'GET') {
         // Handle unauthorized or missing session for GET
         if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'admin') {
             echo json_encode(["error" => "Unauthorized access to volunteers list"]);
         }
    }
}
elseif ($type == 'remove_volunteer') {
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'admin') {
            echo json_encode(["success" => false, "error" => "Unauthorized"]);
            exit;
        }
        $data = json_decode(file_get_contents("php://input"), true);
        $id = $data['id'];
        $reason = $data['reason'];
        
        // Get volunteer name for log
        $volName = $pdo->query("SELECT name FROM users WHERE id = $id")->fetchColumn();
        
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'volunteer'");
        if ($stmt->execute([$id])) {
            logActivity($pdo, $_SESSION['user']['id'], 'remove_volunteer', "Removed volunteer $volName. Reason: $reason");
            echo json_encode(["success" => true]);
        } else {
            echo json_encode(["success" => false, "error" => "Failed to delete"]);
        }
    }
}
elseif ($type == 'activity_logs') {
    if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_SESSION['user']) && $_SESSION['user']['role'] == 'admin') {
        $logs = $pdo->query("SELECT a.*, u.name as user_name, u.role as user_role FROM activity_logs a LEFT JOIN users u ON a.user_id = u.id ORDER BY a.created_at DESC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($logs);
    }
}

elseif ($type == 'donation') {
    } catch (Exception $e) { /* Ignore */ }

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
        $expenses = $pdo->query("SELECT SUM(amount) FROM expenses WHERE status = 'approved'")->fetchColumn() ?: 0;
        $recent_donations = $pdo->query("SELECT d.*, u.name FROM donations d JOIN users u ON d.user_id = u.id ORDER BY d.donation_date DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
        $recent_expenses = $pdo->query("SELECT e.*, u.name as user_name FROM expenses e LEFT JOIN users u ON e.requested_by = u.id WHERE e.status = 'approved' ORDER BY expense_date DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            "balance" => $donations - $expenses,
            "total_donations" => $donations,
            "total_expenses" => $expenses,
            "recent_donations" => $recent_donations,
            "recent_expenses" => $recent_expenses
        ]);
    }
}
elseif ($type == 'expense') {
    $user = $_SESSION['user'] ?? null;
    if (!$user) exit;

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $data = json_decode(file_get_contents("php://input"), true);
        $amount = $data['amount'];
        $desc = $data['description'];
        
        if ($user['role'] == 'admin') {
            // Admin: Immediate Approval Check
            $donations = $pdo->query("SELECT SUM(amount) FROM donations")->fetchColumn() ?: 0;
            $expenses = $pdo->query("SELECT SUM(amount) FROM expenses WHERE status = 'approved'")->fetchColumn() ?: 0;
            
            if (($donations - $expenses) < $amount) {
                echo json_encode(["success" => false, "error" => "Insufficient Funds"]);
            } else {
                $pdo->prepare("INSERT INTO expenses (description, amount, status, requested_by) VALUES (?, ?, 'approved', ?)")->execute([$desc, $amount, $user['id']]);
                logActivity($pdo, $user['id'], 'create_expense', "Added manual expense: $amount ($desc)");
                echo json_encode(["success" => true, "message" => "Expense recorded successfully"]);
            }
        } elseif ($user['role'] == 'volunteer') {
            // Volunteer: Request
            $pdo->prepare("INSERT INTO expenses (description, amount, status, requested_by) VALUES (?, ?, 'pending', ?)")->execute([$desc, $amount, $user['id']]);
            logActivity($pdo, $user['id'], 'request_expense', "Requested funds: $amount ($desc)");
            echo json_encode(["success" => true, "message" => "Expense request submitted for approval"]);
        }
    }
}
elseif ($type == 'expense_requests') {
    if (!isset($_SESSION['user'])) exit;
    $user = $_SESSION['user'];

    if ($_SERVER['REQUEST_METHOD'] == 'GET') {
        if ($user['role'] == 'admin') {
            // Show all pending
            $reqs = $pdo->query("SELECT e.*, u.name as requester_name FROM expenses e LEFT JOIN users u ON e.requested_by = u.id WHERE e.status = 'pending'")->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode($reqs);
        } else {
             // Show my pending
             $stmt = $pdo->prepare("SELECT * FROM expenses WHERE requested_by = ? AND status = 'pending'");
             $stmt->execute([$user['id']]);
             echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        }
    }
}
elseif ($type == 'expense_action' && $_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'admin') exit;
    
    $data = json_decode(file_get_contents("php://input"), true);
    $id = $data['id'];
    $action = $data['action']; // 'approve' or 'reject'
    
    if ($action == 'approve') {
        // Check funds first!
         $amount = $pdo->query("SELECT amount FROM expenses WHERE id = $id")->fetchColumn();
         $donations = $pdo->query("SELECT SUM(amount) FROM donations")->fetchColumn() ?: 0;
         $expenses = $pdo->query("SELECT SUM(amount) FROM expenses WHERE status = 'approved'")->fetchColumn() ?: 0;
         
         if (($donations - $expenses) < $amount) {
             echo json_encode(["success" => false, "error" => "Insufficient Funds to approve this request"]);
             exit;
         }
         
         $pdo->exec("UPDATE expenses SET status = 'approved' WHERE id = $id");
         logActivity($pdo, $_SESSION['user']['id'], 'approve_expense', "Approved expense ID: $id ($amount)");
         echo json_encode(["success" => true, "message" => "Expense Approved"]);
    } elseif ($action == 'reject') {
        $pdo->exec("DELETE FROM expenses WHERE id = $id"); // Or update status to 'rejected'
        logActivity($pdo, $_SESSION['user']['id'], 'reject_expense', "Rejected expense ID: $id");
        echo json_encode(["success" => true, "message" => "Expense Rejected"]);
    }
}
elseif ($type == 'public_volunteers') {
    // Public endpoint to list volunteers
    if ($_SERVER['REQUEST_METHOD'] == 'GET') {
        $vols = $pdo->query("SELECT name FROM users WHERE role = 'volunteer' ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($vols);
    }
}
?>

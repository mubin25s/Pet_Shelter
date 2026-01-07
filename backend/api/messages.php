<?php
require_once '../config/db.php';
// session_start(); // Handled in db.php
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
// Allow localhost on any port (VS Code Live Server can be random)
if (preg_match('/^http:\/\/localhost(:\d+)?$/', $origin) || preg_match('/^http:\/\/127\.0\.0\.1(:\d+)?$/', $origin)) {
    header("Access-Control-Allow-Origin: $origin");
    header("Access-Control-Allow-Credentials: true");
}
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Helper to send JSON response
function jsonResponse($success, $data = [], $error = null) {
    echo json_encode(['success' => $success, 'data' => $data, 'error' => $error]);
    exit;
}

if (!isset($_SESSION['user']['id'])) {
    jsonResponse(false, [], 'Unauthorized');
}

$current_user_id = $_SESSION['user']['id'];
$current_role = $_SESSION['user']['role'] ?? 'user';
$action = $_GET['action'] ?? '';

try {
    if ($action === 'get_targets') {
        // Who can I talk to?
        // User: Admin + Volunteers
        // Volunteer: Admin + Users
        // Admin: Everyone
        
        $targets = [];
        
        if ($current_role === 'user') {
            // Get admins and volunteers
            $stmt = $pdo->prepare("SELECT id, name, role FROM users WHERE role IN ('admin', 'volunteer')");
            $stmt->execute();
            $targets = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            // Admin/Volunteer can talk to anyone they have a conversation with, OR search for users?
            // For simplicity, let's return:
            // 1. Admins (always good to have line to other admins)
            // 2. Users who have messaged them OR who they have messaged.
            // But the requirement says "volunteer can massage admin and user".
            // Showing ALL users might be too much if there are thousands.
            // Let's list:
            // - All Admins/Volunteers
            // - Any User who has sent a message to the system (or to me?)
            
            // Allow searching or just list recent conversations + all admins/volunteers
            
            // Get all admins and volunteers
            $stmt = $pdo->prepare("SELECT id, name, role FROM users WHERE role IN ('admin', 'volunteer') AND id != ?");
            $stmt->execute([$current_user_id]);
            $staff = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get users I have chatted with
            $stmt = $pdo->prepare("
                SELECT DISTINCT u.id, u.name, u.role 
                FROM users u
                JOIN messages m ON (m.sender_id = u.id OR m.receiver_id = u.id)
                WHERE (m.sender_id = ? OR m.receiver_id = ?) AND u.role = 'user'
            ");
            $stmt->execute([$current_user_id, $current_user_id]);
            $chatted_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Check if we need to include all users? 
            // "volunteer can massage admin and user" suggests initiating too.
            // If they want to initiate to a random user, they might need a search. 
            // For now, let's just return all users if it's small, or maybe just the ones with chat history + a way to 'find' users?
            // Let's just return ALL users for now (assuming small scale) to ensure functionality.
            if ($current_role === 'admin' || $current_role === 'volunteer') {
                 $stmt = $pdo->prepare("SELECT id, name, role FROM users WHERE id != ?");
                 $stmt->execute([$current_user_id]);
                 $targets = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                 $targets = array_merge($staff, $chatted_users);
            }
        }
        
        // Remove duplicates just in case
        $unique_targets = [];
        foreach ($targets as $t) {
            $unique_targets[$t['id']] = $t;
        }
        
        jsonResponse(true, array_values($unique_targets));

    } elseif ($action === 'get_conversation') {
        $other_user_id = $_GET['user_id'] ?? 0;
        
        if (!$other_user_id) {
            jsonResponse(false, [], 'User ID required');
        }

        $stmt = $pdo->prepare("
            SELECT m.*, s.name as sender_name 
            FROM messages m 
            JOIN users s ON m.sender_id = s.id
            WHERE (m.sender_id = ? AND m.receiver_id = ?) 
               OR (m.sender_id = ? AND m.receiver_id = ?)
            ORDER BY m.created_at ASC
        ");
        $stmt->execute([$current_user_id, $other_user_id, $other_user_id, $current_user_id]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Mark as read
        $updateStmt = $pdo->prepare("UPDATE messages SET is_read = true WHERE sender_id = ? AND receiver_id = ?");
        $updateStmt->execute([$other_user_id, $current_user_id]);
        
        jsonResponse(true, $messages);

    } elseif ($action === 'send') {
        $data = json_decode(file_get_contents('php://input'), true);
        $receiver_id = $data['receiver_id'] ?? 0;
        $message = $data['message'] ?? '';
        
        if (!$receiver_id || !$message) {
            jsonResponse(false, [], 'Missing data');
        }
        
        // 1. Send User's Message
        $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, message) VALUES (?, ?, ?)");
        $stmt->execute([$current_user_id, $receiver_id, $message]);
        $userMsgId = $pdo->lastInsertId();
        
        // 2. Auto-Reply Logic
        // If sender is a 'user' and receiver is 'admin' or 'volunteer' (checking receiver role to be safe)
        if ($current_role === 'user') {
            // Check if we should reply? To avoid spamming, maybe only reply if it's the start of a convo or always?
            // "it will apper autometically that thank you for youe massage... like warm massage"
            // Let's do it always for now as per request.
            
            $replyMsg = "Thank you for your time, our admin will soon connect with you.";
            
            // The sender of the reply is the person who received the message (The Admin/Volunteer)
            // The receiver of the reply is the current user
            
            $autoStmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, message) VALUES (?, ?, ?)");
            $autoStmt->execute([$receiver_id, $current_user_id, $replyMsg]);
        }
        
        jsonResponse(true, ['message_id' => $userMsgId, 'auto_reply_sent' => ($current_role === 'user')]);
        
    } elseif ($action === 'check_unread') {
         $stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = false");
         $stmt->execute([$current_user_id]);
         $count = $stmt->fetchColumn();
         jsonResponse(true, ['unread_count' => $count]);
         
    } else {
        jsonResponse(false, [], 'Invalid action');
    }

} catch (PDOException $e) {
    jsonResponse(false, [], 'Database error: ' . $e->getMessage());
}
?>

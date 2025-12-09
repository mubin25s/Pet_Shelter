<?php
require_once '../config/db.php';

$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'update_role') {
            $id = $_POST['user_id'];
            $role = $_POST['role'];
            $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
            if ($stmt->execute([$role, $id])) {
                $message = "User role updated to " . htmlspecialchars($role);
            } else {
                $message = "Failed to update role.";
            }
        } elseif ($_POST['action'] === 'reset_password') {
            $id = $_POST['user_id'];
            $pass = $_POST['new_password'];
            if (!empty($pass)) {
                $hash = password_hash($pass, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                if ($stmt->execute([$hash, $id])) {
                    $message = "Password updated successfully.";
                } else {
                    $message = "Failed to update password.";
                }
            }
        } elseif ($_POST['action'] === 'create_user') {
            $name = $_POST['name'];
            $email = $_POST['email'];
            $pass = $_POST['password'];
            $role = $_POST['role'];
            
            // Check if exists
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetchColumn() > 0) {
                $message = "Error: User with this email already exists.";
            } else {
                $hash = password_hash($pass, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
                if ($stmt->execute([$name, $email, $hash, $role])) {
                    $message = "User created successfully!";
                } else {
                    $message = "Failed to create user.";
                }
            }
        }
    }
}

// Fetch all users
$users = $pdo->query("SELECT * FROM users ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Repair Tool</title>
    <style>
        body { font-family: sans-serif; padding: 20px; background: #f4f4f4; }
        .container { max-width: 900px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        h2 { border-bottom: 2px solid #eee; padding-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px; border-bottom: 1px solid #ddd; text-align: left; }
        th { background: #f8f9fa; }
        .btn { padding: 5px 10px; border-radius: 4px; border: none; cursor: pointer; color: white; }
        .btn-blue { background: #007bff; }
        .btn-green { background: #28a745; }
        .btn-red { background: #dc3545; }
        .message { padding: 10px; background: #d4edda; color: #155724; border-radius: 4px; margin-bottom: 20px; }
        .form-inline { display: flex; gap: 10px; align-items: center; }
        input[type="text"], input[type="password"] { padding: 5px; border: 1px solid #ddd; border-radius: 4px; }
        .section { margin-bottom: 40px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🛠️ Admin Repair Tool</h1>
        
        <?php if ($message): ?>
            <div class="message"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <div class="section">
            <h2>1. Create New Admin</h2>
            <p>If your account doesn't exist, create it here directly.</p>
            <form method="POST" style="background: #f9f9f9; padding: 15px; border-radius: 5px;">
                <input type="hidden" name="action" value="create_user">
                <div class="form-inline">
                    <input type="text" name="name" placeholder="Name" required>
                    <input type="text" name="email" placeholder="Email (e.g. petshelter@gmail.com)" required>
                    <input type="text" name="password" placeholder="Password" required>
                    <select name="role" style="padding: 5px;">
                        <option value="admin">Admin</option>
                        <option value="user">User</option>
                    </select>
                    <button type="submit" class="btn btn-green">Create User</button>
                </div>
            </form>
        </div>

        <div class="section">
            <h2>2. Manage Existing Users</h2>
            <p>Find your user below and use the controls to fix credentials or roles.</p>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?= $user['id'] ?></td>
                        <td><?= htmlspecialchars($user['name']) ?></td>
                        <td><?= htmlspecialchars($user['email']) ?></td>
                        <td>
                            <span style="font-weight: bold; color: <?= $user['role'] == 'admin' ? '#dc3545' : '#28a745' ?>">
                                <?= strtoupper($user['role']) ?>
                            </span>
                        </td>
                        <td>
                            <div style="display: flex; flex-direction: column; gap: 5px;">
                                <!-- Role Toggle -->
                                <form method="POST" class="form-inline">
                                    <input type="hidden" name="action" value="update_role">
                                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                    <?php if ($user['role'] == 'user'): ?>
                                        <input type="hidden" name="role" value="admin">
                                        <button type="submit" class="btn btn-red">Make Admin</button>
                                    <?php else: ?>
                                        <input type="hidden" name="role" value="user">
                                        <button type="submit" class="btn btn-blue">Make User</button>
                                    <?php endif; ?>
                                </form>

                                <!-- Password Reset -->
                                <form method="POST" class="form-inline">
                                    <input type="hidden" name="action" value="reset_password">
                                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                    <input type="text" name="new_password" placeholder="New Pass" required style="width: 80px;">
                                    <button type="submit" class="btn btn-green">Reset</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>

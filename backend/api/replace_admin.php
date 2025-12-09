<?php
require_once '../config/db.php';

header('Content-Type: text/html');

$target_name = "K.M. Fathum Mubin Sachcha";
$target_email = "petshelter@gmail.com";
$target_pass = "petshelter101";

echo "<div style='font-family: sans-serif; padding: 20px; line-height: 1.6;'>";
echo "<h2>🔄 Admin Account Replacement</h2>";

try {
    // 1. Delete the default/old admin (assuming it's admin@paws.com or just any other admin that isn't the new one?)
    // To be safe, let's explicitly delete 'admin@paws.com' which is the default.
    $stmt = $pdo->prepare("DELETE FROM users WHERE email = ?");
    $stmt->execute(['admin@paws.com']);
    if ($stmt->rowCount() > 0) {
        echo "<div style='color: green;'>✅ Deleted default admin (admin@paws.com).</div>";
    } else {
        echo "<div style='color: block;'>ℹ️ Default admin (admin@paws.com) was not found (already deleted?).</div>";
    }

    // 2. Create or Update the new Admin
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$target_email]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    $hash = password_hash($target_pass, PASSWORD_BCRYPT);

    if ($existing) {
        // Update existing
        $stmt = $pdo->prepare("UPDATE users SET name = ?, password = ?, role = 'admin' WHERE id = ?");
        $stmt->execute([$target_name, $hash, $existing['id']]);
        echo "<div style='color: green;'>✅ Updated existing account <b>$target_email</b> to Admin role and set new password.</div>";
    } else {
        // Create new
        $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'admin')");
        $stmt->execute([$target_name, $target_email, $hash]);
        echo "<div style='color: green;'>✅ Created new Admin account: <b>$target_email</b></div>";
    }

    echo "<hr>";
    echo "<h3>👇 Login Details</h3>";
    echo "<ul>";
    echo "<li><b>Email:</b> $target_email</li>";
    echo "<li><b>Password:</b> $target_pass</li>";
    echo "</ul>";
    echo "<p><a href='../../frontend/login.html' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Go to Login Page</a></p>";

} catch (Exception $e) {
    echo "<div style='color: red;'>❌ Error: " . $e->getMessage() . "</div>";
}

echo "</div>";
?>

<?php
require_once 'config/db.php';

try {
    // Users
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(100) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        role ENUM('admin', 'user') DEFAULT 'user',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Pets
    $pdo->exec("CREATE TABLE IF NOT EXISTS pets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        type VARCHAR(50) NOT NULL,
        image VARCHAR(255),
        history TEXT,
        health_status ENUM('red', 'yellow', 'green') NOT NULL,
        vaccine_status VARCHAR(100),
        food_habit TEXT,
        status ENUM('available', 'adopted') DEFAULT 'available',
        added_by INT,
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Adoptions
    $pdo->exec("CREATE TABLE IF NOT EXISTS adoptions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        pet_id INT NOT NULL,
        experience TEXT,
        other_pets TEXT,
        financial_status TEXT,
        adoption_status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
        request_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Rescues
    $pdo->exec("CREATE TABLE IF NOT EXISTS rescues (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        description TEXT NOT NULL,
        location VARCHAR(255) NOT NULL,
        condition_desc TEXT,
        status ENUM('reported', 'rescued') DEFAULT 'reported',
        report_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Donations
    $pdo->exec("CREATE TABLE IF NOT EXISTS donations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT,
        amount DECIMAL(10, 2) NOT NULL,
        donation_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Expenses
    $pdo->exec("CREATE TABLE IF NOT EXISTS expenses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        description VARCHAR(255) NOT NULL,
        amount DECIMAL(10, 2) NOT NULL,
        expense_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Seed Admin
    $admin_email = 'admin@paws.com';
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
    $stmt->execute([$admin_email]);
    if ($stmt->fetchColumn() == 0) {
        $admin_pass = password_hash('admin123', PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
        $stmt->execute(['Admin', $admin_email, $admin_pass, 'admin']);
    }

    echo json_encode(["message" => "Database setup complete"]);

} catch (PDOException $e) {
    echo json_encode(["error" => $e->getMessage()]);
}
?>

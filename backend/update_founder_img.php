<?php
require_once 'config/db.php';

try {
    $founder_image = 'images/founder_2.png';
    $stmt = $pdo->prepare("UPDATE settings SET founder_image = ? WHERE id = 1");
    $stmt->execute([$founder_image]);
    echo "Founder image updated to: " . $founder_image;
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>

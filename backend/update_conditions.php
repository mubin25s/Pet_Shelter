<?php
require_once __DIR__ . '/config/db.php';

try {
    // 1. Fetch all pet IDs
    $stmt = $pdo->query("SELECT id FROM pets");
    $petIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (count($petIds) < 35) { // Need at least 21+12+1 = 34, let's aim for 50
        echo "Insufficient pets found (" . count($petIds) . "). Seeding database with dummy pets...\n";
        
        $types = ['Dog', 'Cat', 'Rabbit', 'Hamster', 'Bird'];
        $names = ['Bella', 'Max', 'Charlie', 'Luna', 'Rocky', 'Buddy', 'Coco', 'Milo', 'Daisy', 'Leo'];
        
        $insertStmt = $pdo->prepare("INSERT INTO pets (name, type, health_status, status, description) VALUES (?, ?, 'green', 'available', 'A lovely pet looking for a home.')");
        
        $needed = 50 - count($petIds);
        for ($i = 0; $i < $needed; $i++) {
            $name = $names[array_rand($names)] . " " . ($i + 1);
            $type = $types[array_rand($types)];
            $insertStmt->execute([$name, $type]);
        }
        
        // Re-fetch IDs
        $stmt = $pdo->query("SELECT id FROM pets");
        $petIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
        echo "Seeded. Total pets: " . count($petIds) . "\n";
    } else {
        echo "Found " . count($petIds) . " pets.\n";
    }

    // 2. Shuffle to randomize
    shuffle($petIds);

    // 3. Determine distribution counts
    $total = count($petIds);
    $healthyCount = 21;
    $needsHelpCount = 12;
    // The rest will be critical
    
    $healthyIds = array_slice($petIds, 0, $healthyCount);
    $needsHelpIds = array_slice($petIds, $healthyCount, $needsHelpCount);
    $criticalIds = array_slice($petIds, $healthyCount + $needsHelpCount);

    echo "Assigning conditions:\n";
    echo "Healthy (Green): " . count($healthyIds) . " (Target: 21)\n";
    echo "Needs Help (Yellow): " . count($needsHelpIds) . " (Target: 12)\n";
    echo "Critical (Red): " . count($criticalIds) . " (Rest)\n";

    // 4. Update Database
    $pdo->beginTransaction();

    if (!empty($healthyIds)) {
        $inQuery = implode(',', array_fill(0, count($healthyIds), '?'));
        $stmt = $pdo->prepare("UPDATE pets SET health_status = 'green' WHERE id IN ($inQuery)");
        $stmt->execute($healthyIds);
    }

    if (!empty($needsHelpIds)) {
        $inQuery = implode(',', array_fill(0, count($needsHelpIds), '?'));
        $stmt = $pdo->prepare("UPDATE pets SET health_status = 'yellow' WHERE id IN ($inQuery)");
        $stmt->execute($needsHelpIds);
    }

    if (!empty($criticalIds)) {
        $inQuery = implode(',', array_fill(0, count($criticalIds), '?'));
        $stmt = $pdo->prepare("UPDATE pets SET health_status = 'red' WHERE id IN ($inQuery)");
        $stmt->execute($criticalIds);
    }

    $pdo->commit();
    echo "Update complete.\n";

    // 5. Verify
    $stmt = $pdo->query("SELECT health_status, COUNT(*) as count FROM pets GROUP BY health_status");
    $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "\nVerification Results:\n";
    print_r($stats);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "Error: " . $e->getMessage() . "\n";
}

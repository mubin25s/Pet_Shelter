<?php
require_once 'config/db.php';

$names = [
    'Luna', 'Bella', 'Charlie', 'Max', 'Lucy', 'Bailey', 'Cooper', 'Daisy', 'Sadie', 'Molly',
    'Buddy', 'Lola', 'Stella', 'Tucker', 'Bear', 'Zoey', 'Duke', 'Harley', 'Riley', 'Piper',
    'Bentley', 'Jake', 'Penny', 'Chloe', 'Coco', 'Jack', 'Kylo', 'Toby', 'Leo', 'Baxter',
    'Oliver', 'Ellie', 'Winston', 'Murphy', 'Nala', 'Scout', 'Milo', 'Ruby', 'Rosie', 'Teddy',
    'Abby', 'Simba', 'Gus', 'Marley', 'Lilly', 'Sophie', 'Zeus', 'Jackson', 'Koda', 'Thor',
    'Ace', 'Shadow', 'Ginger', 'Gizmo', 'Bandit', 'Rex', 'Oreo', 'Jasper', 'Blue', 'Ranger',
    'Brody', 'Hazel', 'Bruno', 'Peanut', 'Lucky', 'Sasha', 'Diesel', 'Sam', 'Fiona', 'George',
    'Loki', 'Moose', 'Romeo', 'Otis', 'Louie', 'Rocco', 'Buster', 'Pepper', 'Willow', 'Mia',
    'Finn', 'Ollie', 'Ziggy', 'Minnie', 'Mac', 'Benny', 'Chester', 'Barney', 'Bo', 'Emma'
];

try {
    // Get all pets
    $stmt = $pdo->query("SELECT id FROM pets");
    $pets = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Found " . count($pets) . " pets.<br>";
    
    // Shuffle names to ensure randomness
    shuffle($names);

    $pdo->beginTransaction();

    foreach ($pets as $index => $pet) {
        // If we run out of unique names, append a letter or small suffix, but try to avoid simple numbers if possible
        // But with 100 names and ~50 pets, we should be fine.
        $newName = $names[$index % count($names)];
        
        // Update
        $update = $pdo->prepare("UPDATE pets SET name = ? WHERE id = ?");
        $update->execute([$newName, $pet['id']]);
    }

    $pdo->commit();
    echo "Successfully renamed all pets!";

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "Error: " . $e->getMessage();
}
?>

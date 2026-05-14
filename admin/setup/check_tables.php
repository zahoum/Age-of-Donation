<?php
session_start();
require_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

// Check livreurs table structure
$query = "SHOW COLUMNS FROM livreurs";
try {
    $stmt = $db->prepare($query);
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>Table 'livreurs' columns:</h3>";
    foreach($columns as $col) {
        echo $col['Field'] . "<br>";
    }
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
    
    // Create table if not exists
    $create = "CREATE TABLE IF NOT EXISTS livreurs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        vehicule_type VARCHAR(50),
        plaque_immatriculation VARCHAR(50),
        zone_intervention TEXT,
        statut VARCHAR(20) DEFAULT 'actif',
        note_moyenne DECIMAL(3,2) DEFAULT 0,
        total_livraisons INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )";
    
    $db->exec($create);
    echo "<br>Table 'livreurs' created successfully!";
}
?>
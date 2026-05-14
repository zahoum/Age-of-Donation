<?php
session_start();
require_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

// Fix livreurs table structure
try {
    // Check if table exists
    $check = $db->prepare("SHOW TABLES LIKE 'livreurs'");
    $check->execute();
    
    if ($check->rowCount() == 0) {
        // Create table
        $create = "CREATE TABLE livreurs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            vehicule_type VARCHAR(50) DEFAULT 'voiture',
            plaque_immatriculation VARCHAR(50),
            zone_intervention TEXT,
            statut VARCHAR(20) DEFAULT 'actif',
            note_moyenne DECIMAL(3,2) DEFAULT 0,
            total_livraisons INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )";
        $db->exec($create);
        echo "✅ Table 'livreurs' created successfully!<br>";
    } else {
        // Check columns
        $columns = $db->prepare("SHOW COLUMNS FROM livreurs");
        $columns->execute();
        $existing = $columns->fetchAll(PDO::FETCH_COLUMN);
        
        if (!in_array('vehicule_type', $existing)) {
            $db->exec("ALTER TABLE livreurs ADD COLUMN vehicule_type VARCHAR(50) DEFAULT 'voiture'");
            echo "✅ Added vehicule_type column<br>";
        }
        if (!in_array('plaque_immatriculation', $existing)) {
            $db->exec("ALTER TABLE livreurs ADD COLUMN plaque_immatriculation VARCHAR(50)");
            echo "✅ Added plaque_immatriculation column<br>";
        }
        if (!in_array('zone_intervention', $existing)) {
            $db->exec("ALTER TABLE livreurs ADD COLUMN zone_intervention TEXT");
            echo "✅ Added zone_intervention column<br>";
        }
        if (!in_array('note_moyenne', $existing)) {
            $db->exec("ALTER TABLE livreurs ADD COLUMN note_moyenne DECIMAL(3,2) DEFAULT 0");
            echo "✅ Added note_moyenne column<br>";
        }
        if (!in_array('total_livraisons', $existing)) {
            $db->exec("ALTER TABLE livreurs ADD COLUMN total_livraisons INT DEFAULT 0");
            echo "✅ Added total_livraisons column<br>";
        }
    }
    
    echo "<br>🎉 Database structure is ready! <a href='../livreurs.php'>Go to Livreurs Page</a>";
    
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
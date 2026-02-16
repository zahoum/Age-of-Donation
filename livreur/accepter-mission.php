<?php
require_once '../config/database.php';
require_once '../includes/header.php';

checkAuth(['livreur']);

$database = new Database();
$db = $database->getConnection();

$user_id = $_SESSION['user_id'];
$mission_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if(!$mission_id) {
    header('Location: missions.php');
    exit;
}

try {
    // Vérifier que la mission existe et est disponible
    $query = "SELECT l.*, d.ville, d.donateur_id, de.beneficiaire_id 
              FROM livraisons l
              INNER JOIN demandes de ON l.demande_id = de.id
              INNER JOIN dons d ON de.don_id = d.id
              WHERE l.id = :id AND l.livreur_id IS NULL AND l.statut = 'en_attente'";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $mission_id);
    $stmt->execute();
    $mission = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if(!$mission) {
        $_SESSION['error'] = "هذه المهمة غير متاحة";
        header('Location: missions.php');
        exit;
    }
    
    // Accepter la mission
    $update = "UPDATE livraisons SET livreur_id = :livreur_id, statut = 'assignee' WHERE id = :id";
    $stmt = $db->prepare($update);
    $stmt->bindParam(':livreur_id', $user_id);
    $stmt->bindParam(':id', $mission_id);
    
    if($stmt->execute()) {
        // Créer une notification (optionnel)
        $_SESSION['success'] = "تم قبول المهمة بنجاح";
    } else {
        $_SESSION['error'] = "حدث خطأ أثناء قبول المهمة";
    }
    
} catch(PDOException $e) {
    $_SESSION['error'] = "خطأ في قاعدة البيانات: " . $e->getMessage();
}

header('Location: missions.php?filter=en_cours');
exit;
?>
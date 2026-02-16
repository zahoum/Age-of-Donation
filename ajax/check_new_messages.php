<?php
// ajax/check_new_messages.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Non authentifié']);
    exit();
}

require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$user_id = $_SESSION['user_id'];
$last_check = isset($_GET['last_check']) ? $_GET['last_check'] : date('Y-m-d H:i:s', strtotime('-1 minute'));

// Récupérer les nouveaux messages non lus avec les informations de l'expéditeur
$query = "SELECT m.*, u.nom as expediteur_nom, u.type as expediteur_type 
          FROM messages m
          INNER JOIN users u ON m.expediteur_id = u.id
          WHERE m.destinataire_id = :user_id 
          AND m.lu = 0
          AND m.created_at > :last_check
          ORDER BY m.created_at DESC";

$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $user_id);
$stmt->bindParam(':last_check', $last_check);
$stmt->execute();

$new_messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer le nombre total de messages non lus
$query_count = "SELECT COUNT(*) as total_unread 
                FROM messages 
                WHERE destinataire_id = :user_id AND lu = 0";
$stmt_count = $db->prepare($query_count);
$stmt_count->bindParam(':user_id', $user_id);
$stmt_count->execute();
$total_unread = $stmt_count->fetch(PDO::FETCH_ASSOC)['total_unread'];

echo json_encode([
    'success' => true,
    'new_messages' => $new_messages,
    'total_unread' => $total_unread,
    'current_time' => date('Y-m-d H:i:s')
]);
?>
<?php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get new messages count
$msg_query = "SELECT COUNT(*) as count FROM contact_messages WHERE status = 'new'";
$msg_stmt = $db->prepare($msg_query);
$msg_stmt->execute();
$new_messages = $msg_stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Get pending requests count
$req_query = "SELECT COUNT(*) as count FROM demandes WHERE statut = 'en_attente'";
$req_stmt = $db->prepare($req_query);
$req_stmt->execute();
$pending_requests = $req_stmt->fetch(PDO::FETCH_ASSOC)['count'];

echo json_encode([
    'new_messages' => $new_messages,
    'pending_requests' => $pending_requests
]);
?>
<?php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$database = new Database();
$db = $database->getConnection();

$user_id = $_POST['user_id'] ?? 0;
$status = $_POST['status'] ?? '';

$valid_statuses = ['actif', 'inactif'];

if (!in_array($status, $valid_statuses)) {
    echo json_encode(['error' => 'Invalid status']);
    exit();
}

$query = "UPDATE livreurs SET statut = :status WHERE user_id = :user_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':status', $status);
$stmt->bindParam(':user_id', $user_id);

if ($stmt->execute()) {
    // Update user status
    $user_status = $status == 'actif' ? 'active' : 'inactive';
    $user_query = "UPDATE users SET status = :status WHERE id = :id";
    $user_stmt = $db->prepare($user_query);
    $user_stmt->bindParam(':status', $user_status);
    $user_stmt->bindParam(':id', $user_id);
    $user_stmt->execute();
    
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['error' => 'Database error']);
}
?>
<?php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$database = new Database();
$db = $database->getConnection();

$id = $_POST['id'] ?? 0;
$status = $_POST['status'] ?? '';

$valid_statuses = ['disponible', 'réservé', 'completé', 'annulé'];

if (!in_array($status, $valid_statuses)) {
    echo json_encode(['error' => 'Invalid status']);
    exit();
}

$query = "UPDATE dons SET statut = :status WHERE id = :id";
$stmt = $db->prepare($query);
$stmt->bindParam(':status', $status);
$stmt->bindParam(':id', $id);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['error' => 'Database error']);
}
?>
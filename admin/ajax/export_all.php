<?php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    die('Unauthorized');
}

$database = new Database();
$db = $database->getConnection();

$type = $_GET['type'] ?? 'dons';
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $type . '_' . date('Y-m-d') . '.csv"');

$output = fopen('php://output', 'w');

// Add UTF-8 BOM for Arabic support
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

if ($type == 'dons') {
    // Export donations
    fputcsv($output, array('ID', 'العنوان', 'الوصف', 'الفئة', 'المبلغ', 'المتبرع', 'البريد', 'الهاتف', 'الولاية', 'المدينة', 'الحالة', 'تاريخ الإضافة'));
    
    $query = "SELECT d.*, u.nom as donateur_nom, u.email as donateur_email, u.telephone as donateur_telephone 
              FROM dons d 
              INNER JOIN users u ON d.donateur_id = u.id 
              WHERE 1=1";
    
    if (!empty($search)) {
        $query .= " AND (d.titre LIKE '%$search%' OR u.nom LIKE '%$search%')";
    }
    if (!empty($status)) {
        $query .= " AND d.statut = '$status'";
    }
    if (!empty($date_from)) {
        $query .= " AND DATE(d.created_at) >= '$date_from'";
    }
    if (!empty($date_to)) {
        $query .= " AND DATE(d.created_at) <= '$date_to'";
    }
    
    $query .= " ORDER BY d.created_at DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    $dons = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($dons as $don) {
        fputcsv($output, array(
            $don['id'],
            $don['titre'],
            $don['description'],
            $don['categorie'],
            $don['montant'] ?? 'غير محدد',
            $don['donateur_nom'],
            $don['donateur_email'],
            $don['donateur_telephone'] ?? '',
            $don['etat'] ?? '',
            $don['ville'] ?? '',
            $don['statut'],
            date('Y-m-d H:i', strtotime($don['created_at']))
        ));
    }
    
} elseif ($type == 'users') {
    // Export users
    fputcsv($output, array('ID', 'الاسم', 'البريد', 'الهاتف', 'النوع', 'الحالة', 'تاريخ التسجيل'));
    
    $query = "SELECT id, nom, email, telephone, type, status, created_at FROM users WHERE type != 'admin'";
    
    if (!empty($search)) {
        $query .= " AND (nom LIKE '%$search%' OR email LIKE '%$search%')";
    }
    
    $query .= " ORDER BY created_at DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($users as $user) {
        fputcsv($output, array(
            $user['id'],
            $user['nom'],
            $user['email'],
            $user['telephone'] ?? '',
            $user['type'],
            $user['status'],
            date('Y-m-d H:i', strtotime($user['created_at']))
        ));
    }
    
} elseif ($type == 'demandes') {
    // Export requests
    fputcsv($output, array('ID', 'التبرع', 'المستفيد', 'البريد', 'الكمية', 'السبب', 'الحالة', 'التاريخ'));
    
    $query = "SELECT de.*, d.titre as don_titre, u.nom as beneficiaire_nom, u.email as beneficiaire_email 
              FROM demandes de 
              INNER JOIN dons d ON de.don_id = d.id 
              INNER JOIN users u ON de.beneficiaire_id = u.id 
              WHERE 1=1";
    
    if (!empty($status)) {
        $query .= " AND de.statut = '$status'";
    }
    
    $query .= " ORDER BY de.created_at DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    $demandes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($demandes as $demande) {
        fputcsv($output, array(
            $demande['id'],
            $demande['don_titre'],
            $demande['beneficiaire_nom'],
            $demande['beneficiaire_email'],
            $demande['quantite_demandee'] ?? '',
            $demande['raison'] ?? '',
            $demande['statut'],
            date('Y-m-d H:i', strtotime($demande['created_at']))
        ));
    }
}

fclose($output);
exit();
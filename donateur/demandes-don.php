
<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'donateur') {
    header('Location: ../auth/login.php');
    exit();
}

require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$user_id = $_SESSION['user_id'];
$don_id = $_GET['id'] ?? null;

if (!$don_id) {
    header('Location: voir-don.php');
    exit();
}

// Récupérer les demandes pour ce don
$query_demandes = "
    SELECT d.*, u.nom as donateur_nom, u.ville as donateur_ville
    FROM demandes d
    INNER JOIN users u ON d.donateur_id = u.id
    WHERE d.don_id = :don_id
    ORDER BY d.created_at DESC
";
$stmt_demandes = $db->prepare($query_demandes);
$stmt_demandes->bindParam(":don_id", $don_id);
$demandes = $stmt_demandes->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'عرض التبرع';
require_once '../includes/header.php';
?>



<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <style>
        p{
            text-align: center;
            justify-content: center;
            text-transform: uppercase;
            padding: 55px;
            margin-left: 195px;
            margin-right: 195px;
            border-radius: 100px;
            
            font-size: 30px;

            box-shadow: 1px black;
            background-color: #8b93fd;

        }
    </style>
</head>
<body>
    <p>hello to the Donation Requests </p>
    <?php if(!empty($demandes)): ?>
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-file-alt"></i> الطلبات على هذا التبرع (<?php echo count($demandes); ?>)</h3>
            </div>
            <div class="card-body">
                <?php foreach($demandes as $demande): ?>
                    <div style="border: 1px solid #eee; border-radius: 10px; padding: 20px; margin-bottom: 15px;">
                        <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 15px;">
                            <div>
                                <h5 style="margin-bottom: 5px;">
                                    <i class="fas fa-user"></i> <?php echo htmlspecialchars($demande['beneficiaire_nom']); ?>
                                    <?php if($demande['beneficiaire_ville']): ?>
                                        <small style="color: #666; margin-right: 10px;">
                                            <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($demande['beneficiaire_ville']); ?>
                                        </small>
                                    <?php endif; ?>
                                </h5>
                                <span class="badge badge-<?php 
                                    echo $demande['statut'] == 'en_attente' ? 'warning' : 
                                         ($demande['statut'] == 'acceptee' ? 'success' : 'danger');
                                ?>">
                                    <?php echo $demande['statut']; ?>
                                </span>
                                <small style="color: #888; margin-right: 10px;">
                                    <i class="fas fa-calendar"></i> <?php echo date('d/m/Y H:i', strtotime($demande['created_at'])); ?>
                                </small>
                            </div>
                            
                        </div>
                        
                        <div style="background: #f8f9fa; padding: 15px; border-radius: 8px;">
                            <strong style="color: #666;">رسالة المستفيد:</strong>
                            <p style="margin-top: 10px; color: #333; line-height: 1.5;">
                                <?php echo nl2br(htmlspecialchars($demande['message_demande'])); ?>
                            </p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
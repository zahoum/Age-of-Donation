<?php
require_once '../config/database.php';
require_once '../includes/auth-check.php';
requireAuth(['donateur']);

$database = new Database();
$db = $database->getConnection();

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

// Gérer la suppression d'un don
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $don_id = $_GET['id'];
    
    try {
        $query = "DELETE FROM dons WHERE id = :id AND donateur_id = :donateur_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":id", $don_id);
        $stmt->bindParam(":donateur_id", $user_id);
        
        if ($stmt->execute()) {
            $success = "Don supprimé avec succès";
        } else {
            $error = "Erreur lors de la suppression du don";
        }
    } catch(PDOException $e) {
        $error = "Erreur: " . $e->getMessage();
    }
}

// Récupérer tous les dons du donateur avec les demandes
$query = "
    SELECT d.*, 
           COUNT(de.id) as nb_demandes,
           SUM(CASE WHEN de.statut = 'en_attente' THEN 1 ELSE 0 END) as demandes_attente
    FROM dons d
    LEFT JOIN demandes de ON d.id = de.don_id
    WHERE d.donateur_id = :user_id
    GROUP BY d.id
    ORDER BY d.created_at DESC
";
$stmt = $db->prepare($query);
$stmt->bindParam(":user_id", $user_id);
$stmt->execute();
$dons = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes dons - Age of Donnation</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php 
    $pageTitle = "Mes dons";
    include '../includes/header.php'; 
    ?>

    <div class="container">
        <div class="dashboard-header">
            <h1>Mes dons</h1>
            <p>Gérez vos publications et consultez les demandes</p>
        </div>

        <?php if($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header" style="display: flex; justify-content: between; align-items: center;">
                <h3 style="margin: 0;">Liste de vos dons</h3>
                <a href="publier-don.php" class="btn btn-primary">➕ Publier un nouveau don</a>
            </div>
            <div class="card-body">
                <?php if(empty($dons)): ?>
                    <div style="text-align: center; padding: 3rem;">
                        <h3 style="color: #666; margin-bottom: 1rem;">Aucun don publié</h3>
                        <p style="color: #888; margin-bottom: 2rem;">Commencez par publier votre premier don pour aider ceux qui en ont besoin</p>
                        <a href="publier-don.php" class="btn btn-primary">Publier mon premier don</a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Titre</th>
                                    <th>Catégorie</th>
                                    <th>État</th>
                                    <th>Statut</th>
                                    <th>Demandes</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($dons as $don): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($don['titre']); ?></strong>
                                            <?php if(strlen($don['description']) > 50): ?>
                                                <br><small style="color: #666;"><?php echo substr(htmlspecialchars($don['description']), 0, 50); ?>...</small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge badge-secondary">
                                                <?php echo getCategorieLabel($don['categorie']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo getEtatLabel($don['etat']); ?></td>
                                        <td><?php echo getStatusBadge($don['statut']); ?></td>
                                        <td>
                                            <?php if($don['nb_demandes'] > 0): ?>
                                                <span class="badge badge-<?php echo $don['demandes_attente'] > 0 ? 'warning' : 'info'; ?>">
                                                    <?php echo $don['nb_demandes']; ?> demande(s)
                                                    <?php if($don['demandes_attente'] > 0): ?>
                                                        <br><small><?php echo $don['demandes_attente']; ?> en attente</small>
                                                    <?php endif; ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="badge badge-light">Aucune</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo formatDate($don['created_at']); ?></td>
                                        <td>
                                            <div style="display: flex; gap: 0.3rem; flex-wrap: wrap;">
                                                <a href="voir-don.php?id=<?php echo $don['id']; ?>" class="btn btn-outline" style="padding: 0.3rem 0.6rem; font-size: 0.8rem;">Voir</a>
                                                <?php if($don['statut'] == 'disponible'): ?>
                                                    <a href="modifier-don.php?id=<?php echo $don['id']; ?>" class="btn btn-warning" style="padding: 0.3rem 0.6rem; font-size: 0.8rem;">Modifier</a>
                                                    <a href="?action=delete&id=<?php echo $don['id']; ?>" class="btn btn-danger" style="padding: 0.3rem 0.6rem; font-size: 0.8rem;" 
                                                       onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce don ?')">Supprimer</a>
                                                <?php endif; ?>
                                                <?php if($don['nb_demandes'] > 0): ?>
                                                    <a href="demandes-don.php?don_id=<?php echo $don['id']; ?>" class="btn btn-info" style="padding: 0.3rem 0.6rem; font-size: 0.8rem;">Demandes</a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Statistiques -->
                    <div style="margin-top: 2rem; padding: 1rem; background: #f8f9fa; border-radius: 5px;">
                        <h4>📊 Résumé de vos dons</h4>
                        <div style="display: flex; gap: 2rem; flex-wrap: wrap;">
                            <?php
                            $stats = [
                                'total' => count($dons),
                                'disponible' => array_sum(array_map(fn($d) => $d['statut'] == 'disponible' ? 1 : 0, $dons)),
                                'reserve' => array_sum(array_map(fn($d) => $d['statut'] == 'reserve' ? 1 : 0, $dons)),
                                'donne' => array_sum(array_map(fn($d) => $d['statut'] == 'donne' ? 1 : 0, $dons))
                            ];
                            ?>
                            <div><strong>Total:</strong> <?php echo $stats['total']; ?> dons</div>
                            <div><strong>Disponibles:</strong> <?php echo $stats['disponible']; ?></div>
                            <div><strong>Réservés:</strong> <?php echo $stats['reserve']; ?></div>
                            <div><strong>Donnés:</strong> <?php echo $stats['donne']; ?></div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>
</body>
</html>
<?php
require_once '../config/database.php';
checkAuth(['donateur']);

$database = new Database();
$db = $database->getConnection();

// Statistiques du donateur
$user_id = $_SESSION['user_id'];
$stats_query = "
    SELECT 
        COUNT(*) as total_dons,
        SUM(CASE WHEN statut = 'donne' THEN 1 ELSE 0 END) as dons_termines,
        SUM(CASE WHEN statut = 'disponible' THEN 1 ELSE 0 END) as dons_actifs,
        SUM(CASE WHEN statut = 'reserve' THEN 1 ELSE 0 END) as dons_reserves
    FROM dons 
    WHERE donateur_id = :user_id
";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->bindParam(":user_id", $user_id);
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Dons récents
$dons_query = "SELECT * FROM dons WHERE donateur_id = :user_id ORDER BY created_at DESC LIMIT 5";
$dons_stmt = $db->prepare($dons_query);
$dons_stmt->bindParam(":user_id", $user_id);
$dons_stmt->execute();
$dons_recent = $dons_stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de Bord Donateur - Age of Donnation</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php 
    $pageTitle = "Tableau de bord Donateur";
    include '../includes/header.php'; 
    ?>

    <div class="container">
        <!-- Bienvenue -->
        <div class="dashboard-header">
            <h1>Bienvenue sur votre espace Donateur</h1>
            <p>Gérez vos dons et aidez ceux qui en ont besoin</p>
        </div>

        <!-- Statistiques -->
        <div class="grid-4">
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total_dons'] ?? 0; ?></div>
                <div class="stat-label">Total des dons</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['dons_actifs'] ?? 0; ?></div>
                <div class="stat-label">Dons actifs</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['dons_reserves'] ?? 0; ?></div>
                <div class="stat-label">Dons réservés</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['dons_termines'] ?? 0; ?></div>
                <div class="stat-label">Dons terminés</div>
            </div>
        </div>
<br>
        <!-- Actions rapides -->
        <div class="quick-actions">
            <a href="publier-don.php" class="action-card">
                <h3>📦 Publier un don</h3>
                <p>Proposer un objet dont vous ne vous servez plus</p>
            </a>
            <a href="mes-dons.php" class="action-card">
                <h3>📋 Mes dons</h3>
                <p>Gérer vos publications et demandes</p>
            </a>
            <a href="messagerie.php" class="action-card">
                <h3>💬 Messagerie</h3>
                <p>Communiquer avec les bénéficiaires</p>
            </a>
            <a href="#" class="action-card">
                <h3>📊 Statistiques</h3>
                <p>Voir votre impact détaillé</p>
            </a>
        </div>

        <!-- Dons récents -->
        <div class="card">
            <div class="card-header">
                <h3>Vos dons récents</h3>
            </div>
            <div class="card-body">
                <?php if(empty($dons_recent)): ?>
                    <div style="text-align: center; padding: 3rem; color: #666;">
                        <h3 style="margin-bottom: 1rem;">Aucun don publié</h3>
                        <p style="margin-bottom: 2rem;">Commencez par publier votre premier don pour aider ceux qui en ont besoin</p>
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
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($dons_recent as $don): ?>
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
                                        <td><?php echo date('d/m/Y', strtotime($don['created_at'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Section d'encouragement -->
        <?php if(empty($dons_recent)): ?>
            <div class="card" style="background: linear-gradient(135deg, #007bff, #0056b3); color: white; text-align: center;">
                <div class="card-body" style="padding: 3rem;">
                    <div style="font-size: 4rem; margin-bottom: 1rem;">🌟</div>
                    <h2 style="margin-bottom: 1rem;">Commencez votre voyage de générosité</h2>
                    <p style="margin-bottom: 2rem; opacity: 0.9;">Votre premier don peut faire la différence dans la vie de quelqu'un. Publiez un objet dont vous ne vous servez plus et donnez-lui une seconde vie.</p>
                    <a href="publier-don.php" class="btn btn-primary" style="background: white; color: #007bff; border: none; padding: 1rem 2rem; font-size: 1.1rem;">
                        🎁 Publier mon premier don
                    </a>
                </div>
            </div>
        <?php else: ?>
            <!-- Statistiques supplémentaires -->
            <div style="margin-top: 2rem; padding: 1.5rem; background: #f8f9fa; border-radius: 10px;">
                <h4>📈 Votre impact</h4>
                <div style="display: flex; gap: 2rem; flex-wrap: wrap;">
                    <div>
                        <strong>Dons publiés:</strong> <?php echo $stats['total_dons'] ?? 0; ?>
                    </div>
                    <div>
                        <strong>En cours:</strong> <?php echo ($stats['dons_actifs'] ?? 0) + ($stats['dons_reserves'] ?? 0); ?>
                    </div>
                    <div>
                        <strong>Terminés:</strong> <?php echo $stats['dons_termines'] ?? 0; ?>
                    </div>
                    <div>
                        <strong>Taux de réussite:</strong> 
                        <?php 
                        $total = $stats['total_dons'] ?? 1;
                        $termines = $stats['dons_termines'] ?? 0;
                        echo round(($termines / $total) * 100, 1); 
                        ?>%
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <?php include '../includes/footer.php'; ?>
</body>
</html>
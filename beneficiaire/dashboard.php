<?php
require_once '../config/database.php';
checkAuth(['beneficiaire']);

$database = new Database();
$db = $database->getConnection();

$user_id = $_SESSION['user_id'];

// Statistiques du bénéficiaire
$stats_query = "
    SELECT 
        COUNT(*) as total_demandes,
        SUM(CASE WHEN statut = 'acceptee' THEN 1 ELSE 0 END) as demandes_acceptees,
        SUM(CASE WHEN statut = 'en_attente' THEN 1 ELSE 0 END) as demandes_attente,
        SUM(CASE WHEN statut = 'refusee' THEN 1 ELSE 0 END) as demandes_refusees
    FROM demandes 
    WHERE beneficiaire_id = :user_id
";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->bindParam(":user_id", $user_id);
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Dons récents disponibles
$dons_query = "
    SELECT d.*, u.nom as donateur_nom, u.ville as donateur_ville
    FROM dons d 
    INNER JOIN users u ON d.donateur_id = u.id 
    WHERE d.statut = 'disponible' 
    ORDER BY d.created_at DESC 
    LIMIT 6
";
$dons_stmt = $db->prepare($dons_query);
$dons_stmt->execute();
$dons_recent = $dons_stmt->fetchAll(PDO::FETCH_ASSOC);

// Mes demandes récentes
$demandes_query = "
    SELECT de.*, d.titre as don_titre, d.categorie, u.nom as donateur_nom
    FROM demandes de
    INNER JOIN dons d ON de.don_id = d.id
    INNER JOIN users u ON d.donateur_id = u.id
    WHERE de.beneficiaire_id = :user_id
    ORDER BY de.created_at DESC
    LIMIT 5
";
$demandes_stmt = $db->prepare($demandes_query);
$demandes_stmt->bindParam(":user_id", $user_id);
$demandes_stmt->execute();
$mes_demandes = $demandes_stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de Bord Bénéficiaire - Age of Donnation</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php 
    $pageTitle = "Tableau de bord";
    include '../includes/header.php'; 
    ?>

    <div class="container">
        <!-- Bienvenue -->
        <div class="dashboard-header">
            <h1>Bienvenue sur votre espace Bénéficiaire</h1>
            <p>Trouvez les dons dont vous avez besoin et suivez vos demandes</p>
        </div>

        <!-- Statistiques -->
        <div class="grid-4">
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total_demandes'] ?? 0; ?></div>
                <div class="stat-label">Demandes totales</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['demandes_acceptees'] ?? 0; ?></div>
                <div class="stat-label">Demandes acceptées</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['demandes_attente'] ?? 0; ?></div>
                <div class="stat-label">En attente</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['demandes_refusees'] ?? 0; ?></div>
                <div class="stat-label">Refusées</div>
            </div>
        </div>
<br>
        <!-- Actions rapides -->
        <div class="quick-actions">
            <a href="catalogue.php" class="action-card">
                <h3>🔍 Voir le catalogue</h3>
                <p>Parcourir tous les dons disponibles</p>
            </a>
            <a href="mes-demandes.php" class="action-card">
                <h3>📋 Mes demandes</h3>
                <p>Suivre vos demandes en cours</p>
            </a>
            <a href="messagerie.php" class="action-card">
                <h3>💬 Messagerie</h3>
                <p>Communiquer avec les donateurs</p>
            </a>
            <a href="../auth/logout.php" class="action-card">
                <h3>⚙️ Paramètres</h3>
                <p>Gérer votre compte</p>
            </a>
        </div>

        <div class="grid-2">
            <!-- Dons récents disponibles -->
            <div class="card">
                <div class="card-header">
                    <h3>🎁 Dons récemment disponibles</h3>
                </div>
                <div class="card-body">
                    <?php if(empty($dons_recent)): ?>
                        <p style="text-align: center; color: #666; padding: 2rem;">Aucun don disponible pour le moment.</p>
                    <?php else: ?>
                        <div style="display: grid; gap: 1rem;">
                            <?php foreach($dons_recent as $don): ?>
                                <div style="padding: 1rem; border: 1px solid #eee; border-radius: 5px;">
                                    <div style="display: flex; justify-content: between; align-items: start;">
                                        <div style="flex: 1;">
                                            <h4 style="margin: 0 0 0.5rem 0;"><?php echo htmlspecialchars($don['titre']); ?></h4>
                                            <p style="color: #666; margin: 0; font-size: 0.9rem;">
                                                <?php echo strlen($don['description']) > 100 ? substr(htmlspecialchars($don['description']), 0, 100) . '...' : htmlspecialchars($don['description']); ?>
                                            </p>
                                            <div style="margin-top: 0.5rem;">
                                                <span class="badge badge-secondary"><?php echo getCategorieLabel($don['categorie']); ?></span>
                                                <span class="badge badge-info"><?php echo getEtatLabel($don['etat']); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                    <div style="display: flex; justify-content: between; align-items: center; margin-top: 1rem;">
                                        <small style="color: #888;">
                                            Par <?php echo htmlspecialchars($don['donateur_nom']); ?> • <?php echo $don['ville']; ?>
                                        </small>
                                        <a href="catalogue.php#don-<?php echo $don['id']; ?>" class="btn btn-primary" style="padding: 0.3rem 0.8rem; font-size: 0.8rem;">Voir le don</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <div style="text-align: center; margin-top: 1.5rem;">
                        <a href="catalogue.php" class="btn btn-outline">Voir tous les dons disponibles</a>
                    </div>
                </div>
            </div>

            <!-- Mes demandes récentes -->
            <div class="card">
                <div class="card-header">
                    <h3>📝 Mes demandes récentes</h3>
                </div>
                <div class="card-body">
                    <?php if(empty($mes_demandes)): ?>
                        <div style="text-align: center; padding: 2rem; color: #666;">
                            <p>Vous n'avez encore fait aucune demande</p>
                            <a href="catalogue.php" class="btn btn-primary">Parcourir le catalogue</a>
                        </div>
                    <?php else: ?>
                        <div style="max-height: 400px; overflow-y: auto;">
                            <?php foreach($mes_demandes as $demande): ?>
                                <div style="padding: 1rem; border-bottom: 1px solid #eee;">
                                    <div style="display: flex; justify-content: between; align-items: start;">
                                        <div style="flex: 1;">
                                            <strong><?php echo htmlspecialchars($demande['don_titre']); ?></strong>
                                            <br>
                                            <small style="color: #666;">
                                                À <?php echo htmlspecialchars($demande['donateur_nom']); ?> • 
                                                <?php echo date('d/m/Y', strtotime($demande['created_at'])); ?>
                                            </small>
                                        </div>
                                        <div>
                                            <?php echo getStatusBadge($demande['statut']); ?>
                                        </div>
                                    </div>
                                    <?php if($demande['message_demande']): ?>
                                        <p style="color: #666; font-size: 0.9rem; margin: 0.5rem 0 0 0;">
                                            "<?php echo substr(htmlspecialchars($demande['message_demande']), 0, 100); ?>..."
                                        </p>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <div style="text-align: center; margin-top: 1.5rem;">
                        <a href="mes-demandes.php" class="btn btn-outline">Voir toutes mes demandes</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>
</body>
</html>
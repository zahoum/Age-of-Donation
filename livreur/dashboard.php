<?php
require_once '../config/database.php';
checkAuth(['livreur']);

$database = new Database();
$db = $database->getConnection();

$user_id = $_SESSION['user_id'];

// Vérifier si le livreur est actif
$query = "SELECT l.* FROM livreurs l WHERE l.user_id = :user_id AND l.statut = 'actif'";
$stmt = $db->prepare($query);
$stmt->bindParam(":user_id", $user_id);
$stmt->execute();
$livreur = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$livreur) {
    die("Votre compte livreur n'est pas encore activé par l'administrateur.");
}

// Statistiques du livreur
$stats_query = "
    SELECT 
        COUNT(*) as total_missions,
        SUM(CASE WHEN statut = 'livree' THEN 1 ELSE 0 END) as missions_terminees,
        SUM(CASE WHEN statut = 'en_cours' THEN 1 ELSE 0 END) as missions_en_cours
    FROM livraisons 
    WHERE livreur_id = :user_id
";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->bindParam(":user_id", $user_id);
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Missions récentes
$missions_query = "
    SELECT l.*, d.titre as don_titre, u.nom as beneficiaire_nom, u2.nom as donateur_nom
    FROM livraisons l
    INNER JOIN demandes de ON l.demande_id = de.id
    INNER JOIN dons d ON de.don_id = d.id
    INNER JOIN users u ON de.beneficiaire_id = u.id
    INNER JOIN users u2 ON d.donateur_id = u2.id
    WHERE l.livreur_id = :user_id
    ORDER BY l.created_at DESC
    LIMIT 5
";
$missions_stmt = $db->prepare($missions_query);
$missions_stmt->bindParam(":user_id", $user_id);
$missions_stmt->execute();
$missions_recent = $missions_stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de Bord Livreur</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <header class="header">
        <nav class="nav">
            <a href="../index.php" class="logo">Age of Donnation</a>
            <ul class="nav-links">
                <li><a href="dashboard.php" class="active">Tableau de bord</a></li>
                <li><a href="missions.php">Missions</a></li>
                <li><a href="profil.php">Mon profil</a></li>
            </ul>
            <div class="auth-buttons">
                <span style="color: #333; margin-right: 1rem;">Bonjour, <?php echo $_SESSION['user_nom']; ?></span>
                <a href="../auth/logout.php" class="btn btn-outline">Déconnexion</a>
            </div>
        </nav>
    </header>

    <div class="container">
        <!-- Bienvenue -->
        <div class="dashboard-header">
            <h1>Bienvenue sur votre espace Livreur</h1>
            <p>Gérez vos missions de livraison</p>
        </div>

        <!-- Statistiques -->
        <div class="grid-3">
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total_missions'] ?? 0; ?></div>
                <div class="stat-label">Missions totales</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['missions_en_cours'] ?? 0; ?></div>
                <div class="stat-label">Missions en cours</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['missions_terminees'] ?? 0; ?></div>
                <div class="stat-label">Missions terminées</div>
            </div>
        </div>

        <!-- Actions rapides -->
        <div class="quick-actions">
            <a href="missions.php" class="action-card">
                <h3>🚚 Voir les missions</h3>
                <p>Consulter les missions disponibles</p>
            </a>
            <a href="missions.php?filter=en_cours" class="action-card">
                <h3>📋 Mes missions en cours</h3>
                <p>Gérer mes missions actuelles</p>
            </a>
            <a href="profil.php" class="action-card">
                <h3>👤 Mon profil</h3>
                <p>Modifier mes informations</p>
            </a>
        </div>

        <!-- Missions récentes -->
        <div class="card">
            <div class="card-header">
                <h3>Vos missions récentes</h3>
            </div>
            <div class="card-body">
                <?php if(empty($missions_recent)): ?>
                    <p style="text-align: center; color: #666; padding: 2rem;">Aucune mission pour le moment.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Don</th>
                                    <th>Bénéficiaire</th>
                                    <th>Donateur</th>
                                    <th>Statut</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($missions_recent as $mission): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($mission['don_titre']); ?></td>
                                        <td><?php echo htmlspecialchars($mission['beneficiaire_nom']); ?></td>
                                        <td><?php echo htmlspecialchars($mission['donateur_nom']); ?></td>
                                        <td><?php echo getStatusBadge($mission['statut']); ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($mission['created_at'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
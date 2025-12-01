<?php
require_once '../config/database.php';
checkAuth(['admin']);

$database = new Database();
$db = $database->getConnection();

// Statistiques générales
$stats_query = "
    SELECT 
        (SELECT COUNT(*) FROM users WHERE type != 'admin') as total_utilisateurs,
        (SELECT COUNT(*) FROM dons) as total_dons,
        (SELECT COUNT(*) FROM demandes) as total_demandes,
        (SELECT COUNT(*) FROM livreurs WHERE statut = 'actif') as livreurs_actifs,
        (SELECT COUNT(*) FROM dons WHERE statut = 'disponible') as dons_disponibles,
        (SELECT COUNT(*) FROM demandes WHERE statut = 'en_attente') as demandes_attente
";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Dons récents
$dons_query = "SELECT d.*, u.nom as donateur_nom FROM dons d INNER JOIN users u ON d.donateur_id = u.id ORDER BY d.created_at DESC LIMIT 5";
$dons_stmt = $db->prepare($dons_query);
$dons_stmt->execute();
$dons_recent = $dons_stmt->fetchAll(PDO::FETCH_ASSOC);

// Demandes récentes
$demandes_query = "
    SELECT de.*, d.titre as don_titre, u.nom as beneficiaire_nom 
    FROM demandes de 
    INNER JOIN dons d ON de.don_id = d.id 
    INNER JOIN users u ON de.beneficiaire_id = u.id 
    ORDER BY de.created_at DESC 
    LIMIT 5
";
$demandes_stmt = $db->prepare($demandes_query);
$demandes_stmt->execute();
$demandes_recent = $demandes_stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de Bord Admin - Age of Donnation</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <header class="header">
        <nav class="nav">
            <a href="../index.php" class="logo">Age of Donnation</a>
            <ul class="nav-links">
                <li><a href="dashboard.php" class="active">Tableau de bord</a></li>
                <li><a href="utilisateurs.php">Utilisateurs</a></li>
                <li><a href="dons.php">Dons</a></li>
                <li><a href="livreurs.php">Livreurs</a></li>
                <li><a href="statistiques.php">Statistiques</a></li>
            </ul>
            <div class="auth-buttons">
                <span style="color: #333; margin-right: 1rem;">Admin: <?php echo $_SESSION['user_nom']; ?></span>
                <a href="../auth/logout.php" class="btn btn-outline">Déconnexion</a>
            </div>
        </nav>
    </header>

    <div class="container">
        <!-- Bienvenue -->
        <div class="dashboard-header">
            <h1>Tableau de Bord Administrateur</h1>
            <p>Gérez la plateforme Age of Donnation</p>
        </div>

        <!-- Statistiques -->
        <div class="grid-4">
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total_utilisateurs'] ?? 0; ?></div>
                <div class="stat-label">Utilisateurs</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total_dons'] ?? 0; ?></div>
                <div class="stat-label">Dons total</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total_demandes'] ?? 0; ?></div>
                <div class="stat-label">Demandes</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['livreurs_actifs'] ?? 0; ?></div>
                <div class="stat-label">Livreurs actifs</div>
            </div>
        </div>

        <div class="grid-2">
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['dons_disponibles'] ?? 0; ?></div>
                <div class="stat-label">Dons disponibles</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['demandes_attente'] ?? 0; ?></div>
                <div class="stat-label">Demandes en attente</div>
            </div>
        </div>

        <div class="grid-2">
            <!-- Dons récents -->
            <div class="card">
                <div class="card-header">
                    <h3>Dons récents</h3>
                </div>
                <div class="card-body">
                    <?php if(empty($dons_recent)): ?>
                        <p style="text-align: center; color: #666; padding: 2rem;">Aucun don récent</p>
                    <?php else: ?>
                        <div style="max-height: 300px; overflow-y: auto;">
                            <?php foreach($dons_recent as $don): ?>
                                <div style="padding: 1rem; border-bottom: 1px solid #eee;">
                                    <div style="display: flex; justify-content: between; align-items: start;">
                                        <div style="flex: 1;">
                                            <strong><?php echo htmlspecialchars($don['titre']); ?></strong>
                                            <br>
                                            <small style="color: #666;">Par: <?php echo htmlspecialchars($don['donateur_nom']); ?></small>
                                        </div>
                                        <div>
                                            <?php echo getStatusBadge($don['statut']); ?>
                                            <br>
                                            <small style="color: #888;"><?php echo date('d/m', strtotime($don['created_at'])); ?></small>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <div style="text-align: center; margin-top: 1rem;">
                        <a href="dons.php" class="btn btn-outline">Voir tous les dons</a>
                    </div>
                </div>
            </div>

            <!-- Demandes récentes -->
            <div class="card">
                <div class="card-header">
                    <h3>Demandes récentes</h3>
                </div>
                <div class="card-body">
                    <?php if(empty($demandes_recent)): ?>
                        <p style="text-align: center; color: #666; padding: 2rem;">Aucune demande récente</p>
                    <?php else: ?>
                        <div style="max-height: 300px; overflow-y: auto;">
                            <?php foreach($demandes_recent as $demande): ?>
                                <div style="padding: 1rem; border-bottom: 1px solid #eee;">
                                    <div style="display: flex; justify-content: between; align-items: start;">
                                        <div style="flex: 1;">
                                            <strong><?php echo htmlspecialchars($demande['don_titre']); ?></strong>
                                            <br>
                                            <small style="color: #666;">Par: <?php echo htmlspecialchars($demande['beneficiaire_nom']); ?></small>
                                        </div>
                                        <div>
                                            <?php echo getStatusBadge($demande['statut']); ?>
                                            <br>
                                            <small style="color: #888;"><?php echo date('d/m', strtotime($demande['created_at'])); ?></small>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <div style="text-align: center; margin-top: 1rem;">
                        <a href="utilisateurs.php" class="btn btn-outline">Voir les utilisateurs</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
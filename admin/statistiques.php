<?php
require_once '../config/database.php';
checkAuth(['admin']);

$database = new Database();
$db = $database->getConnection();

        
// Statistiques détaillées
$stats_query = "
    SELECT 
        -- Utilisateurs
        (SELECT COUNT(*) FROM users WHERE type = 'donateur') as donateurs,
        (SELECT COUNT(*) FROM users WHERE type = 'beneficiaire') as beneficiaires,
        (SELECT COUNT(*) FROM users WHERE type = 'livreur') as livreurs_total,
        
        -- Dons
        (SELECT COUNT(*) FROM dons WHERE statut = 'disponible') as dons_disponibles,
        (SELECT COUNT(*) FROM dons WHERE statut = 'reserve') as dons_reserves,
        (SELECT COUNT(*) FROM dons WHERE statut = 'donne') as dons_donnes,
        
        -- Demandes
        (SELECT COUNT(*) FROM demandes WHERE statut = 'en_attente') as demandes_attente,
        (SELECT COUNT(*) FROM demandes WHERE statut = 'acceptee') as demandes_acceptees,
        (SELECT COUNT(*) FROM demandes WHERE statut = 'refusee') as demandes_refusees,
        
        -- Livraisons
        (SELECT COUNT(*) FROM livraisons WHERE statut = 'livree') as livraisons_terminees,
        (SELECT COUNT(*) FROM livraisons WHERE statut = 'en_cours') as livraisons_en_cours,
        
        -- Activité récente
        (SELECT COUNT(*) FROM dons WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) as dons_7j,
        (SELECT COUNT(*) FROM demandes WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) as demandes_7j
";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Dons par catégorie
$categories_query = "
    SELECT categorie, COUNT(*) as count 
    FROM dons 
    GROUP BY categorie 
    ORDER BY count DESC
";
$categories_stmt = $db->prepare($categories_query);
$categories_stmt->execute();
$categories = $categories_stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statistiques - Age of Donnation</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <header class="header">
        <nav class="nav">
            <a href="../index.php" class="logo">Age of Donnation</a>
            <ul class="nav-links">
                <li><a href="dashboard.php">Tableau de bord</a></li>
                <li><a href="utilisateurs.php">Utilisateurs</a></li>
                <li><a href="dons.php">Dons</a></li>
                <li><a href="livreurs.php">Livreurs</a></li>
                <li><a href="statistiques.php" class="active">Statistiques</a></li>
            </ul>
            <div class="auth-buttons">
                <span style="color: #333; margin-right: 1rem;">Admin: <?php echo $_SESSION['user_nom']; ?></span>
                <a href="../auth/logout.php" class="btn btn-outline">Déconnexion</a>
            </div>
        </nav>
    </header>

    <div class="container">
        <div class="dashboard-header">
            <h1>📊 Statistiques de la Plateforme</h1>
            <p>Analysez les performances et l'impact de Age of Donnation</p>
        </div>

        <!-- Vue d'ensemble -->
        <div class="card">
            <div class="card-header">
                <h3>Vue d'ensemble</h3>
            </div>
            <div class="card-body">
                <div class="grid-4">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $stats['donateurs'] ?? 0; ?></div>
                        <div class="stat-label">Donateurs</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $stats['beneficiaires'] ?? 0; ?></div>
                        <div class="stat-label">Bénéficiaires</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $stats['livreurs_total'] ?? 0; ?></div>
                        <div class="stat-label">Livreurs</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo ($stats['dons_donnes'] ?? 0) + ($stats['dons_reserves'] ?? 0); ?></div>
                        <div class="stat-label">Dons attribués</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid-2">
            <!-- Statistiques des dons -->
            <div class="card">
                <div class="card-header">
                    <h3>📦 Statistiques des dons</h3>
                </div>
                <div class="card-body">
                    <div class="grid-2">
                        <div class="stat-card">
                            <div class="stat-number"><?php echo $stats['dons_disponibles'] ?? 0; ?></div>
                            <div class="stat-label">Disponibles</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number"><?php echo $stats['dons_reserves'] ?? 0; ?></div>
                            <div class="stat-label">Réservés</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number"><?php echo $stats['dons_donnes'] ?? 0; ?></div>
                            <div class="stat-label">Donnés</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number"><?php echo $stats['dons_7j'] ?? 0; ?></div>
                            <div class="stat-label">Nouveaux (7j)</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Statistiques des demandes -->
            <div class="card">
                <div class="card-header">
                    <h3>🙋 Statistiques des demandes</h3>
                </div>
                <div class="card-body">
                    <div class="grid-2">
                        <div class="stat-card">
                            <div class="stat-number"><?php echo $stats['demandes_attente'] ?? 0; ?></div>
                            <div class="stat-label">En attente</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number"><?php echo $stats['demandes_acceptees'] ?? 0; ?></div>
                            <div class="stat-label">Acceptées</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number"><?php echo $stats['demandes_refusees'] ?? 0; ?></div>
                            <div class="stat-label">Refusées</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number"><?php echo $stats['demandes_7j'] ?? 0; ?></div>
                            <div class="stat-label">Nouvelles (7j)</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Dons par catégorie -->
        <div class="card">
            <div class="card-header">
                <h3>📊 Dons par catégorie</h3>
            </div>
            <div class="card-body">
                <?php if(empty($categories)): ?>
                    <p style="text-align: center; color: #666; padding: 2rem;">Aucune donnée disponible</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Catégorie</th>
                                    <th>Nombre de dons</th>
                                    <th>Pourcentage</th>
                                    <th>Progression</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $total_dons = array_sum(array_column($categories, 'count'));
                                foreach($categories as $categorie): 
                                    $percentage = $total_dons > 0 ? round(($categorie['count'] / $total_dons) * 100, 1) : 0;
                                ?>
                                    <tr>
                                        <td><?php echo ucfirst($categorie['categorie']); ?></td>
                                        <td><?php echo $categorie['count']; ?></td>
                                        <td><?php echo $percentage; ?>%</td>
                                        <td>
                                            <div style="background: #e9ecef; border-radius: 10px; height: 10px; width: 100%;">
                                                <div style="background: #007bff; height: 100%; border-radius: 10px; width: <?php echo $percentage; ?>%;"></div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Activité des livreurs -->
        <div class="card">
            <div class="card-header">
                <h3>🚚 Activité des livreurs</h3>
            </div>
            <div class="card-body">
                <div class="grid-2">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $stats['livraisons_terminees'] ?? 0; ?></div>
                        <div class="stat-label">Livraisons terminées</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $stats['livraisons_en_cours'] ?? 0; ?></div>
                        <div class="stat-label">Livraisons en cours</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
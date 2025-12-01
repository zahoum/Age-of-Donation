<?php
require_once '../config/database.php';
checkAuth(['livreur']);

$database = new Database();
$db = $database->getConnection();

$user_id = $_SESSION['user_id'];

// Récupérer les missions disponibles
$query = "
    SELECT l.id as livraison_id, d.titre as don_titre, u.nom as beneficiaire_nom, 
           u2.nom as donateur_nom, do.adresse_retrait, do.ville,
           l.statut, l.created_at, l.frais_livraison
    FROM livraisons l
    INNER JOIN demandes de ON l.demande_id = de.id
    INNER JOIN dons do ON de.don_id = do.id
    INNER JOIN users u ON de.beneficiaire_id = u.id
    INNER JOIN users u2 ON do.donateur_id = u2.id
    WHERE l.livreur_id = :user_id OR l.livreur_id IS NULL
    ORDER BY l.created_at DESC
";
$stmt = $db->prepare($query);
$stmt->bindParam(":user_id", $user_id);
$stmt->execute();
$missions = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Missions - Age of Donnation</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <header class="header">
        <nav class="nav">
            <a href="../index.php" class="logo">Age of Donnation</a>
            <ul class="nav-links">
                <li><a href="dashboard.php">Tableau de bord</a></li>
                <li><a href="missions.php" class="active">Missions</a></li>
                <li><a href="profil.php">Mon profil</a></li>
            </ul>
            <div class="auth-buttons">
                <span style="color: #333; margin-right: 1rem;"><?php echo $_SESSION['user_nom']; ?></span>
                <a href="../auth/logout.php" class="btn btn-outline">Déconnexion</a>
            </div>
        </nav>
    </header>

    <div class="container">
        <div class="dashboard-header">
            <h1>🚚 Missions de livraison</h1>
            <p>Gérez vos missions et acceptez de nouvelles livraisons</p>
        </div>

        <div class="card">
            <div class="card-header">
                <h3>Liste des missions</h3>
            </div>
            <div class="card-body">
                <?php if(empty($missions)): ?>
                    <div style="text-align: center; padding: 3rem;">
                        <h3 style="color: #666; margin-bottom: 1rem;">Aucune mission disponible</h3>
                        <p style="color: #888;">Revenez plus tard pour de nouvelles missions</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Don</th>
                                    <th>Bénéficiaire</th>
                                    <th>Donateur</th>
                                    <th>Lieu de retrait</th>
                                    <th>Frais</th>
                                    <th>Statut</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($missions as $mission): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($mission['don_titre']); ?></td>
                                        <td><?php echo htmlspecialchars($mission['beneficiaire_nom']); ?></td>
                                        <td><?php echo htmlspecialchars($mission['donateur_nom']); ?></td>
                                        <td>
                                            <?php echo htmlspecialchars($mission['ville']); ?>
                                            <?php if($mission['adresse_retrait']): ?>
                                                <br><small style="color: #666;"><?php echo substr(htmlspecialchars($mission['adresse_retrait']), 0, 30); ?>...</small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo $mission['frais_livraison'] ? $mission['frais_livraison'] . '€' : 'Gratuit'; ?></td>
                                        <td><?php echo getStatusBadge($mission['statut']); ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($mission['created_at'])); ?></td>
                                        <td>
                                            <div style="display: flex; gap: 0.5rem;">
                                                <?php if(!$mission['livreur_id']): ?>
                                                    <a href="#" class="btn btn-success" style="padding: 0.3rem 0.6rem; font-size: 0.8rem;">Accepter</a>
                                                <?php elseif($mission['statut'] == 'assignee'): ?>
                                                    <a href="#" class="btn btn-primary" style="padding: 0.3rem 0.6rem; font-size: 0.8rem;">Démarrer</a>
                                                <?php elseif($mission['statut'] == 'en_cours'): ?>
                                                    <a href="#" class="btn btn-warning" style="padding: 0.3rem 0.6rem; font-size: 0.8rem;">Terminer</a>
                                                <?php endif; ?>
                                                <a href="#" class="btn btn-outline" style="padding: 0.3rem 0.6rem; font-size: 0.8rem;">Détails</a>
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
    </div>
</body>
</html>
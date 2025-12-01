<?php
require_once '../config/database.php';
checkAuth(['admin']);

$database = new Database();
$db = $database->getConnection();

// Récupérer tous les livreurs
$query = "
    SELECT u.*, l.vehicule_type, l.plaque_immatriculation, l.zone_intervention, l.statut as livreur_statut, l.note_moyenne
    FROM users u
    INNER JOIN livreurs l ON u.id = l.user_id
    ORDER BY u.created_at DESC
";
$stmt = $db->prepare($query);
$stmt->execute();
$livreurs = $stmt->fetchAll(PDO::FETCH_ASSOC);

$success = '';
$error = '';

// Gérer l'activation/désactivation des livreurs
if (isset($_GET['action']) && isset($_GET['id'])) {
    $user_id = $_GET['id'];
    $action = $_GET['action'];
    
    $new_status = $action == 'activate' ? 'actif' : 'inactif';
    
    try {
        $query = "UPDATE livreurs SET statut = :statut WHERE user_id = :user_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":statut", $new_status);
        $stmt->bindParam(":user_id", $user_id);
        
        if ($stmt->execute()) {
            // Si activation, activer aussi le compte utilisateur
            if ($action == 'activate') {
                $query = "UPDATE users SET status = 'active' WHERE id = :user_id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(":user_id", $user_id);
                $stmt->execute();
            }
            
            $success = "Livreur " . ($action == 'activate' ? 'activé' : 'désactivé') . " avec succès";
        }
    } catch(PDOException $e) {
        $error = "Erreur: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Livreurs - Age of Donnation</title>
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
                <li><a href="livreurs.php" class="active">Livreurs</a></li>
                <li><a href="statistiques.php">Statistiques</a></li>
            </ul>
            <div class="auth-buttons">
                <span style="color: #333; margin-right: 1rem;">Admin: <?php echo $_SESSION['user_nom']; ?></span>
                <a href="../auth/logout.php" class="btn btn-outline">Déconnexion</a>
            </div>
        </nav>
    </header>

    <div class="container">
        <div class="dashboard-header">
            <h1>🚚 Gestion des Livreurs</h1>
            <p>Gérez les livreurs bénévoles de la plateforme</p>
        </div>

        <?php if($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h3>Liste des livreurs</h3>
            </div>
            <div class="card-body">
                <?php if(empty($livreurs)): ?>
                    <p style="text-align: center; color: #666; padding: 2rem;">Aucun livreur inscrit</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Nom</th>
                                    <th>Email</th>
                                    <th>Téléphone</th>
                                    <th>Véhicule</th>
                                    <th>Zone</th>
                                    <th>Statut</th>
                                    <th>Note</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($livreurs as $livreur): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($livreur['nom']); ?></td>
                                        <td><?php echo htmlspecialchars($livreur['email']); ?></td>
                                        <td><?php echo htmlspecialchars($livreur['telephone'] ?? 'Non renseigné'); ?></td>
                                        <td>
                                            <span class="badge badge-secondary">
                                                <?php 
                                                $vehicules = [
                                                    'velo' => 'Vélo',
                                                    'moto' => 'Moto', 
                                                    'voiture' => 'Voiture',
                                                    'camion' => 'Camion'
                                                ];
                                                echo $vehicules[$livreur['vehicule_type']] ?? $livreur['vehicule_type'];
                                                ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($livreur['zone_intervention']); ?></td>
                                        <td>
                                            <span class="badge <?php echo $livreur['livreur_statut'] == 'actif' ? 'badge-success' : 'badge-secondary'; ?>">
                                                <?php echo ucfirst($livreur['livreur_statut']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge badge-info">
                                                <?php echo number_format($livreur['note_moyenne'], 1); ?>/5
                                            </span>
                                        </td>
                                        <td><?php echo date('d/m/Y', strtotime($livreur['created_at'])); ?></td>
                                        <td>
                                            <div style="display: flex; gap: 0.5rem;">
                                                <?php if($livreur['livreur_statut'] == 'actif'): ?>
                                                    <a href="?action=deactivate&id=<?php echo $livreur['id']; ?>" class="btn btn-danger" style="padding: 0.3rem 0.6rem; font-size: 0.8rem;">Désactiver</a>
                                                <?php else: ?>
                                                    <a href="?action=activate&id=<?php echo $livreur['id']; ?>" class="btn btn-success" style="padding: 0.3rem 0.6rem; font-size: 0.8rem;">Activer</a>
                                                <?php endif; ?>
                                                <a href="#" class="btn btn-outline" style="padding: 0.3rem 0.6rem; font-size: 0.8rem;">Voir</a>
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
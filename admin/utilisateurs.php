<?php
require_once '../config/database.php';
checkAuth(['admin']);

$database = new Database();
$db = $database->getConnection();

// Récupérer tous les utilisateurs (sauf admin)
$query = "SELECT * FROM users WHERE type != 'admin' ORDER BY created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$utilisateurs = $stmt->fetchAll(PDO::FETCH_ASSOC);

$success = '';
$error = '';

// Gérer l'activation/désactivation
if (isset($_GET['action']) && isset($_GET['id'])) {
    $user_id = $_GET['id'];
    $action = $_GET['action'];
    
    $new_status = $action == 'activate' ? 'active' : 'inactive';
    
    try {
        $query = "UPDATE users SET status = :status WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":status", $new_status);
        $stmt->bindParam(":id", $user_id);
        
        if ($stmt->execute()) {
            $success = "Utilisateur " . ($action == 'activate' ? 'activé' : 'désactivé') . " avec succès";
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
    <title>Gestion des Utilisateurs - Age of Donnation</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <header class="header">
        <nav class="nav">
            <a href="../index.php" class="logo">Age of Donnation</a>
            <ul class="nav-links">
                <li><a href="dashboard.php">Tableau de bord</a></li>
                <li><a href="utilisateurs.php" class="active">Utilisateurs</a></li>
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
        <div class="dashboard-header">
            <h1>👥 Gestion des Utilisateurs</h1>
            <p>Gérez les comptes utilisateurs de la plateforme</p>
        </div>

        <?php if($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h3>Liste des utilisateurs</h3>
            </div>
            <div class="card-body">
                <?php if(empty($utilisateurs)): ?>
                    <p style="text-align: center; color: #666; padding: 2rem;">Aucun utilisateur</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Nom</th>
                                    <th>Email</th>
                                    <th>Type</th>
                                    <th>Téléphone</th>
                                    <th>Statut</th>
                                    <th>Date d'inscription</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($utilisateurs as $user): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($user['nom']); ?></td>
                                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                                        <td>
                                            <span class="badge 
                                                <?php 
                                                $badge_type = [
                                                    'donateur' => 'badge-primary',
                                                    'beneficiaire' => 'badge-success', 
                                                    'livreur' => 'badge-warning'
                                                ];
                                                echo $badge_type[$user['type']] ?? 'badge-secondary';
                                                ?>
                                            ">
                                                <?php echo ucfirst($user['type']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($user['telephone'] ?? 'Non renseigné'); ?></td>
                                        <td>
                                            <span class="badge <?php echo $user['status'] == 'active' ? 'badge-success' : 'badge-danger'; ?>">
                                                <?php echo ucfirst($user['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('d/m/Y', strtotime($user['created_at'])); ?></td>
                                        <td>
                                            <div style="display: flex; gap: 0.5rem;">
                                                <?php if($user['status'] == 'active'): ?>
                                                    <a href="?action=deactivate&id=<?php echo $user['id']; ?>" class="btn btn-danger" style="padding: 0.3rem 0.6rem; font-size: 0.8rem;">Désactiver</a>
                                                <?php else: ?>
                                                    <a href="?action=activate&id=<?php echo $user['id']; ?>" class="btn btn-success" style="padding: 0.3rem 0.6rem; font-size: 0.8rem;">Activer</a>
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
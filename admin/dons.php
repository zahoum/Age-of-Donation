<?php
require_once '../config/database.php';
checkAuth(['admin']);

$database = new Database();
$db = $database->getConnection();

// Récupérer tous les dons
$query = "SELECT d.*, u.nom as donateur_nom FROM dons d INNER JOIN users u ON d.donateur_id = u.id ORDER BY d.created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$dons = $stmt->fetchAll(PDO::FETCH_ASSOC);

$success = '';
$error = '';

// Gérer la suppression
if (isset($_GET['action']) && isset($_GET['id'])) {
    $don_id = $_GET['id'];
    $action = $_GET['action'];
    
    if ($action == 'delete') {
        try {
            $query = "DELETE FROM dons WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(":id", $don_id);
            
            if ($stmt->execute()) {
                $success = "Don supprimé avec succès";
            }
        } catch(PDOException $e) {
            $error = "Erreur: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Dons - Age of Donnation</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <header class="header">
        <nav class="nav">
            <a href="../index.php" class="logo">Age of Donnation</a>
            <ul class="nav-links">
                <li><a href="dashboard.php">Tableau de bord</a></li>
                <li><a href="utilisateurs.php">Utilisateurs</a></li>
                <li><a href="dons.php" class="active">Dons</a></li>
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
            <h1>📦 Gestion des Dons</h1>
            <p>Supervisez tous les dons publiés sur la plateforme</p>
        </div>

        <?php if($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h3>Liste des dons</h3>
            </div>
            <div class="card-body">
                <?php if(empty($dons)): ?>
                    <p style="text-align: center; color: #666; padding: 2rem;">Aucun don publié</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Titre</th>
                                    <th>Description</th>
                                    <th>Catégorie</th>
                                    <th>Donateur</th>
                                    <th>Ville</th>
                                    <th>Statut</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($dons as $don): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($don['titre']); ?></strong>
                                        </td>
                                        <td>
                                            <?php echo strlen($don['description']) > 50 ? substr(htmlspecialchars($don['description']), 0, 50) . '...' : htmlspecialchars($don['description']); ?>
                                        </td>
                                        <td><?php echo ucfirst($don['categorie']); ?></td>
                                        <td><?php echo htmlspecialchars($don['donateur_nom']); ?></td>
                                        <td><?php echo htmlspecialchars($don['ville']); ?></td>
                                        <td><?php echo getStatusBadge($don['statut']); ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($don['created_at'])); ?></td>
                                        <td>
                                            <div style="display: flex; gap: 0.5rem;">
                                                <a href="#" class="btn btn-outline" style="padding: 0.3rem 0.6rem; font-size: 0.8rem;">Voir</a>
                                                <a href="?action=delete&id=<?php echo $don['id']; ?>" class="btn btn-danger" style="padding: 0.3rem 0.6rem; font-size: 0.8rem;" onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce don?')">Supprimer</a>
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
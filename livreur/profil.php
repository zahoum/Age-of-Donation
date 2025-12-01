<?php
require_once '../config/database.php';
checkAuth(['livreur']);

$database = new Database();
$db = $database->getConnection();

$user_id = $_SESSION['user_id'];

// Récupérer les informations du livreur
$query = "
    SELECT u.*, l.vehicule_type, l.plaque_immatriculation, l.zone_intervention, l.statut as livreur_statut, l.note_moyenne
    FROM users u
    INNER JOIN livreurs l ON u.id = l.user_id
    WHERE u.id = :user_id
";
$stmt = $db->prepare($query);
$stmt->bindParam(":user_id", $user_id);
$stmt->execute();
$livreur = $stmt->fetch(PDO::FETCH_ASSOC);

$success = '';
$error = '';

if ($_POST) {
    $telephone = trim($_POST['telephone']);
    $vehicule_type = $_POST['vehicule_type'];
    $plaque_immatriculation = trim($_POST['plaque_immatriculation']);
    $zone_intervention = trim($_POST['zone_intervention']);
    
    try {
        $db->beginTransaction();
        
        // Mettre à jour l'utilisateur
        $query = "UPDATE users SET telephone = :telephone, updated_at = NOW() WHERE id = :user_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":telephone", $telephone);
        $stmt->bindParam(":user_id", $user_id);
        $stmt->execute();
        
        // Mettre à jour le livreur
        $query = "UPDATE livreurs SET vehicule_type = :vehicule_type, plaque_immatriculation = :plaque_immatriculation, 
                  zone_intervention = :zone_intervention, updated_at = NOW() WHERE user_id = :user_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":vehicule_type", $vehicule_type);
        $stmt->bindParam(":plaque_immatriculation", $plaque_immatriculation);
        $stmt->bindParam(":zone_intervention", $zone_intervention);
        $stmt->bindParam(":user_id", $user_id);
        $stmt->execute();
        
        $db->commit();
        $success = "Profil mis à jour avec succès!";
        
        // Recharger les données
        $stmt = $db->prepare($query);
        $stmt->bindParam(":user_id", $user_id);
        $stmt->execute();
        $livreur = $stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch(PDOException $e) {
        $db->rollBack();
        $error = "Erreur lors de la mise à jour: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Profil Livreur - Age of Donnation</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <header class="header">
        <nav class="nav">
            <a href="../index.php" class="logo">Age of Donnation</a>
            <ul class="nav-links">
                <li><a href="dashboard.php">Tableau de bord</a></li>
                <li><a href="missions.php">Missions</a></li>
                <li><a href="profil.php" class="active">Mon profil</a></li>
            </ul>
            <div class="auth-buttons">
                <span style="color: #333; margin-right: 1rem;"><?php echo $_SESSION['user_nom']; ?></span>
                <a href="../auth/logout.php" class="btn btn-outline">Déconnexion</a>
            </div>
        </nav>
    </header>

    <div class="container">
        <div class="dashboard-header">
            <h1>👤 Mon profil livreur</h1>
            <p>Gérez vos informations personnelles et professionnelles</p>
        </div>

        <div class="grid-2">
            <!-- Informations du profil -->
            <div class="card">
                <div class="card-header">
                    <h3>Informations personnelles</h3>
                </div>
                <div class="card-body">
                    <?php if($success): ?>
                        <div class="alert alert-success"><?php echo $success; ?></div>
                    <?php endif; ?>
                    
                    <?php if($error): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="form-group">
                            <label class="form-label">Nom complet</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($livreur['nom']); ?>" disabled>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" value="<?php echo htmlspecialchars($livreur['email']); ?>" disabled>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Téléphone *</label>
                            <input type="tel" name="telephone" class="form-control" value="<?php echo htmlspecialchars($livreur['telephone'] ?? ''); ?>" required>
                        </div>

                        <h4 style="margin: 2rem 0 1rem 0; color: #333;">Informations professionnelles</h4>
                        
                        <div class="form-group">
                            <label class="form-label">Type de véhicule *</label>
                            <select name="vehicule_type" class="form-control" required>
                                <option value="velo" <?php echo ($livreur['vehicule_type'] ?? '') == 'velo' ? 'selected' : ''; ?>>Vélo</option>
                                <option value="moto" <?php echo ($livreur['vehicule_type'] ?? '') == 'moto' ? 'selected' : ''; ?>>Moto</option>
                                <option value="voiture" <?php echo ($livreur['vehicule_type'] ?? '') == 'voiture' ? 'selected' : ''; ?>>Voiture</option>
                                <option value="camion" <?php echo ($livreur['vehicule_type'] ?? '') == 'camion' ? 'selected' : ''; ?>>Camion</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Plaque d'immatriculation</label>
                            <input type="text" name="plaque_immatriculation" class="form-control" value="<?php echo htmlspecialchars($livreur['plaque_immatriculation'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Zone d'intervention *</label>
                            <input type="text" name="zone_intervention" class="form-control" value="<?php echo htmlspecialchars($livreur['zone_intervention'] ?? ''); ?>" required>
                        </div>

                        <button type="submit" class="btn btn-primary">Mettre à jour le profil</button>
                    </form>
                </div>
            </div>

            <!-- Statut et statistiques -->
            <div class="card">
                <div class="card-header">
                    <h3>Statut et performances</h3>
                </div>
                <div class="card-body">
                    <div style="text-align: center; padding: 1rem;">
                        <div class="stat-card" style="margin-bottom: 2rem;">
                            <div class="stat-number"><?php echo $livreur['note_moyenne'] ?? '5.0'; ?>/5</div>
                            <div class="stat-label">Note moyenne</div>
                        </div>
                        
                        <div style="margin-bottom: 2rem;">
                            <h4>Statut: 
                                <span class="badge <?php echo $livreur['livreur_statut'] == 'actif' ? 'badge-success' : 'badge-secondary'; ?>">
                                    <?php echo ucfirst($livreur['livreur_statut']); ?>
                                </span>
                            </h4>
                            <?php if($livreur['livreur_statut'] == 'actif'): ?>
                                <p style="color: #28a745;">✅ Votre compte est actif</p>
                            <?php else: ?>
                                <p style="color: #6c757d;">⏳ En attente d'activation</p>
                            <?php endif; ?>
                        </div>
                        
                        <div>
                            <h4>Informations de compte</h4>
                            <p><strong>Membre depuis:</strong> <?php echo date('d/m/Y', strtotime($livreur['created_at'])); ?></p>
                            <p><strong>Dernière mise à jour:</strong> <?php echo date('d/m/Y', strtotime($livreur['updated_at'] ?? $livreur['created_at'])); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
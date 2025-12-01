<?php
require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$success = '';
$error = '';

if ($_POST) {
    $nom = trim($_POST['nom']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $telephone = trim($_POST['telephone']);
    $vehicule_type = $_POST['vehicule_type'];
    $plaque_immatriculation = trim($_POST['plaque_immatriculation']);
    $zone_intervention = trim($_POST['zone_intervention']);

    if (empty($nom) || empty($email) || empty($password)) {
        $error = "Tous les champs obligatoires doivent être remplis";
    } elseif ($password !== $confirm_password) {
        $error = "Les mots de passe ne correspondent pas";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Format d'email invalide";
    } else {
        $query = "SELECT id FROM users WHERE email = :email";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":email", $email);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $error = "Cet email est déjà utilisé";
        } else {
            try {
                $db->beginTransaction();
                
                // Créer l'utilisateur
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $query = "INSERT INTO users (nom, email, password, type, telephone, status, created_at) 
                          VALUES (:nom, :email, :password, 'livreur', :telephone, 'pending', NOW())";
                
                $stmt = $db->prepare($query);
                $stmt->bindParam(":nom", $nom);
                $stmt->bindParam(":email", $email);
                $stmt->bindParam(":password", $hashed_password);
                $stmt->bindParam(":telephone", $telephone);
                $stmt->execute();
                
                $user_id = $db->lastInsertId();
                
                // Créer le profil livreur
                $query = "INSERT INTO livreurs (user_id, vehicule_type, plaque_immatriculation, zone_intervention, statut, created_at) 
                          VALUES (:user_id, :vehicule_type, :plaque_immatriculation, :zone_intervention, 'inactif', NOW())";
                
                $stmt = $db->prepare($query);
                $stmt->bindParam(":user_id", $user_id);
                $stmt->bindParam(":vehicule_type", $vehicule_type);
                $stmt->bindParam(":plaque_immatriculation", $plaque_immatriculation);
                $stmt->bindParam(":zone_intervention", $zone_intervention);
                $stmt->execute();
                
                $db->commit();
                
                $success = "Votre inscription a été envoyée avec succès! Elle doit être validée par un administrateur. Vous recevrez un email de confirmation.";
                $_POST = array();
            } catch(PDOException $e) {
                $db->rollBack();
                $error = "Erreur lors de l'inscription: " . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Devenir Livreur - Age of Donnation</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="header">
        <nav class="nav">
            <a href="../index.php" class="logo">Age of Donnation</a>
            <div class="auth-buttons">
                <a href="../auth/login.php" class="btn btn-outline">Connexion</a>
            </div>
        </nav>
    </div>

    <div class="container">
        <div class="card" style="max-width: 600px; margin: 0 auto;">
            <div class="card-header">
                <h2 style="text-align: center; margin: 0;">Devenir Livreur Bénévole</h2>
            </div>
            <div class="card-body">
                <?php if($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>

                <form method="POST">
                    <h3 style="margin-bottom: 1rem; color: #333;">Informations personnelles</h3>
                    <div class="grid-2">
                        <div class="form-group">
                            <label class="form-label">Nom complet *</label>
                            <input type="text" name="nom" class="form-control" value="<?php echo $_POST['nom'] ?? ''; ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Email *</label>
                            <input type="email" name="email" class="form-control" value="<?php echo $_POST['email'] ?? ''; ?>" required>
                        </div>
                    </div>

                    <div class="grid-2">
                        <div class="form-group">
                            <label class="form-label">Mot de passe *</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Confirmer le mot de passe *</label>
                            <input type="password" name="confirm_password" class="form-control" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Téléphone *</label>
                        <input type="tel" name="telephone" class="form-control" value="<?php echo $_POST['telephone'] ?? ''; ?>" required>
                    </div>

                    <h3 style="margin: 2rem 0 1rem 0; color: #333;">Informations du véhicule</h3>
                    <div class="grid-2">
                        <div class="form-group">
                            <label class="form-label">Type de véhicule *</label>
                            <select name="vehicule_type" class="form-control" required>
                                <option value="">Choisir un type</option>
                                <option value="velo" <?php echo ($_POST['vehicule_type'] ?? '') == 'velo' ? 'selected' : ''; ?>>Vélo</option>
                                <option value="moto" <?php echo ($_POST['vehicule_type'] ?? '') == 'moto' ? 'selected' : ''; ?>>Moto</option>
                                <option value="voiture" <?php echo ($_POST['vehicule_type'] ?? '') == 'voiture' ? 'selected' : ''; ?>>Voiture</option>
                                <option value="camion" <?php echo ($_POST['vehicule_type'] ?? '') == 'camion' ? 'selected' : ''; ?>>Camion</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Plaque d'immatriculation</label>
                            <input type="text" name="plaque_immatriculation" class="form-control" value="<?php echo $_POST['plaque_immatriculation'] ?? ''; ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Zone d'intervention *</label>
                        <input type="text" name="zone_intervention" class="form-control" value="<?php echo $_POST['zone_intervention'] ?? ''; ?>" required placeholder="Ex: Paris, Lyon, Marseille...">
                    </div>

                    <button type="submit" class="btn btn-primary" style="width: 100%;">S'inscrire comme livreur</button>
                </form>
                
                <div style="text-align: center; margin-top: 1.5rem;">
                    Déjà un compte? <a href="../auth/login.php">Se connecter</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
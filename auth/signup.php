<?php
require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$error = '';
$success = '';

if ($_POST) {
    $nom = trim($_POST['nom']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $type = $_POST['type'];
    $telephone = trim($_POST['telephone']);

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
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $query = "INSERT INTO users (nom, email, password, type, telephone, created_at) 
                      VALUES (:nom, :email, :password, :type, :telephone, NOW())";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(":nom", $nom);
            $stmt->bindParam(":email", $email);
            $stmt->bindParam(":password", $hashed_password);
            $stmt->bindParam(":type", $type);
            $stmt->bindParam(":telephone", $telephone);

            if ($stmt->execute()) {
                $success = "Compte créé avec succès! Vous pouvez vous connecter.";
                $_POST = array();
            } else {
                $error = "Erreur lors de la création du compte";
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
    <title>Inscription - Age of Donnation</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="header">
        <nav class="nav">
            <a href="../index.php" class="logo">Age of Donnation</a>
            <div class="auth-buttons">
                <a href="login.php" class="btn btn-outline">Connexion</a>
            </div>
        </nav>
    </div>

    <div class="container">
        <div class="card" style="max-width: 500px; margin: 0 auto;">
            <div class="card-header">
                <h2 style="text-align: center; margin: 0;">Créer un compte</h2>
            </div>
            <div class="card-body">
                <?php if($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>

                <form method="POST">
                    <div class="form-group">
                        <label class="form-label">Nom complet *</label>
                        <input type="text" name="nom" class="form-control" value="<?php echo $_POST['nom'] ?? ''; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Email *</label>
                        <input type="email" name="email" class="form-control" value="<?php echo $_POST['email'] ?? ''; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Mot de passe *</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Confirmer le mot de passe *</label>
                        <input type="password" name="confirm_password" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Téléphone</label>
                        <input type="tel" name="telephone" class="form-control" value="<?php echo $_POST['telephone'] ?? ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Type de compte *</label>
                        <select name="type" class="form-control" required>
                            <option value="">Choisir un type</option>
                            <option value="donateur" <?php echo ($_POST['type'] ?? '') == 'donateur' ? 'selected' : ''; ?>>Donateur</option>
                            <option value="beneficiaire" <?php echo ($_POST['type'] ?? '') == 'beneficiaire' ? 'selected' : ''; ?>>Bénéficiaire</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn btn-primary" style="width: 100%;">S'inscrire</button>
                </form>
                
                <div style="text-align: center; margin-top: 1.5rem;">
                    Déjà un compte? <a href="login.php">Se connecter</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
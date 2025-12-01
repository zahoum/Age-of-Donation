<?php
require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$error = '';
$success = '';

if ($_POST) {
    $email = trim($_POST['email']);
    
    $query = "SELECT id FROM users WHERE email = :email";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":email", $email);
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        $success = "Un lien de réinitialisation a été envoyé à votre email.";
    } else {
        $error = "Aucun compte trouvé avec cet email.";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mot de passe oublié - Age of Donnation</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="header">
        <nav class="nav">
            <a href="../index.php" class="logo">Age of Donnation</a>
        </nav>
    </div>

    <div class="container">
        <div class="card" style="max-width: 400px; margin: 0 auto;">
            <div class="card-header">
                <h2 style="text-align: center; margin: 0;">Mot de passe oublié</h2>
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
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" value="<?php echo $_POST['email'] ?? ''; ?>" required>
                    </div>
                    
                    <button type="submit" class="btn btn-primary" style="width: 100%;">Réinitialiser le mot de passe</button>
                </form>
                
                <div style="text-align: center; margin-top: 1.5rem;">
                    <a href="login.php">Retour à la connexion</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
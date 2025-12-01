<?php
require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$error = '';

if ($_POST) {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    $query = "SELECT id, nom, email, password, type, status FROM users WHERE email = :email";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":email", $email);
    $stmt->execute();

    if ($stmt->rowCount() == 1) {
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (password_verify($password, $user['password'])) {
            if ($user['status'] == 'active') {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_nom'] = $user['nom'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_type'] = $user['type'];
                
                // Redirection selon le type d'utilisateur
                switch($user['type']) {
                    case 'donateur':
                        header('Location: ../donateur/dashboard.php');
                        break;
                    case 'beneficiaire':
                        header('Location: ../beneficiaire/dashboard.php');
                        break;
                    case 'livreur':
                        header('Location: ../livreur/dashboard.php');
                        break;
                    case 'admin':
                        header('Location: ../admin/dashboard.php');
                        break;
                    default:
                        header('Location: ../index.php');
                }
                exit();
            } else {
                $error = "Votre compte est désactivé. Contactez l'administrateur.";
            }
        } else {
            $error = "Mot de passe incorrect";
        }
    } else {
        $error = "Aucun compte trouvé avec cet email";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - Age of Donnation</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="header">
        <nav class="nav">
            <a href="../index.php" class="logo">Age of Donnation</a>
            <div class="auth-buttons">
                <a href="signup.php" class="btn btn-primary">S'inscrire</a>
            </div>
        </nav>
    </div>

    <div class="container">
        <div class="card" style="max-width: 400px; margin: 0 auto;">
            <div class="card-header">
                <h2 style="text-align: center; margin: 0;">Connexion</h2>
            </div>
            <div class="card-body">
                <?php if($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <form method="POST">
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" value="<?php echo $_POST['email'] ?? ''; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Mot de passe</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    
                    <button type="submit" class="btn btn-primary" style="width: 100%;">Se connecter</button>
                </form>
                
                <div style="text-align: center; margin-top: 1.5rem;">
                    <a href="forgot-password.php">Mot de passe oublié?</a><br>
                    <span style="color: #666;">Pas de compte? </span><a href="signup.php">S'inscrire</a>
                </div>

                <!-- Comptes de test
                <div style="margin-top: 2rem; padding: 1rem; background: #f8f9fa; border-radius: 5px; font-size: 0.9rem;">
                    <strong>Comptes de test:</strong><br>
                    <strong>Admin:</strong> admin@ageofdonnation.org / admin123<br>
                    <strong>Bénéficiaire:</strong> marie.martin@email.com / admin123<br>
                    <strong>Donateur:</strong> jean.dupont@email.com / admin123
                </div> -->
            </div>
        </div>
    </div>
</body>
</html>
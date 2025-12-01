<?php
// Script de réinitialisation du mot de passe admin
$host = 'localhost';
$dbname = 'age_of_donnation';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Nouveau mot de passe hashé pour "admin123"
    $new_password_hash = password_hash('admin123', PASSWORD_DEFAULT);
    
    // Mettre à jour le mot de passe admin
    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = 'admin@ageofdonnation.org'");
    $stmt->execute([$new_password_hash]);
    
    echo "<h2>✅ Mot de passe admin réinitialisé avec succès!</h2>";
    echo "<p><strong>Email:</strong> admin@ageofdonnation.org</p>";
    echo "<p><strong>Nouveau mot de passe:</strong> admin123</p>";
    echo "<a href='auth/login.php'>Se connecter</a>";
    
} catch(PDOException $e) {
    echo "Erreur: " . $e->getMessage();
}
?>
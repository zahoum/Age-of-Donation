<?php
// Script de réinitialisation des mots de passe
$host = 'localhost';
$dbname = 'age_of_donnation';
$username = 'root';
$password = '';

echo "<h2>🔧 Réinitialisation des mots de passe</h2>";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Nouveau mot de passe hashé pour "admin123"
    $new_password = 'admin123';
    $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
    
    // Mettre à jour le mot de passe admin
    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = 'admin@ageofdonnation.org'");
    $stmt->execute([$new_password_hash]);
    
    echo "<div style='background: #d4edda; color: #155724; padding: 1rem; border-radius: 5px; margin: 1rem 0;'>";
    echo "✅ <strong>Admin password reset successfully!</strong><br>";
    echo "Email: admin@ageofdonnation.org<br>";
    echo "Password: admin123";
    echo "</div>";
    
    // Réinitialiser aussi les autres comptes de test
    $test_users = [
        'jean.dupont@email.com',
        'marie.martin@email.com', 
        'pierre.durand@email.com'
    ];
    
    foreach($test_users as $email) {
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
        $stmt->execute([$new_password_hash, $email]);
    }
    
    echo "<div style='background: #d1ecf1; color: #0c5460; padding: 1rem; border-radius: 5px; margin: 1rem 0;'>";
    echo "✅ Tous les comptes de test ont été réinitialisés<br>";
    echo "Mot de passe pour tous: <strong>admin123</strong>";
    echo "</div>";
    
    echo "<hr>";
    echo "<h3>🔑 Comptes disponibles:</h3>";
    echo "<ul>";
    echo "<li><strong>Admin:</strong> admin@ageofdonnation.org / admin123</li>";
    echo "<li><strong>Donateur:</strong> jean.dupont@email.com / admin123</li>";
    echo "<li><strong>Bénéficiaire:</strong> marie.martin@email.com / admin123</li>";
    echo "<li><strong>Livreur:</strong> pierre.durand@email.com / admin123</li>";
    echo "</ul>";
    
    echo "<div style='margin-top: 2rem;'>";
    echo "<a href='auth/login.php' style='padding: 1rem 2rem; background: #007bff; color: white; text-decoration: none; border-radius: 5px;'>Se connecter</a>";
    echo "</div>";
    
} catch(PDOException $e) {
    echo "<div style='background: #f8d7da; color: #721c24; padding: 1rem; border-radius: 5px;'>";
    echo "❌ Erreur: " . $e->getMessage();
    echo "</div>";
    
    // Aide au débogage
    echo "<h3>🔧 Vérifications:</h3>";
    echo "<ul>";
    echo "<li>✅ XAMPP MySQL est-il démarré?</li>";
    echo "<li>✅ La base 'age_of_donnation' existe-t-elle?</li>";
    echo "<li>✅ La table 'users' existe-t-elle?</li>";
    echo "</ul>";
}
?>
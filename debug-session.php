<?php
session_start();
echo "<h2>🔍 Debug Session</h2>";
echo "<pre>";
echo "Session ID: " . session_id() . "\n";
echo "Session data:\n";
print_r($_SESSION);
echo "</pre>";

// Test de connexion à la base de données
try {
    $pdo = new PDO("mysql:host=localhost;dbname=age_of_donnation", "root", "");
    echo "<p style='color: green;'>✅ Connexion DB réussie</p>";
    
    // Vérifier l'utilisateur
    if (isset($_SESSION['user_id'])) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        
        if ($user) {
            echo "<p style='color: green;'>✅ Utilisateur trouvé en DB: " . $user['email'] . " (" . $user['type'] . ")</p>";
        } else {
            echo "<p style='color: red;'>❌ Utilisateur NON trouvé en DB</p>";
        }
    }
} catch(PDOException $e) {
    echo "<p style='color: red;'>❌ Erreur DB: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<h3>Test d'accès:</h3>";
echo "<ul>";
echo "<li><a href='beneficiaire/dashboard.php'>Accès bénéficiaire</a></li>";
echo "<li><a href='donateur/dashboard.php'>Accès donateur</a></li>";
echo "<li><a href='admin/dashboard.php'>Accès admin</a></li>";
echo "</ul>";
?>
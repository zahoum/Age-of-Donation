<?php
// Vérifier si l'utilisateur est connecté
$isLoggedIn = isset($_SESSION['user_id']);
$userType = $_SESSION['user_type'] ?? '';
$userName = $_SESSION['user_nom'] ?? '';
$pageTitle = $pageTitle ?? 'Age of Donnation';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <header class="header">
        <nav class="nav">
            <a href="../index.php" class="logo">Age of Donnation</a>
            
            <?php if($isLoggedIn): ?>
                <!-- Navigation pour utilisateur connecté -->
                <ul class="nav-links">
                    <?php if($userType == 'donateur'): ?>
                        <li><a href="../donateur/dashboard.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">Tableau de bord</a></li>
                        <li><a href="../donateur/publier-don.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'publier-don.php' ? 'active' : ''; ?>">Publier un don</a></li>
                        <li><a href="../donateur/mes-dons.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'mes-dons.php' ? 'active' : ''; ?>">Mes dons</a></li>
                        <li><a href="../donateur/messagerie.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'messagerie.php' ? 'active' : ''; ?>">Messagerie</a></li>
                    <?php elseif($userType == 'beneficiaire'): ?>
                        <li><a href="../beneficiaire/dashboard.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">Tableau de bord</a></li>
                        <li><a href="../beneficiaire/catalogue.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'catalogue.php' ? 'active' : ''; ?>">Catalogue</a></li>
                        <li><a href="../beneficiaire/mes-demandes.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'mes-demandes.php' ? 'active' : ''; ?>">Mes demandes</a></li>
                        <li><a href="../beneficiaire/messagerie.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'messagerie.php' ? 'active' : ''; ?>">Messagerie</a></li>
                    <?php elseif($userType == 'livreur'): ?>
                        <li><a href="../livreur/dashboard.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">Tableau de bord</a></li>
                        <li><a href="../livreur/missions.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'missions.php' ? 'active' : ''; ?>">Missions</a></li>
                        <li><a href="../livreur/profil.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'profil.php' ? 'active' : ''; ?>">Profil</a></li>
                    <?php elseif($userType == 'admin'): ?>
                        <li><a href="../admin/dashboard.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">Tableau de bord</a></li>
                        <li><a href="../admin/utilisateurs.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'utilisateurs.php' ? 'active' : ''; ?>">Utilisateurs</a></li>
                        <li><a href="../admin/dons.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'dons.php' ? 'active' : ''; ?>">Dons</a></li>
                        <li><a href="../admin/livreurs.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'livreurs.php' ? 'active' : ''; ?>">Livreurs</a></li>
                        <li><a href="../admin/statistiques.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'statistiques.php' ? 'active' : ''; ?>">Statistiques</a></li>
                    <?php endif; ?>
                </ul>
                
                <div class="auth-buttons">
                    <span style="color: #333; margin-right: 1rem;"><?php echo $userName; ?> (<?php echo ucfirst($userType); ?>)</span>
                    <a href="../auth/logout.php" class="btn btn-outline">Déconnexion</a>
                </div>
                
            <?php else: ?>
                <!-- Navigation pour visiteur -->
                <ul class="nav-links">
                    <li><a href="../index.php">Accueil</a></li>
                    <li><a href="../index.php#about">Qui sommes-nous</a></li>
                    <li><a href="../index.php#contact">Contact</a></li>
                </ul>
                <div class="auth-buttons">
                    <a href="../auth/login.php" class="btn btn-outline">Connexion</a>
                    <a href="../auth/signup.php" class="btn btn-primary">Inscription</a>
                </div>
            <?php endif; ?>
        </nav>
    </header>
    <main>
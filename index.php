<?php
require_once 'config/database.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Age of Donnation - Plateforme de dons 100% bénévolat</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <!-- Header -->
    <header class="header">
        <nav class="nav">
            <a href="index.php" class="logo">Age of Donnation</a>
            <ul class="nav-links">
                <li><a href="index.php">Accueil</a></li>
                <li><a href="#about">Qui sommes-nous</a></li>
                <li><a href="#contact">Contact</a></li>
            </ul>
            <div class="auth-buttons">
                <a href="auth/login.php" class="btn btn-outline">Connexion</a>
                <a href="auth/signup.php" class="btn btn-primary">Inscription</a>
            </div>
        </nav>
    </header>

    <!-- Hero Section -->
    <section class="hero">
        <div class="container">
            <h1>Partagez, Faites la Différence</h1>
            <p>Plateforme 100% basée sur le bénévolat. Connectons ceux qui ont à donner avec ceux qui en ont besoin.</p>
            <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
                <a href="auth/signup.php?type=donateur" class="btn btn-primary">Faire un don</a>
                <a href="auth/signup.php?type=beneficiaire" class="btn btn-outline" style="border-color: white; color: white;">Devenir bénéficiaire</a>
                <a href="livreur/inscription.php" class="btn btn-outline" style="border-color: white; color: white;">Devenir livreur</a>
            </div>
        </div>
    </section>

    <!-- Stats Section -->
    <section class="stats-section">
        <div class="stats-grid">
            <div class="stat-card">
                <span class="stat-number">1,000+</span>
                <span class="stat-label">Dons effectués</span>
            </div>
            <div class="stat-card">
                <span class="stat-number">500+</span>
                <span class="stat-label">Bénéficiaires</span>
            </div>
            <div class="stat-card">
                <span class="stat-number">50+</span>
                <span class="stat-label">Livreurs bénévoles</span>
            </div>
            <div class="stat-card">
                <span class="stat-number">100%</span>
                <span class="stat-label">Gratuit</span>
            </div>
        </div>
    </section>

    <!-- About Section -->
    <section class="about-section" id="about">
        <div class="container">
            <h2>Qui sommes-nous?</h2>
            <p>Age of Donnation est une plateforme solidaire qui met en relation les personnes souhaitant faire des dons avec celles qui en ont besoin. Notre mission est de faciliter l'entraide et le partage dans un cadre 100% bénévole et transparent.</p>
            <p>Nous croyons en la puissance du partage et en la générosité de chacun. Rejoignez notre communauté et participez à cette belle chaîne de solidarité.</p>
            <a href="auth/signup.php" class="btn btn-primary">Rejoindre la communauté</a>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer" id="contact">
        <div class="container">
            <p>&copy; 2024 Age of Donnation. Tous droits réservés.</p>
            <p>Email: zahoumMerkhiArrach@ageofdonnation.org | Téléphone: +212 6 XX XX XX XX</p>
        </div>
    </footer>
</body>
</html>
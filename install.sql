-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1
-- Généré le : lun. 08 déc. 2025 à 21:25
-- Version du serveur : 10.4.32-MariaDB
-- Version de PHP : 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `age_of_donnation`
--

-- --------------------------------------------------------

--
-- Structure de la table `demandes`
--

CREATE TABLE `demandes` (
  `id` int(11) NOT NULL,
  `beneficiaire_id` int(11) NOT NULL,
  `don_id` int(11) NOT NULL,
  `message_demande` text DEFAULT NULL,
  `statut` enum('en_attente','acceptee','refusee','annulee') DEFAULT 'en_attente',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `demandes`
--

INSERT INTO `demandes` (`id`, `beneficiaire_id`, `don_id`, `message_demande`, `statut`, `created_at`) VALUES
(1, 3, 1, 'Bonjour, je suis intéressée par les livres pour enfants pour ma fille de 5 ans. Serait-il possible de les récupérer ce week-end ?', 'en_attente', '2025-12-08 17:50:38'),
(2, 3, 2, 'Ces vêtements me seraient très utiles pour un entretien d embauche. Merci pour votre générosité.', 'en_attente', '2025-12-08 17:50:38');

-- --------------------------------------------------------

--
-- Structure de la table `dons`
--

CREATE TABLE `dons` (
  `id` int(11) NOT NULL,
  `donateur_id` int(11) NOT NULL,
  `titre` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `photo_principale` varchar(255) DEFAULT NULL,
  `categorie` enum('vetements','nourriture','meubles','livres','electromenager','divers') NOT NULL,
  `etat` enum('neuf','bon_etat','usage') NOT NULL,
  `adresse_retrait` text DEFAULT NULL,
  `ville` varchar(100) DEFAULT NULL,
  `statut` enum('disponible','reserve','donne','expire') DEFAULT 'disponible',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `dons`
--

INSERT INTO `dons` (`id`, `donateur_id`, `titre`, `description`, `photo_principale`, `categorie`, `etat`, `adresse_retrait`, `ville`, `statut`, `created_at`, `updated_at`) VALUES
(1, 2, 'Livres pour enfants', 'Collection de livres jeunesse en bon état, idéale pour enfants de 3 à 8 ans.', NULL, 'livres', 'bon_etat', '123 Avenue des Champs-Élysées', 'Paris', 'disponible', '2025-12-08 17:50:38', '2025-12-08 17:50:38'),
(2, 2, 'Vêtements femme taille M', 'Lot de vêtements femme taille M : robes, jupes, hauts. Très bon état.', NULL, 'vetements', 'bon_etat', '123 Avenue des Champs-Élysées', 'Paris', 'disponible', '2025-12-08 17:50:38', '2025-12-08 17:50:38'),
(3, 2, 'Meuble TV en bois', 'Meuble télévision en bois massif, dimensions 120x40x50 cm. Quelques traces d usage.', NULL, 'meubles', 'usage', '123 Avenue des Champs-Élysées', 'Paris', 'disponible', '2025-12-08 17:50:38', '2025-12-08 17:50:38');

-- --------------------------------------------------------

--
-- Structure de la table `don_photos`
--

CREATE TABLE `don_photos` (
  `id` int(11) NOT NULL,
  `don_id` int(11) NOT NULL,
  `photo_path` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `livraisons`
--

CREATE TABLE `livraisons` (
  `id` int(11) NOT NULL,
  `demande_id` int(11) NOT NULL,
  `livreur_id` int(11) DEFAULT NULL,
  `frais_livraison` decimal(10,2) DEFAULT 0.00,
  `statut` enum('en_attente','assignee','en_cours','livree','annulee') DEFAULT 'en_attente',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `livraisons`
--

INSERT INTO `livraisons` (`id`, `demande_id`, `livreur_id`, `frais_livraison`, `statut`, `created_at`) VALUES
(1, 1, 4, 0.00, 'en_attente', '2025-12-08 17:50:38');

-- --------------------------------------------------------

--
-- Structure de la table `livreurs`
--

CREATE TABLE `livreurs` (
  `user_id` int(11) NOT NULL,
  `vehicule_type` enum('velo','moto','voiture','camion') NOT NULL,
  `plaque_immatriculation` varchar(50) DEFAULT NULL,
  `zone_intervention` text DEFAULT NULL,
  `statut` enum('actif','inactif','en_conge') DEFAULT 'actif',
  `note_moyenne` decimal(3,2) DEFAULT 5.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `livreurs`
--

INSERT INTO `livreurs` (`user_id`, `vehicule_type`, `plaque_immatriculation`, `zone_intervention`, `statut`, `note_moyenne`, `created_at`, `updated_at`) VALUES
(4, 'voiture', 'AB-123-CD', 'Paris, Lyon, Marseille', 'actif', 5.00, '2025-12-08 17:50:38', '2025-12-08 17:50:38');

-- --------------------------------------------------------

--
-- Structure de la table `messages`
--

CREATE TABLE `messages` (
  `id` int(11) NOT NULL,
  `expediteur_id` int(11) NOT NULL,
  `destinataire_id` int(11) NOT NULL,
  `demande_id` int(11) DEFAULT NULL,
  `message` text NOT NULL,
  `lu` tinyint(1) DEFAULT 0,
  `lu_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `messages`
--

INSERT INTO `messages` (`id`, `expediteur_id`, `destinataire_id`, `demande_id`, `message`, `lu`, `lu_at`, `created_at`) VALUES
(1, 3, 2, 1, 'Bonjour, je suis intéressée par les livres pour enfants. Quand puis-je les récupérer ?', 1, '2025-12-08 19:31:04', '2025-12-08 17:50:38'),
(2, 2, 3, 1, 'Bonjour, les livres sont disponibles ce week-end de 14h à 18h. Ça vous convient ?', 1, '2025-12-08 19:13:37', '2025-12-08 17:50:38'),
(3, 2, 3, NULL, 'ok', 1, NULL, '2025-12-08 19:31:12'),
(4, 3, 2, NULL, 'no', 0, NULL, '2025-12-08 19:31:52');

-- --------------------------------------------------------

--
-- Structure de la table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `nom` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `type` enum('donateur','beneficiaire','livreur','admin') NOT NULL,
  `telephone` varchar(20) DEFAULT NULL,
  `adresse` text DEFAULT NULL,
  `ville` varchar(100) DEFAULT NULL,
  `status` enum('active','inactive','pending') DEFAULT 'active',
  `reset_token` varchar(100) DEFAULT NULL,
  `reset_expires` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `users`
--

INSERT INTO `users` (`id`, `nom`, `email`, `password`, `type`, `telephone`, `adresse`, `ville`, `status`, `reset_token`, `reset_expires`, `created_at`, `updated_at`) VALUES
(1, 'Administrateur', 'admin@ageofdonnation.org', '$2y$10$/Hjf3UHjG4fmJxgvbnx.yOATlzaw/zsYO/5Y.VTX8Qkx46WlKz0t.', 'admin', NULL, NULL, NULL, 'active', NULL, NULL, '2025-12-08 17:50:38', '2025-12-08 19:07:26'),
(2, 'Jean Dupont', 'jean.dupont@email.com', '$2y$10$/Hjf3UHjG4fmJxgvbnx.yOATlzaw/zsYO/5Y.VTX8Qkx46WlKz0t.', 'donateur', '0123456789', NULL, 'Paris', 'active', NULL, NULL, '2025-12-08 17:50:38', '2025-12-08 19:07:26'),
(3, 'Marie Martin', 'marie.martin@email.com', '$2y$10$/Hjf3UHjG4fmJxgvbnx.yOATlzaw/zsYO/5Y.VTX8Qkx46WlKz0t.', 'beneficiaire', '0123456790', NULL, 'Lyon', 'active', NULL, NULL, '2025-12-08 17:50:38', '2025-12-08 19:07:26'),
(4, 'Pierre Durand', 'pierre.durand@email.com', '$2y$10$/Hjf3UHjG4fmJxgvbnx.yOATlzaw/zsYO/5Y.VTX8Qkx46WlKz0t.', 'livreur', '0123456791', NULL, 'Marseille', 'active', NULL, NULL, '2025-12-08 17:50:38', '2025-12-08 19:07:26'),
(5, 'aissa zahoum', 'aissazahoum6@gmail.com', '$2y$10$GnfODvHI6lW8ypnlH3HxzeW11sikV5aSrKDEBySbLLMbzoihkHZzO', 'donateur', '0649339948', NULL, NULL, 'active', NULL, NULL, '2025-12-08 18:01:22', '2025-12-08 18:01:22');

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `demandes`
--
ALTER TABLE `demandes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `beneficiaire_id` (`beneficiaire_id`),
  ADD KEY `don_id` (`don_id`);

--
-- Index pour la table `dons`
--
ALTER TABLE `dons`
  ADD PRIMARY KEY (`id`),
  ADD KEY `donateur_id` (`donateur_id`);

--
-- Index pour la table `don_photos`
--
ALTER TABLE `don_photos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `don_id` (`don_id`);

--
-- Index pour la table `livraisons`
--
ALTER TABLE `livraisons`
  ADD PRIMARY KEY (`id`),
  ADD KEY `demande_id` (`demande_id`),
  ADD KEY `livreur_id` (`livreur_id`);

--
-- Index pour la table `livreurs`
--
ALTER TABLE `livreurs`
  ADD PRIMARY KEY (`user_id`);

--
-- Index pour la table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `expediteur_id` (`expediteur_id`),
  ADD KEY `destinataire_id` (`destinataire_id`),
  ADD KEY `demande_id` (`demande_id`);

--
-- Index pour la table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `demandes`
--
ALTER TABLE `demandes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT pour la table `dons`
--
ALTER TABLE `dons`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT pour la table `don_photos`
--
ALTER TABLE `don_photos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `livraisons`
--
ALTER TABLE `livraisons`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT pour la table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `demandes`
--
ALTER TABLE `demandes`
  ADD CONSTRAINT `demandes_ibfk_1` FOREIGN KEY (`beneficiaire_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `demandes_ibfk_2` FOREIGN KEY (`don_id`) REFERENCES `dons` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `dons`
--
ALTER TABLE `dons`
  ADD CONSTRAINT `dons_ibfk_1` FOREIGN KEY (`donateur_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `don_photos`
--
ALTER TABLE `don_photos`
  ADD CONSTRAINT `don_photos_ibfk_1` FOREIGN KEY (`don_id`) REFERENCES `dons` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `livraisons`
--
ALTER TABLE `livraisons`
  ADD CONSTRAINT `livraisons_ibfk_1` FOREIGN KEY (`demande_id`) REFERENCES `demandes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `livraisons_ibfk_2` FOREIGN KEY (`livreur_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `livreurs`
--
ALTER TABLE `livreurs`
  ADD CONSTRAINT `livreurs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`expediteur_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`destinataire_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `messages_ibfk_3` FOREIGN KEY (`demande_id`) REFERENCES `demandes` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
-- =============================================
-- INFORMATIONS DE CONNEXION
-- =============================================

-- 🔐 COMPTE ADMIN PAR DÉFAUT :
-- Email: admin@ageofdonnation.org
-- Mot de passe: admin123
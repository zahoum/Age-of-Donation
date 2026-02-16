-- =============================================
-- CRÉATION DE LA BASE DE DONNÉES
-- =============================================
CREATE DATABASE IF NOT EXISTS `age_of_donnation` 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE `age_of_donnation`;

-- =============================================
-- TABLE `users`
-- =============================================
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- TABLE `dons`
-- =============================================
CREATE TABLE `dons` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `donateur_id` (`donateur_id`),
  CONSTRAINT `dons_ibfk_1` FOREIGN KEY (`donateur_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- TABLE `don_photos`
-- =============================================
CREATE TABLE `don_photos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `don_id` int(11) NOT NULL,
  `photo_path` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `don_id` (`don_id`),
  CONSTRAINT `don_photos_ibfk_1` FOREIGN KEY (`don_id`) REFERENCES `dons` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- TABLE `demandes`
-- =============================================
CREATE TABLE `demandes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `beneficiaire_id` int(11) NOT NULL,
  `don_id` int(11) NOT NULL,
  `message_demande` text DEFAULT NULL,
  `statut` enum('en_attente','acceptee','refusee','annulee') DEFAULT 'en_attente',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `beneficiaire_id` (`beneficiaire_id`),
  KEY `don_id` (`don_id`),
  CONSTRAINT `demandes_ibfk_1` FOREIGN KEY (`beneficiaire_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `demandes_ibfk_2` FOREIGN KEY (`don_id`) REFERENCES `dons` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- TABLE `livreurs`
-- =============================================
CREATE TABLE `livreurs` (
  `user_id` int(11) NOT NULL,
  `vehicule_type` enum('velo','moto','voiture','camion') NOT NULL,
  `plaque_immatriculation` varchar(50) DEFAULT NULL,
  `zone_intervention` text DEFAULT NULL,
  `statut` enum('actif','inactif','en_conge') DEFAULT 'actif',
  `note_moyenne` decimal(3,2) DEFAULT 5.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`user_id`),
  CONSTRAINT `livreurs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- TABLE `livraisons`
-- =============================================
CREATE TABLE `livraisons` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `demande_id` int(11) NOT NULL,
  `livreur_id` int(11) DEFAULT NULL,
  `frais_livraison` decimal(10,2) DEFAULT 0.00,
  `statut` enum('en_attente','assignee','en_cours','livree','annulee') DEFAULT 'en_attente',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `demande_id` (`demande_id`),
  KEY `livreur_id` (`livreur_id`),
  CONSTRAINT `livraisons_ibfk_1` FOREIGN KEY (`demande_id`) REFERENCES `demandes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `livraisons_ibfk_2` FOREIGN KEY (`livreur_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- TABLE `messages`
-- =============================================
CREATE TABLE `messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `expediteur_id` int(11) NOT NULL,
  `destinataire_id` int(11) NOT NULL,
  `demande_id` int(11) DEFAULT NULL,
  `message` text NOT NULL,
  `lu` tinyint(1) DEFAULT 0,
  `lu_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `expediteur_id` (`expediteur_id`),
  KEY `destinataire_id` (`destinataire_id`),
  KEY `demande_id` (`demande_id`),
  CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`expediteur_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`destinataire_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `messages_ibfk_3` FOREIGN KEY (`demande_id`) REFERENCES `demandes` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE contact_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    subject VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    status ENUM('new', 'read', 'replied', 'archived') DEFAULT 'new',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE (
  email varchar(50) not null,
  key varchar(58) not null,
  expDate date 
)
-- =============================================
-- CORRECTION DE LA TABLE `livreurs`
-- =============================================
ALTER TABLE `livreurs` 
ADD COLUMN `nombre_livraisons` INT DEFAULT 0 AFTER `note_moyenne`,
ADD COLUMN `date_activation` DATETIME NULL AFTER `nombre_livraisons`,
ADD COLUMN `documents_verifies` BOOLEAN DEFAULT FALSE AFTER `date_activation`;

-- =============================================
-- TABLE POUR LES DOCUMENTS DES LIVREURS
-- =============================================
CREATE TABLE IF NOT EXISTS `livreur_documents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `document_type` enum('permis','assurance','carte_identite','photo_vehicule') NOT NULL,
  `document_path` varchar(255) NOT NULL,
  `statut` enum('en_attente','valide','refuse') DEFAULT 'en_attente',
  `commentaire_admin` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `livreur_documents_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- TABLE POUR L'HISTORIQUE DES LIVRAISONS
-- =============================================
CREATE TABLE IF NOT EXISTS `livraison_historique` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `livraison_id` int(11) NOT NULL,
  `statut_ancien` varchar(50) DEFAULT NULL,
  `statut_nouveau` varchar(50) NOT NULL,
  `commentaire` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `livraison_id` (`livraison_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `livraison_historique_ibfk_1` FOREIGN KEY (`livraison_id`) REFERENCES `livraisons` (`id`) ON DELETE CASCADE,
  CONSTRAINT `livraison_historique_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- TABLE POUR LES NOTIFICATIONS
-- =============================================
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `type` varchar(50) NOT NULL,
  `titre` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `lien` varchar(255) DEFAULT NULL,
  `lu` tinyint(1) DEFAULT 0,
  `lu_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- MISE À JOUR DE LA TABLE `livraisons`
-- =============================================
ALTER TABLE `livraisons`
ADD COLUMN `code_postal` varchar(10) DEFAULT NULL ,
ADD COLUMN `instructions` text DEFAULT NULL,
ADD COLUMN `date_livraison` datetime DEFAULT NULL,
ADD COLUMN `photo_livraison` varchar(255) DEFAULT NULL ,
ADD COLUMN `signature` varchar(255) DEFAULT NULL ;

-- =============================================
-- new modification in laivraison option 
ALTER TABLE dons ADD COLUMN livraison_option ENUM('none', 'fifty', 'full') DEFAULT 'none' AFTER ville;
-- =============================================

-- Vérifier et ajouter les colonnes manquantes si nécessaire
ALTER TABLE livraisons 
ADD COLUMN IF NOT EXISTS `ville` varchar(100) DEFAULT NULL AFTER `code_postal`,
ADD COLUMN IF NOT EXISTS `frais_livraison` decimal(10,2) DEFAULT 0.00 AFTER `livreur_id`;


-- Ajouter une colonne pour la suppression logique dans la table dons
ALTER TABLE dons 
ADD COLUMN `is_deleted` TINYINT(1) DEFAULT 0 AFTER `statut`,
ADD COLUMN `deleted_at` TIMESTAMP NULL DEFAULT NULL AFTER `is_deleted`;


-- Table pour l'historique des livraisons
CREATE TABLE IF NOT EXISTS `livraison_historique` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `livraison_id` int(11) NOT NULL,
    `statut_ancien` varchar(50) DEFAULT NULL,
    `statut_nouveau` varchar(50) NOT NULL,
    `commentaire` text DEFAULT NULL,
    `created_by` int(11) DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`),
    KEY `livraison_id` (`livraison_id`),
    KEY `created_by` (`created_by`),
    CONSTRAINT `livraison_historique_ibfk_1` FOREIGN KEY (`livraison_id`) REFERENCES `livraisons` (`id`) ON DELETE CASCADE,
    CONSTRAINT `livraison_historique_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ajouter des colonnes si elles n'existent pas
ALTER TABLE livraisons
ADD COLUMN IF NOT EXISTS `code_postal` varchar(10) DEFAULT NULL ,
ADD COLUMN IF NOT EXISTS `instructions` text DEFAULT NULL ,
ADD COLUMN IF NOT EXISTS `date_livraison` datetime DEFAULT NULL ,
ADD COLUMN IF NOT EXISTS `photo_livraison` varchar(255) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS `signature` varchar(255) DEFAULT NULL 
-- =============================================
-- INSERTION DES DONNÉES D'EXEMPLE
-- =============================================

-- Utilisateurs
-- INSERT INTO `users` (`id`, `nom`, `email`, `password`, `type`, `telephone`, `adresse`, `ville`, `status`, `reset_token`, `reset_expires`, `created_at`, `updated_at`) VALUES
-- (1, 'Administrateur', 'admin@ageofdonnation.org', '$2y$10$/Hjf3UHjG4fmJxgvbnx.yOATlzaw/zsYO/5Y.VTX8Qkx46WlKz0t.', 'admin', NULL, NULL, NULL, 'active', NULL, NULL, '2025-12-08 17:50:38', '2025-12-08 19:07:26'),
-- (2, 'Jean Dupont', 'jean.dupont@email.com', '$2y$10$/Hjf3UHjG4fmJxgvbnx.yOATlzaw/zsYO/5Y.VTX8Qkx46WlKz0t.', 'donateur', '0123456789', NULL, 'Paris', 'active', NULL, NULL, '2025-12-08 17:50:38', '2025-12-08 19:07:26'),
-- (3, 'Marie Martin', 'marie.martin@email.com', '$2y$10$/Hjf3UHjG4fmJxgvbnx.yOATlzaw/zsYO/5Y.VTX8Qkx46WlKz0t.', 'beneficiaire', '0123456790', NULL, 'Lyon', 'active', NULL, NULL, '2025-12-08 17:50:38', '2025-12-08 19:07:26'),
-- (4, 'Pierre Durand', 'pierre.durand@email.com', '$2y$10$/Hjf3UHjG4fmJxgvbnx.yOATlzaw/zsYO/5Y.VTX8Qkx46WlKz0t.', 'livreur', '0123456791', NULL, 'Marseille', 'active', NULL, NULL, '2025-12-08 17:50:38', '2025-12-08 19:07:26'),
-- (5, 'aissa zahoum', 'aissazahoum6@gmail.com', '$2y$10$GnfODvHI6lW8ypnlH3HxzeW11sikV5aSrKDEBySbLLMbzoihkHZzO', 'donateur', '0649339948', NULL, NULL, 'active', NULL, NULL, '2025-12-08 18:01:22', '2025-12-08 18:01:22');

-- -- Dons
-- INSERT INTO `dons` (`id`, `donateur_id`, `titre`, `description`, `photo_principale`, `categorie`, `etat`, `adresse_retrait`, `ville`, `statut`, `created_at`, `updated_at`) VALUES
-- (1, 2, 'Livres pour enfants', 'Collection de livres jeunesse en bon état, idéale pour enfants de 3 à 8 ans.', NULL, 'livres', 'bon_etat', '123 Avenue des Champs-Élysées', 'Paris', 'disponible', '2025-12-08 17:50:38', '2025-12-08 17:50:38'),
-- (2, 2, 'Vêtements femme taille M', 'Lot de vêtements femme taille M : robes, jupes, hauts. Très bon état.', NULL, 'vetements', 'bon_etat', '123 Avenue des Champs-Élysées', 'Paris', 'disponible', '2025-12-08 17:50:38', '2025-12-08 17:50:38'),
-- (3, 2, 'Meuble TV en bois', 'Meuble télévision en bois massif, dimensions 120x40x50 cm. Quelques traces d usage.', NULL, 'meubles', 'usage', '123 Avenue des Champs-Élysées', 'Paris', 'disponible', '2025-12-08 17:50:38', '2025-12-08 17:50:38');

-- -- Demandes
-- INSERT INTO `demandes` (`id`, `beneficiaire_id`, `don_id`, `message_demande`, `statut`, `created_at`) VALUES
-- (1, 3, 1, 'Bonjour, je suis intéressée par les livres pour enfants pour ma fille de 5 ans. Serait-il possible de les récupérer ce week-end ?', 'en_attente', '2025-12-08 17:50:38'),
-- (2, 3, 2, 'Ces vêtements me seraient très utiles pour un entretien d embauche. Merci pour votre générosité.', 'en_attente', '2025-12-08 17:50:38');

-- -- Livreurs
-- INSERT INTO `livreurs` (`user_id`, `vehicule_type`, `plaque_immatriculation`, `zone_intervention`, `statut`, `note_moyenne`, `created_at`, `updated_at`) VALUES
-- (4, 'voiture', 'AB-123-CD', 'Paris, Lyon, Marseille', 'actif', 5.00, '2025-12-08 17:50:38', '2025-12-08 17:50:38');

-- -- Livraisons
-- INSERT INTO `livraisons` (`id`, `demande_id`, `livreur_id`, `frais_livraison`, `statut`, `created_at`) VALUES
-- (1, 1, 4, 0.00, 'en_attente', '2025-12-08 17:50:38');

-- -- Messages
-- INSERT INTO `messages` (`id`, `expediteur_id`, `destinataire_id`, `demande_id`, `message`, `lu`, `lu_at`, `created_at`) VALUES
-- (1, 3, 2, 1, 'Bonjour, je suis intéressée par les livres pour enfants. Quand puis-je les récupérer ?', 1, '2025-12-08 19:31:04', '2025-12-08 17:50:38'),
-- (2, 2, 3, 1, 'Bonjour, les livres sont disponibles ce week-end de 14h à 18h. Ça vous convient ?', 1, '2025-12-08 19:13:37', '2025-12-08 17:50:38'),
-- (3, 2, 3, NULL, 'ok', 1, NULL, '2025-12-08 19:31:12'),
-- (4, 3, 2, NULL, 'no', 0, NULL, '2025-12-08 19:31:52');

-- =============================================
-- INFORMATIONS DE CONNEXION
-- =============================================
-- 🔐 COMPTE ADMIN PAR DÉFAUT :
-- Email: admin@ageofdonnation.org
-- Mot de passe: admin123
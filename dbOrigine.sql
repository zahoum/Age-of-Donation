CREATE DATABASE IF NOT EXISTS `age_of_donnation`
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE `age_of_donnation`;

-- =============================================
-- TABLE users
-- =============================================
CREATE TABLE `users` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `nom` VARCHAR(100) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `type` ENUM('donateur','beneficiaire','livreur','admin') NOT NULL,
  `telephone` VARCHAR(20) DEFAULT NULL,
  `adresse` TEXT DEFAULT NULL,
  `ville` VARCHAR(100) DEFAULT NULL,
  `status` ENUM('active','inactive','pending') DEFAULT 'active',
  `reset_token` VARCHAR(100) DEFAULT NULL,
  `reset_expires` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- TABLE dons
-- =============================================
CREATE TABLE `dons` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `donateur_id` INT(11) NOT NULL,
  `titre` VARCHAR(255) NOT NULL,
  `description` TEXT NOT NULL,
  `photo_principale` VARCHAR(255) DEFAULT NULL,
  `categorie` ENUM('vetements','nourriture','meubles','livres','electromenager','divers') NOT NULL,
  `etat` ENUM('neuf','bon_etat','usage') NOT NULL,
  `adresse_retrait` TEXT DEFAULT NULL,
  `ville` VARCHAR(100) DEFAULT NULL,
  `livraison_option` ENUM('none','fifty','full') DEFAULT 'none',
  `statut` ENUM('disponible','reserve','donne','expire') DEFAULT 'disponible',
  `is_deleted` TINYINT(1) DEFAULT 0,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `donateur_id` (`donateur_id`),
  CONSTRAINT `dons_ibfk_1`
    FOREIGN KEY (`donateur_id`) REFERENCES `users` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- TABLE don_photos
-- =============================================
CREATE TABLE `don_photos` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `don_id` INT(11) NOT NULL,
  `photo_path` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `don_id` (`don_id`),
  CONSTRAINT `don_photos_ibfk_1`
    FOREIGN KEY (`don_id`) REFERENCES `dons` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- TABLE demandes
-- =============================================
CREATE TABLE `demandes` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `beneficiaire_id` INT(11) NOT NULL,
  `don_id` INT(11) NOT NULL,
  `message_demande` TEXT DEFAULT NULL,
  `statut` ENUM('en_attente','acceptee','refusee','annulee') DEFAULT 'en_attente',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `beneficiaire_id` (`beneficiaire_id`),
  KEY `don_id` (`don_id`),
  CONSTRAINT `demandes_ibfk_1`
    FOREIGN KEY (`beneficiaire_id`) REFERENCES `users` (`id`)
    ON DELETE CASCADE,
  CONSTRAINT `demandes_ibfk_2`
    FOREIGN KEY (`don_id`) REFERENCES `dons` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- TABLE livreurs
-- =============================================
CREATE TABLE `livreurs` (
  `user_id` INT(11) NOT NULL,
  `vehicule_type` ENUM('velo','moto','voiture','camion') NOT NULL,
  `plaque_immatriculation` VARCHAR(50) DEFAULT NULL,
  `zone_intervention` TEXT DEFAULT NULL,
  `statut` ENUM('actif','inactif','en_conge') DEFAULT 'actif',
  `note_moyenne` DECIMAL(3,2) DEFAULT 5.00,
  `nombre_livraisons` INT DEFAULT 0,
  `date_activation` DATETIME DEFAULT NULL,
  `documents_verifies` BOOLEAN DEFAULT FALSE,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  CONSTRAINT `livreurs_ibfk_1`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- TABLE livraisons
-- =============================================
CREATE TABLE `livraisons` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `demande_id` INT(11) NOT NULL,
  `livreur_id` INT(11) DEFAULT NULL,
  `frais_livraison` DECIMAL(10,2) DEFAULT 0.00,
  `code_postal` VARCHAR(10) DEFAULT NULL,
  `ville` VARCHAR(100) DEFAULT NULL,
  `instructions` TEXT DEFAULT NULL,
  `date_livraison` DATETIME DEFAULT NULL,
  `photo_livraison` VARCHAR(255) DEFAULT NULL,
  `signature` VARCHAR(255) DEFAULT NULL,
  `statut` ENUM('en_attente','assignee','en_cours','livree','annulee') DEFAULT 'en_attente',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `demande_id` (`demande_id`),
  KEY `livreur_id` (`livreur_id`),
  CONSTRAINT `livraisons_ibfk_1`
    FOREIGN KEY (`demande_id`) REFERENCES `demandes` (`id`)
    ON DELETE CASCADE,
  CONSTRAINT `livraisons_ibfk_2`
    FOREIGN KEY (`livreur_id`) REFERENCES `users` (`id`)
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- TABLE messages
-- =============================================
CREATE TABLE `messages` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `expediteur_id` INT(11) NOT NULL,
  `destinataire_id` INT(11) NOT NULL,
  `demande_id` INT(11) DEFAULT NULL,
  `message` TEXT NOT NULL,
  `lu` TINYINT(1) DEFAULT 0,
  `lu_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `expediteur_id` (`expediteur_id`),
  KEY `destinataire_id` (`destinataire_id`),
  KEY `demande_id` (`demande_id`),
  CONSTRAINT `messages_ibfk_1`
    FOREIGN KEY (`expediteur_id`) REFERENCES `users` (`id`)
    ON DELETE CASCADE,
  CONSTRAINT `messages_ibfk_2`
    FOREIGN KEY (`destinataire_id`) REFERENCES `users` (`id`)
    ON DELETE CASCADE,
  CONSTRAINT `messages_ibfk_3`
    FOREIGN KEY (`demande_id`) REFERENCES `demandes` (`id`)
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- TABLE contact_messages
-- =============================================
CREATE TABLE `contact_messages` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100) NOT NULL,
  `phone` VARCHAR(20),
  `subject` VARCHAR(200) NOT NULL,
  `message` TEXT NOT NULL,
  `status` ENUM('new','read','replied','archived') DEFAULT 'new',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- TABLE password_reset_temp
-- =============================================
CREATE TABLE `password_reset_temp` (
  `email` VARCHAR(50) NOT NULL,
  `reset_key` VARCHAR(58) NOT NULL,
  `expDate` DATE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- TABLE livreur_documents
-- =============================================
CREATE TABLE `livreur_documents` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `document_type` ENUM('permis','assurance','carte_identite','photo_vehicule') NOT NULL,
  `document_path` VARCHAR(255) NOT NULL,
  `statut` ENUM('en_attente','valide','refuse') DEFAULT 'en_attente',
  `commentaire_admin` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `livreur_documents_ibfk_1`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- TABLE livraison_historique
-- =============================================
CREATE TABLE `livraison_historique` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `livraison_id` INT(11) NOT NULL,
  `statut_ancien` VARCHAR(50) DEFAULT NULL,
  `statut_nouveau` VARCHAR(50) NOT NULL,
  `commentaire` TEXT DEFAULT NULL,
  `created_by` INT(11) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `livraison_id` (`livraison_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `livraison_historique_ibfk_1`
    FOREIGN KEY (`livraison_id`) REFERENCES `livraisons` (`id`)
    ON DELETE CASCADE,
  CONSTRAINT `livraison_historique_ibfk_2`
    FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- TABLE notifications
-- =============================================
CREATE TABLE `notifications` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `type` VARCHAR(50) NOT NULL,
  `titre` VARCHAR(255) NOT NULL,
  `message` TEXT NOT NULL,
  `lien` VARCHAR(255) DEFAULT NULL,
  `lu` TINYINT(1) DEFAULT 0,
  `lu_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `notifications_ibfk_1`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `password_reset_temp` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `email` varchar(250) NOT NULL,
    `key` varchar(250) NOT NULL,
    `expDate` datetime NOT NULL,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- =============================================
-- INSERT USERS
-- =============================================
INSERT INTO `users`
(`id`, `nom`, `email`, `password`, `type`, `telephone`, `ville`, `status`)
VALUES
(1, 'Administrateur', 'admin@ageofdonnation.org', 'admin123', 'admin', NULL, NULL, 'active'),
(2, 'Jean Dupont', 'jean.dupont@email.com', 'password123', 'donateur', '0123456789', 'Paris', 'active'),
(3, 'Marie Martin', 'marie.martin@email.com', 'password123', 'beneficiaire', '0123456790', 'Lyon', 'active'),
(4, 'Pierre Durand', 'pierre.durand@email.com', 'password123', 'livreur', '0123456791', 'Marseille', 'active');

-- =============================================
-- INSERT DONS
-- =============================================
INSERT INTO `dons`
(`id`, `donateur_id`, `titre`, `description`, `categorie`, `etat`, `adresse_retrait`, `ville`, `livraison_option`, `statut`)
VALUES
(1, 2, 'Livres pour enfants', 'Collection de livres jeunesse.', 'livres', 'bon_etat', '123 Avenue Paris', 'Paris', 'none', 'disponible'),
(2, 2, 'Vetements femme', 'Vetements taille M.', 'vetements', 'bon_etat', '123 Avenue Paris', 'Paris', 'fifty', 'disponible');

-- =============================================
-- INSERT DEMANDES
-- =============================================
INSERT INTO `demandes`
(`id`, `beneficiaire_id`, `don_id`, `message_demande`, `statut`)
VALUES
(1, 3, 1, 'Je suis interessee.', 'acceptee');

-- =============================================
-- INSERT LIVREURS
-- =============================================
INSERT INTO `livreurs`
(`user_id`, `vehicule_type`, `plaque_immatriculation`, `zone_intervention`)
VALUES
(4, 'voiture', 'AB-123-CD', 'Paris, Lyon, Marseille');

-- =============================================
-- INSERT LIVRAISONS
-- =============================================
INSERT INTO `livraisons`
(`id`, `demande_id`, `livreur_id`, `frais_livraison`, `ville`, `statut`)
VALUES
(1, 1, 4, 5.00, 'Paris', 'en_attente');

-- =============================================
-- INSERT MESSAGES
-- =============================================
INSERT INTO `messages`
(`expediteur_id`, `destinataire_id`, `demande_id`, `message`)
VALUES
(3, 2, 1, 'Bonjour, je suis interessee par ce don.');

-- =============================================
-- INFORMATIONS DE CONNEXION
-- =============================================
-- 🔐 COMPTE ADMIN PAR DÉFAUT :
-- Email: admin@ageofdonnation.org
-- Mot de passe: admin123
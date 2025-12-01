-- =============================================
-- AGE OF DONNATION - SCRIPT SQL COMPLET
-- =============================================

-- 1. CRÉATION DE LA BASE DE DONNÉES
CREATE DATABASE IF NOT EXISTS age_of_donnation CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE age_of_donnation;

-- 2. CRÉATION DE LA TABLE USERS
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nom VARCHAR(100) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    type ENUM('donateur', 'beneficiaire', 'livreur', 'admin') NOT NULL,
    telephone VARCHAR(20),
    adresse TEXT,
    ville VARCHAR(100),
    status ENUM('active', 'inactive', 'pending') DEFAULT 'active',
    reset_token VARCHAR(100),
    reset_expires DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 3. CRÉATION DE LA TABLE DONS
CREATE TABLE dons (
    id INT PRIMARY KEY AUTO_INCREMENT,
    donateur_id INT NOT NULL,
    titre VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    categorie ENUM('vetements', 'nourriture', 'meubles', 'livres', 'electromenager', 'divers') NOT NULL,
    etat ENUM('neuf', 'bon_etat', 'usage') NOT NULL,
    adresse_retrait TEXT,
    ville VARCHAR(100),
    statut ENUM('disponible', 'reserve', 'donne', 'expire') DEFAULT 'disponible',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (donateur_id) REFERENCES users(id) ON DELETE CASCADE
);

-- 4. CRÉATION DE LA TABLE DEMANDES
CREATE TABLE demandes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    beneficiaire_id INT NOT NULL,
    don_id INT NOT NULL,
    message_demande TEXT,
    statut ENUM('en_attente', 'acceptee', 'refusee', 'annulee') DEFAULT 'en_attente',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (beneficiaire_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (don_id) REFERENCES dons(id) ON DELETE CASCADE
);

-- 5. CRÉATION DE LA TABLE LIVRAISONS
CREATE TABLE livraisons (
    id INT PRIMARY KEY AUTO_INCREMENT,
    demande_id INT NOT NULL,
    livreur_id INT,
    frais_livraison DECIMAL(10,2) DEFAULT 0,
    statut ENUM('en_attente', 'assignee', 'en_cours', 'livree', 'annulee') DEFAULT 'en_attente',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (demande_id) REFERENCES demandes(id) ON DELETE CASCADE,
    FOREIGN KEY (livreur_id) REFERENCES users(id) ON DELETE SET NULL
);

-- 6. CRÉATION DE LA TABLE MESSAGES
CREATE TABLE messages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    expediteur_id INT NOT NULL,
    destinataire_id INT NOT NULL,
    demande_id INT,
    message TEXT NOT NULL,
    lu BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (expediteur_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (destinataire_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (demande_id) REFERENCES demandes(id) ON DELETE SET NULL
);

-- 7. CRÉATION DE LA TABLE LIVREURS
CREATE TABLE livreurs (
    user_id INT PRIMARY KEY,
    vehicule_type ENUM('velo', 'moto', 'voiture', 'camion') NOT NULL,
    plaque_immatriculation VARCHAR(50),
    zone_intervention TEXT,
    statut ENUM('actif', 'inactif', 'en_conge') DEFAULT 'actif',
    note_moyenne DECIMAL(3,2) DEFAULT 5.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- =============================================
-- DONNÉES DE TEST
-- =============================================

-- 8. INSERTION DU COMPTE ADMIN
INSERT INTO users (nom, email, password, type, status) VALUES 
('Administrateur', 'admin@ageofdonnation.org', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'active');

-- 9. INSERTION DE QUELQUES UTILISATEURS DE TEST
INSERT INTO users (nom, email, password, type, telephone, ville, status) VALUES 
('Jean Dupont', 'jean.dupont@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'donateur', '0123456789', 'Paris', 'active'),
('Marie Martin', 'marie.martin@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'beneficiaire', '0123456790', 'Lyon', 'active'),
('Pierre Durand', 'pierre.durand@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'livreur', '0123456791', 'Marseille', 'active');

-- 10. INSERTION DE QUELQUES DONS DE TEST
INSERT INTO dons (donateur_id, titre, description, categorie, etat, adresse_retrait, ville, statut) VALUES 
(2, 'Livres pour enfants', 'Collection de livres jeunesse en bon état, idéale pour enfants de 3 à 8 ans.', 'livres', 'bon_etat', '123 Avenue des Champs-Élysées', 'Paris', 'disponible'),
(2, 'Vêtements femme taille M', 'Lot de vêtements femme taille M : robes, jupes, hauts. Très bon état.', 'vetements', 'bon_etat', '123 Avenue des Champs-Élysées', 'Paris', 'disponible'),
(2, 'Meuble TV en bois', 'Meuble télévision en bois massif, dimensions 120x40x50 cm. Quelques traces d usage.', 'meubles', 'usage', '123 Avenue des Champs-Élysées', 'Paris', 'disponible');

-- 11. INSERTION DE DEMANDES DE TEST
INSERT INTO demandes (beneficiaire_id, don_id, message_demande, statut) VALUES 
(3, 1, 'Bonjour, je suis intéressée par les livres pour enfants pour ma fille de 5 ans. Serait-il possible de les récupérer ce week-end ?', 'en_attente'),
(3, 2, 'Ces vêtements me seraient très utiles pour un entretien d embauche. Merci pour votre générosité.', 'en_attente');

-- 12. INSERTION D UN LIVREUR DE TEST
INSERT INTO livreurs (user_id, vehicule_type, plaque_immatriculation, zone_intervention, statut) VALUES 
(4, 'voiture', 'AB-123-CD', 'Paris, Lyon, Marseille', 'actif');

-- 13. INSERTION DE LIVRAISONS DE TEST
INSERT INTO livraisons (demande_id, livreur_id, frais_livraison, statut) VALUES 
(1, 4, 0.00, 'en_attente');

-- 14. INSERTION DE MESSAGES DE TEST
INSERT INTO messages (expediteur_id, destinataire_id, demande_id, message) VALUES 
(3, 2, 1, 'Bonjour, je suis intéressée par les livres pour enfants. Quand puis-je les récupérer ?'),
(2, 3, 1, 'Bonjour, les livres sont disponibles ce week-end de 14h à 18h. Ça vous convient ?');

-- =============================================
-- VÉRIFICATION
-- =============================================

-- 15. AFFICHER LES TABLES CRÉÉES
SHOW TABLES;

-- 16. COMPTER LES ENREGISTREMENTS PAR TABLE
SELECT 'users' as table_name, COUNT(*) as count FROM users
UNION ALL
SELECT 'dons', COUNT(*) FROM dons
UNION ALL
SELECT 'demandes', COUNT(*) FROM demandes
UNION ALL
SELECT 'livraisons', COUNT(*) FROM livraisons
UNION ALL
SELECT 'messages', COUNT(*) FROM messages
UNION ALL
SELECT 'livreurs', COUNT(*) FROM livreurs;

-- =============================================
-- INFORMATIONS DE CONNEXION
-- =============================================

-- 🔐 COMPTE ADMIN PAR DÉFAUT :
-- Email: admin@ageofdonnation.org
-- Mot de passe: admin123
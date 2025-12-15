<?php
// Fonctions utilitaires pour tout le site

function getCategorieLabel($categorie) {
    $labels = [
        'vetements' => 'Vêtements',
        'nourriture' => 'Nourriture',
        'meubles' => 'Meubles',
        'livres' => 'Livres',
        'electromenager' => 'Électroménager',
        'divers' => 'Divers'
    ];
    return $labels[$categorie] ?? $categorie;
}

function getEtatLabel($etat) {
    $labels = [
        'neuf' => 'Neuf',
        'bon_etat' => 'Bon état',
        'usage' => 'État d\'usage'
    ];
    return $labels[$etat] ?? $etat;
}

function formatDate($dateString) {
    return date('d/m/Y à H:i', strtotime($dateString));
}

function getBadgeClass($statut) {
    $classes = [
        'disponible' => 'badge-success',
        'reserve' => 'badge-warning',
        'donne' => 'badge-info',
        'expire' => 'badge-danger',
        'en_attente' => 'badge-secondary',
        'acceptee' => 'badge-success',
        'refusee' => 'badge-danger',
        'annulee' => 'badge-dark'
    ];
    return $classes[$statut] ?? 'badge-light';
}

// Fonction pour afficher une image par défaut si aucune photo n'est disponible
function getDonImage($don) {
    // Si une photo principale existe et le fichier existe
    if (!empty($don['photo_principale']) && file_exists('../' . $don['photo_principale'])) {
        // Retourner une balise img HTML
        return '<img src="../' . $don['photo_principale'] . '" alt="' . htmlspecialchars($don['titre']) . '" class="don-image">';
    }
    
    // Sinon, retourner un emoji selon la catégorie
    $defaultImages = [
        'vetements' => '👕 Vêtements',
        'nourriture' => '🍎 Nourriture',
        'meubles' => '🛋️ Meubles',
        'livres' => '📚 Livres',
        'electromenager' => '🔌 Électroménager',
        'divers' => '📦 Divers'
    ];
    
    $emoji = $defaultImages[$don['categorie']] ?? '📦 Divers';
    return '<div class="don-placeholder">' . $emoji . '</div>';
}

// Fonction pour vérifier si un fichier est une image
function isImageFile($filename) {
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return in_array($extension, $allowedExtensions);
}

// Fonction pour générer un nom de fichier unique
function generateUniqueFilename($originalName) {
    $extension = pathinfo($originalName, PATHINFO_EXTENSION);
    return uniqid() . '_' . time() . '.' . $extension;
}
?>

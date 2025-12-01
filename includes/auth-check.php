<?php
function requireAuth($allowedTypes = []) {
    // Vérifier si l'utilisateur est connecté
    if (!isset($_SESSION['user_id'])) {
        header('Location: ../auth/login.php');
        exit();
    }
    
    // Vérifier les permissions si des types sont spécifiés
    if (!empty($allowedTypes)) {
        $userType = $_SESSION['user_type'] ?? '';
        if (!in_array($userType, $allowedTypes)) {
            http_response_code(403);
            die('Accès non autorisé. Vous n\'avez pas les permissions nécessaires.');
        }
    }
}

function isAdmin() {
    return isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin';
}

function isDonateur() {
    return isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'donateur';
}

function isBeneficiaire() {
    return isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'beneficiaire';
}

function isLivreur() {
    return isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'livreur';
}

// Fonction pour rediriger selon le type d'utilisateur
function redirectByUserType() {
    if (!isset($_SESSION['user_type'])) {
        header('Location: ../auth/login.php');
        exit();
    }
    
    switch($_SESSION['user_type']) {
        case 'donateur':
            header('Location: ../donateur/dashboard.php');
            break;
        case 'beneficiaire':
            header('Location: ../beneficiaire/dashboard.php');
            break;
        case 'livreur':
            header('Location: ../livreur/dashboard.php');
            break;
        case 'admin':
            header('Location: ../admin/dashboard.php');
            break;
        default:
            header('Location: ../index.php');
    }
    exit();
}
?>
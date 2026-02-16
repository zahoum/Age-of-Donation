<?php
// donateur/voir-don.php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'donateur') {
    header('Location: ../auth/login.php');
    exit();
}

require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$user_id = $_SESSION['user_id'];

// جلب معلومات المستخدم الحالي
$query_user = "SELECT * FROM users WHERE id = :user_id";
$stmt_user = $db->prepare($query_user);
$stmt_user->bindParam(":user_id", $user_id);
$stmt_user->execute();
$current_user = $stmt_user->fetch(PDO::FETCH_ASSOC);

$don_id = $_GET['id'] ?? null;

if (!$don_id) {
    header('Location: mes-dons.php');
    exit();
}

// Récupérer les détails du don
$query = "
    SELECT d.*, 
           u.nom as donateur_nom,
           u.email as donateur_email,
           u.telephone as donateur_telephone
    FROM dons d
    INNER JOIN users u ON d.donateur_id = u.id
    WHERE d.id = :don_id AND d.donateur_id = :user_id
";

$stmt = $db->prepare($query);
$stmt->bindParam(":don_id", $don_id);
$stmt->bindParam(":user_id", $user_id);
$stmt->execute();
$don = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$don) {
    header('Location: mes-dons.php');
    exit();
}

// Récupérer les photos supplémentaires
$query_photos = "SELECT photo_path FROM don_photos WHERE don_id = :don_id ORDER BY id";
$stmt_photos = $db->prepare($query_photos);
$stmt_photos->bindParam(":don_id", $don_id);
$stmt_photos->execute();
$photos = $stmt_photos->fetchAll(PDO::FETCH_COLUMN);

// Récupérer les demandes pour ce don
$query_demandes = "
    SELECT d.*, u.nom as beneficiaire_nom, u.ville as beneficiaire_ville, u.email as beneficiaire_email
    FROM demandes d
    INNER JOIN users u ON d.beneficiaire_id = u.id
    WHERE d.don_id = :don_id
    ORDER BY d.created_at DESC
";
$stmt_demandes = $db->prepare($query_demandes);
$stmt_demandes->bindParam(":don_id", $don_id);
$stmt_demandes->execute();
$demandes = $stmt_demandes->fetchAll(PDO::FETCH_ASSOC);

// Compter les demandes en attente
$demandes_attente = array_filter($demandes, function($d) {
    return $d['statut'] == 'en_attente';
});

$page_title = 'عرض التبرع';
?>

<!DOCTYPE html>
<html lang="fr" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - Age of Donnation</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #2d3436;
            --secondary: #636e72;
            --accent: #0984e3;
            --light: #f5f6fa;
            --dark: #2d3436;
            --success: #00b894;
            --danger: #d63031;
            --warning: #fdcb6e;
            --info: #00cec9;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Tajawal', sans-serif;
        }
        
        body {
            background-color: #f8f9fa;
            color: #333;
            line-height: 1.6;
        }
        
        /* Navbar */
        .navbar {
            background: white;
            box-shadow: 0 2px 15px rgba(0,0,0,0.08);
            padding: 0 20px;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: 70px;
        }
        
        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            color: var(--primary);
            font-weight: 700;
            font-size: 24px;
        }
        
        .logo-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--accent), #74b9ff);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
        }
        
        .nav-links {
            display: flex;
            gap: 5px;
            list-style: none;
            margin: 0;
            padding: 0;
        }
        
        .nav-item {
            position: relative;
        }
        
        .nav-link {
            text-decoration: none;
            color: var(--secondary);
            padding: 10px 20px;
            border-radius: 8px;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
        }
        
        .nav-link:hover {
            background: #f1f2f6;
            color: var(--accent);
        }
        
        .nav-link.active {
            background: linear-gradient(135deg, var(--accent), #74b9ff);
            color: white;
            box-shadow: 0 4px 12px rgba(116, 185, 255, 0.3);
        }
        
        .user-menu {
            display: flex;
            align-items: center;
            gap: 15px;
            position: relative;
        }
        
        .user-avatar {
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, #00b894, #00cec9);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 18px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .user-avatar:hover {
            transform: scale(1.05);
            box-shadow: 0 5px 15px rgba(0, 184, 148, 0.3);
        }
        
        .user-dropdown {
            position: absolute;
            top: 60px;
            left: 0;
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
            min-width: 200px;
            display: none;
            z-index: 1000;
            overflow: hidden;
        }
        
        .user-dropdown.active {
            display: block;
        }
        
        .user-dropdown-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 15px 20px;
            text-decoration: none;
            color: var(--dark);
            border-bottom: 1px solid #f1f2f6;
            transition: all 0.3s;
        }
        
        .user-dropdown-item:hover {
            background: #f8f9fa;
            color: var(--accent);
        }
        
        .user-dropdown-item:last-child {
            border-bottom: none;
            color: var(--danger);
        }
        
        .user-dropdown-item:last-child:hover {
            background: #ffebee;
        }
        
        /* Main Content */
        .main-content {
            margin-top: 90px;
            padding: 20px;
            min-height: calc(100vh - 160px);
        }
        
        /* Container */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 15px;
        }
        
        /* Welcome Section */
        .welcome-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
        }

        .welcome-section::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: rotate 20s linear infinite;
        }

        .welcome-section::after {
            content: '';
            position: absolute;
            bottom: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.05) 0%, transparent 60%);
            animation: rotate 25s linear infinite reverse;
        }

        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .welcome-content {
            position: relative;
            z-index: 1;
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .welcome-icon {
            width: 60px;
            height: 60px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 30px;
            color: white;
            backdrop-filter: blur(10px);
            border: 2px solid rgba(255, 255, 255, 0.3);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        .welcome-text h1 {
            font-size: 28px;
            margin-bottom: 5px;
            font-weight: 700;
        }

        .welcome-text p {
            font-size: 16px;
            opacity: 0.95;
            margin: 0;
        }
        
        /* Cards */
        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            margin-bottom: 25px;
            overflow: hidden;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.12);
        }
        
        .card-header {
            padding: 20px 25px;
            background: #f8f9fa;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .card-header h3 {
            margin: 0;
            color: var(--primary);
            font-size: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .card-body {
            padding: 25px;
        }
        
        /* Row and Col */
        .row {
            display: flex;
            flex-wrap: wrap;
            margin: 0 -15px;
        }
        
        .col-8 {
            flex: 0 0 66.666%;
            max-width: 66.666%;
            padding: 0 15px;
        }
        
        .col-4 {
            flex: 0 0 33.333%;
            max-width: 33.333%;
            padding: 0 15px;
        }
        
        .col-5 {
            flex: 0 0 41.666%;
            max-width: 41.666%;
            padding: 0 15px;
        }
        
        .col-7 {
            flex: 0 0 58.333%;
            max-width: 58.333%;
            padding: 0 15px;
        }
        
        @media (max-width: 992px) {
            .col-8, .col-4, .col-5, .col-7 {
                flex: 0 0 100%;
                max-width: 100%;
            }
        }
        
        /* Buttons */
        .btn {
            padding: 10px 25px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            font-size: 15px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--accent), #74b9ff);
            color: white;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #0984e3, #0984e3);
            box-shadow: 0 5px 15px rgba(116, 185, 255, 0.4);
        }
        
        .btn-success {
            background: linear-gradient(135deg, #00b894, #00cec9);
            color: white;
        }
        
        .btn-warning {
            background: linear-gradient(135deg, #fdcb6e, #e17055);
            color: white;
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #d63031, #ff7675);
            color: white;
        }
        
        .btn-info {
            background: linear-gradient(135deg, #00cec9, #00b894);
            color: white;
        }
        
        .btn-outline {
            background: transparent;
            border: 2px solid var(--accent);
            color: var(--accent);
        }
        
        .btn-outline:hover {
            background: var(--accent);
            color: white;
        }
        
        .btn-sm {
            padding: 5px 15px;
            font-size: 13px;
        }
        
        /* Badge */
        .badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            display: inline-block;
        }
        
        .badge-primary {
            background: #cce5ff;
            color: #004085;
        }
        
        .badge-success {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-warning {
            background: #fff3cd;
            color: #856404;
        }
        
        .badge-danger {
            background: #f8d7da;
            color: #721c24;
        }
        
        .badge-info {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        /* Image Gallery */
        .main-image {
            height: 300px;
            background: #f8f9fa;
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 15px;
            border: 1px solid #eee;
        }
        
        .main-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .image-placeholder {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #aaa;
            font-size: 80px;
            background: linear-gradient(135deg, #f5f7fa, #e4e8f0);
        }
        
        .additional-images {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 15px;
        }
        
        .additional-image {
            width: 80px;
            height: 80px;
            border-radius: 8px;
            overflow: hidden;
            cursor: pointer;
            border: 2px solid transparent;
            transition: all 0.3s;
        }
        
        .additional-image:hover {
            transform: scale(1.05);
            border-color: var(--accent);
        }
        
        .additional-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        /* Info Box */
        .info-box {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-top: 20px;
        }
        
        .info-row {
            display: flex;
            margin-bottom: 12px;
            padding-bottom: 12px;
            border-bottom: 1px dashed #ddd;
        }
        
        .info-row:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        
        .info-label {
            width: 120px;
            color: #666;
            font-weight: 500;
        }
        
        .info-value {
            flex: 1;
            color: var(--dark);
        }
        
        /* Description Box */
        .description-box {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
            border-right: 4px solid var(--accent);
        }
        
        /* Demand Card */
        .demand-card {
            border: 1px solid #eee;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            transition: all 0.3s;
        }
        
        .demand-card:hover {
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        
        .demand-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
        }
        
        .demand-status {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 500;
        }
        
        .demand-status.pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .demand-status.accepted {
            background: #d4edda;
            color: #155724;
        }
        
        .demand-status.rejected {
            background: #f8d7da;
            color: #721c24;
        }
        
        .demand-message {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
            border-right: 3px solid var(--accent);
        }
        
        /* Avatar */
        .avatar-small {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #f59e0b, #d97706);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 18px;
        }
        
        /* Action Group */
        .action-group {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.8);
            z-index: 3000;
            justify-content: center;
            align-items: center;
        }
        
        .modal-content {
            position: relative;
            max-width: 90%;
            max-height: 90%;
        }
        
        .modal-content img {
            max-width: 100%;
            max-height: 90vh;
            border-radius: 10px;
        }
        
        .modal-close {
            position: absolute;
            top: -40px;
            left: 0;
            background: none;
            border: none;
            color: white;
            font-size: 30px;
            cursor: pointer;
        }
        
        /* Mobile Menu Toggle */
        .menu-toggle {
            display: none;
            background: none;
            border: none;
            font-size: 24px;
            color: var(--primary);
            cursor: pointer;
        }
        
        @media (max-width: 768px) {
            .menu-toggle {
                display: block;
            }
            
            .nav-links {
                display: none;
            }
            
            .nav-links.active {
                display: flex;
                flex-direction: column;
                position: absolute;
                top: 70px;
                left: 0;
                right: 0;
                background: white;
                box-shadow: 0 5px 15px rgba(0,0,0,0.1);
                padding: 20px;
            }
            
            .main-content {
                margin-top: 80px;
                padding: 15px;
            }
            
            .demand-header {
                flex-direction: column;
                gap: 10px;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <a href="dashboard.php" class="logo">
            <div class="logo-icon">
                <i class="fas fa-hands-helping"></i>
            </div>
            <span>Age of Donnation</span>
        </a>
        
        <button class="menu-toggle" onclick="toggleMenu()">
            <i class="fas fa-bars"></i>
        </button>
        
        <ul class="nav-links" id="navLinks">
            <li class="nav-item"><a href="dashboard.php" class="nav-link"><i class="fas fa-home"></i> لوحة التحكم</a></li>
            <li class="nav-item"><a href="publier-don.php" class="nav-link"><i class="fas fa-plus-circle"></i> نشر تبرع</a></li>
            <li class="nav-item"><a href="mes-dons.php" class="nav-link"><i class="fas fa-boxes"></i> تبرعاتي</a></li>
            <li class="nav-item"><a href="confirmer-commandes.php" class="nav-link"><i class="fas fa-check-circle"></i> تأكيد الطلبات</a></li>
            <li class="nav-item"><a href="messagerie.php" class="nav-link"><i class="fas fa-comments"></i> المراسلة</a></li>
        </ul>
        
        <div class="user-menu">
            <div class="user-avatar" onclick="toggleDropdown()" title="الملف الشخصي">
                <?php echo strtoupper(substr($current_user['nom'], 0, 1)); ?>
            </div>
            <div class="user-dropdown" id="userDropdown">
                <a href="profile.php" class="user-dropdown-item">
                    <i class="fas fa-user"></i> الملف الشخصي
                </a>
                <a href="mes-dons.php" class="user-dropdown-item">
                    <i class="fas fa-boxes"></i> تبرعاتي
                </a>
                <a href="messagerie.php" class="user-dropdown-item">
                    <i class="fas fa-comments"></i> المراسلة
                </a>
                <a href="../auth/logout.php" class="user-dropdown-item">
                    <i class="fas fa-sign-out-alt"></i> تسجيل الخروج
                </a>
            </div>
        </div>
    </nav>
    
    <!-- Main Content -->
    <div class="main-content">
        <div class="container">
            <!-- Welcome Section -->
            <div class="welcome-section">
                <div class="welcome-content">
                    <div class="welcome-icon">
                        <i class="fas fa-eye"></i>                    
                    </div>
                    <div class="welcome-text">
                        <h1>تفاصيل التبرع</h1>
                        <p><?php echo htmlspecialchars($don['titre']); ?></p>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-8">
                    <!-- Don Information -->
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-gift"></i> <?php echo htmlspecialchars($don['titre']); ?></h3>
                            <span class="badge badge-<?php 
                                echo $don['statut'] == 'disponible' ? 'success' : 
                                    ($don['statut'] == 'reserve' ? 'warning' : 'info'); 
                            ?>">
                                <?php 
                                $statuts = [
                                    'disponible' => 'متاح',
                                    'reserve' => 'محجوز',
                                    'donne' => 'مكتمل'
                                ];
                                echo $statuts[$don['statut']] ?? $don['statut'];
                                ?>
                            </span>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-5">
                                    <!-- Main Image -->
                                    <div class="main-image">
                                        <?php if(!empty($don['photo_principale'])): 
                                            $image_path = '../' . $don['photo_principale'];
                                            if(file_exists($image_path)): ?>
                                                <img src="<?php echo $image_path; ?>" 
                                                     alt="<?php echo htmlspecialchars($don['titre']); ?>"
                                                     onclick="openImageModal('<?php echo $image_path; ?>')">
                                            <?php else: ?>
                                                <div class="image-placeholder">
                                                    <?php 
                                                    $defaultImages = [
                                                        'vetements' => '👕',
                                                        'nourriture' => '🍎',
                                                        'meubles' => '🛋️',
                                                        'livres' => '📚',
                                                        'electromenager' => '🔌',
                                                        'divers' => '📦'
                                                    ];
                                                    echo $defaultImages[$don['categorie']] ?? '📦';
                                                    ?>
                                                </div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <div class="image-placeholder">
                                                <?php 
                                                $defaultImages = [
                                                    'vetements' => '👕',
                                                    'nourriture' => '🍎',
                                                    'meubles' => '🛋️',
                                                    'livres' => '📚',
                                                    'electromenager' => '🔌',
                                                    'divers' => '📦'
                                                ];
                                                echo $defaultImages[$don['categorie']] ?? '📦';
                                                ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Additional Photos -->
                                    <?php if(!empty($photos)): ?>
                                        <div>
                                            <h5 style="margin-bottom: 10px; color: var(--primary);">
                                                <i class="fas fa-images"></i> صور إضافية
                                            </h5>
                                            <div class="additional-images">
                                                <?php foreach($photos as $photo): 
                                                    $photo_path = '../' . $photo;
                                                    if(file_exists($photo_path)): ?>
                                                        <div class="additional-image" onclick="openImageModal('<?php echo $photo_path; ?>')">
                                                            <img src="<?php echo $photo_path; ?>" alt="صورة إضافية">
                                                        </div>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="col-7">
                                    <!-- Badges -->
                                    <div style="display: flex; gap: 10px; margin-bottom: 20px;">
                                        <span class="badge badge-primary">
                                            <?php 
                                            $categories = [
                                                'vetements' => 'ملابس',
                                                'nourriture' => 'طعام',
                                                'meubles' => 'أثاث',
                                                'livres' => 'كتب',
                                                'electromenager' => 'أجهزة كهربائية',
                                                'divers' => 'متنوع'
                                            ];
                                            echo $categories[$don['categorie']] ?? $don['categorie'];
                                            ?>
                                        </span>
                                        <span class="badge badge-success">
                                            <?php 
                                            $etats = [
                                                'neuf' => 'جديد',
                                                'bon_etat' => 'حالة جيدة',
                                                'usage' => 'مستعمل'
                                            ];
                                            echo $etats[$don['etat']] ?? $don['etat'];
                                            ?>
                                        </span>
                                    </div>
                                    
                                    <!-- Description -->
                                    <div class="description-box">
                                        <strong style="color: var(--accent); display: block; margin-bottom: 10px;">
                                            <i class="fas fa-align-right"></i> الوصف:
                                        </strong>
                                        <p style="color: #333; line-height: 1.8;">
                                            <?php echo nl2br(htmlspecialchars($don['description'])); ?>
                                        </p>
                                    </div>
                                    
                                    <!-- Location Info -->
                                    <div class="info-box">
                                        <h5 style="margin-bottom: 15px; color: var(--primary);">
                                            <i class="fas fa-map-marker-alt" style="color: var(--accent);"></i> معلومات الاستلام
                                        </h5>
                                        <div class="info-row">
                                            <span class="info-label">المدينة:</span>
                                            <span class="info-value"><?php echo htmlspecialchars($don['ville'] ?: 'غير محدد'); ?></span>
                                        </div>
                                        <div class="info-row">
                                            <span class="info-label">العنوان:</span>
                                            <span class="info-value"><?php echo htmlspecialchars($don['adresse_retrait'] ?: 'غير محدد'); ?></span>
                                        </div>
                                    </div>
                                    
                                    <!-- Date Info -->
                                    <div style="margin-top: 15px; color: #666; font-size: 14px;">
                                        <i class="fas fa-calendar"></i> تاريخ النشر: <?php echo date('d/m/Y', strtotime($don['created_at'])); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Demandes List -->
                    <?php if(!empty($demandes)): ?>
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-file-alt"></i> الطلبات على هذا التبرع</h3>
                            <span class="badge badge-warning"><?php echo count($demandes_attente); ?> في الانتظار</span>
                        </div>
                        <div class="card-body">
                            <?php foreach($demandes as $demande): ?>
                                <div class="demand-card">
                                    <div class="demand-header">
                                        <div style="display: flex; align-items: center; gap: 15px;">
                                            <div class="avatar-small">
                                                <?php echo strtoupper(substr($demande['beneficiaire_nom'], 0, 1)); ?>
                                            </div>
                                            <div>
                                                <h5 style="margin-bottom: 5px;"><?php echo htmlspecialchars($demande['beneficiaire_nom']); ?></h5>
                                                <?php if($demande['beneficiaire_ville']): ?>
                                                    <small style="color: #666;">
                                                        <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($demande['beneficiaire_ville']); ?>
                                                    </small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div style="text-align: left;">
                                            <span class="demand-status <?php 
                                                echo $demande['statut'] == 'en_attente' ? 'pending' : 
                                                    ($demande['statut'] == 'acceptee' ? 'accepted' : 'rejected');
                                            ?>">
                                                <?php 
                                                $statuts = [
                                                    'en_attente' => 'في الانتظار',
                                                    'acceptee' => 'مقبول',
                                                    'refusee' => 'مرفوض'
                                                ];
                                                echo $statuts[$demande['statut']] ?? $demande['statut'];
                                                ?>
                                            </span>
                                            <small style="display: block; margin-top: 5px; color: #888;">
                                                <i class="fas fa-clock"></i> <?php echo date('d/m/Y H:i', strtotime($demande['created_at'])); ?>
                                            </small>
                                        </div>
                                    </div>
                                    
                                    <div class="demand-message">
                                        <strong style="color: var(--accent); display: block; margin-bottom: 8px;">
                                            <i class="fas fa-quote-right"></i> رسالة المستفيد:
                                        </strong>
                                        <p style="color: #333; line-height: 1.6;">
                                            <?php echo nl2br(htmlspecialchars($demande['message_demande'])); ?>
                                        </p>
                                    </div>
                                    
                                    <?php if($demande['statut'] == 'en_attente'): ?>
                                    <div style="display: flex; gap: 10px; margin-top: 15px;">
                                        <a href="confirmer-commandes.php?confirm=1&demande_id=<?php echo $demande['id']; ?>" 
                                           class="btn btn-success btn-sm"
                                           onclick="return confirm('هل أنت متأكد من قبول هذا الطلب؟')">
                                            <i class="fas fa-check"></i> قبول
                                        </a>
                                        <a href="confirmer-commandes.php?refuse=<?php echo $demande['id']; ?>" 
                                           class="btn btn-danger btn-sm"
                                           onclick="return confirm('هل أنت متأكد من رفض هذا الطلب؟')">
                                            <i class="fas fa-times"></i> رفض
                                        </a>
                                        <a href="messagerie.php?user_id=<?php echo $demande['beneficiaire_id']; ?>" 
                                           class="btn btn-outline btn-sm">
                                            <i class="fas fa-comments"></i> تواصل
                                        </a>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="col-4">
                    <!-- Actions -->
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-cogs"></i> الإجراءات</h3>
                        </div>
                        <div class="card-body">
                            <div class="action-group">
                                <a href="mes-dons.php" class="btn btn-outline">
                                    <i class="fas fa-arrow-right"></i> العودة إلى قائمة التبرعات
                                </a>
                                
                                <?php if($don['statut'] == 'disponible'): ?>
                                    <a href="modifier-don.php?id=<?php echo $don['id']; ?>" class="btn btn-warning">
                                        <i class="fas fa-edit"></i> تعديل التبرع
                                    </a>
                                    
                                    <a href="mes-dons.php?action=delete&id=<?php echo $don['id']; ?>" 
                                       class="btn btn-danger"
                                       onclick="return confirm('هل أنت متأكد من حذف هذا التبرع؟')">
                                        <i class="fas fa-trash"></i> حذف التبرع
                                    </a>
                                <?php endif; ?>
                                
                                <?php if(!empty($demandes_attente)): ?>
                                    <a href="confirmer-commandes.php?don_id=<?php echo $don['id']; ?>" class="btn btn-primary">
                                        <i class="fas fa-file-alt"></i> إدارة الطلبات (<?php echo count($demandes_attente); ?>)
                                    </a>
                                <?php endif; ?>
                                
                                <a href="messagerie.php" class="btn btn-info">
                                    <i class="fas fa-comments"></i> الذهاب للمراسلة
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Don Information -->
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-info-circle"></i> معلومات النشر</h3>
                        </div>
                        <div class="card-body">
                            <div class="info-row">
                                <span class="info-label">رقم التبرع:</span>
                                <span class="info-value">#<?php echo $don['id']; ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">تاريخ النشر:</span>
                                <span class="info-value"><?php echo date('d/m/Y', strtotime($don['created_at'])); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">آخر تحديث:</span>
                                <span class="info-value"><?php echo date('d/m/Y', strtotime($don['updated_at'] ?? $don['created_at'])); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">عدد الطلبات:</span>
                                <span class="info-value"><?php echo count($demandes); ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Contact Info -->
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-user-circle"></i> معلومات المتبرع</h3>
                        </div>
                        <div class="card-body">
                            <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 20px;">
                                <div class="avatar-small" style="width: 60px; height: 60px; font-size: 24px;">
                                    <?php echo strtoupper(substr($don['donateur_nom'], 0, 1)); ?>
                                </div>
                                <div>
                                    <h4 style="margin-bottom: 5px;"><?php echo htmlspecialchars($don['donateur_nom']); ?></h4>
                                    <small style="color: #666;">متبرع</small>
                                </div>
                            </div>
                            
                            <div style="background: #f8f9fa; padding: 15px; border-radius: 8px;">
                                <?php if($don['donateur_email']): ?>
                                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                                    <i class="fas fa-envelope" style="color: var(--accent);"></i>
                                    <span style="color: #333;"><?php echo htmlspecialchars($don['donateur_email']); ?></span>
                                </div>
                                <?php endif; ?>
                                
                                <?php if($don['donateur_telephone']): ?>
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <i class="fas fa-phone" style="color: var(--accent);"></i>
                                    <span style="color: #333; direction: ltr; text-align: right;"><?php echo htmlspecialchars($don['donateur_telephone']); ?></span>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Image Modal -->
    <div id="imageModal" class="modal" onclick="closeImageModal(event)">
        <div class="modal-content">
            <img id="modalImage" src="">
            <button class="modal-close" onclick="closeImageModal(event)">×</button>
        </div>
    </div>

    <script>
    function toggleMenu() {
        const navLinks = document.getElementById('navLinks');
        navLinks.classList.toggle('active');
    }
    
    function toggleDropdown() {
        const dropdown = document.getElementById('userDropdown');
        dropdown.classList.toggle('active');
    }
    
    function openImageModal(imageSrc) {
        document.getElementById('modalImage').src = imageSrc;
        document.getElementById('imageModal').style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }
    
    function closeImageModal(event) {
        if (event.target === document.getElementById('imageModal') || event.target.classList.contains('modal-close')) {
            document.getElementById('imageModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }
    }
    
    // Close modal with Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.getElementById('imageModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }
    });
    
    // إغلاق القوائم عند النقر خارجها
    document.addEventListener('click', function(event) {
        const navLinks = document.getElementById('navLinks');
        const menuToggle = document.querySelector('.menu-toggle');
        const userDropdown = document.getElementById('userDropdown');
        const userAvatar = document.querySelector('.user-avatar');
        
        if (!navLinks.contains(event.target) && !menuToggle.contains(event.target)) {
            navLinks.classList.remove('active');
        }
        
        if (!userDropdown.contains(event.target) && !userAvatar.contains(event.target)) {
            userDropdown.classList.remove('active');
        }
    });
    </script>
</body>
</html>
<?php
include '../includes/footer.php'; ?>
<?php
// donateur/mes-dons.php
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

$success = '';
$error = '';

// ========== حذف تبرع (Soft Delete) ==========
if (isset($_GET['delete']) && isset($_GET['don_id'])) {
    $don_id = $_GET['delete'];
    
    try {
        // التحقق من أن التبرع يخص المستخدم الحالي
        $check_query = "SELECT id FROM dons WHERE id = :don_id AND donateur_id = :user_id AND (is_deleted IS NULL OR is_deleted = 0)";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->bindParam(':don_id', $don_id);
        $check_stmt->bindParam(':user_id', $user_id);
        $check_stmt->execute();
        
        if ($check_stmt->rowCount() > 0) {
            // Soft delete: marquer comme supprimé sans effacer de la base
            $query = "UPDATE dons SET is_deleted = 1, deleted_at = NOW() WHERE id = :don_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':don_id', $don_id);
            
            if ($stmt->execute()) {
                $success = "✅ تم حذف التبرع بنجاح";
            } else {
                $error = "❌ حدث خطأ أثناء حذف التبرع";
            }
        } else {
            $error = "❌ هذا التبرع غير موجود أو لا يخصك";
        }
    } catch(PDOException $e) {
        $error = "❌ خطأ في قاعدة البيانات: " . $e->getMessage();
    }
}

// ========== استعادة تبرع محذوف ==========
if (isset($_GET['restore']) && isset($_GET['don_id'])) {
    $don_id = $_GET['restore'];
    
    try {
        // التحقق من أن التبرع يخص المستخدم الحالي وهو محذوف
        $check_query = "SELECT id FROM dons WHERE id = :don_id AND donateur_id = :user_id AND is_deleted = 1";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->bindParam(':don_id', $don_id);
        $check_stmt->bindParam(':user_id', $user_id);
        $check_stmt->execute();
        
        if ($check_stmt->rowCount() > 0) {
            $query = "UPDATE dons SET is_deleted = 0, deleted_at = NULL WHERE id = :don_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':don_id', $don_id);
            
            if ($stmt->execute()) {
                $success = "✅ تم استعادة التبرع بنجاح";
            } else {
                $error = "❌ حدث خطأ أثناء استعادة التبرع";
            }
        } else {
            $error = "❌ هذا التبرع غير موجود أو لا يخصك";
        }
    } catch(PDOException $e) {
        $error = "❌ خطأ في قاعدة البيانات: " . $e->getMessage();
    }
}

// ========== جلب التبرعات ==========
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';

$query_dons = "
    SELECT d.*, 
           (SELECT COUNT(*) FROM demandes WHERE don_id = d.id AND statut = 'en_attente') as demandes_attente,
           (SELECT COUNT(*) FROM demandes WHERE don_id = d.id AND statut = 'acceptee') as demandes_acceptees,
           (SELECT COUNT(*) FROM demandes WHERE don_id = d.id AND statut = 'refusee') as demandes_refusees,
           (SELECT COUNT(*) FROM livraisons l INNER JOIN demandes de ON l.demande_id = de.id WHERE de.don_id = d.id) as a_livraison
    FROM dons d
    WHERE d.donateur_id = :user_id
";

// تطبيق الفلتر
if($filter == 'active') {
    $query_dons .= " AND d.statut = 'disponible' AND (d.is_deleted IS NULL OR d.is_deleted = 0)";
} elseif($filter == 'reserved') {
    $query_dons .= " AND d.statut = 'reserve' AND (d.is_deleted IS NULL OR d.is_deleted = 0)";
} elseif($filter == 'completed') {
    $query_dons .= " AND (d.statut = 'donne' OR d.is_deleted = 1)";
} elseif($filter == 'deleted') {
    $query_dons .= " AND d.is_deleted = 1";
} else {
    // tous les dons
    $query_dons .= " ORDER BY 
        CASE 
            WHEN d.is_deleted = 1 THEN 2 
            WHEN d.statut = 'disponible' THEN 0
            ELSE 1 
        END,
        d.created_at DESC";
}

if(!in_array($filter, ['all', 'active', 'reserved', 'completed', 'deleted'])) {
    $query_dons .= " ORDER BY d.created_at DESC";
}

$stmt = $db->prepare($query_dons);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$dons = $stmt->fetchAll(PDO::FETCH_ASSOC);

// إحصائيات سريعة
$stats_query = "
    SELECT 
        COUNT(CASE WHEN statut = 'disponible' AND (is_deleted IS NULL OR is_deleted = 0) THEN 1 END) as disponibles,
        COUNT(CASE WHEN statut = 'reserve' AND (is_deleted IS NULL OR is_deleted = 0) THEN 1 END) as reserves,
        COUNT(CASE WHEN statut = 'donne' OR is_deleted = 1 THEN 1 END) as termines,
        COUNT(CASE WHEN is_deleted = 1 THEN 1 END) as supprimes
    FROM dons
    WHERE donateur_id = :user_id
";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->bindParam(':user_id', $user_id);
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// تجهيز الفئات والحالات للعرض
$categories = [
    'vetements' => 'ملابس',
    'nourriture' => 'طعام',
    'meubles' => 'أثاث',
    'livres' => 'كتب',
    'electromenager' => 'أجهزة كهربائية',
    'divers' => 'متنوع'
];

$etats = [
    'neuf' => 'جديد',
    'bon_etat' => 'حالة جيدة',
    'usage' => 'مستعمل'
];

$livraison_options = [
    'none' => 'المستفيد يتحمل التوصيل',
    'fifty' => 'المتبرع يتحمل 50%',
    'full' => 'المتبرع يتحمل التوصيل كاملاً'
];

$page_title = 'تبرعاتي';
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
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            gap: 15px;
            transition: transform 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
        }
        
        .stat-content h3 {
            font-size: 24px;
            margin: 0;
            color: var(--primary);
        }
        
        .stat-content p {
            margin: 5px 0 0;
            color: var(--secondary);
            font-size: 14px;
        }
        
        /* Filters */
        .filters {
            background: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .filter-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            justify-content: center;
        }
        
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
        
        .btn-success:hover {
            background: linear-gradient(135deg, #00a085, #00b7a8);
            box-shadow: 0 5px 15px rgba(0, 184, 148, 0.3);
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #d63031, #ff7675);
            color: white;
        }
        
        .btn-danger:hover {
            background: linear-gradient(135deg, #c0392b, #e17055);
            box-shadow: 0 5px 15px rgba(214, 48, 49, 0.3);
        }
        
        .btn-warning {
            background: linear-gradient(135deg, #fdcb6e, #f39c12);
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
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        /* Card */
        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            margin-bottom: 25px;
            overflow: hidden;
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
        
        /* Don Grid */
        .dons-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
        }
        
        .don-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            border: 1px solid #eee;
            transition: all 0.3s;
            position: relative;
        }
        
        .don-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }
        
        .don-card.deleted {
            opacity: 0.8;
            background: #f8f9fa;
            border: 1px dashed #dc3545;
        }
        
        .don-card.completed {
            border-right: 4px solid #6c757d;
        }
        
        .deleted-badge {
            position: absolute;
            top: 15px;
            left: 15px;
            background: #dc3545;
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            z-index: 1;
        }
        
        .don-header {
            padding: 15px 20px;
            background: #f8f9fa;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .don-header h3 {
            margin: 0;
            font-size: 18px;
            color: var(--primary);
        }
        
        .don-image {
            height: 180px;
            background: #e9ecef;
            position: relative;
            overflow: hidden;
        }
        
        .don-image img {
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
            font-size: 48px;
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            color: #adb5bd;
        }
        
        .don-badge {
            position: absolute;
            bottom: 15px;
            right: 15px;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            color: white;
        }
        
        .badge-success { background: var(--success); }
        .badge-warning { background: var(--warning); color: #333; }
        .badge-secondary { background: #6c757d; }
        .badge-danger { background: var(--danger); }
        
        .don-content {
            padding: 20px;
        }
        
        .don-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .meta-item {
            background: #f8f9fa;
            padding: 5px 10px;
            border-radius: 6px;
            font-size: 12px;
            color: #666;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .meta-item i {
            color: var(--accent);
        }
        
        .don-description {
            color: #666;
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 15px;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .don-stats {
            display: flex;
            justify-content: space-around;
            padding: 15px 0;
            border-top: 1px solid #eee;
            border-bottom: 1px solid #eee;
            margin: 15px 0;
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-number {
            font-size: 20px;
            font-weight: 700;
            color: var(--primary);
        }
        
        .stat-label {
            font-size: 12px;
            color: #666;
        }
        
        .don-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 15px;
        }
        
        .don-actions .btn {
            flex: 1;
            justify-content: center;
        }
        
        /* Alert */
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-right: 4px solid;
        }
        
        .alert-success {
            background: #d4edda;
            border-right-color: #155724;
            color: #155724;
        }
        
        .alert-danger {
            background: #f8d7da;
            border-right-color: #721c24;
            color: #721c24;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }
        
        .empty-state i {
            font-size: 80px;
            color: #ccc;
            margin-bottom: 20px;
        }
        
        .empty-state h3 {
            color: #666;
            margin-bottom: 10px;
        }
        
        .empty-state p {
            color: #999;
            margin-bottom: 20px;
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
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .dons-grid {
                grid-template-columns: 1fr;
            }
            
            .don-actions {
                flex-direction: column;
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
            <li class="nav-item"><a href="mes-dons.php" class="nav-link active"><i class="fas fa-boxes"></i> تبرعاتي</a></li>
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
                        <i class="fas fa-boxes"></i>                    
                    </div>
                    <div class="welcome-text">
                        <h1>تبرعاتي</h1>
                        <p>عرض وإدارة جميع تبرعاتك</p>
                    </div>
                </div>
            </div>

            <?php if($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo $success; ?>
                </div>
            <?php endif; ?>

            <?php if($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <!-- Statistiques -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #00b894, #00cec9);">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $stats['disponibles'] ?? 0; ?></h3>
                        <p>متاحة</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #fdcb6e, #f39c12);">
                        <i class="fas fa-hand-holding-heart"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $stats['reserves'] ?? 0; ?></h3>
                        <p>محجوزة</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #6c5ce7, #a29bfe);">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $stats['termines'] ?? 0; ?></h3>
                        <p>منتهية</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #d63031, #ff7675);">
                        <i class="fas fa-trash-alt"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $stats['supprimes'] ?? 0; ?></h3>
                        <p>محذوفة</p>
                    </div>
                </div>
            </div>

            <!-- Filtres -->
            <div class="filters">
                <div class="filter-buttons">
                    <a href="?filter=all" class="btn <?php echo $filter == 'all' ? 'btn-primary' : 'btn-outline'; ?>">
                        <i class="fas fa-list"></i> الكل
                    </a>
                    <a href="?filter=active" class="btn <?php echo $filter == 'active' ? 'btn-primary' : 'btn-outline'; ?>">
                        <i class="fas fa-clock"></i> المتاحة
                    </a>
                    <a href="?filter=reserved" class="btn <?php echo $filter == 'reserved' ? 'btn-primary' : 'btn-outline'; ?>">
                        <i class="fas fa-hand-holding-heart"></i> المحجوزة
                    </a>
                    <a href="?filter=completed" class="btn <?php echo $filter == 'completed' ? 'btn-primary' : 'btn-outline'; ?>">
                        <i class="fas fa-check-circle"></i> المنتهية
                    </a>
                    <a href="?filter=deleted" class="btn <?php echo $filter == 'deleted' ? 'btn-danger' : 'btn-outline'; ?>">
                        <i class="fas fa-trash-alt"></i> المحذوفة
                    </a>
                </div>
            </div>

            <!-- Liste des dons -->
            <?php if(empty($dons)): ?>
                <div class="empty-state">
                    <i class="fas fa-box-open"></i>
                    <h3>لا توجد تبرعات</h3>
                    <p>لم تقم بنشر أي تبرع بعد</p>
                    <a href="publier-don.php" class="btn btn-primary">
                        <i class="fas fa-plus-circle"></i> نشر تبرع جديد
                    </a>
                </div>
            <?php else: ?>
                <div class="dons-grid">
                    <?php foreach($dons as $don): 
                        $est_supprime = ($don['is_deleted'] == 1);
                        $est_termine = ($don['statut'] == 'donne');
                        $est_reserve = ($don['statut'] == 'reserve');
                        $est_disponible = ($don['statut'] == 'disponible' && !$est_supprime);
                    ?>
                        <div class="don-card <?php echo $est_supprime ? 'deleted' : ($est_termine ? 'completed' : ''); ?>">
                            <?php if($est_supprime): ?>
                                <div class="deleted-badge">
                                    <i class="fas fa-trash"></i> محذوف
                                </div>
                            <?php endif; ?>
                            
                            <div class="don-image">
                                <?php if(!empty($don['photo_principale'])): 
                                    $image_path = '../' . $don['photo_principale'];
                                    if(file_exists($image_path)): ?>
                                        <img src="<?php echo $image_path; ?>" alt="<?php echo htmlspecialchars($don['titre']); ?>">
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
                                        echo $defaultImages[$don['categorie']] ?? '📦';
                                        ?>
                                    </div>
                                <?php endif; ?>
                                
                                <span class="don-badge <?php 
                                    echo $est_supprime ? 'badge-danger' : 
                                        ($est_termine ? 'badge-secondary' : 
                                        ($est_reserve ? 'badge-warning' : 'badge-success')); 
                                ?>">
                                    <?php 
                                    if($est_supprime) echo 'محذوف';
                                    elseif($est_termine) echo 'تم التسليم';
                                    elseif($est_reserve) echo 'محجوز';
                                    else echo 'متاح';
                                    ?>
                                </span>
                            </div>
                            
                            <div class="don-header">
                                <h3><?php echo htmlspecialchars($don['titre']); ?></h3>
                                <small style="color: #666;">
                                    <i class="fas fa-calendar"></i> <?php echo date('d/m/Y', strtotime($don['created_at'])); ?>
                                </small>
                            </div>
                            
                            <div class="don-content">
                                <div class="don-meta">
                                    <span class="meta-item">
                                        <i class="fas fa-tag"></i> <?php echo $categories[$don['categorie']] ?? $don['categorie']; ?>
                                    </span>
                                    <span class="meta-item">
                                        <i class="fas fa-star"></i> <?php echo $etats[$don['etat']] ?? $don['etat']; ?>
                                    </span>
                                    <span class="meta-item">
                                        <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($don['ville'] ?? 'غير محدد'); ?>
                                    </span>
                                    <?php if($don['livraison_option'] != 'none'): ?>
                                        <span class="meta-item" style="background: #e3f2fd; color: #1976d2;">
                                            <i class="fas fa-truck"></i> توصيل
                                        </span>
                                    <?php endif; ?>
                                </div>
                                
                                <p class="don-description">
                                    <?php echo htmlspecialchars($don['description']); ?>
                                </p>
                                
                                <?php if(!$est_supprime && !$est_termine): ?>
                                <div class="don-stats">
                                    <div class="stat-item">
                                        <div class="stat-number"><?php echo $don['demandes_attente']; ?></div>
                                        <div class="stat-label">في الانتظار</div>
                                    </div>
                                    <div class="stat-item">
                                        <div class="stat-number"><?php echo $don['demandes_acceptees']; ?></div>
                                        <div class="stat-label">مقبولة</div>
                                    </div>
                                    <div class="stat-item">
                                        <div class="stat-number"><?php echo $don['demandes_refusees']; ?></div>
                                        <div class="stat-label">مرفوضة</div>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <div class="don-actions">
                                    <?php if($est_supprime): ?>
                                        <a href="?restore=<?php echo $don['id']; ?>" class="btn btn-success btn-sm" onclick="return confirm('هل تريد استعادة هذا التبرع؟')">
                                            <i class="fas fa-undo"></i> استعادة
                                        </a>
                                    <?php else: ?>
                                        <a href="voir-don.php?id=<?php echo $don['id']; ?>" class="btn btn-outline btn-sm">
                                            <i class="fas fa-eye"></i> عرض
                                        </a>
                                        
                                        <?php if($est_disponible): ?>
                                            <a href="modifier-don.php?id=<?php echo $don['id']; ?>" class="btn btn-warning btn-sm">
                                                <i class="fas fa-edit"></i> تعديل
                                            </a>
                                        <?php endif; ?>
                                        
                                        <?php if($don['demandes_attente'] > 0): ?>
                                            <a href="confirmer-commandes.php" class="btn btn-info btn-sm">
                                                <i class="fas fa-clock"></i> طلبات (<?php echo $don['demandes_attente']; ?>)
                                            </a>
                                        <?php endif; ?>
                                        
                                        <?php if($est_disponible): ?>
                                            <a href="?delete=<?php echo $don['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('هل أنت متأكد من حذف هذا التبرع؟')">
                                                <i class="fas fa-trash"></i> حذف
                                            </a>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
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
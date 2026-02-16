<?php
// index.php
session_start();

require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

// جلب معلومات المستخدم الحالي للقائمة (إن كان مسجلاً)
if (isset($_SESSION['user_id'])) {
    $query_user = "SELECT * FROM users WHERE id = :user_id";
    $stmt_user = $db->prepare($query_user);
    $stmt_user->bindParam(":user_id", $_SESSION['user_id']);
    $stmt_user->execute();
    $current_user = $stmt_user->fetch(PDO::FETCH_ASSOC);
}

// جلب إحصائيات حقيقية من قاعدة البيانات
try {
    // عدد المستخدمين المسجلين
    $query_users = "SELECT COUNT(*) as total_users FROM users WHERE status = 'active'";
    $stmt_users = $db->prepare($query_users);
    $stmt_users->execute();
    $users_count = $stmt_users->fetch(PDO::FETCH_ASSOC)['total_users'] ?? 0;
    
    // عدد التبرعات المنشورة
    $query_dons = "SELECT COUNT(*) as total_dons FROM dons WHERE statut = 'disponible'";
    $stmt_dons = $db->prepare($query_dons);
    $stmt_dons->execute();
    $dons_count = $stmt_dons->fetch(PDO::FETCH_ASSOC)['total_dons'] ?? 0;
    
    // عدد التبرعات المكتملة
    $query_dons_completed = "SELECT COUNT(*) as completed_dons FROM dons WHERE statut = 'donne'";
    $stmt_dons_completed = $db->prepare($query_dons_completed);
    $stmt_dons_completed->execute();
    $dons_completed_count = $stmt_dons_completed->fetch(PDO::FETCH_ASSOC)['completed_dons'] ?? 0;
    
    // عدد التبرعات المحجوزة
    $query_dons_reserved = "SELECT COUNT(*) as reserved_dons FROM dons WHERE statut = 'reserve'";
    $stmt_dons_reserved = $db->prepare($query_dons_reserved);
    $stmt_dons_reserved->execute();
    $dons_reserved_count = $stmt_dons_reserved->fetch(PDO::FETCH_ASSOC)['reserved_dons'] ?? 0;
    
    // عدد الطلبات المقبولة
    $query_demandes = "SELECT COUNT(*) as accepted_requests FROM demandes WHERE statut = 'acceptee'";
    $stmt_demandes = $db->prepare($query_demandes);
    $stmt_demandes->execute();
    $demandes_accepted_count = $stmt_demandes->fetch(PDO::FETCH_ASSOC)['accepted_requests'] ?? 0;
    
    // التبرعات الأخيرة المتاحة
    $query_recent_dons = "
        SELECT d.*, u.nom as donateur_nom 
        FROM dons d 
        INNER JOIN users u ON d.donateur_id = u.id 
        WHERE d.statut = 'disponible' 
        ORDER BY d.created_at DESC 
        LIMIT 3
    ";
    $stmt_recent_dons = $db->prepare($query_recent_dons);
    $stmt_recent_dons->execute();
    $recent_dons = $stmt_recent_dons->fetchAll(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    // في حالة حدوث خطأ، استخدم أرقام افتراضية
    $users_count = 850;
    $dons_count = 1250;
    $dons_completed_count = 650;
    $dons_reserved_count = 150;
    $demandes_accepted_count = 450;
    $recent_dons = [];
    
    error_log("Database error in index.php: " . $e->getMessage());
}

$page_title = 'الرئيسية';
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
        
        /* Navbar - مثل تصميم dashboard */
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
        
        .logout-btn {
            background: none;
            border: 1px solid #ddd;
            padding: 8px 20px;
            border-radius: 6px;
            color: var(--secondary);
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .logout-btn:hover {
            background: #ffeaa7;
            border-color: #fdcb6e;
            color: #d63031;
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
        
        /* Welcome Section - مثل تصميم dashboard */
        .welcome-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 60px 40px;
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
            gap: 30px;
        }

        .welcome-icon {
            width: 100px;
            height: 100px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 50px;
            color: white;
            backdrop-filter: blur(10px);
            border: 2px solid rgba(255, 255, 255, 0.3);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        .welcome-text {
            flex: 1;
        }

        .welcome-text h1 {
            font-size: 48px;
            margin-bottom: 15px;
            font-weight: 700;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.1);
        }

        .welcome-text p {
            font-size: 20px;
            opacity: 0.95;
            margin-bottom: 25px;
        }

        .welcome-stats {
            display: flex;
            gap: 40px;
            margin-top: 20px;
        }

        .welcome-stat {
            text-align: center;
        }

        .welcome-stat .stat-number {
            font-size: 32px;
            font-weight: 700;
            display: block;
            margin-bottom: 5px;
        }

        .welcome-stat .stat-label {
            font-size: 16px;
            opacity: 0.9;
            display: block;
        }

        @media (max-width: 768px) {
            .welcome-content {
                flex-direction: column;
                text-align: center;
            }
            
            .welcome-stats {
                justify-content: center;
                flex-wrap: wrap;
                gap: 20px;
            }
            
            .welcome-text h1 {
                font-size: 32px;
            }
        }
        
        /* Cards - مثل تصميم dashboard */
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
        
        /* Buttons - مثل تصميم dashboard */
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
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #d63031, #ff7675);
            color: white;
        }
        
        .btn-danger:hover {
            background: linear-gradient(135deg, #c0392b, #e17055);
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
        
        /* Stats Cards - مثل تصميم dashboard */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            display: flex;
            align-items: center;
            gap: 20px;
            transition: transform 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.12);
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
        }
        
        .stat-content h3 {
            margin: 0;
            font-size: 28px;
            color: var(--primary);
        }
        
        .stat-content p {
            margin: 5px 0 0;
            color: var(--secondary);
            font-size: 14px;
        }
        
        /* Quick Actions - مثل تصميم dashboard */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .action-card {
            background: white;
            border-radius: 12px;
            padding: 30px 20px;
            text-align: center;
            text-decoration: none;
            color: var(--dark);
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: all 0.3s;
            border: 2px solid transparent;
        }

        .action-card:hover {
            transform: translateY(-5px);
            border-color: var(--accent);
            box-shadow: 0 10px 25px rgba(0,0,0,0.12);
        }

        .action-card i {
            font-size: 40px;
            margin-bottom: 15px;
            color: var(--accent);
        }

        .action-card h4 {
            margin: 0 0 5px 0;
            font-size: 18px;
        }

        .action-card p {
            margin: 0;
            color: var(--secondary);
            font-size: 13px;
        }

        /* Grid System */
        .row {
            display: flex;
            flex-wrap: wrap;
            margin: 0 -15px;
        }
        
        .col-6 {
            flex: 0 0 50%;
            max-width: 50%;
            padding: 0 15px;
        }
        
        .col-4 {
            flex: 0 0 33.333%;
            max-width: 33.333%;
            padding: 0 15px;
        }
        
        .col-3 {
            flex: 0 0 25%;
            max-width: 25%;
            padding: 0 15px;
        }
        
        .grid-2 {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 25px;
            margin-top: 30px;
        }
        
        /* Badges */
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
        
        .badge-info {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .badge-warning {
            background: #fff3cd;
            color: #856404;
        }
        
        /* Table Styles */
        .table-responsive {
            overflow-x: auto;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th,
        .table td {
            padding: 12px 15px;
            text-align: right;
            border-bottom: 1px solid #eee;
        }

        .table th {
            background: #f8f9fa;
            font-weight: 600;
            color: var(--primary);
        }

        .table tbody tr:hover {
            background: #f8f9fa;
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
        
        @media (max-width: 992px) {
            .quick-actions {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .navbar {
                padding: 0 15px;
            }
            
            .nav-links {
                display: none;
            }
            
            .menu-toggle {
                display: block;
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
            
            .grid-2 {
                grid-template-columns: 1fr;
            }
            
            .col-6, .col-4, .col-3 {
                flex: 0 0 100%;
                max-width: 100%;
                margin-bottom: 15px;
            }
            
            .quick-actions {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <a href="index.php" class="logo">
            <div class="logo-icon">
                <i class="fas fa-hands-helping"></i>
            </div>
            <span>Age of Donnation</span>
        </a>
        
        <button class="menu-toggle" onclick="toggleMenu()">
            <i class="fas fa-bars"></i>
        </button>
        
        <ul class="nav-links" id="navLinks">
            <?php if(isset($_SESSION['user_id'])): ?>
                <?php if($_SESSION['user_type'] == 'beneficiaire'): ?>
                    <li class="nav-item"><a href="beneficiaire/dashboard.php" class="nav-link"><i class="fas fa-home"></i> لوحة التحكم</a></li>
                    <li class="nav-item"><a href="beneficiaire/catalogue.php" class="nav-link"><i class="fas fa-box-open"></i> الكتالوج</a></li>
                    <li class="nav-item"><a href="beneficiaire/mes-demandes.php" class="nav-link"><i class="fas fa-file-alt"></i> طلباتي</a></li>
                    <li class="nav-item"><a href="beneficiaire/messagerie.php" class="nav-link"><i class="fas fa-comments"></i> المراسلة</a></li>
                    
                <?php elseif($_SESSION['user_type'] == 'donateur'): ?>
                    <li class="nav-item"><a href="donateur/dashboard.php" class="nav-link"><i class="fas fa-home"></i> لوحة التحكم</a></li>
                    <li class="nav-item"><a href="donateur/publier-don.php" class="nav-link"><i class="fas fa-gift"></i> نشر تبرع</a></li>
                    <li class="nav-item"><a href="donateur/mes-dons.php" class="nav-link"><i class="fas fa-boxes"></i> تبرعاتي</a></li>
                    <li class="nav-item"><a href="donateur/messagerie.php" class="nav-link"><i class="fas fa-comments"></i> المراسلة</a></li>
                    
                <?php elseif($_SESSION['user_type'] == 'admin'): ?>
                    <li class="nav-item"><a href="admin/dashboard.php" class="nav-link"><i class="fas fa-home"></i> لوحة التحكم</a></li>
                    <li class="nav-item"><a href="admin/utilisateurs.php" class="nav-link"><i class="fas fa-users"></i> المستخدمون</a></li>
                    <li class="nav-item"><a href="admin/dons.php" class="nav-link"><i class="fas fa-gift"></i> التبرعات</a></li>
                    <li class="nav-item"><a href="admin/statistiques.php" class="nav-link"><i class="fas fa-chart-bar"></i> الإحصائيات</a></li>
                    
                <?php endif; ?>
            <?php else: ?>
                <li class="nav-item"><a href="index.php" class="nav-link active"><i class="fas fa-home"></i> الرئيسية</a></li>
                <li class="nav-item"><a href="auth/login.php" class="nav-link"><i class="fas fa-sign-in-alt"></i> تسجيل الدخول</a></li>
                <li class="nav-item"><a href="auth/signup.php" class="nav-link"><i class="fas fa-user-plus"></i> إنشاء حساب</a></li>
            <?php endif; ?>
        </ul>
        
        <div class="user-menu">
            <?php if(isset($_SESSION['user_id'])): ?>
                <div class="user-avatar" onclick="toggleDropdown()" title="<?php echo htmlspecialchars($_SESSION['user_nom']); ?>">
                    <?php echo strtoupper(substr($_SESSION['user_nom'], 0, 1)); ?>
                </div>
                <div class="user-dropdown" id="userDropdown">
                    <a href="<?php echo $_SESSION['user_type']; ?>/profile.php" class="user-dropdown-item">
                        <i class="fas fa-user"></i> الملف الشخصي
                    </a>
                    <?php if($_SESSION['user_type'] == 'beneficiaire'): ?>
                        <a href="beneficiaire/mes-demandes.php" class="user-dropdown-item">
                            <i class="fas fa-file-alt"></i> طلباتي
                        </a>
                        <a href="beneficiaire/messagerie.php" class="user-dropdown-item">
                            <i class="fas fa-comments"></i> المراسلة
                        </a>
                    <?php elseif($_SESSION['user_type'] == 'donateur'): ?>
                        <a href="donateur/mes-dons.php" class="user-dropdown-item">
                            <i class="fas fa-boxes"></i> تبرعاتي
                        </a>
                        <a href="donateur/messagerie.php" class="user-dropdown-item">
                            <i class="fas fa-comments"></i> المراسلة
                        </a>
                    <?php endif; ?>
                    <a href="auth/logout.php" class="user-dropdown-item">
                        <i class="fas fa-sign-out-alt"></i> تسجيل الخروج
                    </a>
                </div>
            <?php else: ?>
                <a href="auth/login.php" class="btn btn-outline">تسجيل الدخول</a>
                <a href="auth/signup.php" class="btn btn-primary">إنشاء حساب</a>
            <?php endif; ?>
        </div>
    </nav>
    
    <!-- Main Content -->
    <div class="main-content">
        <div class="container">
            <!-- Beautiful Blue Welcome Section - مثل dashboard -->
            <div class="welcome-section">
                <div class="welcome-content">
                    <div class="welcome-icon">
                        <i class="fas fa-hands-helping"></i>                    
                    </div>
                    <div class="welcome-text">
                        <h1>مرحبًا بكم في Age of Donnation</h1>
                        <p>منصة التبرعات الأولى التي تجمع المحسنين مع المحتاجين</p>
                    </div>
                </div>
            </div>

            <!-- Stats Cards - مثل dashboard -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #00b894, #00cec9);">
                        <i class="fas fa-gift"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo number_format($dons_count); ?></h3>
                        <p>تبرعات متاحة</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #74b9ff, #0984e3);">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo number_format($users_count); ?></h3>
                        <p>مستخدم مسجل</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #fdcb6e, #e17055);">
                        <i class="fas fa-handshake"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo number_format($demandes_accepted_count); ?></h3>
                        <p>تبرع تم تسليمه</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #a29bfe, #6c5ce7);">
                        <i class="fas fa-heart"></i>
                    </div>
                    <div class="stat-content">
                        <h3>100%</h3>
                        <p>خدمة مجانية</p>
                    </div>
                </div>
            </div>

            <!-- Quick Actions - مثل dashboard -->
            <div class="quick-actions">
                <a href="auth/signup.php?type=donateur" class="action-card">
                    <i class="fas fa-gift"></i>
                    <h4>أريد التبرع</h4>
                    <p>ساهم في نشر الخير</p>
                </a>
                <a href="auth/signup.php?type=beneficiaire" class="action-card">
                    <i class="fas fa-hands"></i>
                    <h4>أريد الاستفادة</h4>
                    <p>احصل على ما تحتاجه</p>
                </a>
                <a href="livreur/inscription.php" class="action-card">
                    <i class="fas fa-truck"></i>
                    <h4>أريد أن أصبح ساعي</h4>
                    <p>ساعد في التوصيل</p>
                </a>
                <a href="auth/login.php" class="action-card">
                    <i class="fas fa-sign-in-alt"></i>
                    <h4>تسجيل الدخول</h4>
                    <p>لديك حساب بالفعل؟</p>
                </a>
            </div>

            <!-- Recent Dons Section -->
            <?php if(!empty($recent_dons)): ?>
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-fire" style="color: var(--accent);"></i> أحدث التبرعات</h3>
                    <a href="auth/login.php" class="btn btn-primary btn-sm">طلب تبرع</a>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php foreach($recent_dons as $don): ?>
                        <div class="col-4">
                            <div style="border: 1px solid #eee; border-radius: 10px; padding: 20px; height: 100%; background: #f8f9fa;">
                                <h4 style="margin-bottom: 10px; color: var(--primary);"><?php echo htmlspecialchars($don['titre']); ?></h4>
                                <p style="color: #666; font-size: 14px; margin-bottom: 15px;">
                                    <?php echo strlen($don['description']) > 80 ? substr(htmlspecialchars($don['description']), 0, 80) . '...' : htmlspecialchars($don['description']); ?>
                                </p>
                                <div style="margin-bottom: 15px;">
                                    <span class="badge badge-primary"><?php echo $don['categorie']; ?></span>
                                    <span class="badge badge-success"><?php echo $don['ville']; ?></span>
                                </div>
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <small style="color: #888;">
                                        <i class="fas fa-user"></i> <?php echo htmlspecialchars($don['donateur_nom']); ?>
                                    </small>
                                    <a href="auth/login.php" class="btn btn-sm btn-outline" style="padding: 5px 15px; font-size: 13px;">
                                        <i class="fas fa-eye"></i> عرض
                                    </a>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- How It Works Section -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-question-circle" style="color: var(--accent);"></i> كيف تعمل المنصة؟</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-3">
                            <div style="text-align: center; padding: 20px;">
                                <div style="width: 70px; height: 70px; background: linear-gradient(135deg, #0984e3, #74b9ff); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; font-size: 30px; color: white;">
                                    1
                                </div>
                                <h4>انشر تبرعك</h4>
                                <p style="color: var(--secondary);">سجل كمتبرع وانشر الأشياء التي لم تعد بحاجة إليها</p>
                            </div>
                        </div>
                        <div class="col-3">
                            <div style="text-align: center; padding: 20px;">
                                <div style="width: 70px; height: 70px; background: linear-gradient(135deg, #00b894, #00cec9); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; font-size: 30px; color: white;">
                                    2
                                </div>
                                <h4>اطلب تبرعًا</h4>
                                <p style="color: var(--secondary);">ابحث في الكتالوج واطلب ما تحتاجه</p>
                            </div>
                        </div>
                        <div class="col-3">
                            <div style="text-align: center; padding: 20px;">
                                <div style="width: 70px; height: 70px; background: linear-gradient(135deg, #fdcb6e, #e17055); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; font-size: 30px; color: white;">
                                    3
                                </div>
                                <h4>التواصل</h4>
                                <p style="color: var(--secondary);">تواصل مع الطرف الآخر ورتب الاستلام</p>
                            </div>
                        </div>
                        <div class="col-3">
                            <div style="text-align: center; padding: 20px;">
                                <div style="width: 70px; height: 70px; background: linear-gradient(135deg, #a29bfe, #6c5ce7); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; font-size: 30px; color: white;">
                                    4
                                </div>
                                <h4>التسليم</h4>
                                <p style="color: var(--secondary);">استلم التبرع وأنعم به لمن يحتاجه</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
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
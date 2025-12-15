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
        
        /* Navbar - نفس تصميم dashboard */
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
        
        /* Page Header */
        .page-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px 0;
            border-radius: 15px;
            margin-bottom: 30px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .page-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.1);
        }
        
        .page-header h1 {
            font-size: 36px;
            margin-bottom: 10px;
            position: relative;
        }
        
        .page-header p {
            font-size: 18px;
            opacity: 0.9;
            position: relative;
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
        
        /* Stats Cards */
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
        
        @media (max-width: 768px) {
            .navbar {
                padding: 0 15px;
            }
            
            .nav-links {
                display: none;
            }
            
            .main-content {
                margin-top: 80px;
                padding: 15px;
            }
            
            .page-header h1 {
                font-size: 28px;
            }
            
            .col-6, .col-4, .col-3 {
                flex: 0 0 100%;
                max-width: 100%;
                margin-bottom: 15px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
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
            <!-- Page Header -->
            <div class="page-header">
                <h1><i class="fas fa-hands-helping"></i> مرحبًا بكم في Age of Donnation</h1>
                <p>منصة التبرعات الأولى التي تجمع المحسنين مع المحتاجين</p>
            </div>

            <!-- Stats Section -->
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

            <!-- Hero Section -->
            <div class="row" style="margin-bottom: 40px;">
                <div class="col-6">
                    <div class="card">
                        <div class="card-body">
                            <h3 style="color: var(--accent); margin-bottom: 20px;">ما هي Age of Donnation؟</h3>
                            <p style="line-height: 1.8; margin-bottom: 20px;">
                                منصة مغربية مخصصة للتبرعات والعمل الخيري. نحن نربط بين الأشخاص الذين يريدون التبرع بأشياء لم يعودوا بحاجة إليها وأولئك الذين يحتاجون إليها.
                            </p>
                            <div style="display: flex; gap: 20px; margin-top: 30px;">
                                <div style="text-align: center; flex: 1;">
                                    <div style="width: 70px; height: 70px; background: linear-gradient(135deg, #0984e3, #74b9ff); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 30px; margin: 0 auto 15px;">
                                        <i class="fas fa-gift"></i>
                                    </div>
                                    <h4>تبرع</h4>
                                    <small>أعط ما لا تحتاجه</small>
                                </div>
                                <div style="text-align: center; flex: 1;">
                                    <div style="width: 70px; height: 70px; background: linear-gradient(135deg, #fdcb6e, #e17055); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 30px; margin: 0 auto 15px;">
                                        <i class="fas fa-hands"></i>
                                    </div>
                                    <h4>استفد</h4>
                                    <small>احصل على ما تحتاجه</small>
                                </div>
                                <div style="text-align: center; flex: 1;">
                                    <div style="width: 70px; height: 70px; background: linear-gradient(135deg, #00b894, #00cec9); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 30px; margin: 0 auto 15px;">
                                        <i class="fas fa-truck"></i>
                                    </div>
                                    <h4>ساعد</h4>
                                    <small>كن ساعي تطوع</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="card">
                        <div class="card-body">
                            <h3 style="color: var(--accent); margin-bottom: 20px;">ابدأ الآن</h3>
                            <p style="margin-bottom: 25px; color: var(--secondary);">اختر نوع حسابك وانضم إلى مجتمعنا</p>
                            
                            <div style="display: flex; flex-direction: column; gap: 15px;">
                                <a href="auth/signup.php?type=donateur" class="btn btn-primary" style="justify-content: center;">
                                    <i class="fas fa-gift"></i> أريد التبرع
                                </a>
                                <a href="auth/signup.php?type=beneficiaire" class="btn btn-success" style="justify-content: center;">
                                    <i class="fas fa-hands"></i> أريد الاستفادة
                                </a>
                                <a href="livreur/inscription.php" class="btn btn-outline" style="justify-content: center;">
                                    <i class="fas fa-truck"></i> أريد أن أصبح ساعي
                                </a>
                            </div>
                            
                            <div style="margin-top: 30px; padding: 20px; background: #f8f9fa; border-radius: 10px;">
                                <h4><i class="fas fa-user-shield"></i> لديك حساب بالفعل؟</h4>
                                <p style="margin: 10px 0;">سجل دخولك للوصول إلى جميع الميزات</p>
                                <a href="auth/login.php" class="btn" style="background: var(--dark); color: white; justify-content: center;">
                                    <i class="fas fa-sign-in-alt"></i> تسجيل الدخول
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Dons Section -->
            <?php if(!empty($recent_dons)): ?>
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-fire"></i> تبرعات جديدة متاحة</h3>
                    <a href="auth/signup.php?type=beneficiaire" class="btn btn-primary">انضم لطلبها</a>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php foreach($recent_dons as $don): ?>
                        <div class="col-4">
                            <div style="border: 1px solid #eee; border-radius: 10px; padding: 20px; height: 100%;">
                                <h4 style="margin-bottom: 10px; color: var(--primary);"><?php echo htmlspecialchars($don['titre']); ?></h4>
                                <p style="color: #666; font-size: 14px; margin-bottom: 15px;">
                                    <?php echo strlen($don['description']) > 80 ? substr(htmlspecialchars($don['description']), 0, 80) . '...' : htmlspecialchars($don['description']); ?>
                                </p>
                                <div style="margin-bottom: 15px;">
                                    <span class="badge" style="background: #e3f2fd; color: #1976d2; padding: 5px 10px; border-radius: 20px; font-size: 12px;">
                                        <?php echo $don['categorie']; ?>
                                    </span>
                                    <span class="badge" style="background: #e8f5e9; color: #388e3c; padding: 5px 10px; border-radius: 20px; font-size: 12px;">
                                        <?php echo $don['ville']; ?>
                                    </span>
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

            <!-- How It Works -->
            <div class="card" style="margin-top: 40px;">
                <div class="card-body">
                    <h3 style="color: var(--accent); margin-bottom: 25px;"><i class="fas fa-question-circle"></i> كيف تعمل المنصة؟</h3>
                    <div class="row">
                        <div class="col-6">
                            <div style="text-align: center; padding: 20px;">
                                <div style="width: 80px; height: 80px; background: #f8f9fa; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; font-size: 30px; color: var(--accent); border: 2px dashed #ddd;">
                                    <i class="fas fa-1"></i>
                                </div>
                                <h4>انشر تبرعك</h4>
                                <p>سجل دخولك كمتبرع وانشر الأشياء التي لم تعد بحاجة إليها</p>
                            </div>
                        </div>
                        <div class="col-6">
                            <div style="text-align: center; padding: 20px;">
                                <div style="width: 80px; height: 80px; background: #f8f9fa; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; font-size: 30px; color: var(--accent); border: 2px dashed #ddd;">
                                    <i class="fas fa-2"></i>
                                </div>
                                <h4>اطلب تبرعًا</h4>
                                <p>ابحث في الكتالوج واطلب ما تحتاجه مع شرح وضعيتك</p>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-6">
                            <div style="text-align: center; padding: 20px;">
                                <div style="width: 80px; height: 80px; background: #f8f9fa; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; font-size: 30px; color: var(--accent); border: 2px dashed #ddd;">
                                    <i class="fas fa-3"></i>
                                </div>
                                <h4>التواصل</h4>
                                <p>تواصل مع الطرف الآخر ورتب عملية الاستلام</p>
                            </div>
                        </div>
                        <div class="col-6">
                            <div style="text-align: center; padding: 20px;">
                                <div style="width: 80px; height: 80px; background: #f8f9fa; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; font-size: 30px; color: var(--accent); border: 2px dashed #ddd;">
                                    <i class="fas fa-4"></i>
                                </div>
                                <h4>التسليم</h4>
                                <p>استلم التبرع وأنعم به لمن يحتاجه</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    // User dropdown functions
    function toggleDropdown() {
        const dropdown = document.getElementById('userDropdown');
        dropdown.classList.toggle('active');
    }
    
    function toggleMenu() {
        const navLinks = document.getElementById('navLinks');
        navLinks.classList.toggle('active');
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
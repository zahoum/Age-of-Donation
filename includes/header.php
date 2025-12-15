<?php
// includes/header.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    $is_logged_in = false;
    $user_nom = '';
    $user_type = '';
} else {
    $is_logged_in = true;
    $user_nom = $_SESSION['user_nom'] ?? '';
    $user_type = $_SESSION['user_type'] ?? '';
}

$page_title = $page_title ?? 'Age of Donnation';

// احصل على المسار الحالي للصفحة
$current_file = $_SERVER['PHP_SELF'];
$current_dir = dirname($current_file);

// تحديد المسار النسبي بناءً على الموقع الحالي
$up = '../';
$is_in_subdir = false;

// تحقق إذا كنا في مجلد فرعي
if (strpos($current_dir, '/beneficiaire') !== false || 
    strpos($current_dir, '/donateur') !== false || 
    strpos($current_dir, '/admin') !== false ||
    strpos($current_dir, '/includes') !== false ||
    strpos($current_dir, '/livreur') !== false) {
    $is_in_subdir = true;
}

// تحديد المسار الصحيح للروابط
if ($is_in_subdir) {
    $base_path = '../';
    $auth_path = '../auth/';
} else {
    $base_path = '';
    $auth_path = 'auth/';
}

// تحديد مسارات كل نوع مستخدم
if ($is_logged_in) {
    if ($user_type == 'beneficiaire') {
        $user_base = $base_path . 'beneficiaire/';
    } elseif ($user_type == 'donateur') {
        $user_base = $base_path . 'donateur/';
    } elseif ($user_type == 'admin') {
        $user_base = $base_path . 'admin/';
    } elseif ($user_type == 'livreur') {
        $user_base = $base_path . 'livreur/';
    }
}
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
        }
        
        .user-avatar {
            width: 42px;
            height: 42px;
            background: linear-gradient(135deg, #00b894, #00cec9);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 16px;
            cursor: pointer;
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
        
        /* Forms */
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--primary);
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 15px;
            transition: border 0.3s;
        }
        
        .form-control:focus {
            border-color: var(--accent);
            outline: none;
            box-shadow: 0 0 0 3px rgba(116, 185, 255, 0.2);
        }
        
        /* Alerts */
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid;
        }
        
        .alert-success {
            background: #d4edda;
            border-color: #28a745;
            color: #155724;
        }
        
        .alert-danger {
            background: #f8d7da;
            border-color: #dc3545;
            color: #721c24;
        }
        
        .alert-info {
            background: #d1ecf1;
            border-color: #17a2b8;
            color: #0c5460;
        }
        
        .alert-warning {
            background: #fff3cd;
            border-color: #ffc107;
            color: #856404;
        }
        
        /* Tables */
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .table th {
            background: #f8f9fa;
            padding: 15px;
            text-align: right;
            font-weight: 600;
            color: var(--primary);
            border-bottom: 2px solid #dee2e6;
        }
        
        .table td {
            padding: 15px;
            border-bottom: 1px solid #dee2e6;
        }
        
        .table tr:hover {
            background: #f8f9fa;
        }
        
        /* Badges */
        .badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }
        
        .badge-primary {
            background: #e3f2fd;
            color: #1976d2;
        }
        
        .badge-success {
            background: #e8f5e9;
            color: #388e3c;
        }
        
        .badge-warning {
            background: #fff3e0;
            color: #f57c00;
        }
        
        .badge-danger {
            background: #ffebee;
            color: #d32f2f;
        }
        
        /* Grid System */
        .row {
            display: flex;
            flex-wrap: wrap;
            margin: 0 -15px;
        }
        
        .col {
            flex: 1;
            padding: 0 15px;
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
        
        /* Footer */
        .footer {
            background: var(--primary);
            color: white;
            padding: 30px 0;
            margin-top: 50px;
            text-align: center;
        }
        
        .footer p {
            margin: 10px 0;
            opacity: 0.8;
        }
        
        /* Responsive */
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
            
            .col, .col-6, .col-4, .col-3 {
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
        <a href="<?php echo $base_path; ?>index.php" class="logo">
            <div class="logo-icon">
                <i class="fas fa-hands-helping"></i>
            </div>
            <span>Age of Donnation</span>
        </a>
        
        <button class="menu-toggle" onclick="toggleMenu()">
            <i class="fas fa-bars"></i>
        </button>
        
        <ul class="nav-links" id="navLinks">
            <?php if($is_logged_in): ?>
                <?php if($user_type == 'beneficiaire'): ?>
                    <li class="nav-item"><a href="<?php echo $user_base; ?>dashboard.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>"><i class="fas fa-home"></i> لوحة التحكم</a></li>
                    <li class="nav-item"><a href="<?php echo $user_base; ?>catalogue.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'catalogue.php' ? 'active' : ''; ?>"><i class="fas fa-box-open"></i> الكتالوج</a></li>
                    <li class="nav-item"><a href="<?php echo $user_base; ?>mes-demandes.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'mes-demandes.php' ? 'active' : ''; ?>"><i class="fas fa-file-alt"></i> طلباتي</a></li>
                    <li class="nav-item"><a href="<?php echo $user_base; ?>messagerie.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'messagerie.php' ? 'active' : ''; ?>"><i class="fas fa-comments"></i> المراسلة</a></li>
                    
                <?php elseif($user_type == 'donateur'): ?>
                    <li class="nav-item"><a href="<?php echo $user_base; ?>dashboard.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>"><i class="fas fa-home"></i> لوحة التحكم</a></li>
                    <li class="nav-item"><a href="<?php echo $user_base; ?>publier-don.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'publier-don.php' ? 'active' : ''; ?>"><i class="fas fa-gift"></i> نشر تبرع</a></li>
                    <li class="nav-item"><a href="<?php echo $user_base; ?>mes-dons.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'mes-dons.php' ? 'active' : ''; ?>"><i class="fas fa-boxes"></i> تبرعاتي</a></li>
                    <li class="nav-item"><a href="<?php echo $user_base; ?>messagerie.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'messagerie.php' ? 'active' : ''; ?>"><i class="fas fa-comments"></i> المراسلة</a></li>
                    <li class="nav-item"><a href="<?php echo $user_base; ?>confirmer-commandes.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'confirmer-commandes.php' ? 'active' : ''; ?>"><i class="fas fa-check-circle"></i> تأكيد الطلبات</a></li>
                    
                <?php elseif($user_type == 'admin'): ?>
                    <li class="nav-item"><a href="<?php echo $user_base; ?>dashboard.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>"><i class="fas fa-home"></i> لوحة التحكم</a></li>
                    <li class="nav-item"><a href="<?php echo $user_base; ?>utilisateurs.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'utilisateurs.php' ? 'active' : ''; ?>"><i class="fas fa-users"></i> المستخدمون</a></li>
                    <li class="nav-item"><a href="<?php echo $user_base; ?>dons.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dons.php' ? 'active' : ''; ?>"><i class="fas fa-gift"></i> التبرعات</a></li>
                    <li class="nav-item"><a href="<?php echo $user_base; ?>statistiques.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'statistiques.php' ? 'active' : ''; ?>"><i class="fas fa-chart-bar"></i> الإحصائيات</a></li>
                    
                <?php elseif($user_type == 'livreur'): ?>
                    <li class="nav-item"><a href="<?php echo $user_base; ?>dashboard.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>"><i class="fas fa-home"></i> لوحة التحكم</a></li>
                    <li class="nav-item"><a href="<?php echo $user_base; ?>missions.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'missions.php' ? 'active' : ''; ?>"><i class="fas fa-tasks"></i> المهام</a></li>
                    <li class="nav-item"><a href="<?php echo $user_base; ?>statistiques.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'statistiques.php' ? 'active' : ''; ?>"><i class="fas fa-chart-line"></i> الإحصائيات</a></li>
                    
                <?php endif; ?>
            <?php else: ?>
                <li class="nav-item"><a href="<?php echo $base_path; ?>index.php" class="nav-link"><i class="fas fa-home"></i> الرئيسية</a></li>
                <li class="nav-item"><a href="<?php echo $auth_path; ?>login.php" class="nav-link"><i class="fas fa-sign-in-alt"></i> تسجيل الدخول</a></li>
                <li class="nav-item"><a href="<?php echo $auth_path; ?>signup.php" class="nav-link"><i class="fas fa-user-plus"></i> إنشاء حساب</a></li>
            <?php endif; ?>
        </ul>
        
        <div class="user-menu">
            <?php if($is_logged_in): ?>
                <div class="user-avatar" title="<?php echo htmlspecialchars($user_nom); ?>">
                    <?php echo strtoupper(substr($user_nom, 0, 1)); ?>
                </div>
                <a href="<?php echo $auth_path; ?>logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    تسجيل الخروج
                </a>
            <?php else: ?>
                <a href="<?php echo $auth_path; ?>login.php" class="btn btn-outline">تسجيل الدخول</a>
                <a href="<?php echo $auth_path; ?>signup.php" class="btn btn-primary">إنشاء حساب</a>
            <?php endif; ?>
        </div>
    </nav>
    
    <!-- Main Content -->
    <div class="main-content">
        <div class="container">

<script>
function toggleMenu() {
    const navLinks = document.getElementById('navLinks');
    navLinks.classList.toggle('active');
}

// إغلاق القائمة عند النقر خارجها
document.addEventListener('click', function(event) {
    const navLinks = document.getElementById('navLinks');
    const menuToggle = document.querySelector('.menu-toggle');
    
    if (!navLinks.contains(event.target) && !menuToggle.contains(event.target)) {
        navLinks.classList.remove('active');
    }
});
</script>
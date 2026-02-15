<?php
// beneficiaire/dashboard.php
session_start();
if (!isset($_SESSION['user_id'])) {
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

// إحصائيات المستفيد
$stats_query = "
    SELECT 
        COUNT(*) as total_demandes,
        SUM(CASE WHEN statut = 'acceptee' THEN 1 ELSE 0 END) as demandes_acceptees,
        SUM(CASE WHEN statut = 'en_attente' THEN 1 ELSE 0 END) as demandes_attente,
        SUM(CASE WHEN statut = 'refusee' THEN 1 ELSE 0 END) as demandes_refusees
    FROM demandes 
    WHERE beneficiaire_id = :user_id
";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->bindParam(":user_id", $user_id);
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// آخر الطلبات
$recent_demandes_query = "
    SELECT d.*, don.titre as don_titre, don.categorie
    FROM demandes d
    INNER JOIN dons don ON d.don_id = don.id
    WHERE d.beneficiaire_id = :user_id
    ORDER BY d.created_at DESC
    LIMIT 5
";
$recent_demandes_stmt = $db->prepare($recent_demandes_query);
$recent_demandes_stmt->bindParam(":user_id", $user_id);
$recent_demandes_stmt->execute();
$recent_demandes = $recent_demandes_stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'لوحة التحكم';
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
        
        /* Welcome Section - New Beautiful Blue Design */
        .welcome-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px;
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
            width: 80px;
            height: 80px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
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
            font-size: 36px;
            margin-bottom: 10px;
            font-weight: 700;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.1);
        }

        .welcome-text p {
            font-size: 18px;
            opacity: 0.95;
            margin-bottom: 20px;
        }

        .welcome-stats {
            display: flex;
            gap: 30px;
            margin-top: 20px;
        }

        .welcome-stat {
            text-align: center;
        }

        .welcome-stat .stat-number {
            font-size: 28px;
            font-weight: 700;
            display: block;
            margin-bottom: 5px;
        }

        .welcome-stat .stat-label {
            font-size: 14px;
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
            }
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

        .btn-sm {
            padding: 5px 15px;
            font-size: 13px;
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
        
        /* Profile Section */
        .profile-header {
            display: flex;
            align-items: center;
            gap: 30px;
            margin-bottom: 40px;
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
        }
        
        .profile-avatar {
            width: 120px;
            height: 120px;
            background: linear-gradient(135deg, #00b894, #00cec9);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 48px;
        }
        
        .profile-info h2 {
            margin-bottom: 10px;
            color: var(--primary);
        }
        
        .profile-info p {
            color: var(--secondary);
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 10px;
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
        
        .grid-2 {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 25px;
            margin-top: 30px;
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
            
            .welcome-text h1 {
                font-size: 28px;
            }
            
            .col-6, .col-4 {
                flex: 0 0 100%;
                max-width: 100%;
                margin-bottom: 15px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .profile-header {
                flex-direction: column;
                text-align: center;
                gap: 20px;
            }
            
            .grid-2 {
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

        .badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            display: inline-block;
        }

        .badge-success {
            background: #d4edda;
            color: #155724;
        }

        .badge-warning {
            background: #fff3cd;
            color: #856404;
        }

        .badge-info {
            background: #d1ecf1;
            color: #0c5460;
        }

        .badge-primary {
            background: #cce5ff;
            color: #004085;
        }

        .badge-danger {
            background: #f8d7da;
            color: #721c24;
        }

        /* Demandes List */
        .demandes-list {
            list-style: none;
            padding: 0;
        }

        .demande-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            border-bottom: 1px solid #eee;
        }

        .demande-item:last-child {
            border-bottom: none;
        }

        .demande-info h4 {
            margin: 0 0 5px 0;
            color: var(--primary);
        }

        .demande-info p {
            margin: 0;
            color: var(--secondary);
            font-size: 13px;
        }

        .demande-actions {
            display: flex;
            gap: 10px;
        }

        /* Quick Actions */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
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

        @media (max-width: 992px) {
            .quick-actions {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 576px) {
            .quick-actions {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <a href="../index.php" class="logo">
            <div class="logo-icon">
                <i class="fas fa-hands-helping"></i>
            </div>
            <span>Age of Donnation</span>
        </a>
        
        <button class="menu-toggle" onclick="toggleMenu()">
            <i class="fas fa-bars"></i>
        </button>
        
        <ul class="nav-links" id="navLinks">
            <li class="nav-item"><a href="dashboard.php" class="nav-link active"><i class="fas fa-home"></i> لوحة التحكم</a></li>
            <li class="nav-item"><a href="catalogue.php" class="nav-link"><i class="fas fa-box-open"></i> الكتالوج</a></li>
            <li class="nav-item"><a href="mes-demandes.php" class="nav-link"><i class="fas fa-file-alt"></i> طلباتي</a></li>
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
                <a href="mes-demandes.php" class="user-dropdown-item">
                    <i class="fas fa-file-alt"></i> طلباتي
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
            <!-- New Beautiful Blue Welcome Section -->
            <div class="welcome-section">
                <div class="welcome-content">
                    <div class="welcome-icon">
                        <i class="fas fa-tachometer-alt"></i>                    
                    </div>
                    <div class="welcome-text">
                        <h1>مرحبًا بك <?php echo htmlspecialchars($current_user['nom']); ?> </h1>
                        <p>تابع طلباتك وتصفح التبرعات المتاحة</p>
                        
                    </div>
                </div>
            </div>
           <div class="page-header">
                <p>مرحبًا بك <?php echo htmlspecialchars($current_user['nom']); ?>، تابع طلباتك وتصفح التبرعات</p>
            </div>
            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #74b9ff, #0984e3);">
                        <i class="fas fa-file-alt"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $stats['total_demandes'] ?? 0; ?></h3>
                        <p>إجمالي الطلبات</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #00b894, #00cec9);">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $stats['demandes_acceptees'] ?? 0; ?></h3>
                        <p>طلبات مقبولة</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #fdcb6e, #e17055);">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $stats['demandes_attente'] ?? 0; ?></h3>
                        <p>في الانتظار</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #ff7675, #d63031);">
                        <i class="fas fa-times-circle"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $stats['demandes_refusees'] ?? 0; ?></h3>
                        <p>مرفوضة</p>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="quick-actions">
                <a href="catalogue.php" class="action-card">
                    <i class="fas fa-search"></i>
                    <h4>تصفح التبرعات</h4>
                    <p>ابحث عن تبرعات جديدة</p>
                </a>
                <a href="mes-demandes.php" class="action-card">
                    <i class="fas fa-list"></i>
                    <h4>طلباتي</h4>
                    <p>تابع حالة طلباتك</p>
                </a>
                <a href="messagerie.php" class="action-card">
                    <i class="fas fa-comments"></i>
                    <h4>المراسلة</h4>
                    <p>تواصل مع المتبرعين</p>
                </a>
            </div>

            <!-- Recent Activity -->
            <div class="grid-2">
                <!-- Recent Demandes -->
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-history"></i> آخر طلباتك</h3>
                        <a href="mes-demandes.php" class="btn btn-outline btn-sm">عرض الكل</a>
                    </div>
                    <div class="card-body">
                        <?php if(empty($recent_demandes)): ?>
                            <div style="text-align: center; padding: 40px;">
                                <i class="fas fa-file-alt" style="font-size: 60px; color: #ccc; margin-bottom: 20px;"></i>
                                <p style="color: #666;">لم تقم بتقديم أي طلبات بعد</p>
                                <a href="catalogue.php" class="btn btn-primary" style="margin-top: 15px;">
                                    <i class="fas fa-search"></i> تصفح التبرعات
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>التبرع</th>
                                            <th>الفئة</th>
                                            <th>الحالة</th>
                                            <th>التاريخ</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($recent_demandes as $demande): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($demande['don_titre']); ?></td>
                                            <td>
                                                <span class="badge badge-primary"><?php echo $demande['categorie']; ?></span>
                                            </td>
                                            <td>
                                                <?php if($demande['statut'] == 'acceptee'): ?>
                                                    <span class="badge badge-success">مقبولة</span>
                                                <?php elseif($demande['statut'] == 'en_attente'): ?>
                                                    <span class="badge badge-warning">في الانتظار</span>
                                                <?php elseif($demande['statut'] == 'refusee'): ?>
                                                    <span class="badge badge-danger">مرفوضة</span>
                                                <?php else: ?>
                                                    <span class="badge badge-info">قيد المعالجة</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo date('d/m/Y', strtotime($demande['created_at'])); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Profile Info Card -->
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-user"></i> معلوماتك الشخصية</h3>
                        <a href="profile.php" class="btn btn-outline btn-sm">
                            <i class="fas fa-edit"></i> تعديل
                        </a>
                    </div>
                    <div class="card-body">
                        <div style="display: flex; align-items: center; gap: 20px; margin-bottom: 20px;">
                            <div class="profile-avatar" style="width: 80px; height: 80px; font-size: 32px;">
                                <?php echo strtoupper(substr($current_user['nom'], 0, 1)); ?>
                            </div>
                            <div>
                                <h3 style="margin-bottom: 5px;"><?php echo htmlspecialchars($current_user['nom']); ?></h3>
                                <p style="color: var(--secondary);"><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($current_user['email']); ?></p>
                            </div>
                        </div>
                        
                        <div style="border-top: 1px solid #eee; padding-top: 20px;">
                            <?php if($current_user['telephone']): ?>
                                <p style="margin-bottom: 10px;"><i class="fas fa-phone" style="width: 25px; color: var(--accent);"></i> <?php echo htmlspecialchars($current_user['telephone']); ?></p>
                            <?php endif; ?>
                            <?php if($current_user['ville']): ?>
                                <p style="margin-bottom: 10px;"><i class="fas fa-map-marker-alt" style="width: 25px; color: var(--accent);"></i> <?php echo htmlspecialchars($current_user['ville']); ?></p>
                            <?php endif; ?>
                            <p><i class="fas fa-user-tag" style="width: 25px; color: var(--accent);"></i> مستفيد</p>
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
    
    // إغلاق القائمة عند النقر خارجها
    document.addEventListener('click', function(event) {
        const navLinks = document.getElementById('navLinks');
        const menuToggle = document.querySelector('.menu-toggle');
        const userDropdown = document.getElementById('userDropdown');
        const userAvatar = document.querySelector('.user-avatar');
        
        // إغلاق قائمة التنقل
        if (!navLinks.contains(event.target) && !menuToggle.contains(event.target)) {
            navLinks.classList.remove('active');
        }
        
        // إغلاق قائمة المستخدم
        if (!userDropdown.contains(event.target) && !userAvatar.contains(event.target)) {
            userDropdown.classList.remove('active');
        }
    });
    </script>
</body>
</html>
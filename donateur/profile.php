<?php
// beneficiaire/profile.php
session_start();
if (!isset($_SESSION['user_id']) ){
    header('Location: ../auth/login.php');
    exit();
}

require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

// جلب معلومات المستخدم
$query_user = "SELECT * FROM users WHERE id = :user_id";
$stmt_user = $db->prepare($query_user);
$stmt_user->bindParam(":user_id", $user_id);
$stmt_user->execute();
$current_user = $stmt_user->fetch(PDO::FETCH_ASSOC);

// تحديث البيانات إذا كان هناك طلب POST
if ($_POST) {
    $nom = trim($_POST['nom']);
    $telephone = trim($_POST['telephone'] ?? '');
    $ville = trim($_POST['ville'] ?? '');
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // تحديث المعلومات الأساسية
    if (!empty($nom)) {
        try {
            $query = "UPDATE users SET nom = :nom, telephone = :telephone, ville = :ville WHERE id = :user_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(":nom", $nom);
            $stmt->bindParam(":telephone", $telephone);
            $stmt->bindParam(":ville", $ville);
            $stmt->bindParam(":user_id", $user_id);
            
            if ($stmt->execute()) {
                $_SESSION['user_nom'] = $nom;
                $success .= "✅ تم تحديث المعلومات بنجاح<br>";
                
                // تحديث بيانات المستخدم الحالية
                $current_user['nom'] = $nom;
                $current_user['telephone'] = $telephone;
                $current_user['ville'] = $ville;
            }
        } catch(PDOException $e) {
            $error .= "❌ خطأ في تحديث المعلومات: " . $e->getMessage() . "<br>";
        }
    }
    
    // تغيير كلمة المرور إذا تم تقديمها
    if (!empty($current_password) && !empty($new_password)) {
        if ($new_password !== $confirm_password) {
            $error .= "❌ كلمة المرور الجديدة غير متطابقة<br>";
        } elseif (strlen($new_password) < 6) {
            $error .= "❌ كلمة المرور يجب أن تكون 6 أحرف على الأقل<br>";
        } else {
            // التحقق من كلمة المرور الحالية
            if (password_verify($current_password, $current_user['password'])) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                try {
                    $query = "UPDATE users SET password = :password WHERE id = :user_id";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(":password", $hashed_password);
                    $stmt->bindParam(":user_id", $user_id);
                    
                    if ($stmt->execute()) {
                        $success .= "✅ تم تغيير كلمة المرور بنجاح<br>";
                    }
                } catch(PDOException $e) {
                    $error .= "❌ خطأ في تغيير كلمة المرور: " . $e->getMessage() . "<br>";
                }
            } else {
                $error .= "❌ كلمة المرور الحالية غير صحيحة<br>";
            }
        }
    }
}

// جلب إحصائيات المستخدم
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

// جلب آخر 5 طلبات
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

$page_title = 'الملف الشخصي';
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
        
        /* Welcome Section - Beautiful Blue Design */
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
            text-shadow: 2px 2px 4px rgba(0,0,0,0.1);
        }

        .welcome-text p {
            font-size: 16px;
            opacity: 0.95;
            margin: 0;
        }

        @media (max-width: 768px) {
            .welcome-content {
                flex-direction: column;
                text-align: center;
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
        
        /* Grid System */
        .row {
            display: flex;
            flex-wrap: wrap;
            margin: 0 -15px;
        }
        
        .col-4 {
            flex: 0 0 33.333%;
            max-width: 33.333%;
            padding: 0 15px;
        }
        
        .col-8 {
            flex: 0 0 66.666%;
            max-width: 66.666%;
            padding: 0 15px;
        }
        
        .col-6 {
            flex: 0 0 50%;
            max-width: 50%;
            padding: 0 15px;
        }
        
        .grid-2 {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 25px;
        }
        
        @media (max-width: 992px) {
            .col-4, .col-8, .col-6 {
                flex: 0 0 100%;
                max-width: 100%;
            }
            
            .grid-2 {
                grid-template-columns: 1fr;
            }
        }
        
        /* Profile */
        .profile-avatar-large {
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
            margin: 0 auto 20px;
            box-shadow: 0 10px 20px rgba(0, 184, 148, 0.3);
        }
        
        .profile-name {
            font-size: 24px;
            margin-bottom: 5px;
            color: var(--primary);
        }
        
        .profile-email {
            color: var(--secondary);
            margin-bottom: 15px;
        }
        
        .profile-badge {
            display: inline-block;
            padding: 5px 15px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border-radius: 20px;
            font-size: 14px;
            margin-bottom: 20px;
        }
        
        .info-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        
        .info-item:last-child {
            border-bottom: none;
        }
        
        .info-item i {
            width: 20px;
            color: var(--accent);
        }
        
        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        
        .stat-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            text-align: center;
        }
        
        .stat-card h4 {
            margin: 0;
            font-size: 24px;
            color: var(--primary);
        }
        
        .stat-card p {
            margin: 5px 0 0;
            color: var(--secondary);
            font-size: 13px;
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
        
        .btn-outline {
            background: transparent;
            border: 2px solid var(--accent);
            color: var(--accent);
        }
        
        .btn-outline:hover {
            background: var(--accent);
            color: white;
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #d63031, #ff7675);
            color: white;
        }
        
        .btn-success {
            background: linear-gradient(135deg, #00b894, #00cec9);
            color: white;
        }
        
        .btn-sm {
            padding: 5px 15px;
            font-size: 13px;
        }
        
        /* Form Styles */
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            color: var(--primary);
            font-weight: 500;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e1e1e1;
            border-radius: 8px;
            font-size: 15px;
            transition: all 0.3s;
            font-family: 'Tajawal', sans-serif;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(9, 132, 227, 0.1);
        }
        
        textarea.form-control {
            resize: vertical;
            min-height: 100px;
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

        .badge-danger {
            background: #f8d7da;
            color: #721c24;
        }

        .badge-primary {
            background: #cce5ff;
            color: #004085;
        }
        
        /* Tabs */
        .tabs {
            display: flex;
            border-bottom: 2px solid #eee;
            margin-bottom: 25px;
        }
        
        .tab {
            padding: 15px 30px;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            color: var(--secondary);
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .tab:hover {
            color: var(--accent);
        }
        
        .tab.active {
            color: var(--accent);
            border-bottom-color: var(--accent);
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        /* Quick Actions */
        .quick-actions {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-top: 15px;
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
            
            .tabs {
                flex-direction: column;
                border-bottom: none;
            }
            
            .tab {
                text-align: center;
                border-bottom: 1px solid #eee;
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
            <!-- Beautiful Blue Welcome Section -->
            <div class="welcome-section">
                <div class="welcome-content">
                    <div class="welcome-icon">
                        <i class="fas fa-user-circle"></i>
                    </div>
                    <div class="welcome-text">
                        <h1>الملف الشخصي</h1>
                        <p>مرحبًا بك <?php echo htmlspecialchars($current_user['nom']); ?>، يمكنك إدارة معلوماتك الشخصية من هنا</p>
                    </div>
                </div>
            </div>

            <?php if($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <?php if($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <div class="row">
                <!-- Profile Sidebar -->
                <div class="col-4">
                    <div class="card" style="text-align: center;">
                        <div class="card-body">
                            <div class="profile-avatar-large">
                                <?php echo strtoupper(substr($current_user['nom'], 0, 1)); ?>
                            </div>
                            <h2 class="profile-name"><?php echo htmlspecialchars($current_user['nom']); ?></h2>
                            <div class="profile-email"><?php echo htmlspecialchars($current_user['email']); ?></div>
                            <div class="profile-badge">
                                <i class="fas fa-user-tag"></i> مستفيد
                            </div>
                            
                            <div style="text-align: right; margin-top: 20px;">
                                <?php if($current_user['telephone']): ?>
                                <div class="info-item">
                                    <i class="fas fa-phone"></i>
                                    <span><?php echo htmlspecialchars($current_user['telephone']); ?></span>
                                </div>
                                <?php endif; ?>
                                
                                <?php if($current_user['ville']): ?>
                                <div class="info-item">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <span><?php echo htmlspecialchars($current_user['ville']); ?></span>
                                </div>
                                <?php endif; ?>
                                
                                <div class="info-item">
                                    <i class="fas fa-calendar"></i>
                                    <span>عضو منذ: <?php echo date('d/m/Y', strtotime($current_user['created_at'])); ?></span>
                                </div>
                            </div>
                            
                            <div class="stats-grid">
                                <div class="stat-card">
                                    <h4><?php echo $stats['total_demandes'] ?? 0; ?></h4>
                                    <p>إجمالي الطلبات</p>
                                </div>
                                <div class="stat-card">
                                    <h4 style="color: var(--success);"><?php echo $stats['demandes_acceptees'] ?? 0; ?></h4>
                                    <p>مقبولة</p>
                                </div>
                                <div class="stat-card">
                                    <h4 style="color: var(--warning);"><?php echo $stats['demandes_attente'] ?? 0; ?></h4>
                                    <p>في الانتظار</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Quick Actions -->
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-bolt"></i> إجراءات سريعة</h3>
                        </div>
                        <div class="card-body">
                            <div class="quick-actions">
                                <a href="dashboard.php" class="btn btn-outline">
                                    <i class="fas fa-home"></i> العودة للوحة التحكم
                                </a>
                                <a href="catalogue.php" class="btn btn-primary">
                                    <i class="fas fa-search"></i> تصفح التبرعات
                                </a>
                                <a href="mes-demandes.php" class="btn btn-outline">
                                    <i class="fas fa-file-alt"></i> طلباتي
                                </a>
                                <a href="messagerie.php" class="btn btn-outline">
                                    <i class="fas fa-comments"></i> المراسلة
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Main Content -->
                <div class="col-8">
                    <!-- Tabs -->
                    <div class="tabs">
                        <div class="tab active" onclick="switchTab('personal', event)">المعلومات الشخصية</div>
                        <div class="tab" onclick="switchTab('password', event)">تغيير كلمة المرور</div>
                        <div class="tab" onclick="switchTab('activity', event)">نشاطي الأخير</div>
                    </div>
                    
                    <!-- Personal Info Tab -->
                    <div id="personalTab" class="tab-content active">
                        <div class="card">
                            <div class="card-header">
                                <h3><i class="fas fa-user-edit"></i> تعديل المعلومات الشخصية</h3>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <div class="form-group">
                                        <label class="form-label">الاسم الكامل *</label>
                                        <input type="text" name="nom" class="form-control" value="<?php echo htmlspecialchars($current_user['nom']); ?>" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label class="form-label">البريد الإلكتروني</label>
                                        <input type="email" class="form-control" value="<?php echo htmlspecialchars($current_user['email']); ?>" disabled>
                                        <small style="color: #666;">لا يمكن تغيير البريد الإلكتروني</small>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-6">
                                            <div class="form-group">
                                                <label class="form-label">رقم الهاتف</label>
                                                <input type="tel" name="telephone" class="form-control" value="<?php echo htmlspecialchars($current_user['telephone'] ?? ''); ?>">
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="form-group">
                                                <label class="form-label">المدينة</label>
                                                <input type="text" name="ville" class="form-control" value="<?php echo htmlspecialchars($current_user['ville'] ?? ''); ?>">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div style="margin-top: 20px;">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save"></i> حفظ التغييرات
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Password Tab -->
                    <div id="passwordTab" class="tab-content">
                        <div class="card">
                            <div class="card-header">
                                <h3><i class="fas fa-lock"></i> تغيير كلمة المرور</h3>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <div class="form-group">
                                        <label class="form-label">كلمة المرور الحالية</label>
                                        <input type="password" name="current_password" class="form-control" required>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-6">
                                            <div class="form-group">
                                                <label class="form-label">كلمة المرور الجديدة</label>
                                                <input type="password" name="new_password" class="form-control" required>
                                                <small style="color: #666;">يجب أن تكون 6 أحرف على الأقل</small>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="form-group">
                                                <label class="form-label">تأكيد كلمة المرور</label>
                                                <input type="password" name="confirm_password" class="form-control" required>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div style="margin-top: 20px;">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-key"></i> تغيير كلمة المرور
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        
                        <!-- Security Tips -->
                        <div class="card" style="background: #f8f9fa; margin-top: 20px;">
                            <div class="card-body">
                                <h4 style="color: var(--accent); margin-bottom: 15px;">
                                    <i class="fas fa-shield-alt"></i> نصائح الأمان
                                </h4>
                                <ul style="padding-right: 20px; color: #666;">
                                    <li>استخدم كلمة مرور قوية تحتوي على أحرف وأرقام ورموز</li>
                                    <li>لا تشارك كلمة المرور مع أي شخص</li>
                                    <li>قم بتغيير كلمة المرور بشكل دوري</li>
                                    <li>تأكد من تسجيل الخروج عند استخدام أجهزة عامة</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Activity Tab -->
                    <div id="activityTab" class="tab-content">
                        <div class="card">
                            <div class="card-header">
                                <h3><i class="fas fa-history"></i> آخر طلباتي</h3>
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
                                                            echo $categories[$demande['categorie']] ?? $demande['categorie'];
                                                            ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?php if($demande['statut'] == 'acceptee'): ?>
                                                            <span class="badge badge-success">مقبولة</span>
                                                        <?php elseif($demande['statut'] == 'en_attente'): ?>
                                                            <span class="badge badge-warning">في الانتظار</span>
                                                        <?php elseif($demande['statut'] == 'refusee'): ?>
                                                            <span class="badge badge-danger">مرفوضة</span>
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
                        
                        <!-- Tips -->
                        
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
    
    function switchTab(tabName, event) {
        // تحديث حالة الألسنة
        document.querySelectorAll('.tab').forEach(tab => {
            tab.classList.remove('active');
        });
        event.target.classList.add('active');
        
        // إظهار المحتوى المناسب
        document.querySelectorAll('.tab-content').forEach(content => {
            content.classList.remove('active');
        });
        document.getElementById(tabName + 'Tab').classList.add('active');
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
    
    // Scroll to top when switching tabs
    window.addEventListener('load', function() {
        window.scrollTo(0, 0);
    });
    </script>
</body>
</html>
<?php
include '../includes/footer.php'; ?>
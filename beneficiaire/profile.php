<?php
// beneficiaire/profile.php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'beneficiaire') {
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
        SUM(CASE WHEN statut = 'acceptee' THEN 1 ELSE 0 END) as demandes_acceptees
    FROM demandes 
    WHERE beneficiaire_id = :user_id
";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->bindParam(":user_id", $user_id);
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

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
        
        /* Navbar - نفس التصميم السابق */
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
            max-width: 1000px;
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
        
        /* Profile */
        .profile-container {
            display: flex;
            gap: 30px;
        }
        
        .profile-sidebar {
            width: 300px;
            flex-shrink: 0;
        }
        
        .profile-main {
            flex: 1;
        }
        
        .profile-card {
            text-align: center;
            padding: 30px;
        }
        
        .profile-avatar-large {
            width: 150px;
            height: 150px;
            background: linear-gradient(135deg, #00b894, #00cec9);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 60px;
            margin: 0 auto 20px;
        }
        
        .profile-name {
            font-size: 24px;
            margin-bottom: 10px;
            color: var(--primary);
        }
        
        .profile-role {
            background: #e3f2fd;
            color: #1976d2;
            padding: 5px 15px;
            border-radius: 20px;
            display: inline-block;
            margin-bottom: 20px;
        }
        
        .profile-info {
            text-align: right;
            margin-top: 20px;
        }
        
        .profile-info-item {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
            color: var(--secondary);
        }
        
        .profile-info-item i {
            width: 20px;
            color: var(--accent);
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
        
        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        
        .stat-card-small {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            text-align: center;
        }
        
        .stat-card-small h4 {
            margin: 0;
            font-size: 24px;
            color: var(--primary);
        }
        
        .stat-card-small p {
            margin: 5px 0 0;
            color: var(--secondary);
            font-size: 13px;
        }
        
        @media (max-width: 768px) {
            .profile-container {
                flex-direction: column;
            }
            
            .profile-sidebar {
                width: 100%;
            }
            
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
        }
        
        /* Tabs */
        .tabs {
            display: flex;
            border-bottom: 2px solid #e0e0e0;
            margin-bottom: 20px;
        }
        
        .tab {
            padding: 15px 30px;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            color: var(--secondary);
            transition: all 0.3s;
        }
        
        .tab:hover {
            color: var(--accent);
        }
        
        .tab.active {
            color: var(--accent);
            border-bottom-color: var(--accent);
            font-weight: 600;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
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
        
        
        
        <ul class="nav-links" id="navLinks">
            <li class="nav-item"><a href="dashboard.php" class="nav-link"><i class="fas fa-home"></i> لوحة التحكم</a></li>
            <li class="nav-item"><a href="catalogue.php" class="nav-link"><i class="fas fa-box-open"></i> الكتالوج</a></li>
            <li class="nav-item"><a href="mes-demandes.php" class="nav-link"><i class="fas fa-file-alt"></i> طلباتي</a></li>
            <li class="nav-item"><a href="messagerie.php" class="nav-link"><i class="fas fa-comments"></i> المراسلة</a></li>
            <li class="nav-item"><a href="profile.php" class="nav-link active"><i class="fas fa-user"></i> الملف الشخصي</a></li>
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
            <!-- Page Header -->
            <div class="page-header">
                <h1><i class="fas fa-user-circle"></i> الملف الشخصي</h1>
                <p>إدارة معلومات حسابك وإعداداتك</p>
            </div>

            <?php if($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <?php if($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <div class="profile-container">
                <!-- Sidebar -->
                <div class="profile-sidebar">
                    <div class="card profile-card">
                        <div class="profile-avatar-large">
                            <?php echo strtoupper(substr($current_user['nom'], 0, 1)); ?>
                        </div>
                        <h2 class="profile-name"><?php echo htmlspecialchars($current_user['nom']); ?></h2>
                        <div class="profile-role">مستفيد</div>
                        
                        <div class="profile-info">
                            <div class="profile-info-item">
                                <i class="fas fa-envelope"></i>
                                <span><?php echo htmlspecialchars($current_user['email']); ?></span>
                            </div>
                            <?php if($current_user['telephone']): ?>
                            <div class="profile-info-item">
                                <i class="fas fa-phone"></i>
                                <span><?php echo htmlspecialchars($current_user['telephone']); ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if($current_user['ville']): ?>
                            <div class="profile-info-item">
                                <i class="fas fa-map-marker-alt"></i>
                                <span><?php echo htmlspecialchars($current_user['ville']); ?></span>
                            </div>
                            <?php endif; ?>
                            <div class="profile-info-item">
                                <i class="fas fa-calendar"></i>
                                <span>منضم منذ: <?php echo date('d/m/Y', strtotime($current_user['created_at'])); ?></span>
                            </div>
                        </div>
                        
                        <div class="stats-grid">
                            <div class="stat-card-small">
                                <h4><?php echo $stats['total_demandes'] ?? 0; ?></h4>
                                <p>طلبات</p>
                            </div>
                            <div class="stat-card-small">
                                <h4><?php echo $stats['demandes_acceptees'] ?? 0; ?></h4>
                                <p>مقبولة</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card" style="margin-top: 20px;">
                        <div class="card-body">
                            <h4><i class="fas fa-cog"></i> إجراءات سريعة</h4>
                            <div style="display: flex; flex-direction: column; gap: 10px; margin-top: 15px;">
                                <a href="dashboard.php" class="btn btn-outline">
                                    <i class="fas fa-home"></i> العودة للوحة التحكم
                                </a>
                                <a href="catalogue.php" class="btn btn-outline">
                                    <i class="fas fa-search"></i> تصفح التبرعات
                                </a>
                                <a href="mes-demandes.php" class="btn btn-outline">
                                    <i class="fas fa-file-alt"></i> طلباتي
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Main Content -->
                <div class="profile-main">
                    <div class="tabs">
                        <div class="tab active" onclick="switchTab('personal')">المعلومات الشخصية</div>
                        <div class="tab" onclick="switchTab('password')">كلمة المرور</div>
                        <div class="tab" onclick="switchTab('activity')">النشاط</div>
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
                                    
                                    <div class="form-group">
                                        <label class="form-label">رقم الهاتف</label>
                                        <input type="tel" name="telephone" class="form-control" value="<?php echo htmlspecialchars($current_user['telephone'] ?? ''); ?>">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label class="form-label">المدينة</label>
                                        <input type="text" name="ville" class="form-control" value="<?php echo htmlspecialchars($current_user['ville'] ?? ''); ?>">
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save"></i> حفظ التغييرات
                                    </button>
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
                                        <input type="password" name="current_password" class="form-control">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label class="form-label">كلمة المرور الجديدة</label>
                                        <input type="password" name="new_password" class="form-control">
                                        <small style="color: #666;">يجب أن تكون 6 أحرف على الأقل</small>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label class="form-label">تأكيد كلمة المرور الجديدة</label>
                                        <input type="password" name="confirm_password" class="form-control">
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-key"></i> تغيير كلمة المرور
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Activity Tab -->
                    <div id="activityTab" class="tab-content">
                        <div class="card">
                            <div class="card-header">
                                <h3><i class="fas fa-chart-line"></i> نشاطك وإحصائياتك</h3>
                            </div>
                            <div class="card-body">
                                <h4 style="margin-bottom: 20px;">إحصائيات طلباتك</h4>
                                
                                <div class="stats-grid">
                                    <div class="stat-card-small">
                                        <h4><?php echo $stats['total_demandes'] ?? 0; ?></h4>
                                        <p>إجمالي الطلبات</p>
                                    </div>
                                    <div class="stat-card-small">
                                        <h4><?php echo $stats['demandes_acceptees'] ?? 0; ?></h4>
                                        <p>طلبات مقبولة</p>
                                    </div>
                                    <div style="grid-column: span 2;">
                                        <p style="text-align: center; color: #666; margin-top: 20px;">
                                            <i class="fas fa-info-circle"></i> هذه الإحصائيات تعتمد على طلباتك في النظام
                                        </p>
                                    </div>
                                </div>
                                
                                <div style="margin-top: 30px;">
                                    <h4>نصائح لزيادة فرص قبول طلباتك:</h4>
                                    <ul style="padding-right: 20px; color: #666; margin-top: 15px;">
                                        <li>اكتب رسالة واضحة ومؤدبة عند طلب التبرع</li>
                                        <li>اشرح وضعك الحالي بشكل مختصر</li>
                                        <li>كن صادقًا في طلبك</li>
                                        <li>رد على رسائل المتبرعين بسرعة</li>
                                        <li>شكر المتبرع بعد استلام التبرع</li>
                                    </ul>
                                </div>
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
    
    function switchTab(tabName) {
        // تحديد الألسنة
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
    </script>
</body>
</html>
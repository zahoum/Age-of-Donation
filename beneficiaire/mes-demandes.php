<?php
// beneficiaire/mes-demandes.php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit();
}

require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$user_id = $_SESSION['user_id'];

// جلب معلومات المستخدم الحالي للقائمة
$query_user = "SELECT * FROM users WHERE id = :user_id";
$stmt_user = $db->prepare($query_user);
$stmt_user->bindParam(":user_id", $user_id);
$stmt_user->execute();
$current_user = $stmt_user->fetch(PDO::FETCH_ASSOC);

// باقي الكود كما هو...
$success = '';
$error = '';

// Gérer l'annulation de demande
if (isset($_GET['action']) && $_GET['action'] == 'annuler' && isset($_GET['id'])) {
    $demande_id = $_GET['id'];
    
    if (!is_numeric($demande_id) || $demande_id <= 0) {
        $error = "❌ معرف الطلب غير صالح";
    } else {
        try {
            // Vérifier que la demande appartient bien à l'utilisateur et est en attente
            $check_query = "SELECT id, statut FROM demandes WHERE id = :id AND beneficiaire_id = :user_id";
            $check_stmt = $db->prepare($check_query);
            $check_stmt->bindParam(":id", $demande_id, PDO::PARAM_INT);
            $check_stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
            $check_stmt->execute();
            
            if ($check_stmt->rowCount() > 0) {
                $demande_data = $check_stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($demande_data['statut'] == 'en_attente') {
                    // SUPPRIMER COMPLÈTEMENT la demande
                    $delete_query = "DELETE FROM demandes WHERE id = :id";
                    $delete_stmt = $db->prepare($delete_query);
                    $delete_stmt->bindParam(":id", $demande_id, PDO::PARAM_INT);
                    
                    if ($delete_stmt->execute()) {
                        $success = "✅ تم إلغاء وحذف الطلب بنجاح";
                    } else {
                        $error = "❌ حدث خطأ أثناء حذف الطلب";
                    }
                } else {
                    $error = "❌ لا يمكن إلغاء هذا الطلب. الحالة الحالية: " . $demande_data['statut'];
                }
            } else {
                $error = "❌ لم يتم العثور على الطلب أو ليس لديك الإذن لإلغائه";
            }
        } catch(PDOException $e) {
            $error = "❌ خطأ في قاعدة البيانات: " . $e->getMessage();
        }
    }
}

// Récupérer les demandes du bénéficiaire (EXCLURE les annulées)
$query = "
    SELECT d.*, 
           don.titre as don_titre, 
           don.description as don_description,
           don.photo_principale,
           don.categorie,
           don.etat,
           don.adresse_retrait,
           don.ville,
           don.donateur_id,
           u.nom as donateur_nom,
           u.email as donateur_email,
           u.telephone as donateur_telephone
    FROM demandes d
    INNER JOIN dons don ON d.don_id = don.id
    INNER JOIN users u ON don.donateur_id = u.id
    WHERE d.beneficiaire_id = :user_id 
    AND d.statut != 'annulee'
    ORDER BY 
        CASE 
            WHEN d.statut = 'en_attente' THEN 1
            WHEN d.statut = 'acceptee' THEN 2
            WHEN d.statut = 'refusee' THEN 3
            ELSE 4
        END,
        d.created_at DESC
";

$stmt = $db->prepare($query);
$stmt->bindParam(":user_id", $user_id);
$stmt->execute();
$demandes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Statistiques des demandes
$stats_query = "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN statut = 'en_attente' THEN 1 ELSE 0 END) as en_attente,
        SUM(CASE WHEN statut = 'acceptee' THEN 1 ELSE 0 END) as acceptees,
        SUM(CASE WHEN statut = 'refusee' THEN 1 ELSE 0 END) as refusees
    FROM demandes 
    WHERE beneficiaire_id = :user_id 
    AND statut != 'annulee'
";

$stats_stmt = $db->prepare($stats_query);
$stats_stmt->bindParam(":user_id", $user_id);
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

$page_title = 'طلباتي';
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
        
        .badge-info {
            background: #e3f2fd;
            color: #0288d1;
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
        
        /* Grid System */
        .row {
            display: flex;
            flex-wrap: wrap;
            margin: 0 -15px;
        }
        
        .col-2 { flex: 0 0 16.666%; max-width: 16.666%; padding: 0 15px; }
        .col-3 { flex: 0 0 25%; max-width: 25%; padding: 0 15px; }
        .col-4 { flex: 0 0 33.333%; max-width: 33.333%; padding: 0 15px; }
        .col-5 { flex: 0 0 41.666%; max-width: 41.666%; padding: 0 15px; }
        .col-6 { flex: 0 0 50%; max-width: 50%; padding: 0 15px; }
        .col-7 { flex: 0 0 58.333%; max-width: 58.333%; padding: 0 15px; }
        .col-8 { flex: 0 0 66.666%; max-width: 66.666%; padding: 0 15px; }
        
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
            
            .col-2, .col-3, .col-4, .col-5, .col-6, .col-7, .col-8 {
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
            <li class="nav-item"><a href="dashboard.php" class="nav-link"><i class="fas fa-home"></i> لوحة التحكم</a></li>
            <li class="nav-item"><a href="catalogue.php" class="nav-link"><i class="fas fa-box-open"></i> الكتالوج</a></li>
            <li class="nav-item"><a href="mes-demandes.php" class="nav-link active"><i class="fas fa-file-alt"></i> طلباتي</a></li>
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
            <!-- Page Header -->
            <div class="page-header">
                <h1><i class="fas fa-file-alt"></i> طلباتي</h1>
                <p>تابع حالة طلبات التبرعات الخاصة بك</p>
            </div>

            <?php if($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>

            <?php if($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #74b9ff, #0984e3);">
                        <i class="fas fa-file-alt"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $stats['total'] ?? 0; ?></h3>
                        <p>إجمالي الطلبات</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #fdcb6e, #e17055);">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $stats['en_attente'] ?? 0; ?></h3>
                        <p>في الانتظار</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #00b894, #00cec9);">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $stats['acceptees'] ?? 0; ?></h3>
                        <p>مؤكدة</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #ff7675, #d63031);">
                        <i class="fas fa-times-circle"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $stats['refusees'] ?? 0; ?></h3>
                        <p>مرفوضة</p>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="card" style="margin-bottom: 25px;">
                <div class="card-body">
                    <div class="row">
                        <div class="col-4">
                            <div class="form-group">
                                <label class="form-label">فلترة حسب الحالة</label>
                                <select id="statusFilter" class="form-control">
                                    <option value="">جميع الحالات</option>
                                    <option value="en_attente">في الانتظار</option>
                                    <option value="acceptee">مؤكدة</option>
                                    <option value="refusee">مرفوضة</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="form-group">
                                <label class="form-label">فلترة حسب الفئة</label>
                                <select id="categoryFilter" class="form-control">
                                    <option value="">جميع الفئات</option>
                                    <option value="vetements">ملابس</option>
                                    <option value="nourriture">طعام</option>
                                    <option value="meubles">أثاث</option>
                                    <option value="livres">كتب</option>
                                    <option value="electromenager">أجهزة كهربائية</option>
                                    <option value="divers">متنوع</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="form-group">
                                <label class="form-label">بحث</label>
                                <input type="text" id="searchInput" class="form-control" placeholder="بحث عن تبرع...">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Demandes List -->
            <div class="card">
                <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                    <h3><i class="fas fa-history"></i> سجل طلباتك</h3>
                    <a href="catalogue.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> طلب جديد
                    </a>
                </div>
                <div class="card-body">
                    <?php if(empty($demandes)): ?>
                        <div style="text-align: center; padding: 50px;">
                            <div style="font-size: 60px; color: #ddd; margin-bottom: 20px;">
                                <i class="fas fa-file-alt"></i>
                            </div>
                            <h3 style="color: #666; margin-bottom: 15px;">لا توجد طلبات</h3>
                            <p style="color: #888; margin-bottom: 25px;">لم تقم بتقديم أي طلبات تبرع بعد</p>
                            <a href="catalogue.php" class="btn btn-primary">
                                <i class="fas fa-search"></i> تصفح التبرعات
                            </a>
                        </div>
                    <?php else: ?>
                        <div id="demandesContainer">
                            <?php foreach($demandes as $demande): ?>
                            <div class="card demande-item" 
                                 data-statut="<?php echo $demande['statut']; ?>" 
                                 data-categorie="<?php echo $demande['categorie']; ?>"
                                 data-titre="<?php echo htmlspecialchars(strtolower($demande['don_titre'])); ?>"
                                 style="margin-bottom: 20px;">
                                <div class="card-body">
                                    <div class="row">
                                        <!-- Don Image -->
                                        <div class="col-2">
                                            <?php 
                                            $image_path = !empty($demande['photo_principale']) ? '../' . $demande['photo_principale'] : '';
                                            if(!empty($image_path) && file_exists($image_path)): ?>
                                                <img src="<?php echo $image_path; ?>" 
                                                     alt="<?php echo htmlspecialchars($demande['don_titre']); ?>"
                                                     style="width: 100%; height: 100px; object-fit: cover; border-radius: 8px;">
                                            <?php else: ?>
                                                <div style="width: 100%; height: 100px; background: #f8f9fa; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 30px; color: #aaa;">
                                                    <?php 
                                                    $defaultImages = [
                                                        'vetements' => '👕',
                                                        'nourriture' => '🍎',
                                                        'meubles' => '🛋️',
                                                        'livres' => '📚',
                                                        'electromenager' => '🔌',
                                                        'divers' => '📦'
                                                    ];
                                                    echo $defaultImages[$demande['categorie']] ?? '📦';
                                                    ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <!-- Demande Info -->
                                        <div class="col-7">
                                            <h4 style="margin-bottom: 10px;"><?php echo htmlspecialchars($demande['don_titre']); ?></h4>
                                            
                                            <div style="margin-bottom: 10px;">
                                                <span class="badge badge-primary"><?php echo $demande['categorie']; ?></span>
                                                <span class="badge badge-success"><?php echo $demande['etat']; ?></span>
                                                <span style="color: #666; margin-right: 10px;">
                                                    <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($demande['ville']); ?>
                                                </span>
                                            </div>
                                            
                                            <?php if($demande['message_demande']): ?>
                                            <p style="color: #666; font-size: 14px; margin-bottom: 10px;">
                                                <strong>رسالتك:</strong> 
                                                <?php echo strlen($demande['message_demande']) > 100 ? substr(htmlspecialchars($demande['message_demande']), 0, 100) . '...' : htmlspecialchars($demande['message_demande']); ?>
                                            </p>
                                            <?php endif; ?>
                                            
                                            <div style="font-size: 13px; color: #888;">
                                                <i class="fas fa-user"></i> <?php echo htmlspecialchars($demande['donateur_nom']); ?>
                                                <?php if($demande['donateur_telephone']): ?>
                                                    • <i class="fas fa-phone"></i> <?php echo htmlspecialchars($demande['donateur_telephone']); ?>
                                                <?php endif; ?>
                                                • <i class="fas fa-calendar"></i> <?php echo date('d/m/Y', strtotime($demande['created_at'])); ?>
                                            </div>
                                        </div>
                                        
                                        <!-- Status & Actions -->
                                        <div class="col-3">
                                            <!-- Status Badge -->
                                            <div style="text-align: center; margin-bottom: 15px;">
                                                <?php 
                                                $statusColors = [
                                                    'en_attente' => ['color' => '#fdcb6e', 'icon' => '⏳'],
                                                    'acceptee' => ['color' => '#00b894', 'icon' => '✅'],
                                                    'refusee' => ['color' => '#ff7675', 'icon' => '❌']
                                                ];
                                                $status = $statusColors[$demande['statut']] ?? ['color' => '#aaa', 'icon' => '📝'];
                                                
                                                $statusLabels = [
                                                    'en_attente' => 'في الانتظار',
                                                    'acceptee' => 'تم التأكيد',
                                                    'refusee' => 'مرفوض'
                                                ];
                                                $statusText = $statusLabels[$demande['statut']] ?? $demande['statut'];
                                                ?>
                                                <span class="badge" style="background: <?php echo $status['color']; ?>20; color: <?php echo $status['color']; ?>; border: 1px solid <?php echo $status['color']; ?>; padding: 6px 12px;">
                                                    <?php echo $status['icon']; ?> <?php echo $statusText; ?>
                                                </span>
                                            </div>
                                            
                                            <!-- Actions -->
                                            <div style="display: flex; flex-direction: column; gap: 8px;">
                                                <a href="details-demande.php?id=<?php echo $demande['id']; ?>" class="btn btn-sm btn-outline">
                                                    <i class="fas fa-eye"></i> التفاصيل
                                                </a>
                                                
                                                <?php if($demande['statut'] == 'en_attente'): ?>
                                                    <a href="?action=annuler&id=<?php echo $demande['id']; ?>" 
                                                       class="btn btn-sm btn-danger"
                                                       onclick="return confirm('هل أنت متأكد من إلغاء هذا الطلب؟ سيتم حذفه نهائيًا.')">
                                                        <i class="fas fa-times"></i> إلغاء
                                                    </a>
                                                <?php elseif($demande['statut'] == 'acceptee'): ?>
                                                    <div style="text-align: center; font-size: 12px; color: #00b894; margin-bottom: 5px;">
                                                        <i class="fas fa-check-circle"></i> تم تأكيد طلبك
                                                    </div>
                                                    <?php if(isset($demande['donateur_id'])): ?>
                                                    <a href="messagerie.php?user_id=<?php echo $demande['donateur_id']; ?>" class="btn btn-sm btn-success">
                                                        <i class="fas fa-comment"></i> تواصل مع المتبرع
                                                    </a>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- Summary -->
                        <div class="card" style="margin-top: 30px; background: #f8f9fa;">
                            <div class="card-body">
                                <h4><i class="fas fa-chart-pie"></i> ملخص طلباتك</h4>
                                <div class="row" style="margin-top: 15px;">
                                    <div class="col-3">
                                        <div style="text-align: center;">
                                            <h3 style="color: var(--accent);"><?php echo $stats['total'] ?? 0; ?></h3>
                                            <p style="color: var(--secondary);">إجمالي الطلبات</p>
                                        </div>
                                    </div>
                                    <div class="col-3">
                                        <div style="text-align: center;">
                                            <h3 style="color: #fdcb6e;"><?php echo $stats['en_attente'] ?? 0; ?></h3>
                                            <p style="color: var(--secondary);">في الانتظار</p>
                                        </div>
                                    </div>
                                    <div class="col-3">
                                        <div style="text-align: center;">
                                            <h3 style="color: #00b894;"><?php echo $stats['acceptees'] ?? 0; ?></h3>
                                            <p style="color: var(--secondary);">مؤكدة</p>
                                        </div>
                                    </div>
                                    <div class="col-3">
                                        <div style="text-align: center;">
                                            <h3 style="color: #ff7675;"><?php echo $stats['refusees'] ?? 0; ?></h3>
                                            <p style="color: var(--secondary);">مرفوضة</p>
                                        </div>
                                    </div>
                                </div>
                                <?php 
                                $tauxSuccess = $stats['total'] > 0 ? round(($stats['acceptees'] / $stats['total']) * 100, 1) : 0;
                                ?>
                                <div style="margin-top: 20px; text-align: center;">
                                    <p style="color: var(--secondary);">
                                        <i class="fas fa-chart-line"></i> نسبة النجاح: <strong><?php echo $tauxSuccess; ?>%</strong>
                                    </p>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
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
    
    // Filtres et recherche
    document.addEventListener('DOMContentLoaded', function() {
        const statusFilter = document.getElementById('statusFilter');
        const categoryFilter = document.getElementById('categoryFilter');
        const searchInput = document.getElementById('searchInput');
        const demandesContainer = document.getElementById('demandesContainer');
        
        function filterDemandes() {
            const selectedStatus = statusFilter.value;
            const selectedCategory = categoryFilter.value;
            const searchTerm = searchInput.value.toLowerCase();
            
            const demandes = demandesContainer.getElementsByClassName('demande-item');
            
            for (let demande of demandes) {
                const statut = demande.getAttribute('data-statut');
                const categorie = demande.getAttribute('data-categorie');
                const titre = demande.getAttribute('data-titre');
                
                const matchesStatus = !selectedStatus || statut === selectedStatus;
                const matchesCategory = !selectedCategory || categorie === selectedCategory;
                const matchesSearch = !searchTerm || titre.includes(searchTerm);
                
                if (matchesStatus && matchesCategory && matchesSearch) {
                    demande.style.display = 'block';
                } else {
                    demande.style.display = 'none';
                }
            }
        }
        
        statusFilter.addEventListener('change', filterDemandes);
        categoryFilter.addEventListener('change', filterDemandes);
        searchInput.addEventListener('input', filterDemandes);
        
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
    });
    </script>
</body>
</html>
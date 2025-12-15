<?php
// beneficiaire/catalogue.php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit();
}

require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$user_id = $_SESSION['user_id'];
$user_nom = $_SESSION['user_nom'];

// جلب معلومات المستخدم الحالي للقائمة
$query_user = "SELECT * FROM users WHERE id = :user_id";
$stmt_user = $db->prepare($query_user);
$stmt_user->bindParam(":user_id", $user_id);
$stmt_user->execute();
$current_user = $stmt_user->fetch(PDO::FETCH_ASSOC);

// باقي الكود كما هو...
// Récupérer tous les dons disponibles
$query = "
    SELECT d.*, u.nom as donateur_nom, u.telephone as donateur_telephone
    FROM dons d 
    INNER JOIN users u ON d.donateur_id = u.id 
    WHERE d.statut = 'disponible' 
    ORDER BY d.created_at DESC
";
$stmt = $db->prepare($query);
$stmt->execute();
$dons = $stmt->fetchAll(PDO::FETCH_ASSOC);

$success = '';
$error = '';

// Traitement de la demande
if ($_POST && isset($_POST['don_id'])) {
    $don_id = $_POST['don_id'];
    $message_demande = trim($_POST['message_demande']);
    
    // Vérifier si une demande existe déjà
    $check_query = "SELECT id FROM demandes WHERE beneficiaire_id = :beneficiaire_id AND don_id = :don_id";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->bindParam(":beneficiaire_id", $_SESSION['user_id']);
    $check_stmt->bindParam(":don_id", $don_id);
    $check_stmt->execute();
    
    if ($check_stmt->rowCount() > 0) {
        $error = "لقد قدمت طلبًا لهذا التبرع بالفعل";
    } else {
        try {
            $query = "INSERT INTO demandes (beneficiaire_id, don_id, message_demande, statut, created_at) 
                      VALUES (:beneficiaire_id, :don_id, :message_demande, 'en_attente', NOW())";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(":beneficiaire_id", $_SESSION['user_id']);
            $stmt->bindParam(":don_id", $don_id);
            $stmt->bindParam(":message_demande", $message_demande);
            
            if ($stmt->execute()) {
                $success = "تم إرسال طلبك بنجاح! سيتصل بك المتبرع قريبًا.";
            } else {
                $error = "حدث خطأ أثناء إرسال الطلب";
            }
        } catch(PDOException $e) {
            $error = "خطأ: " . $e->getMessage();
        }
    }
}

$page_title = 'كتالوج التبرعات';
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
        
        .col-6 {
            flex: 0 0 50%;
            max-width: 50%;
            padding: 0 15px;
        }
        
        /* Dons Grid */
        .don-card {
            height: 100%;
            transition: all 0.3s;
        }
        
        .don-card:hover {
            transform: translateY(-5px);
        }
        
        @media (max-width: 992px) {
            .col-4 {
                flex: 0 0 50%;
                max-width: 50%;
            }
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
            
            .col-4, .col-6 {
                flex: 0 0 100%;
                max-width: 100%;
                margin-bottom: 15px;
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
        
        /* Modal */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 2000;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .modal-content {
            background: white;
            border-radius: 12px;
            max-width: 500px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            border-bottom: 1px solid #eee;
        }
        
        .modal-body {
            padding: 20px;
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
            <li class="nav-item"><a href="catalogue.php" class="nav-link active"><i class="fas fa-box-open"></i> الكتالوج</a></li>
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
            <!-- Page Header -->
            <div class="page-header">
                <h1><i class="fas fa-box-open"></i> كتالوج التبرعات</h1>
                <p>اكتشف جميع التبرعات المتاحة بالقرب منك</p>
            </div>

            <?php if($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>

            <?php if($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <!-- Filters -->
            <div class="card" style="margin-bottom: 25px;">
                <div class="card-body">
                    <div class="row">
                        <div class="col-4">
                            <div class="form-group">
                                <input type="text" id="searchInput" class="form-control" placeholder="🔍 بحث عن تبرع...">
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="form-group">
                                <select id="categorieFilter" class="form-control">
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
                                <select id="villeFilter" class="form-control">
                                    <option value="">جميع المدن</option>
                                    <?php
                                    $villes_query = "SELECT DISTINCT ville FROM dons WHERE ville IS NOT NULL AND ville != '' ORDER BY ville";
                                    $villes_stmt = $db->prepare($villes_query);
                                    $villes_stmt->execute();
                                    $villes = $villes_stmt->fetchAll(PDO::FETCH_COLUMN);
                                    foreach($villes as $ville): 
                                    ?>
                                        <option value="<?php echo htmlspecialchars($ville); ?>"><?php echo htmlspecialchars($ville); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Dons Grid -->
            <?php if(empty($dons)): ?>
                <div class="card">
                    <div class="card-body" style="text-align: center; padding: 50px;">
                        <div style="font-size: 60px; color: #ddd; margin-bottom: 20px;">
                            <i class="fas fa-box-open"></i>
                        </div>
                        <h3 style="color: #666; margin-bottom: 15px;">لا توجد تبرعات متاحة</h3>
                        <p style="color: #888;">ارجع لاحقًا لاكتشاف تبرعات جديدة</p>
                    </div>
                </div>
            <?php else: ?>
                <div class="row" id="donsContainer">
                    <?php foreach($dons as $don): ?>
                    <div class="col-4">
                        <div class="card don-card" 
                             data-categorie="<?php echo $don['categorie']; ?>" 
                             data-ville="<?php echo htmlspecialchars($don['ville']); ?>"
                             data-titre="<?php echo htmlspecialchars(strtolower($don['titre'])); ?>">
                            <div class="card-body">
                                <!-- Don Image -->
                                <div style="width: 100%; height: 180px; margin-bottom: 15px; overflow: hidden; border-radius: 8px;">
                                    <?php if(!empty($don['photo_principale'])): 
                                        $image_path = '../' . $don['photo_principale'];
                                        if(file_exists($image_path)): ?>
                                            <img src="<?php echo $image_path; ?>" 
                                                 alt="<?php echo htmlspecialchars($don['titre']); ?>"
                                                 style="width: 100%; height: 100%; object-fit: cover;">
                                        <?php else: ?>
                                            <div style="width: 100%; height: 100%; background: #f8f9fa; display: flex; align-items: center; justify-content: center; color: #aaa; font-size: 40px;">
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
                                        <div style="width: 100%; height: 100%; background: #f8f9fa; display: flex; align-items: center; justify-content: center; color: #aaa; font-size: 40px;">
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
                                
                                <h4 style="margin-bottom: 10px;"><?php echo htmlspecialchars($don['titre']); ?></h4>
                                
                                <p style="color: #666; font-size: 14px; margin-bottom: 15px; height: 60px; overflow: hidden;">
                                    <?php echo htmlspecialchars($don['description']); ?>
                                </p>
                                
                                <!-- Badges -->
                                <div style="margin-bottom: 15px;">
                                    <span class="badge badge-primary"><?php echo $don['categorie']; ?></span>
                                    <span class="badge badge-success"><?php echo $don['etat']; ?></span>
                                </div>
                                
                                <!-- Informations -->
                                <div style="font-size: 13px; color: #666; margin-bottom: 20px;">
                                    <div style="display: flex; align-items: center; margin-bottom: 8px;">
                                        <i class="fas fa-map-marker-alt" style="margin-left: 8px;"></i>
                                        <span><?php echo htmlspecialchars($don['ville'] ?: 'غير محدد'); ?></span>
                                    </div>
                                    <div style="display: flex; align-items: center; margin-bottom: 8px;">
                                        <i class="fas fa-user" style="margin-left: 8px;"></i>
                                        <span><?php echo htmlspecialchars($don['donateur_nom']); ?></span>
                                    </div>
                                    <div style="display: flex; align-items: center;">
                                        <i class="fas fa-calendar" style="margin-left: 8px;"></i>
                                        <span><?php echo date('d/m/Y', strtotime($don['created_at'])); ?></span>
                                    </div>
                                </div>
                                
                                <!-- Request Button -->
                                <button onclick="openRequestModal(<?php echo $don['id']; ?>, '<?php echo htmlspecialchars(addslashes($don['titre'])); ?>')" 
                                        class="btn btn-primary" style="width: 100%;">
                                    <i class="fas fa-envelope"></i> تقديم طلب
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Statistics -->
                <div class="card" style="margin-top: 30px;">
                    <div class="card-body">
                        <h4><i class="fas fa-chart-bar"></i> ملخص الكتالوج</h4>
                        <div class="row" style="margin-top: 15px;">
                            <div class="col-3">
                                <div style="text-align: center;">
                                    <h3 style="color: var(--accent);"><?php echo count($dons); ?></h3>
                                    <p style="color: var(--secondary);">إجمالي التبرعات</p>
                                </div>
                            </div>
                            <?php
                            $categories_count = [];
                            foreach($dons as $don) {
                                $categories_count[$don['categorie']] = ($categories_count[$don['categorie']] ?? 0) + 1;
                            }
                            foreach($categories_count as $categorie => $count):
                            ?>
                            <div class="col-3">
                                <div style="text-align: center;">
                                    <h3 style="color: var(--accent);"><?php echo $count; ?></h3>
                                    <p style="color: var(--secondary);"><?php echo $categorie; ?></p>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Request Modal -->
    <div id="requestModal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <h3 style="margin: 0;">تقديم طلب</h3>
                <button onclick="closeRequestModal()" style="background: none; border: none; font-size: 20px; cursor: pointer; color: #666;">×</button>
            </div>
            <div class="modal-body">
                <form method="POST" id="requestForm">
                    <input type="hidden" name="don_id" id="don_id">
                    <div class="form-group">
                        <label class="form-label">التبرع المختار:</label>
                        <p id="don_titre" style="font-weight: bold; padding: 12px; background: #f8f9fa; border-radius: 8px; margin: 0;"></p>
                    </div>
                    <div class="form-group">
                        <label class="form-label">رسالة للمتبرع *</label>
                        <textarea name="message_demande" class="form-control" required 
                                  placeholder="اشرح لماذا تحتاج هذا التبرع، وكيف تخطط لاستخدامه، واقترح موعد الاستلام..."
                                  rows="5"></textarea>
                        <small style="color: #666;">يجب أن تكون رسالتك مهذبة وتشرح وضعك.</small>
                    </div>
                    <div style="display: flex; gap: 15px;">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i> إرسال الطلب
                        </button>
                        <button type="button" onclick="closeRequestModal()" class="btn btn-outline">إلغاء</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    // Modal functions
    function openRequestModal(donId, donTitre) {
        document.getElementById('don_id').value = donId;
        document.getElementById('don_titre').textContent = donTitre;
        document.getElementById('requestModal').style.display = 'flex';
    }

    function closeRequestModal() {
        document.getElementById('requestModal').style.display = 'none';
        document.getElementById('requestForm').reset();
    }

    // Close modal when clicking outside
    document.getElementById('requestModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeRequestModal();
        }
    });

    // Filters and search
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('searchInput');
        const categorieFilter = document.getElementById('categorieFilter');
        const villeFilter = document.getElementById('villeFilter');
        const donsContainer = document.getElementById('donsContainer');
        
        function filterDons() {
            const searchTerm = searchInput.value.toLowerCase();
            const selectedCategorie = categorieFilter.value;
            const selectedVille = villeFilter.value;
            
            const dons = donsContainer.getElementsByClassName('don-card');
            
            for (let don of dons) {
                const titre = don.getAttribute('data-titre');
                const categorie = don.getAttribute('data-categorie');
                const ville = don.getAttribute('data-ville');
                
                const matchesSearch = !searchTerm || titre.includes(searchTerm);
                const matchesCategorie = !selectedCategorie || categorie === selectedCategorie;
                const matchesVille = !selectedVille || ville === selectedVille;
                
                if (matchesSearch && matchesCategorie && matchesVille) {
                    don.parentElement.style.display = 'block';
                } else {
                    don.parentElement.style.display = 'none';
                }
            }
        }
        
        searchInput.addEventListener('input', filterDons);
        categorieFilter.addEventListener('change', filterDons);
        villeFilter.addEventListener('change', filterDons);
    });
    
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
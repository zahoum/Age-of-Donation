<?php
// livreur/missions.php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'livreur') {
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

// جلب معلومات المندوب
$query_livreur = "SELECT l.*, u.nom, u.email, u.telephone, u.ville, u.adresse
                 FROM livreurs l
                 INNER JOIN users u ON l.user_id = u.id
                 WHERE l.user_id = :user_id";
$stmt_livreur = $db->prepare($query_livreur);
$stmt_livreur->bindParam(":user_id", $user_id);
$stmt_livreur->execute();
$livreur = $stmt_livreur->fetch(PDO::FETCH_ASSOC);

$success = '';
$error = '';

// ========== قبول مهمة ==========
if (isset($_GET['accept']) && isset($_GET['mission_id'])) {
    $mission_id = $_GET['accept'];
    
    try {
        $db->beginTransaction();
        
        // التحقق فقط من أن المهمة ليس لها مندوب - بدون شرط statut
        $check_query = "SELECT * FROM livraisons WHERE id = :mission_id AND livreur_id IS NULL";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->bindParam(':mission_id', $mission_id);
        $check_stmt->execute();
        $mission = $check_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$mission) {
            throw new Exception("هذه المهمة غير متاحة");
        }
        
        // قبول المهمة - فقط تحديث livreur_id
        $update = "UPDATE livraisons SET livreur_id = :livreur_id, statut = 'assignee' WHERE id = :mission_id";
        $stmt = $db->prepare($update);
        $stmt->bindParam(':livreur_id', $user_id);
        $stmt->bindParam(':mission_id', $mission_id);
        $stmt->execute();
        
        $db->commit();
        
        // توجيه مباشر بدون رسالة خطأ
        header('Location: missions.php?filter=en_cours');
        exit();
        
    } catch(Exception $e) {
        $db->rollBack();
        $error = "❌ خطأ: " . $e->getMessage();
    }
}

// ========== بدء مهمة ==========
if (isset($_GET['start']) && isset($_GET['mission_id'])) {
    $mission_id = $_GET['start'];
    
    try {
        $query = "UPDATE livraisons SET statut = 'en_cours' WHERE id = :mission_id AND livreur_id = :user_id AND statut = 'assignee'";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':mission_id', $mission_id);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            header('Location: missions.php?filter=en_cours');
            exit();
        } else {
            $error = "❌ لا يمكن بدء هذه المهمة";
        }
    } catch(PDOException $e) {
        $error = "❌ خطأ: " . $e->getMessage();
    }
}

// ========== إنهاء مهمة ==========
if (isset($_GET['complete']) && isset($_GET['mission_id'])) {
    $mission_id = $_GET['complete'];
    
    try {
        $db->beginTransaction();
        
        $query = "UPDATE livraisons SET statut = 'livree', date_livraison = NOW() WHERE id = :mission_id AND livreur_id = :user_id AND statut = 'en_cours'";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':mission_id', $mission_id);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            // تحديث عدد توصيلات المندوب
            $update_livreur = "UPDATE livreurs SET nombre_livraisons = nombre_livraisons + 1 WHERE user_id = :user_id";
            $stmt_livreur = $db->prepare($update_livreur);
            $stmt_livreur->bindParam(':user_id', $user_id);
            $stmt_livreur->execute();
            
            $db->commit();
            header('Location: missions.php?filter=terminees');
            exit();
        } else {
            $db->rollBack();
            $error = "❌ لا يمكن إنهاء هذه المهمة";
        }
    } catch(PDOException $e) {
        $db->rollBack();
        $error = "❌ خطأ: " . $e->getMessage();
    }
}

// الفلتر
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'disponibles';
$selected_ville = isset($_GET['ville']) ? $_GET['ville'] : '';

// جلب جميع المدن المتاحة من الطلبات المقبولة
$ville_query = "SELECT DISTINCT d.ville 
                FROM dons d
                INNER JOIN demandes de ON d.id = de.don_id
                WHERE de.statut = 'acceptee' AND d.ville IS NOT NULL AND d.ville != ''
                ORDER BY d.ville";
$ville_stmt = $db->prepare($ville_query);
$ville_stmt->execute();
$villes = $ville_stmt->fetchAll(PDO::FETCH_ASSOC);

// جلب المهام
$query = "
    SELECT 
        l.id,
        l.demande_id,
        l.livreur_id,
        l.frais_livraison,
        l.statut,
        l.created_at,
        l.ville,
        d.id as don_id,
        d.titre as don_titre,
        d.description as don_description,
        d.categorie,
        d.etat,
        d.adresse_retrait,
        d.ville as don_ville,
        u_benef.id as beneficiaire_id,
        u_benef.nom as beneficiaire_nom,
        u_benef.telephone as beneficiaire_telephone,
        u_benef.adresse as beneficiaire_adresse,
        u_don.nom as donateur_nom,
        u_don.telephone as donateur_telephone,
        de.message_demande
    FROM livraisons l
    INNER JOIN demandes de ON l.demande_id = de.id
    INNER JOIN dons d ON de.don_id = d.id
    INNER JOIN users u_benef ON de.beneficiaire_id = u_benef.id
    INNER JOIN users u_don ON d.donateur_id = u_don.id
    WHERE 1=1
";

$params = [];

if($filter == 'disponibles') {
    // المهام المتاحة فقط: لم يتم تعيينها لأي مندوب
    $query .= " AND l.livreur_id IS NULL";
    if(!empty($selected_ville)) {
        $query .= " AND d.ville = :ville";
        $params[':ville'] = $selected_ville;
    }
} elseif($filter == 'en_cours') {
    $query .= " AND l.livreur_id = :user_id AND l.statut IN ('assignee', 'en_cours')";
    $params[':user_id'] = $user_id;
} elseif($filter == 'terminees') {
    $query .= " AND l.livreur_id = :user_id AND l.statut = 'livree'";
    $params[':user_id'] = $user_id;
} elseif($filter == 'mes') {
    $query .= " AND l.livreur_id = :user_id";
    $params[':user_id'] = $user_id;
}

$query .= " ORDER BY l.created_at DESC";

$stmt = $db->prepare($query);
foreach($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$missions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// إحصائيات
$count_query = "
    SELECT 
        COUNT(CASE WHEN l.livreur_id IS NULL THEN 1 END) as disponibles,
        COUNT(CASE WHEN l.livreur_id = :user_id AND l.statut IN ('assignee', 'en_cours') THEN 1 END) as en_cours,
        COUNT(CASE WHEN l.livreur_id = :user_id AND l.statut = 'livree' THEN 1 END) as terminees,
        COUNT(CASE WHEN l.livreur_id = :user_id THEN 1 END) as total_mes
    FROM livraisons l
";
$count_stmt = $db->prepare($count_query);
$count_stmt->bindParam(":user_id", $user_id);
$count_stmt->execute();
$counts = $count_stmt->fetch(PDO::FETCH_ASSOC);

$page_title = 'مهام التوصيل';
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
        
        .main-content {
            margin-top: 90px;
            padding: 20px;
            min-height: calc(100vh - 160px);
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 15px;
        }
        
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
        
        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            font-size: 14px;
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
        
        .btn-warning {
            background: linear-gradient(135deg, #fdcb6e, #ffeaa7);
            color: #2d3436;
        }
        
        .btn-warning:hover {
            background: linear-gradient(135deg, #f39c12, #fdcb6e);
            box-shadow: 0 5px 15px rgba(253, 203, 110, 0.4);
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
            padding: 5px 12px;
            font-size: 12px;
        }
        
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
        
        .badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            display: inline-block;
        }
        
        .badge-warning {
            background: #fff3cd;
            color: #856404;
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
        
        .filters {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 25px;
        }
        
        .city-filter {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #eee;
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .city-select {
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            min-width: 250px;
            font-family: 'Tajawal', sans-serif;
        }
        
        .city-select:focus {
            outline: none;
            border-color: var(--accent);
        }
        
        .mission-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 15px;
            border: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 20px;
            transition: all 0.3s;
        }
        
        .mission-card:hover {
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            border-color: var(--accent);
        }
        
        .mission-card.disponible {
            border-right: 4px solid #00b894;
        }
        
        .mission-card.en-cours {
            border-right: 4px solid #fdcb6e;
        }
        
        .mission-card.terminee {
            border-right: 4px solid #6c5ce7;
            opacity: 0.9;
            background: #f8f9fa;
        }
        
        .mission-info {
            flex: 1;
        }
        
        .mission-info h3 {
            margin: 0 0 10px 0;
            color: var(--primary);
            font-size: 18px;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .mission-details {
            display: flex;
            gap: 20px;
            color: var(--secondary);
            font-size: 14px;
            flex-wrap: wrap;
            margin-bottom: 10px;
        }
        
        .mission-details i {
            color: var(--accent);
            width: 20px;
        }
        
        .mission-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            min-width: 200px;
            justify-content: flex-end;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            gap: 15px;
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
        
        .contact-info {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 8px;
            margin-top: 10px;
            font-size: 13px;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #666;
        }
        
        .empty-state i {
            font-size: 60px;
            margin-bottom: 20px;
            opacity: 0.3;
        }
        
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
            
            .mission-card {
                flex-direction: column;
            }
            
            .mission-actions {
                justify-content: flex-start;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
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
            <li class="nav-item"><a href="missions.php" class="nav-link active"><i class="fas fa-tasks"></i> المهام</a></li>
            <li class="nav-item"><a href="profile.php" class="nav-link"><i class="fas fa-user"></i> ملفي الشخصي</a></li>
            <li class="nav-item"><a href="messagerie.php" class="nav-link"><i class="fas fa-comments"></i> المراسلة</a></li>
        </ul>
        
        <div class="user-menu">
            <div class="user-avatar" onclick="toggleDropdown()">
                <?php echo strtoupper(substr($current_user['nom'], 0, 1)); ?>
            </div>
            <div class="user-dropdown" id="userDropdown">
                <a href="profile.php" class="user-dropdown-item">
                    <i class="fas fa-user"></i> الملف الشخصي
                </a>
                <a href="missions.php" class="user-dropdown-item">
                    <i class="fas fa-tasks"></i> المهام
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
    
    <div class="main-content">
        <div class="container">
            <div class="welcome-section">
                <div class="welcome-content">
                    <div class="welcome-icon">
                        <i class="fas fa-tasks"></i>                    
                    </div>
                    <div class="welcome-text">
                        <h1>مهام التوصيل</h1>
                        <p>مرحباً <?php echo htmlspecialchars($current_user['nom']); ?>، اختر المهام التي تريد توصيلها</p>
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

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #667eea, #764ba2);">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $counts['disponibles'] ?? 0; ?></h3>
                        <p>مهام متاحة</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #f093fb, #f5576c);">
                        <i class="fas fa-play-circle"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $counts['en_cours'] ?? 0; ?></h3>
                        <p>مهام جارية</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #4facfe, #00f2fe);">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $counts['terminees'] ?? 0; ?></h3>
                        <p>مهام منجزة</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #00b894, #00cec9);">
                        <i class="fas fa-user"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $livreur['nombre_livraisons'] ?? 0; ?></h3>
                        <p>إجمالي توصيلاتي</p>
                    </div>
                </div>
            </div>

            <div class="filters">
                <div style="display: flex; gap: 10px; flex-wrap: wrap; justify-content: center;">
                    <a href="?filter=disponibles<?php echo !empty($selected_ville) ? '&ville='.urlencode($selected_ville) : ''; ?>" 
                       class="btn <?php echo $filter == 'disponibles' ? 'btn-primary' : 'btn-outline'; ?>">
                        <i class="fas fa-clock"></i> المهام المتاحة (<?php echo $counts['disponibles'] ?? 0; ?>)
                    </a>
                    <a href="?filter=en_cours" class="btn <?php echo $filter == 'en_cours' ? 'btn-primary' : 'btn-outline'; ?>">
                        <i class="fas fa-play-circle"></i> المهام الجارية (<?php echo $counts['en_cours'] ?? 0; ?>)
                    </a>
                    <a href="?filter=terminees" class="btn <?php echo $filter == 'terminees' ? 'btn-primary' : 'btn-outline'; ?>">
                        <i class="fas fa-check-circle"></i> المهام المنجزة (<?php echo $counts['terminees'] ?? 0; ?>)
                    </a>
                    <a href="?filter=mes" class="btn <?php echo $filter == 'mes' ? 'btn-primary' : 'btn-outline'; ?>">
                        <i class="fas fa-user"></i> مهامي (<?php echo $counts['total_mes'] ?? 0; ?>)
                    </a>
                </div>
                
                <?php if($filter == 'disponibles'): ?>
                <div class="city-filter">
                    <i class="fas fa-filter" style="color: var(--accent);"></i>
                    <span>تصفية حسب المدينة:</span>
                    <form method="GET" style="display: flex; gap: 10px; flex: 1; flex-wrap: wrap;">
                        <input type="hidden" name="filter" value="disponibles">
                        <select name="ville" class="city-select" onchange="this.form.submit()">
                            <option value="">جميع المدن</option>
                            <?php foreach($villes as $ville): ?>
                                <option value="<?php echo htmlspecialchars($ville['ville']); ?>" 
                                        <?php echo $selected_ville == $ville['ville'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($ville['ville']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if(!empty($selected_ville)): ?>
                            <a href="?filter=disponibles" class="btn btn-outline btn-sm">
                                <i class="fas fa-times"></i> إلغاء
                            </a>
                        <?php endif; ?>
                    </form>
                </div>
                <?php endif; ?>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3>
                        <?php 
                        switch($filter) {
                            case 'disponibles': 
                                echo '<i class="fas fa-clock" style="color: #00b894;"></i> المهام المتاحة للتوصيل';
                                if(!empty($selected_ville)) echo ' في ' . htmlspecialchars($selected_ville);
                                break;
                            case 'en_cours': 
                                echo '<i class="fas fa-play-circle" style="color: #fdcb6e;"></i> المهام الجارية'; 
                                break;
                            case 'terminees': 
                                echo '<i class="fas fa-check-circle" style="color: #6c5ce7;"></i> المهام المنجزة'; 
                                break;
                            case 'mes': 
                                echo '<i class="fas fa-user" style="color: #00b894;"></i> مهامي'; 
                                break;
                        }
                        ?>
                    </h3>
                    <span class="badge badge-primary"><?php echo count($missions); ?> مهمة</span>
                </div>
                <div class="card-body">
                    <?php if(empty($missions)): ?>
                        <div class="empty-state">
                            <i class="fas fa-box-open"></i>
                            <h3>لا توجد مهام</h3>
                            <p>
                                <?php 
                                if($filter == 'disponibles') {
                                    echo 'لا توجد مهام متاحة حالياً';
                                } elseif($filter == 'en_cours') {
                                    echo 'ليس لديك مهام جارية';
                                } elseif($filter == 'terminees') {
                                    echo 'لم تقم بإنجاز أي مهمة بعد';
                                } else {
                                    echo 'لا توجد مهام';
                                }
                                ?>
                            </p>
                            <?php if($filter != 'disponibles'): ?>
                                <a href="?filter=disponibles" class="btn btn-primary" style="margin-top: 15px;">
                                    <i class="fas fa-clock"></i> عرض المهام المتاحة
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <?php foreach($missions as $mission): 
                            $card_class = '';
                            $status_text = '';
                            $status_badge = '';
                            
                            if($mission['livreur_id'] === null) {
                                $card_class = 'disponible';
                                $status_text = 'متاحة للتوصيل';
                                $status_badge = 'badge-warning';
                            } elseif($mission['statut'] == 'assignee') {
                                $card_class = 'en-cours';
                                $status_text = 'تم قبولها';
                                $status_badge = 'badge-info';
                            } elseif($mission['statut'] == 'en_cours') {
                                $card_class = 'en-cours';
                                $status_text = 'جارية';
                                $status_badge = 'badge-primary';
                            } elseif($mission['statut'] == 'livree') {
                                $card_class = 'terminee';
                                $status_text = 'تم التوصيل';
                                $status_badge = 'badge-success';
                            }
                        ?>
                            <div class="mission-card <?php echo $card_class; ?>">
                                <div class="mission-info">
                                    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px; flex-wrap: wrap;">
                                        <span class="badge <?php echo $status_badge; ?>"><?php echo $status_text; ?></span>
                                        
                                        <?php if($mission['frais_livraison'] > 0): ?>
                                            <span class="badge badge-success">
                                                <i class="fas fa-money-bill"></i> رسوم: <?php echo number_format($mission['frais_livraison'], 2); ?> درهم
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <h3>
                                        <?php echo htmlspecialchars($mission['don_titre']); ?>
                                        <span class="badge badge-primary"><?php echo htmlspecialchars($mission['ville'] ?: $mission['don_ville']); ?></span>
                                    </h3>
                                    
                                    <div class="mission-details">
                                        <span><i class="fas fa-user"></i> المستفيد: <?php echo htmlspecialchars($mission['beneficiaire_nom']); ?></span>
                                        <span><i class="fas fa-user"></i> المتبرع: <?php echo htmlspecialchars($mission['donateur_nom']); ?></span>
                                        <span><i class="fas fa-tag"></i> <?php echo htmlspecialchars($mission['categorie']); ?></span>
                                        <span><i class="fas fa-calendar"></i> <?php echo date('d/m/Y', strtotime($mission['created_at'])); ?></span>
                                    </div>
                                    
                                    <div style="margin-top: 8px;">
                                        <i class="fas fa-map-pin"></i> 
                                        <strong>الاستلام:</strong> <?php echo htmlspecialchars($mission['adresse_retrait']); ?>
                                    </div>
                                    
                                    <?php if($mission['beneficiaire_adresse']): ?>
                                    <div style="margin-top: 5px;">
                                        <i class="fas fa-home"></i> 
                                        <strong>التسليم:</strong> <?php echo htmlspecialchars($mission['beneficiaire_adresse']); ?>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if(in_array($mission['statut'], ['assignee', 'en_cours']) && $mission['livreur_id'] == $user_id): ?>
                                    <div class="contact-info">
                                        <span><i class="fas fa-phone"></i> المتبرع: <?php echo htmlspecialchars($mission['donateur_telephone'] ?? 'غير متوفر'); ?></span>
                                        <span><i class="fas fa-phone"></i> المستفيد: <?php echo htmlspecialchars($mission['beneficiaire_telephone'] ?? 'غير متوفر'); ?></span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="mission-actions">
                                    <?php if($mission['livreur_id'] === null): ?>
                                        <a href="?accept=1&mission_id=<?php echo $mission['id']; ?>" 
                                           class="btn btn-success" 
                                           onclick="return confirm('هل تريد قبول هذه المهمة؟')">
                                            <i class="fas fa-check"></i> قبول
                                        </a>
                                    <?php elseif($mission['livreur_id'] == $user_id && $mission['statut'] == 'assignee'): ?>
                                        <a href="?start=1&mission_id=<?php echo $mission['id']; ?>" 
                                           class="btn btn-primary"
                                           onclick="return confirm('بدء هذه المهمة؟')">
                                            <i class="fas fa-play"></i> بدء
                                        </a>
                                    <?php elseif($mission['livreur_id'] == $user_id && $mission['statut'] == 'en_cours'): ?>
                                        <a href="?complete=1&mission_id=<?php echo $mission['id']; ?>" 
                                           class="btn btn-warning"
                                           onclick="return confirm('تأكيد إنجاز المهمة؟')">
                                            <i class="fas fa-check-circle"></i> إنهاء
                                        </a>
                                    <?php endif; ?>
                                    
                                    <a href="mission-details.php?id=<?php echo $mission['id']; ?>" class="btn btn-outline">
                                        <i class="fas fa-eye"></i> تفاصيل
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
    function toggleMenu() {
        document.getElementById('navLinks').classList.toggle('active');
    }
    
    function toggleDropdown() {
        document.getElementById('userDropdown').classList.toggle('active');
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


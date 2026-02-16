<?php
// beneficiaire/catalogue.php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'beneficiaire') {
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

// ========== معالجة طلب التبرع ==========
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'request_donation') {
    $don_id = $_POST['don_id'] ?? 0;
    $message = trim($_POST['message'] ?? '');
    
    if (empty($message)) {
        $error = "❌ الرجاء كتابة رسالة توضح سبب طلبك لهذا التبرع";
    } else {
        try {
            // التحقق من أن التبرع لا يزال متاحاً
            $check_query = "SELECT donateur_id, titre FROM dons WHERE id = :don_id AND statut = 'disponible'";
            $check_stmt = $db->prepare($check_query);
            $check_stmt->bindParam(':don_id', $don_id);
            $check_stmt->execute();
            $don = $check_stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$don) {
                $error = "❌ هذا التبرع غير متاح حالياً";
            } else {
                // التحقق من أن المستفيد لم يقدم طلباً على هذا التبرع من قبل
                $check_demande = "SELECT id FROM demandes WHERE don_id = :don_id AND beneficiaire_id = :beneficiaire_id";
                $check_demande_stmt = $db->prepare($check_demande);
                $check_demande_stmt->bindParam(':don_id', $don_id);
                $check_demande_stmt->bindParam(':beneficiaire_id', $user_id);
                $check_demande_stmt->execute();
                
                if ($check_demande_stmt->rowCount() > 0) {
                    $error = "❌ لقد قدمت طلباً على هذا التبرع مسبقاً";
                } else {
                    // إدراج الطلب
                    $query = "INSERT INTO demandes (don_id, beneficiaire_id, message_demande, statut, created_at) 
                              VALUES (:don_id, :beneficiaire_id, :message, 'en_attente', NOW())";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':don_id', $don_id);
                    $stmt->bindParam(':beneficiaire_id', $user_id);
                    $stmt->bindParam(':message', $message);
                    
                    if ($stmt->execute()) {
                        $demande_id = $db->lastInsertId();
                        
                        // إنشاء رسالة في نظام المراسلة للمتابعة
                        $message_content = "مرحباً، لقد تقدمت بطلب للحصول على تبرعك: " . $don['titre'] . "\n\n";
                        $message_content .= "رسالتي: " . $message . "\n\n";
                        $message_content .= "أرجو الرد في أقرب وقت ممكن. شكراً لك!";
                        
                        $msg_query = "INSERT INTO messages (expediteur_id, destinataire_id, message, lu, created_at) 
                                      VALUES (:expediteur_id, :destinataire_id, :message, 0, NOW())";
                        $msg_stmt = $db->prepare($msg_query);
                        $msg_stmt->bindParam(':expediteur_id', $user_id);
                        $msg_stmt->bindParam(':destinataire_id', $don['donateur_id']);
                        $msg_stmt->bindParam(':message', $message_content);
                        $msg_stmt->execute();
                        
                        $success = "✅ تم إرسال طلبك بنجاح! سيتم إشعارك عند الرد على طلبك.";
                    } else {
                        $error = "❌ حدث خطأ أثناء إرسال الطلب";
                    }
                }
            }
        } catch(PDOException $e) {
            $error = "❌ خطأ في قاعدة البيانات: " . $e->getMessage();
        }
    }
}

// ========== جلب جميع التبرعات المتاحة ==========
$query_dons = "
    SELECT d.*, 
           u.nom as donateur_nom,
           u.ville as donateur_ville,
           (SELECT COUNT(*) FROM demandes WHERE don_id = d.id) as nb_demandes
    FROM dons d
    INNER JOIN users u ON d.donateur_id = u.id
    WHERE d.statut = 'disponible'
    ORDER BY d.created_at DESC
";

$dons = $db->query($query_dons)->fetchAll(PDO::FETCH_ASSOC);

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
        
        /* Donation Grid */
        .donations-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
            margin-top: 20px;
        }
        
        .donation-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: all 0.3s;
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        
        .donation-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }
        
        .donation-image {
            height: 200px;
            background: #f8f9fa;
            position: relative;
            overflow: hidden;
        }
        
        .donation-image img {
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
            font-size: 60px;
            background: linear-gradient(135deg, #f5f7fa, #e4e8f0);
            color: #aaa;
        }
        
        .donation-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            background: var(--accent);
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        }
        
        .donation-content {
            padding: 20px;
            flex: 1;
        }
        
        .donation-title {
            font-size: 18px;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 10px;
        }
        
        .donation-meta {
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
        
        .donation-description {
            color: #666;
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 15px;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .donation-footer {
            padding: 15px 20px;
            background: #f8f9fa;
            border-top: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .donor-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .donor-avatar {
            width: 35px;
            height: 35px;
            background: linear-gradient(135deg, #00b894, #00cec9);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 16px;
        }
        
        .donor-details {
            font-size: 13px;
        }
        
        .donor-name {
            font-weight: 600;
            color: var(--dark);
        }
        
        .donor-city {
            color: #666;
            font-size: 12px;
        }
        
        .btn-request {
            background: linear-gradient(135deg, var(--accent), #74b9ff);
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
            text-decoration: none;
            font-size: 14px;
        }
        
        .btn-request:hover {
            background: linear-gradient(135deg, #0984e3, #0984e3);
            transform: scale(1.05);
        }
        
        /* Modal */
        .modal {
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
        }
        
        .modal.active {
            display: flex;
        }
        
        .modal-content {
            background: white;
            border-radius: 15px;
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            animation: modalSlideIn 0.3s ease-out;
        }
        
        @keyframes modalSlideIn {
            from {
                transform: translateY(-50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
        
        .modal-header {
            padding: 20px 25px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header h3 {
            margin: 0;
            font-size: 20px;
        }
        
        .modal-close {
            background: none;
            border: none;
            color: white;
            font-size: 24px;
            cursor: pointer;
            transition: transform 0.3s;
        }
        
        .modal-close:hover {
            transform: scale(1.2);
        }
        
        .modal-body {
            padding: 25px;
        }
        
        /* Form */
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
            min-height: 120px;
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
            
            .donations-grid {
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
            <!-- Welcome Section -->
            <div class="welcome-section">
                <div class="welcome-content">
                    <div class="welcome-icon">
                        <i class="fas fa-box-open"></i>                    
                    </div>
                    <div class="welcome-text">
                        <h1>كتالوج التبرعات</h1>
                        <p>تصفح التبرعات المتاحة واختر ما يناسبك</p>
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

            <!-- Donations Grid -->
            <?php if(empty($dons)): ?>
                <div class="empty-state">
                    <i class="fas fa-gift"></i>
                    <h3>لا توجد تبرعات متاحة حالياً</h3>
                    <p>ترقب قريباً، قد يتم نشر تبرعات جديدة</p>
                </div>
            <?php else: ?>
                <div class="donations-grid">
                    <?php foreach($dons as $don): ?>
                        <div class="donation-card">
                            <div class="donation-image">
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
                                <span class="donation-badge">
                                    <?php echo $categories[$don['categorie']] ?? $don['categorie']; ?>
                                </span>
                            </div>
                            
                            <div class="donation-content">
                                <h3 class="donation-title"><?php echo htmlspecialchars($don['titre']); ?></h3>
                                
                                <div class="donation-meta">
                                    <span class="meta-item">
                                        <i class="fas fa-tag"></i> <?php echo $etats[$don['etat']] ?? $don['etat']; ?>
                                    </span>
                                    <span class="meta-item">
                                        <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($don['ville']); ?>
                                    </span>
                                    <?php if($don['livraison_option'] != 'none'): ?>
                                        <span class="meta-item" style="background: #e3f2fd; color: #1976d2;">
                                            <i class="fas fa-truck"></i> 
                                            <?php echo $livraison_options[$don['livraison_option']] ?? ''; ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                
                                <p class="donation-description">
                                    <?php echo htmlspecialchars($don['description']); ?>
                                </p>
                            </div>
                            
                            <div class="donation-footer">
                                <div class="donor-info">
                                    <div class="donor-avatar">
                                        <?php echo strtoupper(substr($don['donateur_nom'], 0, 1)); ?>
                                    </div>
                                    <div class="donor-details">
                                        <div class="donor-name"><?php echo htmlspecialchars($don['donateur_nom']); ?></div>
                                        <div class="donor-city">
                                            <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($don['donateur_ville'] ?? 'غير محدد'); ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <button class="btn-request" onclick="openRequestModal(<?php echo $don['id']; ?>, '<?php echo htmlspecialchars(addslashes($don['titre'])); ?>')">
                                    <i class="fas fa-hand-holding-heart"></i> طلب
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Request Modal -->
    <div id="requestModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-hand-holding-heart"></i> طلب تبرع</h3>
                <button class="modal-close" onclick="closeRequestModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" action="">
                    <input type="hidden" name="action" value="request_donation">
                    <input type="hidden" name="don_id" id="modal_don_id">
                    
                    <div class="form-group">
                        <label class="form-label">التبرع: <span id="modal_don_title" style="color: var(--accent);"></span></label>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">رسالتك إلى المتبرع *</label>
                        <textarea name="message" class="form-control" placeholder="اكتب رسالة توضح سبب رغبتك في الحصول على هذا التبرع..." required></textarea>
                        <small style="color: #666; display: block; margin-top: 5px;">
                            <i class="fas fa-info-circle"></i> اكتب رسالة واضحة ومؤدبة تشرح فيها سبب حاجتك لهذا التبرع
                        </small>
                    </div>
                    
                    <div style="display: flex; gap: 15px; justify-content: flex-start; margin-top: 20px;">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i> إرسال الطلب
                        </button>
                        <button type="button" class="btn btn-outline" onclick="closeRequestModal()">
                            <i class="fas fa-times"></i> إلغاء
                        </button>
                    </div>
                </form>
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
    
    function openRequestModal(donId, donTitle) {
        document.getElementById('modal_don_id').value = donId;
        document.getElementById('modal_don_title').textContent = donTitle;
        document.getElementById('requestModal').classList.add('active');
        document.body.style.overflow = 'hidden';
    }
    
    function closeRequestModal() {
        document.getElementById('requestModal').classList.remove('active');
        document.body.style.overflow = 'auto';
    }
    
    // إغلاق القوائم عند النقر خارجها
    document.addEventListener('click', function(event) {
        const navLinks = document.getElementById('navLinks');
        const menuToggle = document.querySelector('.menu-toggle');
        const userDropdown = document.getElementById('userDropdown');
        const userAvatar = document.querySelector('.user-avatar');
        const modal = document.getElementById('requestModal');
        
        if (!navLinks.contains(event.target) && !menuToggle.contains(event.target)) {
            navLinks.classList.remove('active');
        }
        
        if (!userDropdown.contains(event.target) && !userAvatar.contains(event.target)) {
            userDropdown.classList.remove('active');
        }
        
        // Close modal when clicking outside
        if (event.target === modal) {
            closeRequestModal();
        }
    });
    
    // Close modal with Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeRequestModal();
        }
    });
    </script>
</body>
</html>
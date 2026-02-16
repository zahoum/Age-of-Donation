<?php
// donateur/publier-don.php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'donateur') {
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

// قائمة المدن المغربية
$moroccan_cities = [
    'أكادير', 'آسفي', 'أزيلال', 'آسا الزاك', 'بني ملال', 'بنسليمان', 'بوجدور', 'بولمان', 
    'بني ملال', 'تارودانت', 'تازة', 'تانطان', 'تاونات', 'تطوان', 'تيزنيت', 'الرشيدية', 
    'الفقيه بن صالح', 'القنيطرة', 'الدار البيضاء', 'الرباط', 'سلا', 'تمارة', 'المحمدية', 
    'الجديدة', 'الصويرة', 'آسفي', 'اليوسفية', 'العرائش', 'العيون', 'بوجدور', 'الداخلة', 
    'إفران', 'الحاجب', 'الخميسات', 'خريبكة', 'خنيفرة', 'جرادة', 'جرسيف', 'فحص أنجرة', 
    'فاس', 'فكيك', 'كلميم', 'العرائش', 'القنيطرة', 'الخميسات', 'الخميسات', 'الرشيدية', 
    'السمارة', 'سيدي بنور', 'سيدي إفني', 'سيدي سليمان', 'سيدي قاسم', 'شفشاون', 'شيشاوة', 
    'صفرو', 'طاطا', 'طنجة', 'تارودانت', 'تازة', 'تطوان', 'تيزنيت', 'وزان', 'ورزازات', 
    'وجدة', 'اليوسفية', 'زاكورة'
];

// ترتيب المدن أبجدياً
sort($moroccan_cities, SORT_STRING);

// جلب المدن الفريدة من المستخدمين (المتبرعين والمستفيدين)
$query_cities = "SELECT DISTINCT ville FROM users WHERE ville IS NOT NULL AND ville != '' ORDER BY ville";
$stmt_cities = $db->prepare($query_cities);
$stmt_cities->execute();
$user_cities = $stmt_cities->fetchAll(PDO::FETCH_COLUMN);

// دمج المدن وإزالة التكرارات
$all_cities = array_unique(array_merge($moroccan_cities, $user_cities));
sort($all_cities, SORT_STRING);

$success = '';
$error = '';

if ($_POST) {
    $titre = trim($_POST['titre']);
    $description = trim($_POST['description']);
    $categorie = $_POST['categorie'];
    $etat = $_POST['etat'];
    $adresse_retrait = trim($_POST['adresse_retrait']);
    $ville = trim($_POST['ville']);
    $livraison_option = $_POST['livraison_option'] ?? 'none';
    
    // التحقق من الحقول الإلزامية
    if (empty($titre) || empty($description) || empty($categorie) || empty($etat) || empty($adresse_retrait) || empty($ville)) {
        $error = "جميع الحقول الإلزامية يجب تعبئتها";
    } else {
        // Gestion de l'upload de photo
        $photo_principale = '';
        if (isset($_FILES['photo_principale']) && $_FILES['photo_principale']['error'] === 0) {
            $uploadDir = '../uploads/dons/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            $fileExtension = pathinfo($_FILES['photo_principale']['name'], PATHINFO_EXTENSION);
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
            
            if (in_array(strtolower($fileExtension), $allowedExtensions)) {
                $fileName = uniqid() . '_' . time() . '.' . $fileExtension;
                $filePath = $uploadDir . $fileName;
                
                if (move_uploaded_file($_FILES['photo_principale']['tmp_name'], $filePath)) {
                    $photo_principale = 'uploads/dons/' . $fileName;
                } else {
                    $error = "خطأ أثناء رفع الصورة";
                }
            } else {
                $error = "صيغة الصورة غير مدعومة. استخدم JPG، JPEG، PNG أو GIF";
            }
        } else {
            $error = "الصورة الرئيسية مطلوبة";
        }
        
        if (!$error) {
            try {
                $query = "INSERT INTO dons (donateur_id, titre, description, photo_principale, categorie, etat, adresse_retrait, ville, livraison_option, statut, created_at) 
                          VALUES (:donateur_id, :titre, :description, :photo_principale, :categorie, :etat, :adresse_retrait, :ville, :livraison_option, 'disponible', NOW())";
                
                $stmt = $db->prepare($query);
                $stmt->bindParam(":donateur_id", $_SESSION['user_id']);
                $stmt->bindParam(":titre", $titre);
                $stmt->bindParam(":description", $description);
                $stmt->bindParam(":photo_principale", $photo_principale);
                $stmt->bindParam(":categorie", $categorie);
                $stmt->bindParam(":etat", $etat);
                $stmt->bindParam(":adresse_retrait", $adresse_retrait);
                $stmt->bindParam(":ville", $ville);
                $stmt->bindParam(":livraison_option", $livraison_option);
                
                if ($stmt->execute()) {
                    $don_id = $db->lastInsertId();
                    
                    // Photos supplémentaires
                    if (!empty($_FILES['photos_supplementaires']['name'][0])) {
                        foreach ($_FILES['photos_supplementaires']['tmp_name'] as $key => $tmp_name) {
                            if ($_FILES['photos_supplementaires']['error'][$key] === 0) {
                                $fileExtension = pathinfo($_FILES['photos_supplementaires']['name'][$key], PATHINFO_EXTENSION);
                                if (in_array(strtolower($fileExtension), $allowedExtensions)) {
                                    $fileName = uniqid() . '_' . time() . '_' . $key . '.' . $fileExtension;
                                    $filePath = $uploadDir . $fileName;
                                    
                                    if (move_uploaded_file($tmp_name, $filePath)) {
                                        $photoQuery = "INSERT INTO don_photos (don_id, photo_path) VALUES (:don_id, :photo_path)";
                                        $photoStmt = $db->prepare($photoQuery);
                                        $photoStmt->bindParam(":don_id", $don_id);
                                        $photoPath = 'uploads/dons/' . $fileName;
                                        $photoStmt->bindParam(":photo_path", $photoPath);
                                        $photoStmt->execute();
                                    }
                                }
                            }
                        }
                    }
                    
                    $success = "✅ تم نشر التبرع بنجاح!";
                    $_POST = array();
                } else {
                    $error = "❌ حدث خطأ أثناء نشر التبرع";
                }
            } catch(PDOException $e) {
                $error = "❌ خطأ: " . $e->getMessage();
            }
        }
    }
}

$page_title = 'نشر تبرع جديد';
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
        
        .btn-outline {
            background: transparent;
            border: 2px solid var(--accent);
            color: var(--accent);
        }
        
        .btn-outline:hover {
            background: var(--accent);
            color: white;
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
        
        select.form-control {
            cursor: pointer;
        }
        
        textarea.form-control {
            resize: vertical;
            min-height: 100px;
        }
        
        /* City Search Dropdown */
        .city-search-container {
            position: relative;
        }
        
        .city-search-input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e1e1e1;
            border-radius: 8px;
            font-size: 15px;
            transition: all 0.3s;
            font-family: 'Tajawal', sans-serif;
        }
        
        .city-search-input:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(9, 132, 227, 0.1);
        }
        
        .city-dropdown {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            max-height: 300px;
            overflow-y: auto;
            background: white;
            border: 2px solid #e1e1e1;
            border-top: none;
            border-radius: 0 0 8px 8px;
            z-index: 1000;
            display: none;
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        
        .city-dropdown.active {
            display: block;
        }
        
        .city-item {
            padding: 10px 15px;
            cursor: pointer;
            transition: all 0.2s;
            border-bottom: 1px solid #f1f2f6;
        }
        
        .city-item:hover {
            background: var(--accent);
            color: white;
        }
        
        .city-item:last-child {
            border-bottom: none;
        }
        
        .city-item.highlighted {
            background: #e3f2fd;
            color: var(--accent);
            font-weight: 500;
        }
        
        /* Row and Col */
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
        
        .col-8 {
            flex: 0 0 66.666%;
            max-width: 66.666%;
            padding: 0 15px;
        }
        
        .col-4 {
            flex: 0 0 33.333%;
            max-width: 33.333%;
            padding: 0 15px;
        }
        
        @media (max-width: 768px) {
            .col-6, .col-8, .col-4 {
                flex: 0 0 100%;
                max-width: 100%;
            }
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
        
        /* Upload Area */
        .upload-area {
            border: 2px dashed #ddd;
            padding: 30px;
            text-align: center;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .upload-area:hover {
            border-color: var(--accent);
            background: #f8f9fa;
        }
        
        .upload-area i {
            font-size: 40px;
            color: #aaa;
            margin-bottom: 10px;
        }
        
        .upload-area p {
            color: #666;
            margin: 0;
        }
        
        .upload-area small {
            color: #888;
        }
        
        /* Preview */
        .preview-container {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 10px;
        }
        
        .preview-item {
            position: relative;
            width: 80px;
            height: 80px;
        }
        
        .preview-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 8px;
            border: 1px solid #ddd;
        }
        
        .preview-remove {
            position: absolute;
            top: -5px;
            right: -5px;
            width: 20px;
            height: 20px;
            background: var(--danger);
            color: white;
            border: none;
            border-radius: 50%;
            cursor: pointer;
            font-size: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        /* =========================
   Compact Modern Delivery Options
========================= */

.delivery-options {
    display: flex;
    gap: 12px;
    margin-top: 10px;
    flex-wrap: wrap;
}

/* Hide radio */
.delivery-option input[type="radio"] {
    display: none;
}

/* Button Style */
.delivery-card {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 14px;
    border-radius: 30px;
    border: 1.5px solid #e5e7eb;
    background: #fff;
    cursor: pointer;
    font-size: 13px;
    font-weight: 500;
    color: #374151;
    transition: all 0.25s ease;
    white-space: nowrap;
}

/* Icon */
.delivery-card i {
    font-size: 14px;
}

/* Percentage */
.delivery-card .percentage {
    font-weight: 700;
    font-size: 13px;
}

/* Hide big description */
.delivery-card .description {
    display: none;
}

/* Hover */
.delivery-card:hover {
    border-color: var(--accent);
    background: #f9fafb;
}

/* Selected */
.delivery-option input[type="radio"]:checked + .delivery-card {
    background: var(--accent);
    color: #fff;
    border-color: var(--accent);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.25);
}

/* Icon color when selected */
.delivery-option input[type="radio"]:checked + .delivery-card i {
    color: #fff;
}

/* Optional color hint before selection */
.delivery-card.none { border-color: #fecaca; }
.delivery-card.fifty { border-color: #fde68a; }
.delivery-card.full { border-color: #bbf7d0; }

        /* Info Card */
        .info-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
        }
        
        .info-card h4 {
            color: var(--primary);
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .info-card ul {
            padding-right: 20px;
            color: #666;
        }
        
        .info-card li {
            margin-bottom: 10px;
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
            
            .delivery-options {
                flex-direction: column;
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
            <li class="nav-item"><a href="publier-don.php" class="nav-link active"><i class="fas fa-plus-circle"></i> نشر تبرع</a></li>
            <li class="nav-item"><a href="mes-dons.php" class="nav-link"><i class="fas fa-boxes"></i> تبرعاتي</a></li>
            <li class="nav-item"><a href="confirmer-commandes.php" class="nav-link"><i class="fas fa-check-circle"></i> تأكيد الطلبات</a></li>
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
                <a href="mes-dons.php" class="user-dropdown-item">
                    <i class="fas fa-boxes"></i> تبرعاتي
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
                        <i class="fas fa-gift"></i>                    
                    </div>
                    <div class="welcome-text">
                        <h1>نشر تبرع جديد</h1>
                        <p>شارك ما لم تعد بحاجة إليه وساعد الآخرين</p>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-8" style="margin: 0 auto;">
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-edit"></i> معلومات التبرع</h3>
                        </div>
                        
                        <div class="card-body">
                            <?php if($success): ?>
                                <div class="alert alert-success">
                                    <i class="fas fa-check-circle"></i>
                                    <?php echo $success; ?>
                                    <br>
                                    <a href="mes-dons.php" class="btn btn-success btn-sm" style="margin-top: 10px;">
                                        <i class="fas fa-boxes"></i> عرض تبرعاتي
                                    </a>
                                </div>
                            <?php endif; ?>

                            <?php if($error): ?>
                                <div class="alert alert-danger">
                                    <i class="fas fa-exclamation-circle"></i>
                                    <?php echo $error; ?>
                                </div>
                            <?php endif; ?>

                            <form method="POST" enctype="multipart/form-data">
                                <div class="form-group">
                                    <label class="form-label">عنوان التبرع *</label>
                                    <input type="text" name="titre" class="form-control" 
                                           value="<?php echo htmlspecialchars($_POST['titre'] ?? ''); ?>" 
                                           placeholder="مثال: كتب للأطفال، ملابس شتوية، أثاث..." required>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">وصف مفصل *</label>
                                    <textarea name="description" class="form-control" rows="4" 
                                              placeholder="صف التبرع بشكل مفصل (الحالة، المقاسات، الملاحظات الهامة...)" required><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                                </div>

                                <div class="row">
                                    <div class="col-6">
                                        <div class="form-group">
                                            <label class="form-label">الفئة *</label>
                                            <select name="categorie" class="form-control" required>
                                                <option value="">اختر الفئة</option>
                                                <option value="vetements" <?php echo ($_POST['categorie'] ?? '') == 'vetements' ? 'selected' : ''; ?>>ملابس</option>
                                                <option value="nourriture" <?php echo ($_POST['categorie'] ?? '') == 'nourriture' ? 'selected' : ''; ?>>طعام</option>
                                                <option value="meubles" <?php echo ($_POST['categorie'] ?? '') == 'meubles' ? 'selected' : ''; ?>>أثاث</option>
                                                <option value="livres" <?php echo ($_POST['categorie'] ?? '') == 'livres' ? 'selected' : ''; ?>>كتب</option>
                                                <option value="electromenager" <?php echo ($_POST['categorie'] ?? '') == 'electromenager' ? 'selected' : ''; ?>>أجهزة كهربائية</option>
                                                <option value="divers" <?php echo ($_POST['categorie'] ?? '') == 'divers' ? 'selected' : ''; ?>>متنوع</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="col-6">
                                        <div class="form-group">
                                            <label class="form-label">الحالة *</label>
                                            <select name="etat" class="form-control" required>
                                                <option value="">اختر الحالة</option>
                                                <option value="neuf" <?php echo ($_POST['etat'] ?? '') == 'neuf' ? 'selected' : ''; ?>>جديد</option>
                                                <option value="bon_etat" <?php echo ($_POST['etat'] ?? '') == 'bon_etat' ? 'selected' : ''; ?>>حالة جيدة</option>
                                                <option value="usage" <?php echo ($_POST['etat'] ?? '') == 'usage' ? 'selected' : ''; ?>>مستعمل</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <!-- Delivery Payment Options -->
                                <div class="form-group">
                                    <label class="form-label">خيارات دفع التوصيل *</label>
                                    <div class="delivery-options">
                                        <div class="delivery-option">
                                            <input type="radio" name="livraison_option" id="livraison_none" value="none" <?php echo ($_POST['livraison_option'] ?? 'none') == 'none' ? 'checked' : ''; ?> required>
                                            <label for="livraison_none" class="delivery-card none">
                                                <i class="fas fa-times-circle"></i>
                                                <div class="percentage">0%</div>
                                                <div class="description">المستفيد يتحمل كامل تكلفة التوصيل</div>
                                            </label>
                                        </div>
                                        
                                        <div class="delivery-option">
                                            <input type="radio" name="livraison_option" id="livraison_fifty" value="fifty" <?php echo ($_POST['livraison_option'] ?? '') == 'fifty' ? 'checked' : ''; ?>>
                                            <label for="livraison_fifty" class="delivery-card fifty">
                                                <i class="fas fa-adjust"></i>
                                                <div class="percentage">50%</div>
                                                <div class="description">تتحمل 50% من تكلفة التوصيل</div>
                                            </label>
                                        </div>
                                        
                                        <div class="delivery-option">
                                            <input type="radio" name="livraison_option" id="livraison_full" value="full" <?php echo ($_POST['livraison_option'] ?? '') == 'full' ? 'checked' : ''; ?>>
                                            <label for="livraison_full" class="delivery-card full">
                                                <i class="fas fa-check-circle"></i>
                                                <div class="percentage">100%</div>
                                                <div class="description">تتحمل كامل تكلفة التوصيل</div>
                                            </label>
                                        </div>
                                    </div>
                                    <small style="color: #666; display: block; margin-top: 10px;">
                                        <i class="fas fa-info-circle"></i> اختر نسبة التوصيل التي تريد تحملها. إذا اخترت 0%، سيتحمل المستفيد التكلفة كاملة.
                                    </small>
                                </div>

                                <!-- Photos -->
                                <div class="row">
                                    <div class="col-6">
                                        <div class="form-group">
                                            <label class="form-label">الصورة الرئيسية *</label>
                                            <div class="upload-area" onclick="document.getElementById('photo_principale').click()">
                                                <i class="fas fa-camera"></i>
                                                <p>انقر لاختيار صورة</p>
                                                <small>(JPG, JPEG, PNG, GIF)</small>
                                                <input type="file" id="photo_principale" name="photo_principale" 
                                                       accept="image/*" style="display:none" onchange="previewPhoto(this, 'preview-principal')" required>
                                            </div>
                                            <div id="preview-principal" class="preview-container"></div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-6">
                                        <div class="form-group">
                                            <label class="form-label">صور إضافية (اختياري)</label>
                                            <div class="upload-area" onclick="document.getElementById('photos_supplementaires').click()">
                                                <i class="fas fa-images"></i>
                                                <p>انقر لإضافة صور</p>
                                                <small>(حد أقصى 5 صور)</small>
                                                <input type="file" id="photos_supplementaires" name="photos_supplementaires[]" 
                                                       multiple accept="image/*" style="display:none" onchange="previewMultiplePhotos(this, 'preview-supplementaires')">
                                            </div>
                                            <div id="preview-supplementaires" class="preview-container"></div>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-6">
                                        <div class="form-group">
                                            <label class="form-label">المدينة *</label>
                                            <div class="city-search-container">
                                                <input type="text" 
                                                       id="citySearchInput" 
                                                       class="city-search-input" 
                                                       placeholder="ابحث عن مدينتك..." 
                                                       value="<?php echo htmlspecialchars($_POST['ville'] ?? ''); ?>"
                                                       oninput="filterCities(this.value)"
                                                       onclick="showCityDropdown()"
                                                       autocomplete="off">
                                                <input type="hidden" name="ville" id="selectedCity" value="<?php echo htmlspecialchars($_POST['ville'] ?? ''); ?>" required>
                                                <div id="cityDropdown" class="city-dropdown">
                                                    <?php foreach($all_cities as $city): ?>
                                                        <div class="city-item" onclick="selectCity('<?php echo htmlspecialchars($city); ?>')">
                                                            <?php echo htmlspecialchars($city); ?>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                            <small style="color: #666;">ابحث عن مدينتك أو اختر من القائمة</small>
                                        </div>
                                    </div>

                                    <div class="col-6">
                                        <div class="form-group">
                                            <label class="form-label">عنوان الاستلام *</label>
                                            <input type="text" name="adresse_retrait" class="form-control" 
                                                   value="<?php echo htmlspecialchars($_POST['adresse_retrait'] ?? ''); ?>" 
                                                   placeholder="العنوان الكامل للاستلام" required>
                                        </div>
                                    </div>
                                </div>

                                <div style="display: flex; gap: 15px; margin-top: 30px;">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-paper-plane"></i> نشر التبرع
                                    </button>
                                    <a href="dashboard.php" class="btn btn-outline">إلغاء والعودة</a>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Information Card -->
                    <div class="card" style="background: #f8f9fa;">
                        <div class="card-body">
                            <div class="info-card">
                                <h4><i class="fas fa-info-circle" style="color: var(--accent);"></i> معلومات مهمة</h4>
                                <ul>
                                    <li><i class="fas fa-check-circle" style="color: var(--success); margin-left: 8px;"></i> تأكد من أن التبرع في حالة جيدة ومناسبة للاستخدام</li>
                                    <li><i class="fas fa-check-circle" style="color: var(--success); margin-left: 8px;"></i> كن دقيقًا في الوصف لتجنب سوء الفهم</li>
                                    <li><i class="fas fa-check-circle" style="color: var(--success); margin-left: 8px;"></i> الصور الجيدة تزيد من فرص قبول التبرع</li>
                                    <li><i class="fas fa-check-circle" style="color: var(--success); margin-left: 8px;"></i> اختر خيار التوصيل المناسب لك وللمستفيد</li>
                                    <li><i class="fas fa-check-circle" style="color: var(--success); margin-left: 8px;"></i> يمكنك تحديث أو حذف التبرع في أي وقت من صفحة "تبرعاتي"</li>
                                </ul>
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
        
        // Close city dropdown when clicking outside
        const citySearchContainer = document.querySelector('.city-search-container');
        if (citySearchContainer && !citySearchContainer.contains(event.target)) {
            document.getElementById('cityDropdown').classList.remove('active');
        }
    });

    // City search functions
    function showCityDropdown() {
        document.getElementById('cityDropdown').classList.add('active');
    }
    
    function filterCities(searchText) {
        const dropdown = document.getElementById('cityDropdown');
        const items = dropdown.getElementsByClassName('city-item');
        const searchLower = searchText.toLowerCase();
        
        dropdown.classList.add('active');
        
        for (let item of items) {
            const cityText = item.textContent.toLowerCase();
            if (cityText.includes(searchLower)) {
                item.style.display = 'block';
            } else {
                item.style.display = 'none';
            }
        }
    }
    
    function selectCity(city) {
        document.getElementById('citySearchInput').value = city;
        document.getElementById('selectedCity').value = city;
        document.getElementById('cityDropdown').classList.remove('active');
    }

    // Photo preview functions
    function previewPhoto(input, previewId) {
        const preview = document.getElementById(previewId);
        preview.innerHTML = '';
        
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            
            reader.onload = function(e) {
                const imgContainer = document.createElement('div');
                imgContainer.className = 'preview-item';
                
                const img = document.createElement('img');
                img.src = e.target.result;
                
                imgContainer.appendChild(img);
                preview.appendChild(imgContainer);
            }
            
            reader.readAsDataURL(input.files[0]);
        }
    }

    function previewMultiplePhotos(input, previewId) {
        const preview = document.getElementById(previewId);
        preview.innerHTML = '';
        
        const files = Array.from(input.files).slice(0, 5);
        
        files.forEach((file, index) => {
            const reader = new FileReader();
            
            reader.onload = function(e) {
                const imgContainer = document.createElement('div');
                imgContainer.className = 'preview-item';
                
                const img = document.createElement('img');
                img.src = e.target.result;
                
                const removeBtn = document.createElement('button');
                removeBtn.className = 'preview-remove';
                removeBtn.innerHTML = '×';
                removeBtn.onclick = function(e) {
                    e.preventDefault();
                    imgContainer.remove();
                    
                    const dt = new DataTransfer();
                    const filesArray = Array.from(input.files);
                    filesArray.splice(index, 1);
                    filesArray.forEach(f => dt.items.add(f));
                    input.files = dt.files;
                };
                
                imgContainer.appendChild(img);
                imgContainer.appendChild(removeBtn);
                preview.appendChild(imgContainer);
            }
            
            reader.readAsDataURL(file);
        });
    }

    // Form validation
    document.querySelector('form').addEventListener('submit', function(e) {
        const requiredFields = this.querySelectorAll('[required]');
        let isValid = true;
        
        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                isValid = false;
                field.style.borderColor = '#ff7675';
            } else {
                field.style.borderColor = '';
            }
        });
        
        // Check if city is selected
        const cityInput = document.getElementById('selectedCity');
        if (!cityInput.value.trim()) {
            isValid = false;
            document.getElementById('citySearchInput').style.borderColor = '#ff7675';
        }
        
        // Check if main photo is selected
        const photoInput = document.getElementById('photo_principale');
        if (photoInput.files.length === 0) {
            isValid = false;
            document.querySelector('.upload-area').style.borderColor = '#ff7675';
        }
        
        if (!isValid) {
            e.preventDefault();
            alert('يرجى ملء جميع الحقول الإلزامية واختيار صورة');
        }
    });
    </script>
</body>
</html>
<?php
// beneficiaire/details-demande.php
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

$demande_id = $_GET['id'] ?? null;

if (!$demande_id) {
    header('Location: mes-demandes.php');
    exit();
}

// Récupérer les détails complets de la demande
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
           don.created_at as don_date_publication,
           u.nom as donateur_nom,
           u.email as donateur_email,
           u.telephone as donateur_telephone
    FROM demandes d
    INNER JOIN dons don ON d.don_id = don.id
    INNER JOIN users u ON don.donateur_id = u.id
    WHERE d.id = :demande_id AND d.beneficiaire_id = :user_id
";

$stmt = $db->prepare($query);
$stmt->bindParam(":demande_id", $demande_id);
$stmt->bindParam(":user_id", $user_id);
$stmt->execute();
$demande = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$demande) {
    header('Location: mes-demandes.php');
    exit();
}

$page_title = 'تفاصيل الطلب';
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
        
        .badge-info {
            background: #e3f2fd;
            color: #0288d1;
        }
        
        /* Grid System */
        .row {
            display: flex;
            flex-wrap: wrap;
            margin: 0 -15px;
        }
        
        .col-4 { flex: 0 0 33.333%; max-width: 33.333%; padding: 0 15px; }
        .col-6 { flex: 0 0 50%; max-width: 50%; padding: 0 15px; }
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
            
            .col-4, .col-6, .col-8 {
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
                <h1><i class="fas fa-file-alt"></i> تفاصيل الطلب</h1>
                <p>معلومات كاملة عن طلب التبرع الخاص بك</p>
            </div>

            <div class="row">
                <div class="col-8">
                    <!-- Don Information -->
                    <div class="card" style="margin-bottom: 25px;">
                        <div class="card-header">
                            <h3><i class="fas fa-gift"></i> معلومات التبرع</h3>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-4">
                                    <!-- Don Image -->
                                    <div style="height: 180px; background: #f8f9fa; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 50px; color: #aaa;">
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
                                </div>
                                <div class="col-8">
                                    <h3 style="margin-bottom: 15px;"><?php echo htmlspecialchars($demande['don_titre']); ?></h3>
                                    
                                    <div style="margin-bottom: 15px;">
                                        <span class="badge badge-primary"><?php echo $demande['categorie']; ?></span>
                                        <span class="badge badge-success"><?php echo $demande['etat']; ?></span>
                                        <span class="badge badge-info"><?php echo $demande['ville']; ?></span>
                                    </div>
                                    
                                    <p style="color: #666; line-height: 1.6; margin-bottom: 20px;">
                                        <?php echo nl2br(htmlspecialchars($demande['don_description'])); ?>
                                    </p>
                                    
                                    <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-top: 20px;">
                                        <h5><i class="fas fa-map-marker-alt"></i> مكان الاستلام</h5>
                                        <p style="margin: 5px 0 0; color: #666;"><?php echo htmlspecialchars($demande['adresse_retrait']); ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Your Request -->
                    <div class="card" style="margin-bottom: 25px;">
                        <div class="card-header">
                            <h3><i class="fas fa-file-contract"></i> طلبك</h3>
                        </div>
                        <div class="card-body">
                            <!-- Status Badge -->
                            <div style="text-align: center; margin-bottom: 25px;">
                                <?php 
                                $statusColors = [
                                    'en_attente' => ['color' => '#fdcb6e', 'icon' => '⏳', 'label' => 'في الانتظار'],
                                    'acceptee' => ['color' => '#00b894', 'icon' => '✅', 'label' => 'مقبول'],
                                    'refusee' => ['color' => '#ff7675', 'icon' => '❌', 'label' => 'مرفوض'],
                                    'annulee' => ['color' => '#aaa', 'icon' => '🚫', 'label' => 'ملغى']
                                ];
                                $status = $statusColors[$demande['statut']] ?? ['color' => '#aaa', 'icon' => '📝', 'label' => $demande['statut']];
                                ?>
                                <div style="display: inline-block; padding: 15px 30px; background: <?php echo $status['color']; ?>20; border-radius: 10px; border: 2px solid <?php echo $status['color']; ?>;">
                                    <div style="font-size: 30px; margin-bottom: 10px;"><?php echo $status['icon']; ?></div>
                                    <h4 style="margin: 0; color: <?php echo $status['color']; ?>;"><?php echo $status['label']; ?></h4>
                                </div>
                            </div>
                            
                            <!-- Your Message -->
                            <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                                <h5><i class="fas fa-comment"></i> رسالتك للمتبرع</h5>
                                <p style="margin: 15px 0 0; color: #666; line-height: 1.6;">
                                    <?php echo nl2br(htmlspecialchars($demande['message_demande'])); ?>
                                </p>
                            </div>
                            
                            <!-- Timeline -->
                            <div style="margin-top: 30px;">
                                <h5><i class="fas fa-history"></i> تتبع الطلب</h5>
                                <div style="margin-top: 15px;">
                                    <div style="display: flex; align-items: center; margin-bottom: 15px;">
                                        <div style="width: 30px; height: 30px; background: #00b894; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; margin-left: 15px;">
                                            <i class="fas fa-check"></i>
                                        </div>
                                        <div>
                                            <strong>تم إرسال الطلب</strong>
                                            <div style="color: #666; font-size: 14px;">
                                                <?php echo date('d/m/Y à H:i', strtotime($demande['created_at'])); ?>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div style="display: flex; align-items: center; margin-bottom: 15px;">
                                        <div style="width: 30px; height: 30px; background: <?php echo $demande['statut'] != 'en_attente' ? '#00b894' : '#ddd'; ?>; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; margin-left: 15px;">
                                            <i class="fas fa-user-check"></i>
                                        </div>
                                        <div>
                                            <strong>مراجعة المتبرع</strong>
                                            <div style="color: #666; font-size: 14px;">
                                                <?php echo $demande['statut'] != 'en_attente' ? 'تمت المراجعة' : 'في انتظار المراجعة'; ?>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div style="display: flex; align-items: center;">
                                        <div style="width: 30px; height: 30px; background: <?php echo $demande['statut'] == 'acceptee' ? '#00b894' : '#ddd'; ?>; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; margin-left: 15px;">
                                            <i class="fas fa-handshake"></i>
                                        </div>
                                        <div>
                                            <strong>اكتمال العملية</strong>
                                            <div style="color: #666; font-size: 14px;">
                                                <?php echo $demande['statut'] == 'acceptee' ? 'تم القبول' : 'في الانتظار'; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-4">
                    <!-- Donateur Information -->
                    <div class="card" style="margin-bottom: 25px;">
                        <div class="card-header">
                            <h3><i class="fas fa-user"></i> معلومات المتبرع</h3>
                        </div>
                        <div class="card-body">
                            <div style="text-align: center; margin-bottom: 20px;">
                                <div style="width: 80px; height: 80px; background: linear-gradient(135deg, #00b894, #00cec9); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 24px; margin: 0 auto 15px;">
                                    <?php echo strtoupper(substr($demande['donateur_nom'], 0, 1)); ?>
                                </div>
                                <h4 style="margin-bottom: 5px;"><?php echo htmlspecialchars($demande['donateur_nom']); ?></h4>
                                <small style="color: #666;">متبرع</small>
                            </div>
                            
                            <div style="background: #f8f9fa; padding: 15px; border-radius: 8px;">
                                <div style="margin-bottom: 10px;">
                                    <i class="fas fa-envelope" style="color: #666; margin-left: 10px;"></i>
                                    <span><?php echo htmlspecialchars($demande['donateur_email']); ?></span>
                                </div>
                                
                                <?php if(isset($demande['donateur_telephone']) && !empty($demande['donateur_telephone'])): ?>
                                <div>
                                    <i class="fas fa-phone" style="color: #666; margin-left: 10px;"></i>
                                    <span><?php echo htmlspecialchars($demande['donateur_telephone']); ?></span>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div style="text-align: center; margin-top: 20px;">
                                <?php if(isset($demande['donateur_id'])): ?>
                                <a href="messagerie.php?user_id=<?php echo $demande['donateur_id']; ?>" class="btn btn-primary" style="width: 100%;">
                                    <i class="fas fa-comments"></i> التواصل مع المتبرع
                                </a>
                                <?php else: ?>
                                <button class="btn btn-primary" style="width: 100%;" disabled>
                                    <i class="fas fa-comments"></i> التواصل مع المتبرع
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Actions -->
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-cogs"></i> الإجراءات</h3>
                        </div>
                        <div class="card-body">
                            <div style="display: flex; flex-direction: column; gap: 10px;">
                                <a href="mes-demandes.php" class="btn btn-outline">
                                    <i class="fas fa-arrow-right"></i> العودة إلى الطلبات
                                </a>
                                
                                <?php if($demande['statut'] == 'en_attente'): ?>
                                    <a href="mes-demandes.php?action=annuler&id=<?php echo $demande['id']; ?>" 
                                       class="btn btn-danger"
                                       onclick="return confirm('هل أنت متأكد من إلغاء هذا الطلب؟')">
                                        <i class="fas fa-times"></i> إلغاء الطلب
                                    </a>
                                <?php endif; ?>
                                
                                <a href="catalogue.php" class="btn btn-primary">
                                    <i class="fas fa-search"></i> تصفح تبرعات أخرى
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Don Publication Info -->
                    <div class="card" style="margin-top: 25px;">
                        <div class="card-body">
                            <h5><i class="fas fa-info-circle"></i> معلومات النشر</h5>
                            <div style="margin-top: 10px;">
                                <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                                    <span style="color: #666;">تاريخ النشر:</span>
                                    <span><?php echo date('d/m/Y', strtotime($demande['don_date_publication'])); ?></span>
                                </div>
                                <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                                    <span style="color: #666;">تاريخ الطلب:</span>
                                    <span><?php echo date('d/m/Y', strtotime($demande['created_at'])); ?></span>
                                </div>
                                <div style="display: flex; justify-content: space-between;">
                                    <span style="color: #666;">رقم الطلب:</span>
                                    <span style="font-family: monospace;">#<?php echo $demande['id']; ?></span>
                                </div>
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
<?php
// donateur/confirmer-commandes.php
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

$success = '';
$error = '';

// ========== تأكيد طلب ==========
if (isset($_GET['confirm']) && isset($_GET['demande_id'])) {
    $demande_id = $_GET['demande_id'];
    
    try {
        // Démarrer une transaction
        $db->beginTransaction();
        
        // 1. Récupérer les informations du don
        $query_check = "SELECT d.*, don.livraison_option, don.donateur_id, don.ville, don.titre as don_titre,
                               u.nom as beneficiaire_nom, u.telephone as beneficiaire_telephone, u.adresse as beneficiaire_adresse
                        FROM demandes d 
                        INNER JOIN dons don ON d.don_id = don.id 
                        INNER JOIN users u ON d.beneficiaire_id = u.id
                        WHERE d.id = :demande_id";
        $stmt_check = $db->prepare($query_check);
        $stmt_check->bindParam(':demande_id', $demande_id);
        $stmt_check->execute();
        $demande_info = $stmt_check->fetch(PDO::FETCH_ASSOC);
        
        if (!$demande_info) {
            throw new Exception("Demande non trouvée");
        }
        
        // 2. Vérifier que le donateur est bien le propriétaire
        if ($demande_info['donateur_id'] != $user_id) {
            throw new Exception("Vous n'êtes pas autorisé à confirmer cette demande");
        }
        
        // 3. Confirmer le statut de la demande
        $query = "UPDATE demandes SET statut = 'acceptee' WHERE id = :demande_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':demande_id', $demande_id);
        $stmt->execute();
        
        // 4. Mettre à jour le statut du don
        $query_don = "UPDATE dons SET statut = 'donne', is_deleted = 1, deleted_at = NOW() 
                     WHERE id = (SELECT don_id FROM demandes WHERE id = :demande_id)";
        $stmt_don = $db->prepare($query_don);
        $stmt_don->bindParam(':demande_id', $demande_id);
        $stmt_don->execute();
        
        // 5. Refuser les autres demandes
        $query_refuse = "UPDATE demandes SET statut = 'refusee' 
                         WHERE don_id = (SELECT don_id FROM demandes WHERE id = :demande_id) 
                         AND id != :demande_id AND statut = 'en_attente'";
        $stmt_refuse = $db->prepare($query_refuse);
        $stmt_refuse->bindParam(':demande_id', $demande_id);
        $stmt_refuse->execute();
        
        // 6. Créer une livraison
        if ($demande_info['livraison_option'] != 'none') {
            // Calculer les frais
            $frais = 0;
            if ($demande_info['livraison_option'] == 'fifty') {
                $frais = 5.00;
            } elseif ($demande_info['livraison_option'] == 'full') {
                $frais = 10.00;
            }
            
            // Insérer la livraison
            $query_livraison = "INSERT INTO livraisons 
                (demande_id, livreur_id, frais_livraison, statut, ville, created_at) 
                VALUES 
                (:demande_id, NULL, :frais, 'en_attente', :ville, NOW())";
            $stmt_livraison = $db->prepare($query_livraison);
            $stmt_livraison->bindParam(':demande_id', $demande_id);
            $stmt_livraison->bindParam(':frais', $frais);
            $stmt_livraison->bindParam(':ville', $demande_info['ville']);
            $stmt_livraison->execute();
        }
        
        $db->commit();
        $success = "✅ تم تأكيد الطلب بنجاح وتم إنشاء مهمة توصيل";
        
    } catch(Exception $e) {
        $db->rollBack();
        $error = "❌ خطأ في تأكيد الطلب: " . $e->getMessage();
    }
}

// ========== رفض طلب ==========
if (isset($_GET['refuse']) && isset($_GET['demande_id'])) {
    $demande_id = $_GET['refuse'];
    
    try {
        $query = "UPDATE demandes SET statut = 'refusee' WHERE id = :demande_id AND don_id IN (SELECT id FROM dons WHERE donateur_id = :user_id)";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':demande_id', $demande_id);
        $stmt->bindParam(':user_id', $user_id);
        
        if ($stmt->execute()) {
            $success = "✅ تم رفض الطلب بنجاح";
        }
    } catch(PDOException $e) {
        $error = "❌ خطأ في رفض الطلب: " . $e->getMessage();
    }
}

// ========== جلب الطلبات المنتظرة ==========
$query_pending = "
    SELECT d.*, 
           u.nom as beneficiaire_nom, 
           u.email as beneficiaire_email,
           u.telephone as beneficiaire_telephone,
           u.ville as beneficiaire_ville,
           don.titre as don_titre,
           don.description as don_description,
           don.categorie,
           don.etat,
           don.ville as don_ville,
           don.adresse_retrait,
           don.livraison_option,
           don.statut as don_statut
    FROM demandes d
    INNER JOIN users u ON d.beneficiaire_id = u.id
    INNER JOIN dons don ON d.don_id = don.id
    WHERE don.donateur_id = :user_id 
    AND d.statut = 'en_attente'
    ORDER BY d.created_at DESC
";

$stmt_pending = $db->prepare($query_pending);
$stmt_pending->bindParam(':user_id', $user_id);
$stmt_pending->execute();
$pending_demandes = $stmt_pending->fetchAll(PDO::FETCH_ASSOC);

// ========== جلب الطلبات المؤكدة ==========
$query_confirmed = "
    SELECT d.*, 
           u.nom as beneficiaire_nom, 
           don.titre as don_titre,
           don.categorie,
           l.id as livraison_id,
           l.statut as livraison_statut
    FROM demandes d
    INNER JOIN users u ON d.beneficiaire_id = u.id
    INNER JOIN dons don ON d.don_id = don.id
    LEFT JOIN livraisons l ON d.id = l.demande_id
    WHERE don.donateur_id = :user_id 
    AND d.statut = 'acceptee'
    ORDER BY d.created_at DESC
    LIMIT 5
";

$stmt_confirmed = $db->prepare($query_confirmed);
$stmt_confirmed->bindParam(':user_id', $user_id);
$stmt_confirmed->execute();
$confirmed_demandes = $stmt_confirmed->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'تأكيد الطلبات';
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
        
        .user-dropdown-item:last-child:hover {
            background: #ffebee;
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
            box-shadow: 0 5px 15px rgba(0, 184, 148, 0.3);
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #d63031, #ff7675);
            color: white;
        }
        
        .btn-danger:hover {
            background: linear-gradient(135deg, #c0392b, #e17055);
            box-shadow: 0 5px 15px rgba(214, 48, 49, 0.3);
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
        
        .row {
            display: flex;
            flex-wrap: wrap;
            margin: 0 -15px;
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
        
        .col-3 {
            flex: 0 0 25%;
            max-width: 25%;
            padding: 0 15px;
        }
        
        .col-5 {
            flex: 0 0 41.666%;
            max-width: 41.666%;
            padding: 0 15px;
        }
        
        @media (max-width: 992px) {
            .col-8, .col-4, .col-3, .col-5 {
                flex: 0 0 100%;
                max-width: 100%;
            }
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
        
        .demand-card {
            border: 1px solid #e0e0e0;
            border-radius: 12px;
            margin-bottom: 20px;
            overflow: hidden;
            transition: all 0.3s;
        }
        
        .demand-card:hover {
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        
        .demand-card.pending {
            border-right: 4px solid var(--warning);
        }
        
        .demand-card.confirmed {
            border-right: 4px solid var(--success);
        }
        
        .demand-header {
            padding: 15px 20px;
            background: #f8f9fa;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .demand-body {
            padding: 20px;
        }
        
        .beneficiary-avatar {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #f59e0b, #d97706);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 24px;
            margin: 0 auto 10px;
        }
        
        .info-item {
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .info-item i {
            width: 20px;
            color: var(--accent);
        }
        
        .message-box {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
            border-right: 3px solid var(--accent);
        }
        
        .action-buttons {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .stat-mini {
            text-align: center;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .stat-mini h4 {
            font-size: 24px;
            margin-bottom: 5px;
        }
        
        .stat-mini p {
            color: #666;
            font-size: 13px;
            margin: 0;
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
        
        .tips-list {
            padding-right: 20px;
            color: #666;
            line-height: 1.8;
        }
        
        .tips-list li {
            margin-bottom: 10px;
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
            
            .main-content {
                margin-top: 80px;
                padding: 15px;
            }
            
            .demand-header {
                flex-direction: column;
                gap: 10px;
                text-align: center;
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
            <li class="nav-item"><a href="publier-don.php" class="nav-link"><i class="fas fa-plus-circle"></i> نشر تبرع</a></li>
            <li class="nav-item"><a href="mes-dons.php" class="nav-link"><i class="fas fa-boxes"></i> تبرعاتي</a></li>
            <li class="nav-item"><a href="confirmer-commandes.php" class="nav-link active"><i class="fas fa-check-circle"></i> تأكيد الطلبات</a></li>
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
    
    <div class="main-content">
        <div class="container">
            <div class="welcome-section">
                <div class="welcome-content">
                    <div class="welcome-icon">
                        <i class="fas fa-check-circle"></i>                    
                    </div>
                    <div class="welcome-text">
                        <h1>تأكيد الطلبات</h1>
                        <p>راجع وقم بتأكيد أو رفض طلبات المستفيدين على تبرعاتك</p>
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

            <div class="row">
                <div class="col-8">
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-clock" style="color: var(--warning);"></i> الطلبات في الانتظار (<?php echo count($pending_demandes); ?>)</h3>
                        </div>
                        <div class="card-body">
                            <?php if(empty($pending_demandes)): ?>
                                <div class="empty-state">
                                    <i class="fas fa-check-circle" style="color: #00b894;"></i>
                                    <p>لا توجد طلبات في الانتظار</p>
                                </div>
                            <?php else: ?>
                                <?php foreach($pending_demandes as $demande): ?>
                                <div class="demand-card pending">
                                    <div class="demand-header">
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <i class="fas fa-clock" style="color: var(--warning);"></i>
                                            <span style="font-weight: 500;">طلب في الانتظار</span>
                                        </div>
                                        <span style="color: #666; font-size: 13px;">
                                            <i class="fas fa-calendar"></i> <?php echo date('d/m/Y H:i', strtotime($demande['created_at'])); ?>
                                        </span>
                                    </div>
                                    <div class="demand-body">
                                        <div class="row">
                                            <div class="col-3" style="text-align: center;">
                                                <div class="beneficiary-avatar">
                                                    <?php echo strtoupper(substr($demande['beneficiaire_nom'], 0, 1)); ?>
                                                </div>
                                                <h5><?php echo htmlspecialchars($demande['beneficiaire_nom']); ?></h5>
                                                <?php if($demande['beneficiaire_ville']): ?>
                                                    <div style="font-size: 13px;">
                                                        <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($demande['beneficiaire_ville']); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <div class="col-5">
                                                <h5><?php echo htmlspecialchars($demande['don_titre']); ?></h5>
                                                
                                                <div style="margin-bottom: 10px;">
                                                    <span class="badge badge-primary"><?php echo $demande['categorie']; ?></span>
                                                    <span class="badge badge-success"><?php echo $demande['etat']; ?></span>
                                                    <?php if($demande['livraison_option'] != 'none'): ?>
                                                        <span class="badge badge-info">توصيل</span>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <div class="message-box">
                                                    <strong>رسالة المستفيد:</strong>
                                                    <p><?php echo nl2br(htmlspecialchars($demande['message_demande'])); ?></p>
                                                </div>
                                                
                                                <div class="info-item">
                                                    <i class="fas fa-map-marker-alt"></i>
                                                    <span><strong>عنوان الاستلام:</strong> <?php echo htmlspecialchars($demande['adresse_retrait']); ?></span>
                                                </div>
                                            </div>
                                            
                                            <div class="col-4">
                                                <div class="action-buttons">
                                                    <a href="?confirm=1&demande_id=<?php echo $demande['id']; ?>" 
                                                       class="btn btn-success"
                                                       onclick="return confirm('هل أنت متأكد من تأكيد هذا الطلب؟')">
                                                        <i class="fas fa-check"></i> تأكيد الطلب
                                                    </a>
                                                    
                                                    <a href="?refuse=<?php echo $demande['id']; ?>" 
                                                       class="btn btn-danger"
                                                       onclick="return confirm('هل أنت متأكد من رفض هذا الطلب؟')">
                                                        <i class="fas fa-times"></i> رفض الطلب
                                                    </a>
                                                    
                                                    <a href="messagerie.php?user_id=<?php echo $demande['beneficiaire_id']; ?>" 
                                                       class="btn btn-outline">
                                                        <i class="fas fa-comments"></i> التواصل
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-4">
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-chart-bar"></i> إحصائيات</h3>
                        </div>
                        <div class="card-body">
                            <div class="stat-mini" style="margin-bottom: 15px;">
                                <h4 style="color: var(--warning);"><?php echo count($pending_demandes); ?></h4>
                                <p>طلبات في الانتظار</p>
                            </div>
                            
                            <div class="stat-mini">
                                <h4 style="color: var(--success);"><?php echo count($confirmed_demandes); ?></h4>
                                <p>طلبات مؤكدة</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-check-circle" style="color: var(--success);"></i> آخر الطلبات المؤكدة</h3>
                        </div>
                        <div class="card-body">
                            <?php if(empty($confirmed_demandes)): ?>
                                <div class="empty-state" style="padding: 20px;">
                                    <i class="fas fa-inbox"></i>
                                    <p>لا توجد طلبات مؤكدة</p>
                                </div>
                            <?php else: ?>
                                <?php foreach($confirmed_demandes as $demande): ?>
                                <div class="demand-card confirmed" style="margin-bottom: 15px;">
                                    <div style="padding: 15px;">
                                        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                                            <div style="width: 40px; height: 40px; background: linear-gradient(135deg, #00b894, #00cec9); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold;">
                                                <?php echo strtoupper(substr($demande['beneficiaire_nom'], 0, 1)); ?>
                                            </div>
                                            <div>
                                                <strong><?php echo htmlspecialchars($demande['beneficiaire_nom']); ?></strong>
                                                <div style="font-size: 12px;">
                                                    <?php echo date('d/m/Y', strtotime($demande['created_at'])); ?>
                                                </div>
                                            </div>
                                        </div>
                                        <div style="font-size: 13px;">
                                            <i class="fas fa-gift" style="color: var(--success);"></i> <?php echo htmlspecialchars($demande['don_titre']); ?>
                                        </div>
                                        <?php if(isset($demande['livraison_id'])): ?>
                                            <div style="margin-top: 8px;">
                                                <span class="badge badge-info">تم إنشاء مهمة توصيل</span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
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
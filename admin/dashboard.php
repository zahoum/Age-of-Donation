<?php
// admin/dashboard.php
session_start();

// Check if user is admin
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: ../index.php');
    exit();   
}

require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

// Get statistics
$stats_query = "
    SELECT 
        (SELECT COUNT(*) FROM users WHERE type != 'admin') as total_utilisateurs,
        (SELECT COUNT(*) FROM dons) as total_dons,
        (SELECT COUNT(*) FROM demandes) as total_demandes,
        (SELECT COUNT(*) FROM livreurs WHERE statut = 'actif') as livreurs_actifs,
        (SELECT COUNT(*) FROM dons WHERE statut = 'disponible') as dons_disponibles,
        (SELECT COUNT(*) FROM demandes WHERE statut = 'en_attente') as demandes_attente,
        (SELECT COUNT(*) FROM contact_messages WHERE status = 'new') as new_messages,
        (SELECT SUM(montant) FROM dons WHERE statut = 'completé') as total_dons_montant,
        (SELECT COUNT(*) FROM users WHERE DATE(created_at) = CURDATE()) as nouveaux_utilisateurs_today
";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Get recent of donstion
$dons_query = "
    SELECT d.*, u.nom as donateur_nom, u.email as donateur_email 
    FROM dons d 
    INNER JOIN users u ON d.donateur_id = u.id 
    ORDER BY d.created_at DESC 
    LIMIT 5
";
$dons_stmt = $db->prepare($dons_query);
$dons_stmt->execute();
$dons_recent = $dons_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent requests
$demandes_query = "
    SELECT de.*, d.titre as don_titre, u.nom as beneficiaire_nom, u.email as beneficiaire_email 
    FROM demandes de 
    INNER JOIN dons d ON de.don_id = d.id 
    INNER JOIN users u ON de.beneficiaire_id = u.id 
    ORDER BY de.created_at DESC 
    LIMIT 5
";
$demandes_stmt = $db->prepare($demandes_query);
$demandes_stmt->execute();
$demandes_recent = $demandes_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent messages
$messages_query = "
    SELECT * FROM contact_messages 
    ORDER BY created_at DESC 
    LIMIT 5
";
$messages_stmt = $db->prepare($messages_query);
$messages_stmt->execute();
$messages_recent = $messages_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent users
$users_query = "
    SELECT id, nom, email, type, created_at 
    FROM users 
    WHERE type != 'admin' 
    ORDER BY created_at DESC 
    LIMIT 5
";
$users_stmt = $db->prepare($users_query);
$users_stmt->execute();
$users_recent = $users_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get donations by status for chart
$dons_status_query = "
    SELECT 
        SUM(CASE WHEN statut = 'disponible' THEN 1 ELSE 0 END) as disponible,
        SUM(CASE WHEN statut = 'réservé' THEN 1 ELSE 0 END) as reserve,
        SUM(CASE WHEN statut = 'completé' THEN 1 ELSE 0 END) as complete,
        SUM(CASE WHEN statut = 'annulé' THEN 1 ELSE 0 END) as annule
    FROM dons
";
$dons_status_stmt = $db->prepare($dons_status_query);
$dons_status_stmt->execute();
$dons_status = $dons_status_stmt->fetch(PDO::FETCH_ASSOC);

// Helper function for status badges
function getStatusBadge($status) {
    $badges = [
        'disponible' => '<span class="badge bg-success">متاح</span>',
        'réservé' => '<span class="badge bg-warning text-dark">محجوز</span>',
        'completé' => '<span class="badge bg-primary">مكتمل</span>',
        'annulé' => '<span class="badge bg-danger">ملغي</span>',
        'en_attente' => '<span class="badge bg-info">في الانتظار</span>',
        'accepté' => '<span class="badge bg-success">مقبول</span>',
        'refusé' => '<span class="badge bg-danger">مرفوض</span>',
        'new' => '<span class="badge bg-danger">جديد</span>',
        'read' => '<span class="badge bg-info">مقروء</span>',
        'replied' => '<span class="badge bg-success">تم الرد</span>'
    ];
    return $badges[$status] ?? '<span class="badge bg-secondary">غير معروف</span>';
}

// Format date
function formatDate($date) {
    return date('Y/m/d H:i', strtotime($date));
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة التحكم - Age of Donnation</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary: #667eea;
            --secondary: #764ba2;
            --success: #28a745;
            --info: #17a2b8;
            --warning: #ffc107;
            --danger: #dc3545;
        }
        
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            position: fixed;
            width: 250px;
            z-index: 1000;
        }
        
        .sidebar .logo {
            color: white;
            font-size: 1.5rem;
            font-weight: bold;
            text-decoration: none;
            padding: 20px;
            display: block;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .sidebar .nav-link {
            color: rgba(255,255,255,.8);
            padding: 12px 20px;
            margin: 5px 15px;
            border-radius: 10px;
            transition: all 0.3s;
            display: flex;
            align-items: center;
        }
        
        .sidebar .nav-link i {
            width: 25px;
            margin-left: 10px;
        }
        
        .sidebar .nav-link:hover, 
        .sidebar .nav-link.active {
            color: white;
            background: rgba(255,255,255,.1);
            text-decoration: none;
        }
        
        .sidebar .badge {
            margin-right: auto;
            font-size: 0.7em;
        }
        
        .main-content {
            margin-right: 250px;
            padding: 20px;
        }
        
        .navbar {
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,.1);
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,.05);
            transition: transform 0.3s, box-shadow 0.3s;
            border: 1px solid #f1f1f1;
            height: 100%;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,.1);
        }
        
        .stat-card .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 15px;
        }
        
        .stat-card .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #333;
            line-height: 1;
        }
        
        .stat-card .stat-label {
            color: #666;
            font-size: 0.9rem;
            margin-top: 5px;
        }
        
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,.05);
            margin-bottom: 20px;
        }
        
        .card-header {
            background: white;
            border-bottom: 1px solid #f1f1f1;
            padding: 15px 20px;
            border-radius: 15px 15px 0 0 !important;
            font-weight: bold;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .card-body {
            padding: 20px;
        }
        
        .list-item {
            padding: 15px;
            border-bottom: 1px solid #f1f1f1;
            transition: background 0.3s;
        }
        
        .list-item:hover {
            background: #f8f9fa;
        }
        
        .list-item:last-child {
            border-bottom: none;
        }
        
        .badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8em;
        }
        
        .chart-container {
            position: relative;
            height: 300px;
        }
        
        .welcome-card {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 20px;
        }
        
        .welcome-card h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
        }
        
        .welcome-card p {
            opacity: 0.9;
            margin-bottom: 0;
        }
        
        .time-display {
            font-size: 1rem;
            background: rgba(255,255,255,0.1);
            padding: 8px 15px;
            border-radius: 20px;
            display: inline-block;
            margin-top: 10px;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                width: 70px;
            }
            
            .sidebar .logo span,
            .sidebar .nav-link span:not(.badge) {
                display: none;
            }
            
            .sidebar .nav-link i {
                margin-left: 0;
            }
            
            .main-content {
                margin-right: 70px;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <a href="../index.php" class="logo">
            <i class="fas fa-hand-holding-heart me-2"></i>
            <span>Age of Donnation</span>
        </a>
        
        <div class="nav flex-column mt-3">
            <a class="nav-link active" href="dashboard.php">
                <i class="fas fa-tachometer-alt"></i>
                <span>لوحة التحكم</span>
            </a>
            
            <a class="nav-link" href="utilisateurs.php">
                <i class="fas fa-users"></i>
                <span>المستخدمين</span>
            </a>
            
            <a class="nav-link" href="dons.php">
                <i class="fas fa-gift"></i>
                <span>التبرعات</span>
            </a>
            
            <a class="nav-link" href="demandes.php">
                <i class="fas fa-hand-paper"></i>
                <span>الطلبات</span>
            </a>
            
            <a class="nav-link" href="livreurs.php">
                <i class="fas fa-truck"></i>
                <span>المساعدين</span>
            </a>
            
            <a class="nav-link" href="messages.php">
                <i class="fas fa-envelope"></i>
                <span>الرسائل</span>
                <?php if(($stats['new_messages'] ?? 0) > 0): ?>
                    <span class="badge bg-danger"><?php echo $stats['new_messages']; ?></span>
                <?php endif; ?>
            </a>
            
            <a class="nav-link" href="statistiques.php">
                <i class="fas fa-chart-bar"></i>
                <span>الإحصائيات</span>
            </a>
            
            <div class="mt-5"></div>
            
            <a class="nav-link" href="../index.php">
                <i class="fas fa-globe"></i>
                <span>الموقع العام</span>
            </a>
            
            <a class="nav-link" href="../auth/logout.php">
                <i class="fas fa-sign-out-alt"></i>
                <span>تسجيل الخروج</span>
            </a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Bar -->
        <nav class="navbar">
            <div class="container-fluid">
                <div class="d-flex align-items-center">
                    <h4 class="mb-0">مرحباً، <?php echo $_SESSION['user_nom'] ?? 'المسؤول'; ?>!</h4>
                </div>
                <div class="d-flex align-items-center">
                    <div class="me-3 text-end">
                        <div id="current-time" class="fw-bold"></div>
                        <small class="text-muted"><?php echo date('Y/m/d'); ?></small>
                    </div>
                    <div class="dropdown">
                        <button class="btn btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle me-2"></i>
                            الحساب
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="#"><i class="fas fa-user me-2"></i>ملفي</a></li>
                            <li><a class="dropdown-item" href="#"><i class="fas fa-cog me-2"></i>الإعدادات</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="../auth/logout.php"><i class="fas fa-sign-out-alt me-2"></i>تسجيل الخروج</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Welcome Card -->
        <div class="welcome-card">
            <h1>لوحة التحكم الإدارية</h1>
            <p>مرحباً بك في نظام إدارة Age of Donnation. هنا يمكنك إدارة جميع جوانب المنصة.</p>
            <div class="time-display">
                <i class="fas fa-clock me-2"></i>
                <span id="welcome-time"></span>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row g-4 mb-4">
            <div class="col-xl-3 col-lg-6">
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(102, 126, 234, 0.1); color: var(--primary);">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-number"><?php echo number_format($stats['total_utilisateurs'] ?? 0); ?></div>
                    <div class="stat-label">إجمالي المستخدمين</div>
                    <small class="text-success">
                        <i class="fas fa-arrow-up me-1"></i>
                        <?php echo $stats['nouveaux_utilisateurs_today'] ?? 0; ?> جديد اليوم
                    </small>
                </div>
            </div>
            
            <div class="col-xl-3 col-lg-6">
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(40, 167, 69, 0.1); color: var(--success);">
                        <i class="fas fa-gift"></i>
                    </div>
                    <div class="stat-number"><?php echo number_format($stats['total_dons'] ?? 0); ?></div>
                    <div class="stat-label">إجمالي التبرعات</div>
                    <small class="text-primary">
                        <i class="fas fa-money-bill-wave me-1"></i>
                        <?php echo number_format($stats['total_dons_montant'] ?? 0, 2); ?> درهم
                    </small>
                </div>
            </div>
            
            <div class="col-xl-3 col-lg-6">
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(220, 53, 69, 0.1); color: var(--danger);">
                        <i class="fas fa-envelope"></i>
                    </div>
                    <div class="stat-number"><?php echo $stats['new_messages'] ?? 0; ?></div>
                    <div class="stat-label">رسائل جديدة</div>
                    <small class="text-danger">
                        <i class="fas fa-exclamation-circle me-1"></i>
                        بحاجة إلى الرد
                    </small>
                </div>
            </div>
            
            <div class="col-xl-3 col-lg-6">
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(255, 193, 7, 0.1); color: var(--warning);">
                        <i class="fas fa-hand-paper"></i>
                    </div>
                    <div class="stat-number"><?php echo $stats['demandes_attente'] ?? 0; ?></div>
                    <div class="stat-label">طلبات في الانتظار</div>
                    <small class="text-info">
                        <i class="fas fa-clock me-1"></i>
                        تحتاج إلى مراجعة
                    </small>
                </div>
            </div>
        </div>

        <!-- Second Row Stats -->
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['dons_disponibles'] ?? 0; ?></div>
                    <div class="stat-label">تبرعات متاحة</div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['total_demandes'] ?? 0; ?></div>
                    <div class="stat-label">إجمالي الطلبات</div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['livreurs_actifs'] ?? 0; ?></div>
                    <div class="stat-label">مساعدين نشطين</div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['total_dons'] ?? 0; ?></div>
                    <div class="stat-label">إجمالي التبرعات</div>
                </div>
            </div>
        </div>

        <!-- Charts and Lists -->
        <div class="row g-4">
            <!-- Donations Chart -->
            <div class="col-xl-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i>حالة التبرعات</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="donationsChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="col-xl-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-bolt me-2"></i>إجراءات سريعة</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-3">
                            <a href="dons.php?action=add" class="btn btn-primary">
                                <i class="fas fa-plus me-2"></i>إضافة تبرع جديد
                            </a>
                            <a href="messages.php" class="btn btn-danger">
                                <i class="fas fa-envelope me-2"></i>عرض الرسائل الجديدة
                            </a>
                            <a href="demandes.php?status=en_attente" class="btn btn-warning">
                                <i class="fas fa-hand-paper me-2"></i>مراجعة الطلبات
                            </a>
                            <a href="statistiques.php" class="btn btn-info">
                                <i class="fas fa-chart-bar me-2"></i>عرض التقارير
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Data -->
        <div class="row g-4 mt-2">
            <!-- Recent Donations -->
            <div class="col-xl-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-gift me-2"></i>آخر التبرعات</h5>
                        <a href="dons.php" class="btn btn-sm btn-outline-primary">عرض الكل</a>
                    </div>
                    <div class="card-body">
                        <?php if(empty($dons_recent)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-gift fa-2x text-muted mb-3"></i>
                                <p class="text-muted">لا توجد تبرعات حديثة</p>
                            </div>
                        <?php else: ?>
                            <div style="max-height: 300px; overflow-y: auto;">
                                <?php foreach($dons_recent as $don): ?>
                                    <div class="list-item">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h6 class="mb-1"><?php echo htmlspecialchars($don['titre']); ?></h6>
                                                <small class="text-muted">
                                                    <i class="fas fa-user me-1"></i>
                                                    <?php echo htmlspecialchars($don['donateur_nom']); ?>
                                                </small>
                                            </div>
                                            <div class="text-end">
                                                <?php echo getStatusBadge($don['statut']); ?>
                                                <div class="text-muted small mt-1">
                                                    <?php echo formatDate($don['created_at']); ?>
                                                </div>
                                            </div>
                                        </div>
                                        <?php if(!empty($don['description'])): ?>
                                            <p class="mb-0 mt-2 small"><?php echo substr(htmlspecialchars($don['description']), 0, 100); ?>...</p>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Recent Messages -->
            <div class="col-xl-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-envelope me-2"></i>آخر الرسائل</h5>
                        <a href="messages.php" class="btn btn-sm btn-outline-primary">عرض الكل</a>
                    </div>
                    <div class="card-body">
                        <?php if(empty($messages_recent)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-envelope fa-2x text-muted mb-3"></i>
                                <p class="text-muted">لا توجد رسائل حديثة</p>
                            </div>
                        <?php else: ?>
                            <div style="max-height: 300px; overflow-y: auto;">
                                <?php foreach($messages_recent as $message): ?>
                                    <div class="list-item">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h6 class="mb-1"><?php echo htmlspecialchars($message['subject']); ?></h6>
                                                <small class="text-muted">
                                                    <i class="fas fa-user me-1"></i>
                                                    <?php echo htmlspecialchars($message['name']); ?>
                                                    <i class="fas fa-envelope ms-2 me-1"></i>
                                                    <?php echo htmlspecialchars($message['email']); ?>
                                                </small>
                                            </div>
                                            <div class="text-end">
                                                <?php echo getStatusBadge($message['status']); ?>
                                                <div class="text-muted small mt-1">
                                                    <?php echo formatDate($message['created_at']); ?>
                                                </div>
                                            </div>
                                        </div>
                                        <p class="mb-0 mt-2 small"><?php echo substr(htmlspecialchars($message['message']), 0, 100); ?>...</p>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Recent Requests -->
            <div class="col-xl-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-hand-paper me-2"></i>آخر الطلبات</h5>
                        <a href="demandes.php" class="btn btn-sm btn-outline-primary">عرض الكل</a>
                    </div>
                    <div class="card-body">
                        <?php if(empty($demandes_recent)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-hand-paper fa-2x text-muted mb-3"></i>
                                <p class="text-muted">لا توجد طلبات حديثة</p>
                            </div>
                        <?php else: ?>
                            <div style="max-height: 300px; overflow-y: auto;">
                                <?php foreach($demandes_recent as $demande): ?>
                                    <div class="list-item">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h6 class="mb-1"><?php echo htmlspecialchars($demande['don_titre']); ?></h6>
                                                <small class="text-muted">
                                                    <i class="fas fa-user me-1"></i>
                                                    <?php echo htmlspecialchars($demande['beneficiaire_nom']); ?>
                                                </small>
                                            </div>
                                            <div class="text-end">
                                                <?php echo getStatusBadge($demande['statut']); ?>
                                                <div class="text-muted small mt-1">
                                                    <?php echo formatDate($demande['created_at']); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Recent Users -->
            <div class="col-xl-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-users me-2"></i>آخر المستخدمين</h5>
                        <a href="utilisateurs.php" class="btn btn-sm btn-outline-primary">عرض الكل</a>
                    </div>
                    <div class="card-body">
                        <?php if(empty($users_recent)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-users fa-2x text-muted mb-3"></i>
                                <p class="text-muted">لا توجد مستخدمين جدد</p>
                            </div>
                        <?php else: ?>
                            <div style="max-height: 300px; overflow-y: auto;">
                                <?php foreach($users_recent as $user): ?>
                                    <div class="list-item">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h6 class="mb-1"><?php echo htmlspecialchars($user['nom']); ?></h6>
                                                <small class="text-muted">
                                                    <i class="fas fa-envelope me-1"></i>
                                                    <?php echo htmlspecialchars($user['email']); ?>
                                                </small>
                                            </div>
                                            <div class="text-end">
                                                <span class="badge bg-info"><?php echo $user['type']; ?></span>
                                                <div class="text-muted small mt-1">
                                                    <?php echo formatDate($user['created_at']); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Update time every second
    function updateTime() {
        const now = new Date();
        const timeString = now.toLocaleTimeString('ar-SA');
        const dateString = now.toLocaleDateString('ar-SA', {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
        
        document.getElementById('current-time').textContent = timeString;
        document.getElementById('welcome-time').textContent = dateString + ' | ' + timeString;
    }
    
    // Update time immediately and every second
    updateTime();
    setInterval(updateTime, 1000);
    
    // Donations Chart
    const ctx = document.getElementById('donationsChart').getContext('2d');
    const donationsChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['متاحة', 'محجوزة', 'مكتملة', 'ملغاة'],
            datasets: [{
                data: [
                    <?php echo $dons_status['disponible'] ?? 0; ?>,
                    <?php echo $dons_status['reserve'] ?? 0; ?>,
                    <?php echo $dons_status['complete'] ?? 0; ?>,
                    <?php echo $dons_status['annule'] ?? 0; ?>
                ],
                backgroundColor: [
                    '#28a745',
                    '#ffc107',
                    '#007bff',
                    '#dc3545'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    rtl: true
                },
                tooltip: {
                    rtl: true,
                    callbacks: {
                        label: function(context) {
                            let label = context.label || '';
                            if (label) {
                                label += ': ';
                            }
                            label += context.raw;
                            return label;
                        }
                    }
                }
            }
        }
    });
    
    // Auto refresh new messages count every 30 seconds
    function refreshMessageCount() {
        fetch('get_new_messages_count.php')
            .then(response => response.json())
            .then(data => {
                const badge = document.querySelector('a[href="messages.php"] .badge');
                if (data.new_count > 0) {
                    if (badge) {
                        badge.textContent = data.new_count;
                        badge.classList.remove('d-none');
                    } else {
                        const link = document.querySelector('a[href="messages.php"]');
                        link.innerHTML += '<span class="badge bg-danger">' + data.new_count + '</span>';
                    }
                } else if (badge) {
                    badge.classList.add('d-none');
                }
            })
            .catch(error => console.error('Error:', error));
    }
    
    // Refresh message count every 30 seconds
    setInterval(refreshMessageCount, 30000);
    </script>
</body>
</html>
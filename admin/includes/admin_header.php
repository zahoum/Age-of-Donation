<?php
session_start();
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}
require_once '../config/database.php';
$database = new Database();
$db = $database->getConnection();

// Get unread messages count
$unread_query = "SELECT COUNT(*) as count FROM contact_messages WHERE status = 'new'";
$unread_stmt = $db->prepare($unread_query);
$unread_stmt->execute();
$unread_count = $unread_stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Get pending requests count
$pending_query = "SELECT COUNT(*) as count FROM demandes WHERE statut = 'en_attente'";
$pending_stmt = $db->prepare($pending_query);
$pending_stmt->execute();
$pending_count = $pending_stmt->fetch(PDO::FETCH_ASSOC)['count'];
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة تحكم المسؤول - Age of Donnation</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * {
            font-family: 'Cairo', 'Segoe UI', sans-serif;
        }
        
        body {
            background-color: #f0f2f5;
        }
        
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            color: white;
            position: fixed;
            width: 280px;
            z-index: 1000;
            transition: all 0.3s;
            box-shadow: 2px 0 20px rgba(0,0,0,0.1);
        }
        
        .sidebar .logo {
            color: white;
            font-size: 1.5rem;
            font-weight: bold;
            text-decoration: none;
            padding: 25px 20px;
            display: block;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            background: rgba(0,0,0,0.2);
        }
        
        .sidebar .logo i {
            margin-left: 10px;
            color: #ff6b6b;
        }
        
        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 12px 25px;
            margin: 5px 15px;
            border-radius: 12px;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            font-weight: 500;
        }
        
        .sidebar .nav-link i {
            width: 30px;
            font-size: 1.2rem;
            margin-left: 12px;
        }
        
        .sidebar .nav-link:hover, 
        .sidebar .nav-link.active {
            color: white;
            background: linear-gradient(135deg, #ff6b6b, #ee5a24);
            transform: translateX(-5px);
        }
        
        .sidebar .nav-link .badge {
            margin-right: auto;
            font-size: 0.7rem;
            padding: 4px 8px;
        }
        
        .main-content {
            margin-right: 280px;
            padding: 20px;
            min-height: 100vh;
        }
        
        .top-navbar {
            background: white;
            border-radius: 15px;
            padding: 15px 25px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .stat-card {
            background: white;
            border-radius: 20px;
            padding: 20px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            transition: all 0.3s;
            border: none;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            margin-bottom: 15px;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 800;
            color: #2d3436;
            line-height: 1.2;
        }
        
        .stat-label {
            color: #636e72;
            font-size: 0.85rem;
            margin-top: 5px;
            font-weight: 500;
        }
        
        .card {
            border: none;
            border-radius: 20px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            margin-bottom: 25px;
            background: white;
        }
        
        .card-header {
            background: white;
            border-bottom: 1px solid #e9ecef;
            padding: 18px 25px;
            border-radius: 20px 20px 0 0 !important;
            font-weight: 700;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .card-header h5 {
            margin: 0;
            font-weight: 700;
            color: #2d3436;
        }
        
        .card-body {
            padding: 25px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #ff6b6b, #ee5a24);
            border: none;
            padding: 10px 25px;
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(238, 90, 36, 0.3);
        }
        
        .btn-outline-primary {
            border: 2px solid #ff6b6b;
            color: #ff6b6b;
            border-radius: 12px;
            font-weight: 600;
        }
        
        .btn-outline-primary:hover {
            background: linear-gradient(135deg, #ff6b6b, #ee5a24);
            border-color: transparent;
        }
        
        .badge {
            padding: 6px 12px;
            border-radius: 25px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .badge-success {
            background: linear-gradient(135deg, #00b894, #00cec9);
        }
        
        .badge-warning {
            background: linear-gradient(135deg, #fdcb6e, #f39c12);
            color: #2d3436;
        }
        
        .badge-danger {
            background: linear-gradient(135deg, #ff7675, #d63031);
        }
        
        .badge-info {
            background: linear-gradient(135deg, #74b9ff, #0984e3);
        }
        
        .badge-secondary {
            background: linear-gradient(135deg, #b2bec3, #636e72);
        }
        
        .table {
            margin-bottom: 0;
        }
        
        .table thead th {
            background: #f8f9fa;
            border-bottom: 2px solid #e9ecef;
            color: #2d3436;
            font-weight: 700;
            padding: 15px;
        }
        
        .table tbody td {
            padding: 15px;
            vertical-align: middle;
            color: #636e72;
        }
        
        .pagination {
            margin-top: 20px;
        }
        
        .pagination .page-link {
            border-radius: 10px;
            margin: 0 3px;
            color: #ff6b6b;
            border: none;
            padding: 8px 15px;
        }
        
        .pagination .page-item.active .page-link {
            background: linear-gradient(135deg, #ff6b6b, #ee5a24);
            color: white;
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
        
        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        
        ::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, #ff6b6b, #ee5a24);
            border-radius: 10px;
        }
        
        .welcome-banner {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 20px;
            padding: 30px;
            color: white;
            margin-bottom: 25px;
        }
        
        .action-buttons .btn {
            margin: 0 3px;
            padding: 5px 12px;
            font-size: 0.8rem;
            border-radius: 10px;
        }
        
        .filter-section {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <a href="dashboard.php" class="logo">
            <i class="fas fa-hand-holding-heart"></i>
            <span>Age of Donnation</span>
        </a>
        
        <div class="nav flex-column mt-3">
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>" href="dashboard.php">
                <i class="fas fa-tachometer-alt"></i>
                <span>لوحة التحكم</span>
            </a>
            
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'utilisateurs.php' ? 'active' : ''; ?>" href="utilisateurs.php">
                <i class="fas fa-users"></i>
                <span>المستخدمين</span>
            </a>
            
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dons.php' ? 'active' : ''; ?>" href="dons.php">
                <i class="fas fa-gift"></i>
                <span>التبرعات</span>
            </a>
            
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'demandes.php' ? 'active' : ''; ?>" href="demandes.php">
                <i class="fas fa-hand-paper"></i>
                <span>الطلبات</span>
                <?php if($pending_count > 0): ?>
                    <span class="badge bg-danger"><?php echo $pending_count; ?></span>
                <?php endif; ?>
            </a>
            
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'livreurs.php' ? 'active' : ''; ?>" href="livreurs.php">
                <i class="fas fa-truck"></i>
                <span>المساعدين</span>
            </a>
            
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'messages.php' ? 'active' : ''; ?>" href="messages.php">
                <i class="fas fa-envelope"></i>
                <span>الرسائل</span>
                <?php if($unread_count > 0): ?>
                    <span class="badge bg-danger"><?php echo $unread_count; ?></span>
                <?php endif; ?>
            </a>
            
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'statistiques.php' ? 'active' : ''; ?>" href="statistiques.php">
                <i class="fas fa-chart-bar"></i>
                <span>الإحصائيات</span>
            </a>
            
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'parametres.php' ? 'active' : ''; ?>" href="parametres.php">
                <i class="fas fa-cog"></i>
                <span>الإعدادات</span>
            </a>
            
            <div class="mt-4"></div>
            
            <a class="nav-link" href="../index.php" target="_blank">
                <i class="fas fa-globe"></i>
                <span>الموقع العام</span>
            </a>
            
            <a class="nav-link text-danger" href="../auth/logout.php">
                <i class="fas fa-sign-out-alt"></i>
                <span>تسجيل الخروج</span>
            </a>
        </div>
    </div>

    <div class="main-content">
        <div class="top-navbar">
            <div class="d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center">
                    <i class="fas fa-user-shield me-2" style="color: #ff6b6b; font-size: 1.5rem;"></i>
                    <div>
                        <h5 class="mb-0">مرحباً، <?php echo htmlspecialchars($_SESSION['user_nom'] ?? 'المسؤول'); ?></h5>
                        <small class="text-muted">لديك صلاحيات إدارية كاملة</small>
                    </div>
                </div>
                <div class="d-flex align-items-center">
                    <div class="me-4 text-end">
                        <div id="current-time" class="fw-bold" style="color: #ff6b6b;"></div>
                        <small class="text-muted"><?php echo date('Y/m/d'); ?></small>
                    </div>
                    <div class="dropdown">
                        <button class="btn btn-light rounded-circle" type="button" data-bs-toggle="dropdown" style="width: 45px; height: 45px;">
                            <i class="fas fa-user-circle fa-2x"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="#"><i class="fas fa-user me-2"></i>ملفي الشخصي</a></li>
                            <li><a class="dropdown-item" href="parametres.php"><i class="fas fa-cog me-2"></i>الإعدادات</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="../auth/logout.php"><i class="fas fa-sign-out-alt me-2"></i>تسجيل الخروج</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
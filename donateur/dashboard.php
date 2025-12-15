<?php
// donateur/dashboard.php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'donateur') {
    header('Location: ../auth/login.php');
    exit();
}

require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$user_id = $_SESSION['user_id'];

// Statistiques du donateur
$stats_query = "
    SELECT 
        COUNT(*) as total_dons,
        SUM(CASE WHEN statut = 'donne' THEN 1 ELSE 0 END) as dons_termines,
        SUM(CASE WHEN statut = 'disponible' THEN 1 ELSE 0 END) as dons_actifs,
        SUM(CASE WHEN statut = 'reserve' THEN 1 ELSE 0 END) as dons_reserves
    FROM dons 
    WHERE donateur_id = :user_id
";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->bindParam(":user_id", $user_id);
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Dons récents
$dons_query = "SELECT * FROM dons WHERE donateur_id = :user_id ORDER BY created_at DESC LIMIT 5";
$dons_stmt = $db->prepare($dons_query);
$dons_stmt->bindParam(":user_id", $user_id);
$dons_stmt->execute();
$dons_recent = $dons_stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'لوحة التحكم - متبرع';
require_once '../includes/header.php';
?>

<!-- Page Header -->
<div class="page-header">
    <h1><i class="fas fa-tachometer-alt"></i> لوحة التحكم</h1>
    <p>مرحبًا بك <?php echo htmlspecialchars($_SESSION['user_nom']); ?>، شكرًا لمساهمتك في الخير</p>
</div>

<!-- Stats -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(135deg, #74b9ff, #0984e3);">
            <i class="fas fa-gift"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $stats['total_dons'] ?? 0; ?></h3>
            <p>إجمالي التبرعات</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(135deg, #00b894, #00cec9);">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $stats['dons_actifs'] ?? 0; ?></h3>
            <p>تبرعات نشطة</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(135deg, #fdcb6e, #e17055);">
            <i class="fas fa-clock"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $stats['dons_reserves'] ?? 0; ?></h3>
            <p>محجوزة</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(135deg, #a29bfe, #6c5ce7);">
            <i class="fas fa-heart"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $stats['dons_termines'] ?? 0; ?></h3>
            <p>تبرعات مكتملة</p>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="row" style="margin-bottom: 30px;">
    <div class="col-3">
        <a href="publier-don.php" class="card" style="text-decoration: none; color: inherit; text-align: center; padding: 25px;">
            <div style="font-size: 40px; color: var(--accent); margin-bottom: 15px;">
                <i class="fas fa-plus-circle"></i>
            </div>
            <h4>نشر تبرع</h4>
            <p style="color: var(--secondary); font-size: 14px;">انشر شيئًا للتبرع به</p>
        </a>
    </div>
    <div class="col-3">
        <a href="mes-dons.php" class="card" style="text-decoration: none; color: inherit; text-align: center; padding: 25px;">
            <div style="font-size: 40px; color: var(--success); margin-bottom: 15px;">
                <i class="fas fa-boxes"></i>
            </div>
            <h4>تبرعاتي</h4>
            <p style="color: var(--secondary); font-size: 14px;">إدارة تبرعاتك</p>
        </a>
    </div>
    <div class="col-3">
        <a href="messagerie.php" class="card" style="text-decoration: none; color: inherit; text-align: center; padding: 25px;">
            <div style="font-size: 40px; color: var(--warning); margin-bottom: 15px;">
                <i class="fas fa-comments"></i>
            </div>
            <h4>المراسلة</h4>
            <p style="color: var(--secondary); font-size: 14px;">تواصل مع المستفيدين</p>
        </a>
    </div>
    <div class="col-3">
        <a href="../auth/logout.php" class="card" style="text-decoration: none; color: inherit; text-align: center; padding: 25px;">
            <div style="font-size: 40px; color: var(--danger); margin-bottom: 15px;">
                <i class="fas fa-cog"></i>
            </div>
            <h4>الإعدادات</h4>
            <p style="color: var(--secondary); font-size: 14px;">إدارة حسابك</p>
        </a>
    </div>
</div>

<!-- Recent Dons -->
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-history"></i> تبرعاتك الأخيرة</h3>
    </div>
    <div class="card-body">
        <?php if(empty($dons_recent)): ?>
            <div style="text-align: center; padding: 40px; color: #666;">
                <i class="fas fa-gift" style="font-size: 60px; margin-bottom: 20px; opacity: 0.3;"></i>
                <p>لم تقم بنشر أي تبرعات بعد</p>
                <a href="publier-don.php" class="btn btn-primary" style="margin-top: 15px;">
                    <i class="fas fa-plus"></i> نشر أول تبرع
                </a>
            </div>
        <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>العنوان</th>
                        <th>الفئة</th>
                        <th>الحالة</th>
                        <th>الوضع</th>
                        <th>التاريخ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($dons_recent as $don): ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($don['titre']); ?></strong>
                        </td>
                        <td>
                            <span class="badge badge-primary"><?php echo $don['categorie']; ?></span>
                        </td>
                        <td><?php echo $don['etat']; ?></td>
                        <td>
                            <?php if($don['statut'] == 'disponible'): ?>
                                <span class="badge badge-success">متاح</span>
                            <?php elseif($don['statut'] == 'reserve'): ?>
                                <span class="badge badge-warning">محجوز</span>
                            <?php else: ?>
                                <span class="badge badge-info">مكتمل</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo date('d/m/Y', strtotime($don['created_at'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div style="text-align: center; margin-top: 20px;">
                <a href="mes-dons.php" class="btn btn-outline">عرض جميع تبرعاتي</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
<?php
// livreur/dashboard.php

session_start();
if (!isset($_SESSION['user_id'])) {
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

// Demandes en attente
$demandes_query = "
    SELECT d.*, u.nom as beneficiaire_nom, don.titre as don_titre
    FROM demandes d
    INNER JOIN users u ON d.beneficiaire_id = u.id
    INNER JOIN dons don ON d.don_id = don.id
    WHERE don.donateur_id = :user_id AND d.statut = 'en_attente'
    ORDER BY d.created_at DESC
    LIMIT 5
";
$demandes_stmt = $db->prepare($demandes_query);
$demandes_stmt->bindParam(":user_id", $user_id);
$demandes_stmt->execute();
$demandes_attente = $demandes_stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'لوحة التحكم - متبرع';
require_once '../includes/header.php';
?>

<style>
.welcome-section {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 40px;
    border-radius: 15px;
    margin-bottom: 30px;
    position: relative;
    overflow: hidden;
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

@keyframes rotate {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.welcome-content {
    position: relative;
    z-index: 1;
}

.welcome-content h1 {
    font-size: 36px;
    margin-bottom: 10px;
}

.welcome-content p {
    font-size: 18px;
    opacity: 0.9;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
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
    transition: transform 0.3s;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.12);
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

.quick-actions {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
    margin-bottom: 30px;
}

.action-card {
    background: white;
    border-radius: 12px;
    padding: 30px 20px;
    text-align: center;
    text-decoration: none;
    color: var(--dark);
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
    transition: all 0.3s;
    border: 2px solid transparent;
}

.action-card:hover {
    transform: translateY(-5px);
    border-color: var(--accent);
    box-shadow: 0 10px 25px rgba(0,0,0,0.12);
}

.action-card i {
    font-size: 40px;
    margin-bottom: 15px;
    color: var(--accent);
}

.action-card h4 {
    margin: 0 0 5px 0;
    font-size: 18px;
}

.action-card p {
    margin: 0;
    color: var(--secondary);
    font-size: 13px;
}

.demandes-list {
    list-style: none;
    padding: 0;
}

.demande-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px;
    border-bottom: 1px solid #eee;
}

.demande-item:last-child {
    border-bottom: none;
}

.demande-info h4 {
    margin: 0 0 5px 0;
    color: var(--primary);
}

.demande-info p {
    margin: 0;
    color: var(--secondary);
    font-size: 13px;
}

.demande-actions {
    display: flex;
    gap: 10px;
}

@media (max-width: 992px) {
    .stats-grid, .quick-actions {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 576px) {
    .stats-grid, .quick-actions {
        grid-template-columns: 1fr;
    }
}
</style>

<!-- Welcome Section -->
<div class="welcome-section">
    <div class="welcome-content">
        <h1><i class="fas fa-hand-holding-heart"></i> مرحباً بك <?php echo htmlspecialchars($_SESSION['user_nom']); ?></h1>
        <p>شكراً لمساهمتك في نشر الخير والعطاء</p>
    </div>
</div>

<!-- Stats Cards -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(135deg, #667eea, #764ba2);">
            <i class="fas fa-gift"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $stats['total_dons'] ?? 0; ?></h3>
            <p>إجمالي التبرعات</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(135deg, #4facfe, #00f2fe);">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $stats['dons_actifs'] ?? 0; ?></h3>
            <p>تبرعات نشطة</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(135deg, #f093fb, #f5576c);">
            <i class="fas fa-clock"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $stats['dons_reserves'] ?? 0; ?></h3>
            <p>محجوزة</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(135deg, #43e97b, #38f9d7);">
            <i class="fas fa-heart"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $stats['dons_termines'] ?? 0; ?></h3>
            <p>تبرعات مكتملة</p>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="quick-actions">
    <a href="publier-don.php" class="action-card">
        <i class="fas fa-plus-circle"></i>
        <h4>نشر تبرع</h4>
        <p>انشر شيئاً جديداً للتبرع به</p>
    </a>
    <a href="mes-dons.php" class="action-card">
        <i class="fas fa-boxes"></i>
        <h4>تبرعاتي</h4>
        <p>إدارة وتتبع تبرعاتك</p>
    </a>
    <a href="messagerie.php" class="action-card">
        <i class="fas fa-comments"></i>
        <h4>المراسلة</h4>
        <p>تواصل مع المستفيدين</p>
    </a>
    <a href="profile.php" class="action-card">
        <i class="fas fa-user-cog"></i>
        <h4>الملف الشخصي</h4>
        <p>إدارة معلومات حسابك</p>
    </a>
</div>

<div class="grid-2">
    <!-- Recent Dons -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-history"></i> آخر تبرعاتك</h3>
            <a href="mes-dons.php" class="btn btn-outline">عرض الكل</a>
        </div>
        <div class="card-body">
            <?php if(empty($dons_recent)): ?>
                <div style="text-align: center; padding: 40px;">
                    <i class="fas fa-gift" style="font-size: 60px; color: #ccc; margin-bottom: 20px;"></i>
                    <p style="color: #666;">لم تقم بنشر أي تبرعات بعد</p>
                    <a href="publier-don.php" class="btn btn-primary" style="margin-top: 15px;">
                        <i class="fas fa-plus"></i> نشر أول تبرع
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>العنوان</th>
                                <th>الفئة</th>
                                <th>الحالة</th>
                                <th>التاريخ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($dons_recent as $don): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($don['titre']); ?></td>
                                <td>
                                    <span class="badge badge-primary"><?php echo $don['categorie']; ?></span>
                                </td>
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
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Demandes en attente -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-clock"></i> طلبات قيد الانتظار</h3>
        </div>
        <div class="card-body">
            <?php if(empty($demandes_attente)): ?>
                <div style="text-align: center; padding: 40px;">
                    <i class="fas fa-inbox" style="font-size: 60px; color: #ccc; margin-bottom: 20px;"></i>
                    <p style="color: #666;">لا توجد طلبات جديدة</p>
                </div>
            <?php else: ?>
                <?php foreach($demandes_attente as $demande): ?>
                <div class="demande-item">
                    <div class="demande-info">
                        <h4><?php echo htmlspecialchars($demande['don_titre']); ?></h4>
                        <p>
                            <i class="fas fa-user"></i> <?php echo htmlspecialchars($demande['beneficiaire_nom']); ?>
                            <i class="fas fa-calendar" style="margin-right: 10px;"></i> <?php echo date('d/m/Y', strtotime($demande['created_at'])); ?>
                        </p>
                    </div>
                    <div class="demande-actions">
                        <a href="repondre-demande.php?id=<?php echo $demande['id']; ?>" class="btn btn-success btn-sm">
                            <i class="fas fa-check"></i> قبول
                        </a>
                        <a href="refuser-demande.php?id=<?php echo $demande['id']; ?>" class="btn btn-danger btn-sm">
                            <i class="fas fa-times"></i> رفض
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
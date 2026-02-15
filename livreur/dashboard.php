<?php
require_once '../config/database.php';
require_once '../includes/header.php';

checkAuth(['livreur']);

$database = new Database();
$db = $database->getConnection();

$user_id = $_SESSION['user_id'];

// Vérifier si le livreur est actif
$query = "SELECT l.* FROM livreurs l WHERE l.user_id = :user_id AND l.statut = 'actif'";
$stmt = $db->prepare($query);
$stmt->bindParam(":user_id", $user_id);
$stmt->execute();
$livreur = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$livreur) {
    echo '<div class="alert alert-warning" style="margin: 20px;">حساب المندوب الخاص بك لم يتم تفعيله بعد من قبل المسؤول</div>';
    include '../includes/footer.php';
    exit;
}

// Statistiques du livreur
$stats_query = "
    SELECT 
        COUNT(*) as total_missions,
        SUM(CASE WHEN statut = 'livree' THEN 1 ELSE 0 END) as missions_terminees,
        SUM(CASE WHEN statut = 'en_cours' THEN 1 ELSE 0 END) as missions_en_cours
    FROM livraisons 
    WHERE livreur_id = :user_id
";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->bindParam(":user_id", $user_id);
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Missions récentes
$missions_query = "
    SELECT l.*, d.titre as don_titre, u.nom as beneficiaire_nom, u2.nom as donateur_nom,
           do.ville, do.adresse_retrait
    FROM livraisons l
    INNER JOIN demandes de ON l.demande_id = de.id
    INNER JOIN dons d ON de.don_id = d.id
    INNER JOIN users u ON de.beneficiaire_id = u.id
    INNER JOIN users u2 ON d.donateur_id = u2.id
    INNER JOIN dons do ON de.don_id = do.id
    WHERE l.livreur_id = :user_id
    ORDER BY l.created_at DESC
    LIMIT 5
";
$missions_stmt = $db->prepare($missions_query);
$missions_stmt->bindParam(":user_id", $user_id);
$missions_stmt->execute();
$missions_recent = $missions_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- Dashboard Header -->
<div class="dashboard-header">
    <h1>مرحباً بك في مساحة المندوب</h1>
    <p>قم بإدارة مهام التوصيل الخاصة بك</p>
</div>

<!-- Statistiques -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(135deg, #667eea, #764ba2);">
            <i class="fas fa-truck"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $stats['total_missions'] ?? 0; ?></h3>
            <p>إجمالي المهام</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(135deg, #f093fb, #f5576c);">
            <i class="fas fa-clock"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $stats['missions_en_cours'] ?? 0; ?></h3>
            <p>المهام الجارية</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(135deg, #4facfe, #00f2fe);">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $stats['missions_terminees'] ?? 0; ?></h3>
            <p>المهام المنجزة</p>
        </div>
    </div>
</div>

<!-- Actions rapides -->
<div class="quick-actions">
    <a href="missions.php" class="action-card">
        <i class="fas fa-tasks" style="font-size: 2rem; margin-bottom: 1rem;"></i>
        <h3>عرض المهام</h3>
        <p>الإطلاع على المهام المتاحة</p>
    </a>
    <a href="missions.php?filter=en_cours" class="action-card">
        <i class="fas fa-play-circle" style="font-size: 2rem; margin-bottom: 1rem;"></i>
        <h3>مهامي الجارية</h3>
        <p>إدارة مهامي الحالية</p>
    </a>
    <a href="profil.php" class="action-card">
        <i class="fas fa-user-cog" style="font-size: 2rem; margin-bottom: 1rem;"></i>
        <h3>ملفي الشخصي</h3>
        <p>تعديل معلوماتي</p>
    </a>
</div>

<!-- Missions récentes -->
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-history"></i> مهامك الأخيرة</h3>
        <a href="missions.php" class="btn btn-outline">عرض الكل</a>
    </div>
    <div class="card-body">
        <?php if(empty($missions_recent)): ?>
            <div style="text-align: center; padding: 3rem;">
                <i class="fas fa-box-open" style="font-size: 4rem; color: #ccc; margin-bottom: 1rem;"></i>
                <h3 style="color: #666; margin-bottom: 1rem;">لا توجد مهام حالياً</h3>
                <p style="color: #888;">تصفح المهام المتاحة واقبل واحدة</p>
                <a href="missions.php" class="btn btn-primary" style="margin-top: 1rem;">عرض المهام</a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>التبرع</th>
                            <th>المستفيد</th>
                            <th>المتبرع</th>
                            <th>المكان</th>
                            <th>الحالة</th>
                            <th>التاريخ</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($missions_recent as $mission): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($mission['don_titre']); ?></td>
                                <td><?php echo htmlspecialchars($mission['beneficiaire_nom']); ?></td>
                                <td><?php echo htmlspecialchars($mission['donateur_nom']); ?></td>
                                <td><?php echo htmlspecialchars($mission['ville']); ?></td>
                                <td>
                                    <?php 
                                    $status_class = '';
                                    $status_text = '';
                                    switch($mission['statut']) {
                                        case 'en_attente':
                                            $status_class = 'badge-warning';
                                            $status_text = 'قيد الانتظار';
                                            break;
                                        case 'assignee':
                                            $status_class = 'badge-info';
                                            $status_text = 'معينة';
                                            break;
                                        case 'en_cours':
                                            $status_class = 'badge-primary';
                                            $status_text = 'جارية';
                                            break;
                                        case 'livree':
                                            $status_class = 'badge-success';
                                            $status_text = 'تم التوصيل';
                                            break;
                                        default:
                                            $status_class = 'badge-secondary';
                                            $status_text = $mission['statut'];
                                    }
                                    ?>
                                    <span class="badge <?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                                </td>
                                <td><?php echo date('d/m/Y', strtotime($mission['created_at'])); ?></td>
                                <td>
                                    <a href="mission-details.php?id=<?php echo $mission['id']; ?>" class="btn btn-outline btn-sm">
                                        <i class="fas fa-eye"></i> عرض
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
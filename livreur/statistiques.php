<?php
require_once '../config/database.php';
require_once '../includes/header.php';

checkAuth(['livreur']);

$database = new Database();
$db = $database->getConnection();

$user_id = $_SESSION['user_id'];

// Statistiques générales
$stats_query = "
    SELECT 
        COUNT(*) as total_missions,
        SUM(CASE WHEN statut = 'livree' THEN 1 ELSE 0 END) as missions_terminees,
        SUM(CASE WHEN statut = 'en_cours' THEN 1 ELSE 0 END) as missions_en_cours,
        SUM(CASE WHEN statut = 'assignee' THEN 1 ELSE 0 END) as missions_assignees,
        SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as missions_aujourdhui,
        SUM(CASE WHEN WEEK(created_at) = WEEK(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE()) THEN 1 ELSE 0 END) as missions_semaine,
        SUM(CASE WHEN MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE()) THEN 1 ELSE 0 END) as missions_mois
    FROM livraisons 
    WHERE livreur_id = :user_id
";

$stats_stmt = $db->prepare($stats_query);
$stats_stmt->bindParam(":user_id", $user_id);
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Évolution mensuelle
$evolution_query = "
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m') as mois,
        COUNT(*) as total,
        SUM(CASE WHEN statut = 'livree' THEN 1 ELSE 0 END) as terminees
    FROM livraisons 
    WHERE livreur_id = :user_id 
        AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY mois DESC
";

$evolution_stmt = $db->prepare($evolution_query);
$evolution_stmt->bindParam(":user_id", $user_id);
$evolution_stmt->execute();
$evolution = $evolution_stmt->fetchAll(PDO::FETCH_ASSOC);

// Top villes
$villes_query = "
    SELECT 
        do.ville,
        COUNT(*) as total_livraisons
    FROM livraisons l
    INNER JOIN demandes de ON l.demande_id = de.id
    INNER JOIN dons do ON de.don_id = do.id
    WHERE l.livreur_id = :user_id AND l.statut = 'livree'
    GROUP BY do.ville
    ORDER BY total_livraisons DESC
    LIMIT 5
";

$villes_stmt = $db->prepare($villes_query);
$villes_stmt->bindParam(":user_id", $user_id);
$villes_stmt->execute();
$top_villes = $villes_stmt->fetchAll(PDO::FETCH_ASSOC);

// Performance mensuelle
$performance_query = "
    SELECT 
        DATE_FORMAT(l.created_at, '%d/%m/%Y') as date,
        COUNT(*) as livraisons_jour
    FROM livraisons l
    WHERE l.livreur_id = :user_id 
        AND l.statut = 'livree'
        AND l.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY DATE(l.created_at)
    ORDER BY date DESC
";

$performance_stmt = $db->prepare($performance_query);
$performance_stmt->bindParam(":user_id", $user_id);
$performance_stmt->execute();
$performance = $performance_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<style>
.stat-circle {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    margin: 0 auto;
    background: linear-gradient(135deg, var(--accent), #74b9ff);
    color: white;
}

.stat-circle .number {
    font-size: 32px;
    font-weight: 700;
    line-height: 1;
}

.stat-circle .label {
    font-size: 14px;
    opacity: 0.9;
}

.performance-bar {
    height: 8px;
    background: #e0e0e0;
    border-radius: 4px;
    overflow: hidden;
    margin: 10px 0;
}

.performance-bar-fill {
    height: 100%;
    background: linear-gradient(90deg, var(--accent), #74b9ff);
    border-radius: 4px;
    transition: width 0.3s;
}

.stats-table {
    width: 100%;
}

.stats-table td {
    padding: 12px;
    border-bottom: 1px solid #eee;
}

.stats-table tr:last-child td {
    border-bottom: none;
}

.stats-table .label {
    color: var(--secondary);
    font-weight: 500;
}

.stats-table .value {
    font-weight: 700;
    color: var(--primary);
    text-align: left;
}
</style>

<div class="dashboard-header">
    <h1><i class="fas fa-chart-bar"></i> إحصائيات الأداء</h1>
    <p>تحليل أدائك وإنجازاتك كمندوب توصيل</p>
</div>

<!-- Cartes de statistiques rapides -->
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
        <div class="stat-icon" style="background: linear-gradient(135deg, #4facfe, #00f2fe);">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $stats['missions_terminees'] ?? 0; ?></h3>
            <p>المهام المنجزة</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(135deg, #f093fb, #f5576c);">
            <i class="fas fa-clock"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $stats['missions_en_cours'] ?? 0; ?></h3>
            <p>قيد التنفيذ</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(135deg, #ff9a9e, #fecfef);">
            <i class="fas fa-percent"></i>
        </div>
        <div class="stat-content">
            <?php 
            $taux_reussite = ($stats['total_missions'] > 0) 
                ? round(($stats['missions_terminees'] / $stats['total_missions']) * 100) 
                : 0;
            ?>
            <h3><?php echo $taux_reussite; ?>%</h3>
            <p>نسبة النجاح</p>
        </div>
    </div>
</div>

<!-- Statistiques détaillées -->
<div class="grid-2" style="margin-bottom: 25px;">
    <!-- Périodes -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-calendar-alt"></i> تحليل زمني</h3>
        </div>
        <div class="card-body">
            <table class="stats-table" style="width: 100%;">
                <tr>
                    <td class="label"><i class="fas fa-sun"></i> اليوم</td>
                    <td class="value"><?php echo $stats['missions_aujourdhui'] ?? 0; ?> مهام</td>
                </tr>
                <tr>
                    <td class="label"><i class="fas fa-calendar-week"></i> هذا الأسبوع</td>
                    <td class="value"><?php echo $stats['missions_semaine'] ?? 0; ?> مهام</td>
                </tr>
                <tr>
                    <td class="label"><i class="fas fa-calendar"></i> هذا الشهر</td>
                    <td class="value"><?php echo $stats['missions_mois'] ?? 0; ?> مهام</td>
                </tr>
                <tr>
                    <td class="label"><i class="fas fa-chart-line"></i> المعدل اليومي</td>
                    <td class="value">
                        <?php 
                        $jours_inscrits = ceil((time() - strtotime($livreur['created_at'])) / (60 * 60 * 24));
                        $moyenne = ($jours_inscrits > 0 && $stats['total_missions'] > 0) 
                            ? round($stats['total_missions'] / $jours_inscrits, 1) 
                            : 0;
                        echo $moyenne; ?> مهمة/يوم
                    </td>
                </tr>
            </table>
        </div>
    </div>

    <!-- Top villes -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-map-marker-alt"></i> أكثر المدن نشاطاً</h3>
        </div>
        <div class="card-body">
            <?php if(empty($top_villes)): ?>
                <div style="text-align: center; padding: 2rem; color: #666;">
                    <i class="fas fa-city" style="font-size: 3rem; opacity: 0.3; margin-bottom: 1rem;"></i>
                    <p>لا توجد إحصائيات مدن بعد</p>
                </div>
            <?php else: ?>
                <?php foreach($top_villes as $ville): ?>
                <div style="margin-bottom: 15px;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                        <span><i class="fas fa-map-pin"></i> <?php echo htmlspecialchars($ville['ville']); ?></span>
                        <span class="badge badge-primary"><?php echo $ville['total_livraisons']; ?> توصيلة</span>
                    </div>
                    <?php 
                    $max_villes = max(array_column($top_villes, 'total_livraisons'));
                    $pourcentage = ($ville['total_livraisons'] / $max_villes) * 100;
                    ?>
                    <div class="performance-bar">
                        <div class="performance-bar-fill" style="width: <?php echo $pourcentage; ?>%;"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Évolution mensuelle -->
<div class="card" style="margin-bottom: 25px;">
    <div class="card-header">
        <h3><i class="fas fa-chart-line"></i> تطور الأداء (آخر 6 أشهر)</h3>
    </div>
    <div class="card-body">
        <?php if(empty($evolution)): ?>
            <div style="text-align: center; padding: 2rem; color: #666;">
                <i class="fas fa-chart-bar" style="font-size: 3rem; opacity: 0.3; margin-bottom: 1rem;"></i>
                <p>لا توجد بيانات كافية للتحليل</p>
            </div>
        <?php else: ?>
            <?php 
            $max_total = max(array_column($evolution, 'total'));
            $mois_fr = [
                '01' => 'يناير', '02' => 'فبراير', '03' => 'مارس', '04' => 'أبريل',
                '05' => 'ماي', '06' => 'يونيو', '07' => 'يوليو', '08' => 'غشت',
                '09' => 'سبتمبر', '10' => 'أكتوبر', '11' => 'نوفمبر', '12' => 'ديسمبر'
            ];
            ?>
            <?php foreach($evolution as $mois): 
                $annee_mois = explode('-', $mois['mois']);
                $nom_mois = $mois_fr[$annee_mois[1]] . ' ' . $annee_mois[0];
                $pourcentage = ($mois['total'] / $max_total) * 100;
            ?>
            <div style="margin-bottom: 20px;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                    <span><?php echo $nom_mois; ?></span>
                    <span>
                        <span class="badge badge-success"><?php echo $mois['terminees']; ?> منجزة</span>
                        <span class="badge badge-primary" style="margin-right: 5px;"><?php echo $mois['total']; ?> إجمالي</span>
                    </span>
                </div>
                <div class="performance-bar">
                    <div class="performance-bar-fill" style="width: <?php echo $pourcentage; ?>%;"></div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Objectifs et récompenses -->
<div class="grid-2">
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-trophy"></i> إنجازاتك</h3>
        </div>
        <div class="card-body">
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; text-align: center;">
                <div style="background: #f8f9fa; padding: 15px; border-radius: 8px;">
                    <i class="fas fa-star" style="color: #ffc107; font-size: 24px; margin-bottom: 5px;"></i>
                    <h4 style="margin: 5px 0;"><?php echo $livreur['note_moyenne'] ?? '5.0'; ?>/5</h4>
                    <p style="color: #666; font-size: 13px;">التقييم</p>
                </div>
                <div style="background: #f8f9fa; padding: 15px; border-radius: 8px;">
                    <i class="fas fa-gem" style="color: #00b894; font-size: 24px; margin-bottom: 5px;"></i>
                    <h4 style="margin: 5px 0;">المستوى <?php 
                        $niveau = floor(($stats['missions_terminees'] ?? 0) / 10) + 1;
                        echo $niveau;
                    ?></h4>
                    <p style="color: #666; font-size: 13px;">المستوى الحالي</p>
                </div>
            </div>
            
            <div style="margin-top: 20px;">
                <h4>المهمة التالية</h4>
                <div class="performance-bar" style="margin: 10px 0;">
                    <?php 
                    $prochain_palier = (floor(($stats['missions_terminees'] ?? 0) / 10) + 1) * 10;
                    $progression = (($stats['missions_terminees'] ?? 0) % 10) * 10;
                    ?>
                    <div class="performance-bar-fill" style="width: <?php echo $progression; ?>%;"></div>
                </div>
                <p style="color: #666; font-size: 14px; text-align: center;">
                    <?php echo 10 - (($stats['missions_terminees'] ?? 0) % 10); ?> مهام للوصول للمستوى التالي
                </p>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-clock"></i> آخر 30 يوم</h3>
        </div>
        <div class="card-body">
            <?php if(empty($performance)): ?>
                <div style="text-align: center; padding: 2rem; color: #666;">
                    <i class="fas fa-calendar" style="font-size: 3rem; opacity: 0.3; margin-bottom: 1rem;"></i>
                    <p>لا توجد بيانات للأيام الأخيرة</p>
                </div>
            <?php else: ?>
                <div style="max-height: 300px; overflow-y: auto;">
                    <?php foreach($performance as $jour): ?>
                    <div style="display: flex; justify-content: space-between; padding: 10px; border-bottom: 1px solid #eee;">
                        <span><?php echo $jour['date']; ?></span>
                        <span class="badge badge-success"><?php echo $jour['livraisons_jour']; ?> توصيلة</span>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
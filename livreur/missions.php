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

// Récupérer le filtre
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'toutes';

// Récupérer les missions - CORRECTION: retrait des colonnes qui n'existent pas
$query = "
    SELECT l.*, d.titre as don_titre, u.nom as beneficiaire_nom, 
           u2.nom as donateur_nom, do.adresse_retrait, do.ville
    FROM livraisons l
    INNER JOIN demandes de ON l.demande_id = de.id
    INNER JOIN dons d ON de.don_id = d.id
    INNER JOIN users u ON de.beneficiaire_id = u.id
    INNER JOIN users u2 ON d.donateur_id = u2.id
    INNER JOIN dons do ON de.don_id = do.id
    WHERE 1=1
";

if($filter == 'disponibles') {
    $query .= " AND l.livreur_id IS NULL";
} elseif($filter == 'en_cours') {
    $query .= " AND l.livreur_id = :user_id AND l.statut IN ('assignee', 'en_cours')";
} elseif($filter == 'terminees') {
    $query .= " AND l.livreur_id = :user_id AND l.statut = 'livree'";
} elseif($filter == 'mes') {
    $query .= " AND l.livreur_id = :user_id";
}

$query .= " ORDER BY l.created_at DESC";

$stmt = $db->prepare($query);
if(in_array($filter, ['en_cours', 'terminees', 'mes'])) {
    $stmt->bindParam(":user_id", $user_id);
}
$stmt->execute();
$missions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Compter les missions par statut pour les onglets
$count_query = "
    SELECT 
        COUNT(CASE WHEN livreur_id IS NULL THEN 1 END) as disponibles,
        COUNT(CASE WHEN livreur_id = :user_id AND statut IN ('assignee', 'en_cours') THEN 1 END) as en_cours,
        COUNT(CASE WHEN livreur_id = :user_id AND statut = 'livree' THEN 1 END) as terminees
    FROM livraisons
";
$count_stmt = $db->prepare($count_query);
$count_stmt->bindParam(":user_id", $user_id);
$count_stmt->execute();
$counts = $count_stmt->fetch(PDO::FETCH_ASSOC);
?>

<style>
/* Styles spécifiques pour la page des missions */
.missions-filters {
    background: white;
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    margin-bottom: 25px;
}

.mission-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 15px;
    border: 1px solid #eee;
    transition: all 0.3s;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.mission-card:hover {
    box-shadow: 0 5px 20px rgba(0,0,0,0.1);
    border-color: var(--accent);
}

.mission-info h3 {
    margin: 0 0 10px 0;
    color: var(--primary);
    font-size: 18px;
}

.mission-details {
    display: flex;
    gap: 20px;
    color: var(--secondary);
    font-size: 14px;
}

.mission-details i {
    color: var(--accent);
    width: 20px;
}

.mission-actions {
    display: flex;
    gap: 10px;
}

.mission-badge {
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    display: inline-block;
    margin-bottom: 10px;
}

@media (max-width: 768px) {
    .mission-card {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }
    
    .mission-actions {
        width: 100%;
        justify-content: flex-end;
    }
    
    .mission-details {
        flex-direction: column;
        gap: 8px;
    }
}
</style>

<div class="dashboard-header">
    <h1><i class="fas fa-tasks"></i> مهام التوصيل</h1>
    <p>قم بإدارة مهامك واقبل مهام توصيل جديدة</p>
</div>

<!-- Statistiques rapides -->
<div class="stats-grid" style="margin-bottom: 25px;">
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(135deg, #667eea, #764ba2);">
            <i class="fas fa-clock"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $counts['disponibles'] ?? 0; ?></h3>
            <p>مهام متاحة</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(135deg, #f093fb, #f5576c);">
            <i class="fas fa-play-circle"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $counts['en_cours'] ?? 0; ?></h3>
            <p>مهام جارية</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(135deg, #4facfe, #00f2fe);">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $counts['terminees'] ?? 0; ?></h3>
            <p>مهام منجزة</p>
        </div>
    </div>
</div>

<!-- Filtres -->
<div class="missions-filters">
    <div style="display: flex; gap: 10px; flex-wrap: wrap; justify-content: center;">
        <a href="?filter=toutes" class="btn <?php echo $filter == 'toutes' ? 'btn-primary' : 'btn-outline'; ?>">
            جميع المهام
        </a>
        <a href="?filter=disponibles" class="btn <?php echo $filter == 'disponibles' ? 'btn-primary' : 'btn-outline'; ?>">
            <i class="fas fa-clock"></i> المتاحة
        </a>
        <a href="?filter=en_cours" class="btn <?php echo $filter == 'en_cours' ? 'btn-primary' : 'btn-outline'; ?>">
            <i class="fas fa-play-circle"></i> الجارية
        </a>
        <a href="?filter=terminees" class="btn <?php echo $filter == 'terminees' ? 'btn-primary' : 'btn-outline'; ?>">
            <i class="fas fa-check-circle"></i> المنجزة
        </a>
        <a href="?filter=mes" class="btn <?php echo $filter == 'mes' ? 'btn-primary' : 'btn-outline'; ?>">
            <i class="fas fa-user"></i> مهامي
        </a>
    </div>
</div>

<!-- Liste des missions -->
<div class="card">
    <div class="card-header">
        <h3>
            <?php 
            switch($filter) {
                case 'disponibles': echo '<i class="fas fa-clock"></i> المهام المتاحة'; break;
                case 'en_cours': echo '<i class="fas fa-play-circle"></i> المهام الجارية'; break;
                case 'terminees': echo '<i class="fas fa-check-circle"></i> المهام المنجزة'; break;
                case 'mes': echo '<i class="fas fa-user"></i> مهامي'; break;
                default: echo '<i class="fas fa-list"></i> جميع المهام';
            }
            ?>
        </h3>
    </div>
    <div class="card-body">
        <?php if(empty($missions)): ?>
            <div style="text-align: center; padding: 3rem;">
                <i class="fas fa-box-open" style="font-size: 4rem; color: #ccc; margin-bottom: 1rem;"></i>
                <h3 style="color: #666; margin-bottom: 1rem;">لا توجد مهام</h3>
                <p style="color: #888;">جرب تغيير الفلاتر أو عد لاحقاً</p>
            </div>
        <?php else: ?>
            <?php foreach($missions as $mission): ?>
            <div class="mission-card">
                <div class="mission-info">
                    <?php 
                    $status_class = '';
                    $status_text = '';
                    switch($mission['statut']) {
                        case 'en_attente':
                            $status_class = 'badge-warning';
                            $status_text = 'في الانتظار';
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
                    <span class="badge <?php echo $status_class; ?> mission-badge"><?php echo $status_text; ?></span>
                    <h3><?php echo htmlspecialchars($mission['don_titre']); ?></h3>
                    <div class="mission-details">
                        <span><i class="fas fa-user"></i> المستفيد: <?php echo htmlspecialchars($mission['beneficiaire_nom']); ?></span>
                        <span><i class="fas fa-user"></i> المتبرع: <?php echo htmlspecialchars($mission['donateur_nom']); ?></span>
                        <span><i class="fas fa-map-marker-alt"></i> المدينة: <?php echo htmlspecialchars($mission['ville']); ?></span>
                        <span><i class="fas fa-calendar"></i> <?php echo date('d/m/Y', strtotime($mission['created_at'])); ?></span>
                    </div>
                    <?php if($mission['adresse_retrait']): ?>
                    <div style="margin-top: 10px; color: var(--secondary); font-size: 13px;">
                        <i class="fas fa-location-dot"></i> 
                        <?php echo htmlspecialchars($mission['adresse_retrait']); ?>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="mission-actions">
                    <?php if(!$mission['livreur_id']): ?>
                        <a href="accepter-mission.php?id=<?php echo $mission['id']; ?>" 
                           class="btn btn-success" 
                           onclick="return confirm('هل تريد قبول هذه المهمة؟')">
                            <i class="fas fa-check"></i> قبول
                        </a>
                    <?php elseif($mission['livreur_id'] == $user_id && $mission['statut'] == 'assignee'): ?>
                        <a href="demarrer-mission.php?id=<?php echo $mission['id']; ?>" 
                           class="btn btn-primary"
                           onclick="return confirm('بدء هذه المهمة؟')">
                            <i class="fas fa-play"></i> بدء
                        </a>
                    <?php elseif($mission['livreur_id'] == $user_id && $mission['statut'] == 'en_cours'): ?>
                        <a href="terminer-mission.php?id=<?php echo $mission['id']; ?>" 
                           class="btn btn-warning"
                           onclick="return confirm('تأكيد إنجاز المهمة؟')">
                            <i class="fas fa-check-circle"></i> إنهاء
                        </a>
                    <?php endif; ?>
                    <a href="mission-details.php?id=<?php echo $mission['id']; ?>" 
                       class="btn btn-outline">
                        <i class="fas fa-eye"></i> تفاصيل
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
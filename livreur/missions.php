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

// Récupérer les missions
$query = "
    SELECT l.*, d.titre as don_titre, u.nom as beneficiaire_nom, 
           u2.nom as donateur_nom, do.adresse_retrait, do.ville,
           do.code_postal, do.instructions
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

<div class="dashboard-header">
    <h1><i class="fas fa-tasks"></i> مهام التوصيل</h1>
    <p>قم بإدارة مهامك واقبل مهام توصيل جديدة</p>
</div>

<!-- Filtres -->
<div style="margin-bottom: 20px;">
    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
        <a href="?filter=toutes" class="btn <?php echo $filter == 'toutes' ? 'btn-primary' : 'btn-outline'; ?>">
            جميع المهام
        </a>
        <a href="?filter=disponibles" class="btn <?php echo $filter == 'disponibles' ? 'btn-primary' : 'btn-outline'; ?>">
            <i class="fas fa-clock"></i> المتاحة (<?php echo $counts['disponibles'] ?? 0; ?>)
        </a>
        <a href="?filter=en_cours" class="btn <?php echo $filter == 'en_cours' ? 'btn-primary' : 'btn-outline'; ?>">
            <i class="fas fa-play-circle"></i> الجارية (<?php echo $counts['en_cours'] ?? 0; ?>)
        </a>
        <a href="?filter=terminees" class="btn <?php echo $filter == 'terminees' ? 'btn-primary' : 'btn-outline'; ?>">
            <i class="fas fa-check-circle"></i> المنجزة (<?php echo $counts['terminees'] ?? 0; ?>)
        </a>
        <a href="?filter=mes" class="btn <?php echo $filter == 'mes' ? 'btn-primary' : 'btn-outline'; ?>">
            <i class="fas fa-user"></i> مهامي
        </a>
    </div>
</div>

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
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>التبرع</th>
                            <th>المستفيد</th>
                            <th>المتبرع</th>
                            <th>مكان الاستلام</th>
                            <th>الحالة</th>
                            <th>التاريخ</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($missions as $mission): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($mission['don_titre']); ?></strong>
                                </td>
                                <td><?php echo htmlspecialchars($mission['beneficiaire_nom']); ?></td>
                                <td><?php echo htmlspecialchars($mission['donateur_nom']); ?></td>
                                <td>
                                    <i class="fas fa-map-marker-alt" style="color: var(--accent);"></i>
                                    <?php echo htmlspecialchars($mission['ville']); ?>
                                    <?php if($mission['adresse_retrait']): ?>
                                        <br><small style="color: #666;"><?php echo htmlspecialchars(substr($mission['adresse_retrait'], 0, 30)); ?>...</small>
                                    <?php endif; ?>
                                </td>
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
                                    <div style="display: flex; gap: 5px;">
                                        <?php if(!$mission['livreur_id']): ?>
                                            <a href="accepter-mission.php?id=<?php echo $mission['id']; ?>" 
                                               class="btn btn-success btn-sm" 
                                               onclick="return confirm('هل تريد قبول هذه المهمة؟')">
                                                <i class="fas fa-check"></i> قبول
                                            </a>
                                        <?php elseif($mission['livreur_id'] == $user_id && $mission['statut'] == 'assignee'): ?>
                                            <a href="demarrer-mission.php?id=<?php echo $mission['id']; ?>" 
                                               class="btn btn-primary btn-sm"
                                               onclick="return confirm('بدء هذه المهمة؟')">
                                                <i class="fas fa-play"></i> بدء
                                            </a>
                                        <?php elseif($mission['livreur_id'] == $user_id && $mission['statut'] == 'en_cours'): ?>
                                            <a href="terminer-mission.php?id=<?php echo $mission['id']; ?>" 
                                               class="btn btn-warning btn-sm"
                                               onclick="return confirm('تأكيد إنجاز المهمة؟')">
                                                <i class="fas fa-check-circle"></i> إنهاء
                                            </a>
                                        <?php endif; ?>
                                        <a href="mission-details.php?id=<?php echo $mission['id']; ?>" 
                                           class="btn btn-outline btn-sm">
                                            <i class="fas fa-eye"></i> تفاصيل
                                        </a>
                                    </div>
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
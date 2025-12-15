<?php
// donateur/mes-dons.php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'donateur') {
    header('Location: ../auth/login.php');
    exit();
}

require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

// Gérer la suppression d'un don
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $don_id = $_GET['id'];
    
    try {
        $query = "DELETE FROM dons WHERE id = :id AND donateur_id = :donateur_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":id", $don_id);
        $stmt->bindParam(":donateur_id", $user_id);
        
        if ($stmt->execute()) {
            $success = "✅ تم حذف التبرع بنجاح";
        } else {
            $error = "❌ حدث خطأ أثناء حذف التبرع";
        }
    } catch(PDOException $e) {
        $error = "❌ خطأ: " . $e->getMessage();
    }
}

// Récupérer tous les dons du donateur avec les demandes
$query = "
    SELECT d.*, 
           COUNT(de.id) as nb_demandes,
           SUM(CASE WHEN de.statut = 'en_attente' THEN 1 ELSE 0 END) as demandes_attente
    FROM dons d
    LEFT JOIN demandes de ON d.id = de.don_id
    WHERE d.donateur_id = :user_id
    GROUP BY d.id
    ORDER BY d.created_at DESC
";
$stmt = $db->prepare($query);
$stmt->bindParam(":user_id", $user_id);
$stmt->execute();
$dons = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'تبرعاتي';
require_once '../includes/header.php';
?>

<!-- Page Header -->
<div class="page-header">
    <h1><i class="fas fa-boxes"></i> تبرعاتي</h1>
    <p>إدارة تبرعاتك وتتبع الطلبات</p>
</div>

<?php if($success): ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
<?php endif; ?>

<?php if($error): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>

<!-- Statistics -->
<div class="stats-grid">
    <?php
    $stats = [
        'total' => count($dons),
        'disponible' => array_sum(array_map(fn($d) => $d['statut'] == 'disponible' ? 1 : 0, $dons)),
        'reserve' => array_sum(array_map(fn($d) => $d['statut'] == 'reserve' ? 1 : 0, $dons)),
        'donne' => array_sum(array_map(fn($d) => $d['statut'] == 'donne' ? 1 : 0, $dons))
    ];
    ?>
    
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(135deg, #74b9ff, #0984e3);">
            <i class="fas fa-gift"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $stats['total']; ?></h3>
            <p>إجمالي التبرعات</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(135deg, #00b894, #00cec9);">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $stats['disponible']; ?></h3>
            <p>متاحة</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(135deg, #fdcb6e, #e17055);">
            <i class="fas fa-clock"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $stats['reserve']; ?></h3>
            <p>محجوزة</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(135deg, #a29bfe, #6c5ce7);">
            <i class="fas fa-heart"></i>
        </div>
        <div class="stat-content">
            <h3><?php echo $stats['donne']; ?></h3>
            <p>مكتملة</p>
        </div>
    </div>
</div>

<!-- Dons List -->
<div class="card">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
        <h3><i class="fas fa-list"></i> قائمة تبرعاتي</h3>
        <a href="publier-don.php" class="btn btn-primary">➕ نشر تبرع جديد</a>
    </div>
    <div class="card-body">
        <?php if(empty($dons)): ?>
            <div style="text-align: center; padding: 50px; color: #666;">
                <i class="fas fa-box-open" style="font-size: 60px; margin-bottom: 20px; opacity: 0.3;"></i>
                <p>لم تقم بنشر أي تبرعات بعد</p>
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
                            <th>الوضع</th>
                            <th>الطلبات</th>
                            <th>التاريخ</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($dons as $don): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($don['titre']); ?></strong>
                                    <?php if(strlen($don['description']) > 50): ?>
                                        <br><small style="color: #666;"><?php echo substr(htmlspecialchars($don['description']), 0, 50); ?>...</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge badge-primary">
                                        <?php echo $don['categorie']; ?>
                                    </span>
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
                                <td>
                                    <?php if($don['nb_demandes'] > 0): ?>
                                        <span class="badge badge-<?php echo $don['demandes_attente'] > 0 ? 'warning' : 'info'; ?>">
                                            <?php echo $don['nb_demandes']; ?> طلب
                                            <?php if($don['demandes_attente'] > 0): ?>
                                                <br><small><?php echo $don['demandes_attente']; ?> في الانتظار</small>
                                            <?php endif; ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge badge-light">لا توجد</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('d/m/Y', strtotime($don['created_at'])); ?></td>
                                <td>
                                    <div style="display: flex; gap: 5px; flex-wrap: wrap;">
                                        <!-- زر العرض المعدل -->
                                        <a href="voir-don.php?id=<?php echo $don['id']; ?>" 
                                           class="btn btn-sm btn-outline"
                                           target="_blank">
                                            <i class="fas fa-eye"></i> عرض
                                        </a>
                                        <?php if($don['statut'] == 'disponible'): ?>
                                            <a href="modifier-don.php?id=<?php echo $don['id']; ?>" class="btn btn-sm btn-warning">
                                                <i class="fas fa-edit"></i> تعديل
                                            </a>
                                            <a href="?action=delete&id=<?php echo $don['id']; ?>" class="btn btn-sm btn-danger" 
                                               onclick="return confirm('هل أنت متأكد من حذف هذا التبرع؟')">
                                                <i class="fas fa-trash"></i> حذف
                                            </a>
                                        <?php endif; ?>
                                        <?php if($don['nb_demandes'] > 0): ?>
                                            <a href="demandes-don.php?don_id=<?php echo $don['id']; ?>" class="btn btn-sm btn-info">
                                                <i class="fas fa-file-alt"></i> الطلبات
                                            </a>
                                        <?php endif; ?>
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

<?php require_once '../includes/footer.php'; ?>
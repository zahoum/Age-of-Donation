<?php
require_once 'includes/admin_header.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id) {
    header('Location: utilisateurs.php');
    exit();
}

// Get user details
$query = "SELECT * FROM users WHERE id = :id AND type != 'admin'";
$stmt = $db->prepare($query);
$stmt->bindParam(':id', $id);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header('Location: utilisateurs.php');
    exit();
}

// Get user's donations
$dons_query = "SELECT * FROM dons WHERE donateur_id = :id ORDER BY created_at DESC LIMIT 10";
$dons_stmt = $db->prepare($dons_query);
$dons_stmt->bindParam(':id', $id);
$dons_stmt->execute();
$dons = $dons_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get user's requests
$demandes_query = "SELECT de.*, d.titre as don_titre 
                   FROM demandes de 
                   INNER JOIN dons d ON de.don_id = d.id 
                   WHERE de.beneficiaire_id = :id 
                   ORDER BY de.created_at DESC LIMIT 10";
$demandes_stmt = $db->prepare($demandes_query);
$demandes_stmt->bindParam(':id', $id);
$demandes_stmt->execute();
$demandes = $demandes_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stats_query = "SELECT 
    (SELECT COUNT(*) FROM dons WHERE donateur_id = :id) as total_dons,
    (SELECT COUNT(*) FROM demandes WHERE beneficiaire_id = :id) as total_demandes,
    (SELECT COUNT(*) FROM dons WHERE donateur_id = :id AND statut = 'completé') as dons_completes";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->bindParam(':id', $id);
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

$user_types = [
    'donateur' => '<span class="badge bg-primary"><i class="fas fa-hand-holding-heart me-1"></i>متبرع</span>',
    'beneficiaire' => '<span class="badge bg-success"><i class="fas fa-hands-helping me-1"></i>مستفيد</span>',
    'livreur' => '<span class="badge bg-warning"><i class="fas fa-truck me-1"></i>مساعد</span>'
];
?>

<div class="row">
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-user-circle me-2"></i>معلومات المستخدم</h5>
            </div>
            <div class="card-body text-center">
                <div class="mb-3">
                    <div style="width: 100px; height: 100px; background: linear-gradient(135deg, #667eea, #764ba2); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto;">
                        <i class="fas fa-user fa-3x text-white"></i>
                    </div>
                </div>
                <h4><?php echo htmlspecialchars($user['nom']); ?></h4>
                <p><?php echo $user_types[$user['type']] ?? '<span class="badge bg-secondary">غير محدد</span>'; ?></p>
                <hr>
                <div class="text-start">
                    <p><i class="fas fa-envelope me-2 text-muted"></i> <?php echo htmlspecialchars($user['email']); ?></p>
                    <p><i class="fas fa-phone me-2 text-muted"></i> <?php echo htmlspecialchars($user['telephone'] ?? 'غير مسجل'); ?></p>
                    <p><i class="fas fa-calendar me-2 text-muted"></i> تاريخ التسجيل: <?php echo date('Y/m/d', strtotime($user['created_at'])); ?></p>
                    <p><i class="fas fa-clock me-2 text-muted"></i> الوقت: <?php echo date('H:i', strtotime($user['created_at'])); ?></p>
                </div>
            </div>
        </div>
        
        <div class="card mt-4">
            <div class="card-header">
                <h5><i class="fas fa-chart-bar me-2"></i>إحصائيات المستخدم</h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-6 mb-3">
                        <h3 class="text-primary"><?php echo $stats['total_dons'] ?? 0; ?></h3>
                        <small class="text-muted">تبرعات</small>
                    </div>
                    <div class="col-6 mb-3">
                        <h3 class="text-success"><?php echo $stats['dons_completes'] ?? 0; ?></h3>
                        <small class="text-muted">تبرعات مكتملة</small>
                    </div>
                    <div class="col-6">
                        <h3 class="text-warning"><?php echo $stats['total_demandes'] ?? 0; ?></h3>
                        <small class="text-muted">طلبات</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-gift me-2"></i>آخر التبرعات</h5>
                <a href="dons.php?user_id=<?php echo $user['id']; ?>" class="btn btn-sm btn-outline-primary">عرض الكل</a>
            </div>
            <div class="card-body">
                <?php if(empty($dons)): ?>
                    <p class="text-muted text-center">لا توجد تبرعات</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>العنوان</th>
                                    <th>الفئة</th>
                                    <th>المبلغ</th>
                                    <th>الحالة</th>
                                    <th>التاريخ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($dons as $don): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($don['titre']); ?></td>
                                        <td><?php echo ucfirst($don['categorie']); ?></td>
                                        <td><?php echo $don['montant'] ? number_format($don['montant'], 2) . ' درهم' : '—'; ?></td>
                                        <td>
                                            <?php
                                            $status_badges = [
                                                'disponible' => '<span class="badge bg-success">متاح</span>',
                                                'réservé' => '<span class="badge bg-warning">محجوز</span>',
                                                'completé' => '<span class="badge bg-info">مكتمل</span>'
                                            ];
                                            echo $status_badges[$don['statut']] ?? '<span class="badge bg-secondary">غير محدد</span>';
                                            ?>
                                        </td>
                                        <td><?php echo date('Y/m/d', strtotime($don['created_at'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="card mt-4">
            <div class="card-header">
                <h5><i class="fas fa-hand-paper me-2"></i>آخر الطلبات</h5>
                <a href="demandes.php?user_id=<?php echo $user['id']; ?>" class="btn btn-sm btn-outline-primary">عرض الكل</a>
            </div>
            <div class="card-body">
                <?php if(empty($demandes)): ?>
                    <p class="text-muted text-center">لا توجد طلبات</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>التبرع</th>
                                    <th>الكمية</th>
                                    <th>الحالة</th>
                                    <th>التاريخ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($demandes as $demande): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($demande['don_titre']); ?></td>
                                        <td><?php echo htmlspecialchars($demande['quantite_demandee'] ?? '—'); ?></td>
                                        <td>
                                            <?php
                                            $req_status = [
                                                'en_attente' => '<span class="badge bg-warning">في الانتظار</span>',
                                                'accepté' => '<span class="badge bg-success">مقبول</span>',
                                                'refusé' => '<span class="badge bg-danger">مرفوض</span>'
                                            ];
                                            echo $req_status[$demande['statut']] ?? '<span class="badge bg-secondary">غير محدد</span>';
                                            ?>
                                        </td>
                                        <td><?php echo date('Y/m/d', strtotime($demande['created_at'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="card mt-4">
            <div class="card-header">
                <h5><i class="fas fa-cog me-2"></i>إجراءات</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-6">
                        <form method="POST" action="utilisateurs.php" onsubmit="return confirm('هل أنت متأكد من تغيير حالة هذا المستخدم؟')">
                            <input type="hidden" name="action" value="toggle_status">
                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                            <button type="submit" class="btn btn-<?php echo $user['status'] == 'active' ? 'warning' : 'success'; ?> w-100">
                                <i class="fas fa-<?php echo $user['status'] == 'active' ? 'ban' : 'check'; ?> me-2"></i>
                                <?php echo $user['status'] == 'active' ? 'تعطيل المستخدم' : 'تفعيل المستخدم'; ?>
                            </button>
                        </form>
                    </div>
                    <div class="col-6">
                        <button class="btn btn-primary w-100" onclick="resetPassword(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['nom']); ?>')">
                            <i class="fas fa-key me-2"></i>
                            إعادة تعيين كلمة المرور
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Reset Password Modal -->
<div class="modal fade" id="resetPasswordModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-key me-2"></i>إعادة تعيين كلمة المرور</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="utilisateurs.php">
                <div class="modal-body">
                    <input type="hidden" name="action" value="reset_password">
                    <input type="hidden" name="user_id" id="reset_user_id">
                    <p>إعادة تعيين كلمة المرور للمستخدم: <strong id="reset_user_name"></strong></p>
                    <div class="mb-3">
                        <label class="form-label">كلمة المرور الجديدة</label>
                        <input type="text" name="new_password" class="form-control" value="123456" required>
                        <small class="text-muted">الافتراضي: 123456</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-primary">إعادة التعيين</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function resetPassword(id, name) {
    document.getElementById('reset_user_id').value = id;
    document.getElementById('reset_user_name').textContent = name;
    new bootstrap.Modal(document.getElementById('resetPasswordModal')).show();
}
</script>

<?php require_once 'includes/admin_footer.php'; ?>
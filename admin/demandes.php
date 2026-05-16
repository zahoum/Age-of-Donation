<?php
require_once 'includes/admin_header.php';

// Handle approve request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_request'])) {
    $demande_id = $_POST['demande_id'] ?? 0;
    
    $update_query = "UPDATE demandes SET statut = 'accepté' WHERE id = :id";
    $update_stmt = $db->prepare($update_query);
    $update_stmt->bindParam(':id', $demande_id);
    
    if ($update_stmt->execute()) {
        $demande_info = $db->prepare("SELECT don_id FROM demandes WHERE id = :id");
        $demande_info->bindParam(':id', $demande_id);
        $demande_info->execute();
        $don_id = $demande_info->fetchColumn();
        
        $update_don = $db->prepare("UPDATE dons SET statut = 'réservé' WHERE id = :id");
        $update_don->bindParam(':id', $don_id);
        $update_don->execute();
        
        header("Location: demandes.php?approved=1");
        exit();
    }
}

// Handle reject request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reject_request'])) {
    $demande_id = $_POST['demande_id'] ?? 0;
    $reason = $_POST['reason'] ?? '';
    
    $update_query = "UPDATE demandes SET statut = 'refusé', notes = :notes WHERE id = :id";
    $update_stmt = $db->prepare($update_query);
    $update_stmt->bindParam(':notes', $reason);
    $update_stmt->bindParam(':id', $demande_id);
    
    if ($update_stmt->execute()) {
        header("Location: demandes.php?rejected=1");
        exit();
    }
}

// Handle delete request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_request'])) {
    $demande_id = $_POST['demande_id'] ?? 0;
    
    $delete_query = "DELETE FROM demandes WHERE id = :id";
    $delete_stmt = $db->prepare($delete_query);
    $delete_stmt->bindParam(':id', $demande_id);
    
    if ($delete_stmt->execute()) {
        header("Location: demandes.php?deleted=1");
        exit();
    }
}

// Handle update request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_request'])) {
    $demande_id = $_POST['demande_id'] ?? 0;
    $quantite_demandee = $_POST['quantite_demandee'] ?? '';
    $raison = $_POST['raison'] ?? '';
    $statut = $_POST['statut'] ?? '';
    
    $update_query = "UPDATE demandes SET quantite_demandee = :quantite, raison = :raison, statut = :statut WHERE id = :id";
    $update_stmt = $db->prepare($update_query);
    $update_stmt->bindParam(':quantite', $quantite_demandee);
    $update_stmt->bindParam(':raison', $raison);
    $update_stmt->bindParam(':statut', $statut);
    $update_stmt->bindParam(':id', $demande_id);
    
    if ($update_stmt->execute()) {
        header("Location: demandes.php?updated=1");
        exit();
    }
}

// Get view ID and edit ID
$view_id = isset($_GET['view']) ? (int)$_GET['view'] : 0;
$edit_id = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;

// View single request
if ($view_id > 0) {
    $view_query = "SELECT de.*, d.titre as don_titre, d.categorie as don_categorie, d.description as don_description,
                   u.nom as beneficiaire_nom, u.email as beneficiaire_email, u.telephone as beneficiaire_telephone,
                   donateur.nom as donateur_nom, donateur.email as donateur_email, donateur.telephone as donateur_telephone
                   FROM demandes de 
                   INNER JOIN dons d ON de.don_id = d.id 
                   INNER JOIN users u ON de.beneficiaire_id = u.id 
                   INNER JOIN users donateur ON d.donateur_id = donateur.id
                   WHERE de.id = :id";
    $view_stmt = $db->prepare($view_query);
    $view_stmt->bindParam(':id', $view_id);
    $view_stmt->execute();
    $request_view = $view_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$request_view) {
        header('Location: demandes.php');
        exit();
    }
}

// Edit request
if ($edit_id > 0) {
    $edit_query = "SELECT * FROM demandes WHERE id = :id";
    $edit_stmt = $db->prepare($edit_query);
    $edit_stmt->bindParam(':id', $edit_id);
    $edit_stmt->execute();
    $edit_request = $edit_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$edit_request) {
        header('Location: demandes.php');
        exit();
    }
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 15;
$offset = ($page - 1) * $limit;

// Filters
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// Build query
$where_conditions = ["1=1"];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(d.titre LIKE :search OR u.nom LIKE :search OR u.email LIKE :search)";
    $params[':search'] = "%$search%";
}
if (!empty($status_filter)) {
    $where_conditions[] = "de.statut = :status";
    $params[':status'] = $status_filter;
}
if (!empty($date_from)) {
    $where_conditions[] = "DATE(de.created_at) >= :date_from";
    $params[':date_from'] = $date_from;
}
if (!empty($date_to)) {
    $where_conditions[] = "DATE(de.created_at) <= :date_to";
    $params[':date_to'] = $date_to;
}

$where_clause = "WHERE " . implode(" AND ", $where_conditions);

// Get total count
$count_query = "SELECT COUNT(*) FROM demandes de 
                INNER JOIN dons d ON de.don_id = d.id 
                INNER JOIN users u ON de.beneficiaire_id = u.id 
                $where_clause";
$count_stmt = $db->prepare($count_query);
foreach ($params as $key => $value) {
    $count_stmt->bindValue($key, $value);
}
$count_stmt->execute();
$total_demandes = $count_stmt->fetchColumn();
$total_pages = ceil($total_demandes / $limit);

// Get requests list
$query = "SELECT de.*, d.titre as don_titre, d.categorie as don_categorie, 
          u.nom as beneficiaire_nom, u.email as beneficiaire_email, u.telephone as beneficiaire_telephone,
          donateur.nom as donateur_nom
          FROM demandes de 
          INNER JOIN dons d ON de.don_id = d.id 
          INNER JOIN users u ON de.beneficiaire_id = u.id 
          INNER JOIN users donateur ON d.donateur_id = donateur.id
          $where_clause 
          ORDER BY 
            CASE WHEN de.statut = 'en_attente' THEN 1 ELSE 2 END,
            de.created_at DESC 
          LIMIT :limit OFFSET :offset";
$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$demandes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stats_query = "SELECT 
    SUM(CASE WHEN statut = 'en_attente' THEN 1 ELSE 0 END) as en_attente,
    SUM(CASE WHEN statut = 'accepté' THEN 1 ELSE 0 END) as acceptes,
    SUM(CASE WHEN statut = 'refusé' THEN 1 ELSE 0 END) as refuses,
    COUNT(*) as total
    FROM demandes";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->execute();
$request_stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
?>

<!-- Statistics Cards -->
<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="stat-card text-center">
            <div class="stat-number text-primary"><?php echo $request_stats['total'] ?? 0; ?></div>
            <div class="stat-label">إجمالي الطلبات</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card text-center">
            <div class="stat-number text-warning"><?php echo $request_stats['en_attente'] ?? 0; ?></div>
            <div class="stat-label">في الانتظار</div>
            <small class="text-muted">بحاجة إلى مراجعة</small>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card text-center">
            <div class="stat-number text-success"><?php echo $request_stats['acceptes'] ?? 0; ?></div>
            <div class="stat-label">مقبولة</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card text-center">
            <div class="stat-number text-danger"><?php echo $request_stats['refuses'] ?? 0; ?></div>
            <div class="stat-label">مرفوضة</div>
        </div>
    </div>
</div>

<?php if(isset($_GET['approved'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-2"></i>تم قبول الطلب بنجاح
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if(isset($_GET['rejected'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-times-circle me-2"></i>تم رفض الطلب
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if(isset($_GET['deleted'])): ?>
    <div class="alert alert-warning alert-dismissible fade show" role="alert">
        <i class="fas fa-trash me-2"></i>تم حذف الطلب بنجاح
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if(isset($_GET['updated'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-2"></i>تم تحديث الطلب بنجاح
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if($view_id > 0 && isset($request_view)): ?>
    <!-- VIEW REQUEST DETAILS -->
    <div class="card">
        <div class="card-header">
            <h5><i class="fas fa-eye me-2"></i>تفاصيل الطلب</h5>
            <div>
                <?php if($request_view['statut'] == 'en_attente'): ?>
                    <button class="btn btn-sm btn-success" onclick="showApproveModal(<?php echo $request_view['id']; ?>)">
                        <i class="fas fa-check me-1"></i>قبول
                    </button>
                    <button class="btn btn-sm btn-danger" onclick="showRejectModal(<?php echo $request_view['id']; ?>)">
                        <i class="fas fa-times me-1"></i>رفض
                    </button>
                <?php endif; ?>
                <a href="demandes.php?edit=<?php echo $request_view['id']; ?>" class="btn btn-sm btn-warning">
                    <i class="fas fa-edit me-1"></i>تعديل
                </a>
                <a href="demandes.php" class="btn btn-sm btn-secondary">
                    <i class="fas fa-arrow-right me-1"></i>رجوع
                </a>
            </div>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <h6 class="mb-3"><i class="fas fa-gift me-2 text-primary"></i>معلومات التبرع</h6>
                    <table class="table table-borderless">
                        <tr><th width="150">عنوان التبرع:</th><td><strong><?php echo htmlspecialchars($request_view['don_titre']); ?></strong></td></tr>
                        <tr><th>الفئة:</th><td><span class="badge bg-info"><?php echo ucfirst($request_view['don_categorie']); ?></span></td></tr>
                        <tr><th>الوصف:</th><td><?php echo nl2br(htmlspecialchars($request_view['don_description'] ?? '')); ?></td></tr>
                        <tr><th>المتبرع:</th><td><strong><?php echo htmlspecialchars($request_view['donateur_nom']); ?></strong><br><small class="text-muted"><?php echo htmlspecialchars($request_view['donateur_email']); ?></small></td></tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <h6 class="mb-3"><i class="fas fa-user me-2 text-success"></i>معلومات المستفيد</h6>
                    <table class="table table-borderless">
                        <tr><th width="150">الاسم:</th><td><strong><?php echo htmlspecialchars($request_view['beneficiaire_nom']); ?></strong></td></tr>
                        <tr><th>البريد الإلكتروني:</th><td><?php echo htmlspecialchars($request_view['beneficiaire_email']); ?></td></tr>
                        <tr><th>رقم الهاتف:</th><td><?php echo htmlspecialchars($request_view['beneficiaire_telephone'] ?? '—'); ?></td></tr>
                        <tr><th>الكمية المطلوبة:</th><td><?php echo htmlspecialchars($request_view['quantite_demandee'] ?? '—'); ?></td></tr>
                        <tr><th>سبب الطلب:</th><td><?php echo nl2br(htmlspecialchars($request_view['raison'] ?? '—')); ?></td></tr>
                        <tr><th>تاريخ الطلب:</th><td><?php echo date('Y/m/d H:i', strtotime($request_view['created_at'])); ?></td></tr>
                    </table>
                </div>
            </div>
            <?php if($request_view['notes']): ?>
                <hr>
                <div class="alert alert-info">
                    <strong><i class="fas fa-comment me-2"></i>ملاحظات الرفض:</strong><br>
                    <?php echo nl2br(htmlspecialchars($request_view['notes'])); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

<?php elseif($edit_id > 0 && isset($edit_request)): ?>
    <!-- EDIT REQUEST FORM -->
    <div class="card">
        <div class="card-header">
            <h5><i class="fas fa-edit me-2"></i>تعديل الطلب</h5>
            <a href="demandes.php" class="btn btn-sm btn-secondary"><i class="fas fa-arrow-right me-1"></i>رجوع</a>
        </div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="update_request" value="1">
                <input type="hidden" name="demande_id" value="<?php echo $edit_request['id']; ?>">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">الكمية المطلوبة</label>
                        <input type="text" name="quantite_demandee" class="form-control" value="<?php echo htmlspecialchars($edit_request['quantite_demandee'] ?? ''); ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">الحالة</label>
                        <select name="statut" class="form-select">
                            <option value="en_attente" <?php echo $edit_request['statut'] == 'en_attente' ? 'selected' : ''; ?>>في الانتظار</option>
                            <option value="accepté" <?php echo $edit_request['statut'] == 'accepté' ? 'selected' : ''; ?>>مقبول</option>
                            <option value="refusé" <?php echo $edit_request['statut'] == 'refusé' ? 'selected' : ''; ?>>مرفوض</option>
                        </select>
                    </div>
                    <div class="col-12 mb-3">
                        <label class="form-label">سبب الطلب</label>
                        <textarea name="raison" class="form-control" rows="4"><?php echo htmlspecialchars($edit_request['raison'] ?? ''); ?></textarea>
                    </div>
                </div>
                <div class="mt-3">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i>حفظ التغييرات</button>
                    <a href="demandes.php" class="btn btn-secondary">إلغاء</a>
                </div>
            </form>
        </div>
    </div>

<?php else: ?>
    <!-- LIST VIEW WITH FILTERS -->
    <div class="card">
        <div class="card-header">
            <h5><i class="fas fa-hand-paper me-2"></i>قائمة الطلبات</h5>
            <button class="btn btn-sm btn-success" onclick="exportRequests()"><i class="fas fa-file-excel me-1"></i>تصدير التقرير</button>
        </div>
        <div class="card-body">
            <!-- Filter Section -->
            <div class="filter-section">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">بحث</label>
                        <input type="text" name="search" class="form-control" placeholder="بحث بالتبرع أو المستفيد..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">الحالة</label>
                        <select name="status" class="form-select">
                            <option value="">الكل</option>
                            <option value="en_attente" <?php echo $status_filter == 'en_attente' ? 'selected' : ''; ?>>في الانتظار</option>
                            <option value="accepté" <?php echo $status_filter == 'accepté' ? 'selected' : ''; ?>>مقبول</option>
                            <option value="refusé" <?php echo $status_filter == 'refusé' ? 'selected' : ''; ?>>مرفوض</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">من تاريخ</label>
                        <input type="date" name="date_from" class="form-control" value="<?php echo $date_from; ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">إلى تاريخ</label>
                        <input type="date" name="date_to" class="form-control" value="<?php echo $date_to; ?>">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search"></i></button>
                    </div>
                </form>
            </div>
            
            <?php if(empty($demandes)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-hand-paper fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">لا توجد طلبات</h5>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>#</th><th>التبرع</th><th>المستفيد</th><th>المتبرع</th><th>الكمية</th><th>سبب الطلب</th><th>الحالة</th><th>التاريخ</th><th>الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($demandes as $index => $demande): ?>
                                <tr class="<?php echo $demande['statut'] == 'en_attente' ? 'table-warning' : ''; ?>">
                                    <td><?php echo $offset + $index + 1; ?></td>
                                    <td><strong><?php echo htmlspecialchars($demande['don_titre']); ?></strong><br><small class="text-muted"><?php echo ucfirst($demande['don_categorie']); ?></small></td>
                                    <td><strong><?php echo htmlspecialchars($demande['beneficiaire_nom']); ?></strong><br><small class="text-muted"><?php echo htmlspecialchars($demande['beneficiaire_email']); ?></small></td>
                                    <td><?php echo htmlspecialchars($demande['donateur_nom']); ?></td>
                                    <td><?php echo htmlspecialchars($demande['quantite_demandee'] ?? '—'); ?></td>
                                    <td><?php echo substr(htmlspecialchars($demande['raison'] ?? ''), 0, 50); ?></td>
                                    <td>
                                        <?php
                                        if($demande['statut'] == 'en_attente') {
                                            echo '<span class="badge bg-warning">في الانتظار</span>';
                                        } elseif($demande['statut'] == 'accepté') {
                                            echo '<span class="badge bg-success">مقبول</span>';
                                        } else {
                                            echo '<span class="badge bg-danger">مرفوض</span>';
                                        }
                                        ?>
                                    </td>
                                    <td><?php echo date('Y/m/d', strtotime($demande['created_at'])); ?><br><small class="text-muted"><?php echo date('H:i', strtotime($demande['created_at'])); ?></small></td>
                                    <td class="action-buttons text-nowrap">
                                        <a href="demandes.php?view=<?php echo $demande['id']; ?>" class="btn btn-sm btn-info"><i class="fas fa-eye"></i></a>
                                        <a href="demandes.php?edit=<?php echo $demande['id']; ?>" class="btn btn-sm btn-warning"><i class="fas fa-edit"></i></a>
                                        <?php if($demande['statut'] == 'en_attente'): ?>
                                            <button class="btn btn-sm btn-success" onclick="showApproveModal(<?php echo $demande['id']; ?>)"><i class="fas fa-check"></i></button>
                                            <button class="btn btn-sm btn-danger" onclick="showRejectModal(<?php echo $demande['id']; ?>)"><i class="fas fa-times"></i></button>
                                        <?php endif; ?>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('هل أنت متأكد من حذف هذا الطلب؟')">
                                            <input type="hidden" name="delete_request" value="1">
                                            <input type="hidden" name="demande_id" value="<?php echo $demande['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if($total_pages > 1): ?>
                    <nav aria-label="Page navigation">
                        <ul class="pagination justify-content-center">
                            <?php if($page > 1): ?>
                                <li class="page-item"><a class="page-link" href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>"><i class="fas fa-chevron-right"></i> السابق</a></li>
                            <?php endif; ?>
                            <?php for($i = max(1, $page-2); $i <= min($total_pages, $page+2); $i++): ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>"><a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>"><?php echo $i; ?></a></li>
                            <?php endfor; ?>
                            <?php if($page < $total_pages): ?>
                                <li class="page-item"><a class="page-link" href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>">التالي <i class="fas fa-chevron-left"></i></a></li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<!-- Approve Modal -->
<div class="modal fade" id="approveModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-check-circle me-2 text-success"></i>قبول الطلب</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="approve_request" value="1">
                    <input type="hidden" name="demande_id" id="approve_demande_id">
                    <p>هل أنت متأكد من قبول هذا الطلب؟</p>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>سيتم تحويل حالة التبرع إلى "محجوز" وتخصيصه للمستفيد.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-success">تأكيد القبول</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-times-circle me-2 text-danger"></i>رفض الطلب</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="reject_request" value="1">
                    <input type="hidden" name="demande_id" id="reject_demande_id">
                    <div class="mb-3">
                        <label class="form-label">سبب الرفض</label>
                        <textarea name="reason" class="form-control" rows="3" required placeholder="الرجاء توضيح سبب رفض الطلب..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-danger">تأكيد الرفض</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function showApproveModal(id) {
    document.getElementById('approve_demande_id').value = id;
    new bootstrap.Modal(document.getElementById('approveModal')).show();
}

function showRejectModal(id) {
    document.getElementById('reject_demande_id').value = id;
    new bootstrap.Modal(document.getElementById('rejectModal')).show();
}

function exportRequests() {
    var search = '<?php echo htmlspecialchars($search); ?>';
    var status = '<?php echo htmlspecialchars($status_filter); ?>';
    var date_from = '<?php echo $date_from; ?>';
    var date_to = '<?php echo $date_to; ?>';
    window.location.href = 'ajax/export_all.php?type=demandes&search=' + encodeURIComponent(search) + '&status=' + encodeURIComponent(status) + '&date_from=' + encodeURIComponent(date_from) + '&date_to=' + encodeURIComponent(date_to);
}
</script>

<?php require_once 'includes/admin_footer.php'; ?>
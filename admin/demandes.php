<?php
require_once 'includes/admin_header.php';

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

// Get requests
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

// Handle actions
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $demande_id = $_POST['demande_id'] ?? 0;
    
    if ($action === 'approve') {
        // Update demande status
        $update_query = "UPDATE demandes SET statut = 'accepté' WHERE id = :id";
        $update_stmt = $db->prepare($update_query);
        $update_stmt->bindParam(':id', $demande_id);
        
        if ($update_stmt->execute()) {
            // Get donation info to update its status
            $demande_info = $db->prepare("SELECT don_id FROM demandes WHERE id = :id");
            $demande_info->bindParam(':id', $demande_id);
            $demande_info->execute();
            $don_id = $demande_info->fetchColumn();
            
            // Update donation status to reserved
            $update_don = $db->prepare("UPDATE dons SET statut = 'réservé' WHERE id = :id");
            $update_don->bindParam(':id', $don_id);
            $update_don->execute();
            
            $success = "تم قبول الطلب بنجاح";
        }
    } elseif ($action === 'reject') {
        $reason = $_POST['reason'] ?? '';
        $update_query = "UPDATE demandes SET statut = 'refusé', notes = :notes WHERE id = :id";
        $update_stmt = $db->prepare($update_query);
        $update_stmt->bindParam(':notes', $reason);
        $update_stmt->bindParam(':id', $demande_id);
        
        if ($update_stmt->execute()) {
            $success = "تم رفض الطلب";
        }
    } elseif ($action === 'delete') {
        $delete_query = "DELETE FROM demandes WHERE id = :id";
        $delete_stmt = $db->prepare($delete_query);
        $delete_stmt->bindParam(':id', $demande_id);
        
        if ($delete_stmt->execute()) {
            $success = "تم حذف الطلب بنجاح";
        }
    }
}

// Get statistics
$stats_query = "SELECT 
    SUM(CASE WHEN statut = 'en_attente' THEN 1 ELSE 0 END) as en_attente,
    SUM(CASE WHEN statut = 'accepté' THEN 1 ELSE 0 END) as acceptes,
    SUM(CASE WHEN statut = 'refusé' THEN 1 ELSE 0 END) as refuses
    FROM demandes";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->execute();
$request_stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
?>

<div class="row g-4 mb-4">
    <div class="col-md-4">
        <div class="stat-card text-center">
            <div class="stat-number text-warning"><?php echo $request_stats['en_attente'] ?? 0; ?></div>
            <div class="stat-label">طلبات في الانتظار</div>
            <small class="text-muted">بحاجة إلى مراجعة</small>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card text-center">
            <div class="stat-number text-success"><?php echo $request_stats['acceptes'] ?? 0; ?></div>
            <div class="stat-label">طلبات مقبولة</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card text-center">
            <div class="stat-number text-danger"><?php echo $request_stats['refuses'] ?? 0; ?></div>
            <div class="stat-label">طلبات مرفوضة</div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h5><i class="fas fa-hand-paper me-2"></i>إدارة الطلبات</h5>
        <button class="btn btn-sm btn-success" onclick="exportRequests()">
            <i class="fas fa-file-excel me-1"></i>تصدير التقرير
        </button>
    </div>
    <div class="card-body">
        <?php if($success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <!-- Filter Section -->
        <div class="filter-section">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">بحث</label>
                    <input type="text" name="search" class="form-control" placeholder="بحث بالتبرع أو المستفيد..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-3">
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
                <div class="col-md-1 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search"></i>
                    </button>
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
                            <th>#</th>
                            <th>التبرع</th>
                            <th>المستفيد</th>
                            <th>المتبرع</th>
                            <th>الكمية المطلوبة</th>
                            <th>سبب الطلب</th>
                            <th>الحالة</th>
                            <th>تاريخ الطلب</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($demandes as $index => $demande): ?>
                            <tr class="<?php echo $demande['statut'] == 'en_attente' ? 'table-warning' : ''; ?>">
                                <td><?php echo $offset + $index + 1; ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($demande['don_titre']); ?></strong>
                                    <br>
                                    <small class="text-muted"><?php echo ucfirst($demande['don_categorie']); ?></small>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($demande['beneficiaire_nom']); ?></strong>
                                    <br>
                                    <small class="text-muted"><?php echo htmlspecialchars($demande['beneficiaire_email']); ?></small>
                                    <br>
                                    <small><?php echo htmlspecialchars($demande['beneficiaire_telephone'] ?? '—'); ?></small>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($demande['donateur_nom']); ?>
                                </td>
                                <td>
                                    <?php echo $demande['quantite_demandee'] ?? 'غير محدد'; ?>
                                </td>
                                <td>
                                    <?php echo substr(htmlspecialchars($demande['raison'] ?? ''), 0, 50); ?>
                                    <?php if(strlen($demande['raison'] ?? '') > 50): ?>...<?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $status_badges = [
                                        'en_attente' => '<span class="badge bg-warning"><i class="fas fa-clock me-1"></i>في الانتظار</span>',
                                        'accepté' => '<span class="badge bg-success"><i class="fas fa-check me-1"></i>مقبول</span>',
                                        'refusé' => '<span class="badge bg-danger"><i class="fas fa-times me-1"></i>مرفوض</span>'
                                    ];
                                    echo $status_badges[$demande['statut']] ?? '<span class="badge bg-secondary">غير محدد</span>';
                                    ?>
                                </td>
                                <td>
                                    <?php echo date('Y/m/d', strtotime($demande['created_at'])); ?>
                                    <br>
                                    <small class="text-muted"><?php echo date('H:i', strtotime($demande['created_at'])); ?></small>
                                </td>
                                <td class="action-buttons">
                                    <?php if($demande['statut'] == 'en_attente'): ?>
                                        <button class="btn btn-sm btn-success" onclick="approveRequest(<?php echo $demande['id']; ?>)">
                                            <i class="fas fa-check"></i> قبول
                                        </button>
                                        <button class="btn btn-sm btn-danger" onclick="rejectRequest(<?php echo $demande['id']; ?>)">
                                            <i class="fas fa-times"></i> رفض
                                        </button>
                                    <?php endif; ?>
                                    <button class="btn btn-sm btn-info" onclick="viewRequest(<?php echo $demande['id']; ?>)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <form method="POST" class="d-inline" onsubmit="return confirmAction('هل أنت متأكد من حذف هذا الطلب؟')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="demande_id" value="<?php echo $demande['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-danger">
                                            <i class="fas fa-trash"></i>
                                        </button>
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
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>">
                                    <i class="fas fa-chevron-right"></i> السابق
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <?php for($i = max(1, $page-2); $i <= min($total_pages, $page+2); $i++): ?>
                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>">
                                    التالي <i class="fas fa-chevron-left"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Approve Request Modal -->
<div class="modal fade" id="approveModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-check-circle me-2"></i>قبول الطلب</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="approve">
                    <input type="hidden" name="demande_id" id="approve_demande_id">
                    <p>هل أنت متأكد من قبول هذا الطلب؟</p>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        سيتم تحويل حالة التبرع إلى "محجوز" وتخصيصه للمستفيد.
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

<!-- Reject Request Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-times-circle me-2"></i>رفض الطلب</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="reject">
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
function approveRequest(id) {
    document.getElementById('approve_demande_id').value = id;
    new bootstrap.Modal(document.getElementById('approveModal')).show();
}

function rejectRequest(id) {
    document.getElementById('reject_demande_id').value = id;
    new bootstrap.Modal(document.getElementById('rejectModal')).show();
}

function viewRequest(id) {
    window.location.href = 'demande_details.php?id=' + id;
}

function exportRequests() {
    window.location.href = 'ajax/export_requests.php?status=<?php echo $status_filter; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>';
}
</script>

<?php require_once 'includes/admin_footer.php'; ?>
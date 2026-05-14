<?php
require_once 'includes/admin_header.php';

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 15;
$offset = ($page - 1) * $limit;

// Filters
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$categorie_filter = $_GET['categorie'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// Build query
$where_conditions = ["1=1"];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(d.titre LIKE :search OR d.description LIKE :search OR u.nom LIKE :search)";
    $params[':search'] = "%$search%";
}
if (!empty($status_filter)) {
    $where_conditions[] = "d.statut = :status";
    $params[':status'] = $status_filter;
}
if (!empty($categorie_filter)) {
    $where_conditions[] = "d.categorie = :categorie";
    $params[':categorie'] = $categorie_filter;
}
if (!empty($date_from)) {
    $where_conditions[] = "DATE(d.created_at) >= :date_from";
    $params[':date_from'] = $date_from;
}
if (!empty($date_to)) {
    $where_conditions[] = "DATE(d.created_at) <= :date_to";
    $params[':date_to'] = $date_to;
}

$where_clause = "WHERE " . implode(" AND ", $where_conditions);

// Get total count
$count_query = "SELECT COUNT(*) FROM dons d INNER JOIN users u ON d.donateur_id = u.id $where_clause";
$count_stmt = $db->prepare($count_query);
foreach ($params as $key => $value) {
    $count_stmt->bindValue($key, $value);
}
$count_stmt->execute();
$total_dons = $count_stmt->fetchColumn();
$total_pages = ceil($total_dons / $limit);

// Get donations
$query = "SELECT d.*, u.nom as donateur_nom, u.email as donateur_email, u.telephone as donateur_telephone 
          FROM dons d 
          INNER JOIN users u ON d.donateur_id = u.id 
          $where_clause 
          ORDER BY d.created_at DESC 
          LIMIT :limit OFFSET :offset";
$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$dons = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle actions
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $don_id = $_POST['don_id'] ?? 0;
    
    if ($action === 'change_status') {
        $new_status = $_POST['new_status'] ?? '';
        $update_query = "UPDATE dons SET statut = :status WHERE id = :id";
        $update_stmt = $db->prepare($update_query);
        $update_stmt->bindParam(':status', $new_status);
        $update_stmt->bindParam(':id', $don_id);
        
        if ($update_stmt->execute()) {
            $success = "تم تحديث حالة التبرع بنجاح";
        }
    } elseif ($action === 'delete') {
        $delete_query = "DELETE FROM dons WHERE id = :id";
        $delete_stmt = $db->prepare($delete_query);
        $delete_stmt->bindParam(':id', $don_id);
        
        if ($delete_stmt->execute()) {
            $success = "تم حذف التبرع بنجاح";
        }
    } elseif ($action === 'bulk_delete' && isset($_POST['selected_ids'])) {
        $ids = implode(',', array_map('intval', $_POST['selected_ids']));
        $delete_query = "DELETE FROM dons WHERE id IN ($ids)";
        if ($db->prepare($delete_query)->execute()) {
            $success = "تم حذف التبرعات المحددة بنجاح";
        }
    } elseif ($action === 'export') {
        exportDonations($dons);
    }
}

// Get statistics
$stats_query = "SELECT 
    SUM(CASE WHEN statut = 'disponible' THEN 1 ELSE 0 END) as disponibles,
    SUM(CASE WHEN statut = 'réservé' THEN 1 ELSE 0 END) as reserves,
    SUM(CASE WHEN statut = 'completé' THEN 1 ELSE 0 END) as completes,
    SUM(CASE WHEN statut = 'annulé' THEN 1 ELSE 0 END) as annules,
    SUM(montant) as total_montant,
    AVG(montant) as moyenne_montant
    FROM dons";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->execute();
$don_stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Get categories for filter
$categories_query = "SELECT DISTINCT categorie FROM dons ORDER BY categorie";
$categories_stmt = $db->prepare($categories_query);
$categories_stmt->execute();
$categories = $categories_stmt->fetchAll(PDO::FETCH_COLUMN);

function exportDonations($dons) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="dons_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, array('ID', 'العنوان', 'الفئة', 'المبلغ', 'المتبرع', 'البريد', 'الهاتف', 'الولاية', 'المدينة', 'الحالة', 'التاريخ'));
    
    foreach ($dons as $don) {
        fputcsv($output, array(
            $don['id'],
            $don['titre'],
            $don['categorie'],
            $don['montant'] ?? 'غير محدد',
            $don['donateur_nom'],
            $don['donateur_email'],
            $don['donateur_telephone'] ?? '',
            $don['etat'] ?? '',
            $don['ville'] ?? '',
            $don['statut'],
            date('Y-m-d H:i', strtotime($don['created_at']))
        ));
    }
    fclose($output);
    exit();
}
?>

<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="stat-card text-center">
            <div class="stat-number text-primary"><?php echo $total_dons; ?></div>
            <div class="stat-label">إجمالي التبرعات</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card text-center">
            <div class="stat-number text-success"><?php echo $don_stats['disponibles'] ?? 0; ?></div>
            <div class="stat-label">متاحة</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card text-center">
            <div class="stat-number text-warning"><?php echo $don_stats['reserves'] ?? 0; ?></div>
            <div class="stat-label">محجوزة</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card text-center">
            <div class="stat-number text-info"><?php echo number_format($don_stats['total_montant'] ?? 0, 2); ?></div>
            <div class="stat-label">إجمالي المبلغ (درهم)</div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h5><i class="fas fa-gift me-2"></i>إدارة التبرعات</h5>
        <div>
            <button class="btn btn-sm btn-success me-2" onclick="exportData()">
                <i class="fas fa-file-excel me-1"></i>تصدير
            </button>
            <a href="#" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addDonModal">
                <i class="fas fa-plus me-1"></i>إضافة تبرع
            </a>
        </div>
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
                <div class="col-md-3">
                    <label class="form-label">بحث</label>
                    <input type="text" name="search" class="form-control" placeholder="بحث بالعنوان أو المتبرع..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">الحالة</label>
                    <select name="status" class="form-select">
                        <option value="">الكل</option>
                        <option value="disponible" <?php echo $status_filter == 'disponible' ? 'selected' : ''; ?>>متاحة</option>
                        <option value="réservé" <?php echo $status_filter == 'réservé' ? 'selected' : ''; ?>>محجوزة</option>
                        <option value="completé" <?php echo $status_filter == 'completé' ? 'selected' : ''; ?>>مكتملة</option>
                        <option value="annulé" <?php echo $status_filter == 'annulé' ? 'selected' : ''; ?>>ملغية</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">الفئة</label>
                    <select name="categorie" class="form-select">
                        <option value="">الكل</option>
                        <?php foreach($categories as $cat): ?>
                            <option value="<?php echo $cat; ?>" <?php echo $categorie_filter == $cat ? 'selected' : ''; ?>>
                                <?php echo ucfirst($cat); ?>
                            </option>
                        <?php endforeach; ?>
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
        
        <?php if(empty($dons)): ?>
            <div class="text-center py-5">
                <i class="fas fa-gift fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">لا توجد تبرعات</h5>
            </div>
        <?php else: ?>
            <form id="bulkForm" method="POST">
                <input type="hidden" name="action" id="bulkAction" value="">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th width="30">
                                    <input type="checkbox" id="selectAll">
                                </th>
                                <th>#</th>
                                <th>العنوان</th>
                                <th>الفئة</th>
                                <th>المبلغ</th>
                                <th>المتبرع</th>
                                <th>الموقع</th>
                                <th>الحالة</th>
                                <th>التاريخ</th>
                                <th>الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($dons as $index => $don): ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" name="selected_ids[]" value="<?php echo $don['id']; ?>" class="donCheckbox">
                                    </td>
                                    <td><?php echo $offset + $index + 1; ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($don['titre']); ?></strong>
                                        <br>
                                        <small class="text-muted"><?php echo substr(htmlspecialchars($don['description']), 0, 50); ?>...</small>
                                    </td>
                                    <td>
                                        <span class="badge bg-info"><?php echo ucfirst($don['categorie']); ?></span>
                                    </td>
                                    <td>
                                        <?php if($don['montant']): ?>
                                            <strong class="text-success"><?php echo number_format($don['montant'], 2); ?> درهم</strong>
                                        <?php else: ?>
                                            <span class="text-muted">غير محدد</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($don['donateur_nom']); ?>
                                        <br>
                                        <small class="text-muted"><?php echo htmlspecialchars($don['donateur_email']); ?></small>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($don['ville'] ?? '—'); ?>
                                        <br>
                                        <small><?php echo htmlspecialchars($don['etat'] ?? '—'); ?></small>
                                    </td>
                                    <td>
                                        <select class="form-select form-select-sm status-select" data-id="<?php echo $don['id']; ?>" style="width: 120px;">
                                            <option value="disponible" <?php echo $don['statut'] == 'disponible' ? 'selected' : ''; ?>>متاحة</option>
                                            <option value="réservé" <?php echo $don['statut'] == 'réservé' ? 'selected' : ''; ?>>محجوزة</option>
                                            <option value="completé" <?php echo $don['statut'] == 'completé' ? 'selected' : ''; ?>>مكتملة</option>
                                            <option value="annulé" <?php echo $don['statut'] == 'annulé' ? 'selected' : ''; ?>>ملغية</option>
                                        </select>
                                    </td>
                                    <td>
                                        <?php echo date('Y/m/d', strtotime($don['created_at'])); ?>
                                        <br>
                                        <small class="text-muted"><?php echo date('H:i', strtotime($don['created_at'])); ?></small>
                                    </td>
                                    <td class="action-buttons">
                                        <button class="btn btn-sm btn-info" onclick="viewDonation(<?php echo $don['id']; ?>)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="btn btn-sm btn-warning" onclick="editDonation(<?php echo $don['id']; ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <form method="POST" class="d-inline" onsubmit="return confirmAction('هل أنت متأكد من حذف هذا التبرع؟')">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="don_id" value="<?php echo $don['id']; ?>">
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
                
                <!-- Bulk Actions -->
                <div class="row mt-3" id="bulkActions" style="display: none;">
                    <div class="col-12">
                        <div class="alert alert-info">
                            <strong><span id="selectedCount">0</span> تبرع محدد</strong>
                            <button type="button" class="btn btn-sm btn-danger ms-3" onclick="bulkDelete()">
                                <i class="fas fa-trash me-1"></i>حذف المحدد
                            </button>
                            <button type="button" class="btn btn-sm btn-success" onclick="bulkExport()">
                                <i class="fas fa-file-excel me-1"></i>تصدير المحدد
                            </button>
                        </div>
                    </div>
                </div>
            </form>
            
            <!-- Pagination -->
            <?php if($total_pages > 1): ?>
                <nav aria-label="Page navigation">
                    <ul class="pagination justify-content-center">
                        <?php if($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>&categorie=<?php echo $categorie_filter; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>">
                                    <i class="fas fa-chevron-right"></i> السابق
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <?php 
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);
                        for($i = $start_page; $i <= $end_page; $i++): ?>
                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>&categorie=<?php echo $categorie_filter; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>&categorie=<?php echo $categorie_filter; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>">
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

<!-- Add Donation Modal -->
<div class="modal fade" id="addDonModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-plus me-2"></i>إضافة تبرع جديد</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="ajax/add_donation.php" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">عنوان التبرع *</label>
                            <input type="text" name="titre" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">الفئة *</label>
                            <select name="categorie" class="form-select" required>
                                <option value="nourriture">مواد غذائية</option>
                                <option value="vêtements">ملابس</option>
                                <option value="argent">مال</option>
                                <option value="électroménager">أجهزة منزلية</option>
                                <option value="mobilier">أثاث</option>
                                <option value="autre">أخرى</option>
                            </select>
                        </div>
                        <div class="col-12 mb-3">
                            <label class="form-label">الوصف</label>
                            <textarea name="description" class="form-control" rows="3"></textarea>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">المبلغ (درهم)</label>
                            <input type="number" name="montant" class="form-control" step="0.01">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">الولاية</label>
                            <input type="text" name="etat" class="form-control">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">المدينة</label>
                            <input type="text" name="ville" class="form-control">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">المتبرع *</label>
                            <select name="donateur_id" class="form-select" required>
                                <option value="">اختر متبرع</option>
                                <?php
                                $donors_query = "SELECT id, nom, email FROM users WHERE type = 'donateur' OR type = 'admin' ORDER BY nom";
                                $donors_stmt = $db->prepare($donors_query);
                                $donors_stmt->execute();
                                $donors = $donors_stmt->fetchAll(PDO::FETCH_ASSOC);
                                foreach($donors as $donor): ?>
                                    <option value="<?php echo $donor['id']; ?>">
                                        <?php echo htmlspecialchars($donor['nom']); ?> (<?php echo htmlspecialchars($donor['email']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">الحالة</label>
                            <select name="statut" class="form-select">
                                <option value="disponible">متاحة</option>
                                <option value="réservé">محجوزة</option>
                                <option value="completé">مكتملة</option>
                            </select>
                        </div>
                        <div class="col-12 mb-3">
                            <label class="form-label">صور التبرع</label>
                            <input type="file" name="images[]" class="form-control" multiple accept="image/*">
                            <small class="text-muted">يمكنك اختيار عدة صور</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-primary">إضافة التبرع</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Select all checkbox
document.getElementById('selectAll')?.addEventListener('change', function() {
    const checkboxes = document.querySelectorAll('.donCheckbox');
    checkboxes.forEach(cb => cb.checked = this.checked);
    updateBulkActions();
});

// Update bulk actions visibility
function updateBulkActions() {
    const checked = document.querySelectorAll('.donCheckbox:checked').length;
    const bulkDiv = document.getElementById('bulkActions');
    const selectedSpan = document.getElementById('selectedCount');
    
    if (checked > 0) {
        bulkDiv.style.display = 'block';
        selectedSpan.textContent = checked;
    } else {
        bulkDiv.style.display = 'none';
    }
}

document.querySelectorAll('.donCheckbox').forEach(cb => {
    cb.addEventListener('change', updateBulkActions);
});

// Change status via AJAX
document.querySelectorAll('.status-select').forEach(select => {
    select.addEventListener('change', function() {
        const donId = this.dataset.id;
        const newStatus = this.value;
        
        fetch('ajax/update_donation_status.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'id=' + donId + '&status=' + newStatus
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('تم تحديث حالة التبرع بنجاح', 'success');
            } else {
                showToast('حدث خطأ: ' + data.error, 'error');
            }
        })
        .catch(error => {
            showToast('حدث خطأ في الاتصال', 'error');
        });
    });
});

function viewDonation(id) {
    window.location.href = 'don_details.php?id=' + id;
}

function editDonation(id) {
    window.location.href = 'edit_donation.php?id=' + id;
}

function bulkDelete() {
    if (confirm('هل أنت متأكد من حذف التبرعات المحددة؟')) {
        document.getElementById('bulkAction').value = 'bulk_delete';
        document.getElementById('bulkForm').submit();
    }
}

function bulkExport() {
    const selected = [];
    document.querySelectorAll('.donCheckbox:checked').forEach(cb => {
        selected.push(cb.value);
    });
    
    if (selected.length === 0) {
        showToast('الرجاء تحديد التبرعات المراد تصديرها', 'error');
        return;
    }
    
    window.location.href = 'ajax/export_selected.php?ids=' + selected.join(',') + '&type=dons';
}

function exportData() {
    window.location.href = 'ajax/export_all.php?type=dons&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>';
}
</script>

<?php require_once 'includes/admin_footer.php'; ?>
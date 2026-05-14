<?php
require_once 'includes/admin_header.php';

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 15;
$offset = ($page - 1) * $limit;

// Filters
$search = isset($_GET['search']) ? $_GET['search'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$zone_filter = isset($_GET['zone']) ? $_GET['zone'] : '';

// Build WHERE conditions
$where_conditions = ["1=1"];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(u.nom LIKE :search OR u.email LIKE :search OR u.telephone LIKE :search)";
    $params[':search'] = "%$search%";
}
if (!empty($status_filter)) {
    $where_conditions[] = "l.statut = :status";
    $params[':status'] = $status_filter;
}
if (!empty($zone_filter)) {
    $where_conditions[] = "l.zone_intervention LIKE :zone";
    $params[':zone'] = "%$zone_filter%";
}

$where_clause = "WHERE " . implode(" AND ", $where_conditions);

// Get total count
$count_query = "SELECT COUNT(*) FROM livreurs l INNER JOIN users u ON l.user_id = u.id $where_clause";
$count_stmt = $db->prepare($count_query);
foreach ($params as $key => $value) {
    $count_stmt->bindValue($key, $value);
}
$count_stmt->execute();
$total_livreurs = $count_stmt->fetchColumn();
$total_pages = ceil($total_livreurs / $limit);

// Get delivery helpers
$query = "SELECT u.id, u.nom, u.email, u.telephone, u.created_at,
          l.user_id, l.vehicule_type, l.plaque_immatriculation, l.zone_intervention, 
          l.statut as livreur_statut, l.note_moyenne, l.total_livraisons
          FROM users u
          INNER JOIN livreurs l ON u.id = l.user_id
          $where_clause 
          ORDER BY l.note_moyenne DESC, l.total_livraisons DESC
          LIMIT :limit OFFSET :offset";
$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$livreurs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle POST actions
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'toggle_status') {
        $user_id = $_POST['user_id'] ?? 0;
        $new_status = $_POST['new_status'] ?? '';
        
        $update_query = "UPDATE livreurs SET statut = :status WHERE user_id = :user_id";
        $update_stmt = $db->prepare($update_query);
        $update_stmt->bindParam(':status', $new_status);
        $update_stmt->bindParam(':user_id', $user_id);
        
        if ($update_stmt->execute()) {
            $user_status = $new_status == 'actif' ? 'active' : 'inactive';
            $update_user = $db->prepare("UPDATE users SET status = :status WHERE id = :id");
            $update_user->bindParam(':status', $user_status);
            $update_user->bindParam(':id', $user_id);
            $update_user->execute();
            
            $success = "تم " . ($new_status == 'actif' ? 'تفعيل' : 'تعطيل') . " المساعد بنجاح";
        }
        
    } elseif ($action === 'delete') {
        $user_id = $_POST['user_id'] ?? 0;
        
        $delete_query = "DELETE FROM livreurs WHERE user_id = :user_id";
        $delete_stmt = $db->prepare($delete_query);
        $delete_stmt->bindParam(':user_id', $user_id);
        
        if ($delete_stmt->execute()) {
            $update_user = $db->prepare("UPDATE users SET type = 'beneficiaire' WHERE id = :id");
            $update_user->bindParam(':id', $user_id);
            $update_user->execute();
            
            $success = "تم حذف المساعد بنجاح";
        }
        
    } elseif ($action === 'add_helper') {
        $nom = $_POST['nom'] ?? '';
        $email = $_POST['email'] ?? '';
        $telephone = $_POST['telephone'] ?? '';
        $password = password_hash($_POST['password'] ?? '123456', PASSWORD_DEFAULT);
        $vehicule_type = $_POST['vehicule_type'] ?? 'voiture';
        $plaque = $_POST['plaque_immatriculation'] ?? '';
        $zone_intervention = $_POST['zone_intervention'] ?? '';
        
        try {
            $db->beginTransaction();
            
            // Check if email exists
            $check = $db->prepare("SELECT id FROM users WHERE email = :email");
            $check->bindParam(':email', $email);
            $check->execute();
            
            if ($check->rowCount() > 0) {
                $error = "البريد الإلكتروني موجود بالفعل";
                $db->rollBack();
            } else {
                // Insert user
                $insert_user = $db->prepare("INSERT INTO users (nom, email, telephone, password, type, status, created_at) 
                                            VALUES (:nom, :email, :telephone, :password, 'livreur', 'active', NOW())");
                $insert_user->bindParam(':nom', $nom);
                $insert_user->bindParam(':email', $email);
                $insert_user->bindParam(':telephone', $telephone);
                $insert_user->bindParam(':password', $password);
                $insert_user->execute();
                
                $user_id = $db->lastInsertId();
                
                // Insert livreur
                $insert_livreur = $db->prepare("INSERT INTO livreurs (user_id, vehicule_type, plaque_immatriculation, zone_intervention, statut) 
                                               VALUES (:user_id, :vehicule_type, :plaque, :zone, 'actif')");
                $insert_livreur->bindParam(':user_id', $user_id);
                $insert_livreur->bindParam(':vehicule_type', $vehicule_type);
                $insert_livreur->bindParam(':plaque', $plaque);
                $insert_livreur->bindParam(':zone', $zone_intervention);
                $insert_livreur->execute();
                
                $db->commit();
                $success = "تم إضافة المساعد بنجاح";
                
                // Refresh page
                echo "<script>window.location.href = 'livreurs.php?success=1';</script>";
                exit();
            }
        } catch(PDOException $e) {
            $db->rollBack();
            $error = "حدث خطأ: " . $e->getMessage();
        }
        
    } elseif ($action === 'edit_helper') {
        $user_id = $_POST['user_id'] ?? 0;
        $nom = $_POST['nom'] ?? '';
        $telephone = $_POST['telephone'] ?? '';
        $vehicule_type = $_POST['vehicule_type'] ?? '';
        $plaque = $_POST['plaque_immatriculation'] ?? '';
        $zone_intervention = $_POST['zone_intervention'] ?? '';
        
        try {
            $db->beginTransaction();
            
            // Update user
            $update_user = $db->prepare("UPDATE users SET nom = :nom, telephone = :telephone WHERE id = :id");
            $update_user->bindParam(':nom', $nom);
            $update_user->bindParam(':telephone', $telephone);
            $update_user->bindParam(':id', $user_id);
            $update_user->execute();
            
            // Update livreur
            $update_livreur = $db->prepare("UPDATE livreurs SET vehicule_type = :vehicule_type, 
                                           plaque_immatriculation = :plaque, zone_intervention = :zone 
                                           WHERE user_id = :user_id");
            $update_livreur->bindParam(':vehicule_type', $vehicule_type);
            $update_livreur->bindParam(':plaque', $plaque);
            $update_livreur->bindParam(':zone', $zone_intervention);
            $update_livreur->bindParam(':user_id', $user_id);
            $update_livreur->execute();
            
            $db->commit();
            $success = "تم تحديث بيانات المساعد بنجاح";
        } catch(PDOException $e) {
            $db->rollBack();
            $error = "حدث خطأ: " . $e->getMessage();
        }
    }
}

// Get statistics
$stats_query = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN statut = 'actif' THEN 1 ELSE 0 END) as actifs,
    SUM(CASE WHEN statut = 'inactif' THEN 1 ELSE 0 END) as inactifs,
    AVG(note_moyenne) as note_moyenne,
    SUM(total_livraisons) as total_livraisons
    FROM livreurs";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->execute();
$helper_stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Get unique zones for filter
$zones_query = "SELECT DISTINCT zone_intervention FROM livreurs WHERE zone_intervention IS NOT NULL AND zone_intervention != ''";
$zones_stmt = $db->prepare($zones_query);
$zones_stmt->execute();
$zones = $zones_stmt->fetchAll(PDO::FETCH_COLUMN);

$vehicule_options = [
    'velo' => 'دراجة',
    'moto' => 'دراجة نارية',
    'voiture' => 'سيارة',
    'camion' => 'شاحنة'
];
?>

<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="stat-card text-center">
            <div class="stat-number"><?php echo $helper_stats['total'] ?? 0; ?></div>
            <div class="stat-label">إجمالي المساعدين</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card text-center">
            <div class="stat-number text-success"><?php echo $helper_stats['actifs'] ?? 0; ?></div>
            <div class="stat-label">نشطون</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card text-center">
            <div class="stat-number text-warning"><?php echo number_format($helper_stats['note_moyenne'] ?? 0, 1); ?></div>
            <div class="stat-label">متوسط التقييم</div>
            <small><i class="fas fa-star text-warning"></i> من 5</small>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card text-center">
            <div class="stat-number text-info"><?php echo $helper_stats['total_livraisons'] ?? 0; ?></div>
            <div class="stat-label">إجمالي التوصيلات</div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h5><i class="fas fa-truck me-2"></i>إدارة المساعدين</h5>
        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addHelperModal">
            <i class="fas fa-plus me-1"></i>إضافة مساعد
        </button>
    </div>
    <div class="card-body">
        <?php if($success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <!-- Filter Section -->
        <div class="filter-section">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">بحث</label>
                    <input type="text" name="search" class="form-control" placeholder="بحث بالاسم أو البريد..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">الحالة</label>
                    <select name="status" class="form-select">
                        <option value="">الكل</option>
                        <option value="actif" <?php echo $status_filter == 'actif' ? 'selected' : ''; ?>>نشط</option>
                        <option value="inactif" <?php echo $status_filter == 'inactif' ? 'selected' : ''; ?>>غير نشط</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">منطقة التدخل</label>
                    <select name="zone" class="form-select">
                        <option value="">الكل</option>
                        <?php foreach($zones as $zone): ?>
                            <option value="<?php echo htmlspecialchars($zone); ?>" <?php echo $zone_filter == $zone ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($zone); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-1 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </form>
        </div>
        
        <?php if(empty($livreurs)): ?>
            <div class="text-center py-5">
                <i class="fas fa-truck fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">لا يوجد مساعدين</h5>
                <button class="btn btn-primary mt-3" data-bs-toggle="modal" data-bs-target="#addHelperModal">
                    <i class="fas fa-plus me-1"></i>أضف أول مساعد
                </button>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>الاسم</th>
                            <th>معلومات الاتصال</th>
                            <th>وسيلة النقل</th>
                            <th>منطقة التدخل</th>
                            <th>التقييم</th>
                            <th>عدد التوصيلات</th>
                            <th>الحالة</th>
                            <th>تاريخ التسجيل</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($livreurs as $index => $livreur): ?>
                            <tr>
                                <td><?php echo $offset + $index + 1; ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($livreur['nom']); ?></strong>
                                </td>
                                <td>
                                    <div><i class="fas fa-envelope me-1 text-muted"></i> <?php echo htmlspecialchars($livreur['email']); ?></div>
                                    <div><i class="fas fa-phone me-1 text-muted"></i> <?php echo htmlspecialchars($livreur['telephone'] ?? '—'); ?></div>
                                </td>
                                <td>
                                    <?php 
                                    $veh_type = $livreur['vehicule_type'] ?? 'voiture';
                                    $icon = '';
                                    switch($veh_type) {
                                        case 'velo': $icon = '<i class="fas fa-bicycle"></i>'; break;
                                        case 'moto': $icon = '<i class="fas fa-motorcycle"></i>'; break;
                                        case 'voiture': $icon = '<i class="fas fa-car"></i>'; break;
                                        case 'camion': $icon = '<i class="fas fa-truck"></i>'; break;
                                        default: $icon = '<i class="fas fa-car"></i>';
                                    }
                                    echo $icon . ' ' . ($vehicule_options[$veh_type] ?? $veh_type);
                                    ?>
                                    <?php if(!empty($livreur['plaque_immatriculation'])): ?>
                                        <br><small class="text-muted">رقم: <?php echo $livreur['plaque_immatriculation']; ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($livreur['zone_intervention'] ?? '—'); ?></td>
                                <td>
                                    <div class="text-nowrap">
                                        <?php 
                                        $note = $livreur['note_moyenne'] ?? 0;
                                        for($i = 1; $i <= 5; $i++): ?>
                                            <i class="fas fa-star <?php echo $i <= $note ? 'text-warning' : 'text-muted'; ?>" style="font-size: 0.8rem;"></i>
                                        <?php endfor; ?>
                                        <span class="ms-1">(<?php echo number_format($note, 1); ?>)</span>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-info"><?php echo $livreur['total_livraisons'] ?? 0; ?></span>
                                </td>
                                <td>
                                    <select class="form-select form-select-sm status-select" data-id="<?php echo $livreur['id']; ?>" style="width: 100px;">
                                        <option value="actif" <?php echo ($livreur['livreur_statut'] ?? '') == 'actif' ? 'selected' : ''; ?>>نشط</option>
                                        <option value="inactif" <?php echo ($livreur['livreur_statut'] ?? '') == 'inactif' ? 'selected' : ''; ?>>غير نشط</option>
                                    </select>
                                </td>
                                <td><?php echo date('Y/m/d', strtotime($livreur['created_at'])); ?></td>
                                <td class="action-buttons text-nowrap">
                                    <button class="btn btn-sm btn-info" onclick="viewHelper(<?php echo $livreur['id']; ?>)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="btn btn-sm btn-warning" onclick="editHelper(
                                        <?php echo $livreur['id']; ?>,
                                        '<?php echo htmlspecialchars($livreur['nom']); ?>',
                                        '<?php echo htmlspecialchars($livreur['telephone'] ?? ''); ?>',
                                        '<?php echo $livreur['vehicule_type'] ?? 'voiture'; ?>',
                                        '<?php echo htmlspecialchars($livreur['plaque_immatriculation'] ?? ''); ?>',
                                        '<?php echo htmlspecialchars($livreur['zone_intervention'] ?? ''); ?>'
                                    )">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <form method="POST" class="d-inline" onsubmit="return confirmAction('هل أنت متأكد من حذف هذا المساعد؟')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="user_id" value="<?php echo $livreur['id']; ?>">
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
                                <a class="page-link" href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>&zone=<?php echo urlencode($zone_filter); ?>">
                                    <i class="fas fa-chevron-right"></i> السابق
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <?php for($i = max(1, $page-2); $i <= min($total_pages, $page+2); $i++): ?>
                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>&zone=<?php echo urlencode($zone_filter); ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>&zone=<?php echo urlencode($zone_filter); ?>">
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

<!-- Add Helper Modal -->
<div class="modal fade" id="addHelperModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-user-plus me-2"></i>إضافة مساعد جديد</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="add_helper">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-12 mb-3">
                            <label class="form-label">الاسم الكامل *</label>
                            <input type="text" name="nom" class="form-control" required>
                        </div>
                        <div class="col-12 mb-3">
                            <label class="form-label">البريد الإلكتروني *</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                        <div class="col-12 mb-3">
                            <label class="form-label">رقم الهاتف</label>
                            <input type="tel" name="telephone" class="form-control">
                        </div>
                        <div class="col-12 mb-3">
                            <label class="form-label">كلمة المرور</label>
                            <input type="text" name="password" class="form-control" value="123456">
                            <small class="text-muted">الافتراضي: 123456</small>
                        </div>
                        <div class="col-12 mb-3">
                            <label class="form-label">وسيلة النقل</label>
                            <select name="vehicule_type" class="form-select">
                                <option value="velo">دراجة</option>
                                <option value="moto">دراجة نارية</option>
                                <option value="voiture" selected>سيارة</option>
                                <option value="camion">شاحنة</option>
                            </select>
                        </div>
                        <div class="col-12 mb-3">
                            <label class="form-label">رقم اللوحة</label>
                            <input type="text" name="plaque_immatriculation" class="form-control">
                        </div>
                        <div class="col-12 mb-3">
                            <label class="form-label">منطقة التدخل</label>
                            <input type="text" name="zone_intervention" class="form-control" placeholder="مثال: الدار البيضاء, الرباط...">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-primary">إضافة</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Helper Modal -->
<div class="modal fade" id="editHelperModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-edit me-2"></i>تعديل بيانات المساعد</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="edit_helper">
                <input type="hidden" name="user_id" id="edit_user_id">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-12 mb-3">
                            <label class="form-label">الاسم الكامل</label>
                            <input type="text" name="nom" id="edit_nom" class="form-control" required>
                        </div>
                        <div class="col-12 mb-3">
                            <label class="form-label">رقم الهاتف</label>
                            <input type="tel" name="telephone" id="edit_telephone" class="form-control">
                        </div>
                        <div class="col-12 mb-3">
                            <label class="form-label">وسيلة النقل</label>
                            <select name="vehicule_type" id="edit_vehicule_type" class="form-select">
                                <option value="velo">دراجة</option>
                                <option value="moto">دراجة نارية</option>
                                <option value="voiture">سيارة</option>
                                <option value="camion">شاحنة</option>
                            </select>
                        </div>
                        <div class="col-12 mb-3">
                            <label class="form-label">رقم اللوحة</label>
                            <input type="text" name="plaque_immatriculation" id="edit_plaque" class="form-control">
                        </div>
                        <div class="col-12 mb-3">
                            <label class="form-label">منطقة التدخل</label>
                            <input type="text" name="zone_intervention" id="edit_zone" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-primary">حفظ التغييرات</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Change status via AJAX
document.querySelectorAll('.status-select').forEach(select => {
    select.addEventListener('change', function() {
        const userId = this.dataset.id;
        const newStatus = this.value;
        
        // Create form data
        const formData = new FormData();
        formData.append('action', 'toggle_status');
        formData.append('user_id', userId);
        formData.append('new_status', newStatus);
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(() => {
            showToast('تم تحديث حالة المساعد بنجاح', 'success');
            setTimeout(() => location.reload(), 1000);
        })
        .catch(error => {
            showToast('حدث خطأ في الاتصال', 'error');
            location.reload();
        });
    });
});

function viewHelper(id) {
    window.location.href = 'livreur_details.php?id=' + id;
}

function editHelper(id, nom, telephone, vehiculeType, plaque, zone) {
    document.getElementById('edit_user_id').value = id;
    document.getElementById('edit_nom').value = nom;
    document.getElementById('edit_telephone').value = telephone || '';
    document.getElementById('edit_vehicule_type').value = vehiculeType || 'voiture';
    document.getElementById('edit_plaque').value = plaque || '';
    document.getElementById('edit_zone').value = zone || '';
    
    new bootstrap.Modal(document.getElementById('editHelperModal')).show();
}
</script>

<?php require_once 'includes/admin_footer.php'; ?>
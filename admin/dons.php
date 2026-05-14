<?php
require_once 'includes/admin_header.php';

// Handle delete action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $don_id = $_POST['don_id'] ?? 0;
    $delete_query = "DELETE FROM dons WHERE id = :id";
    $delete_stmt = $db->prepare($delete_query);
    $delete_stmt->bindParam(':id', $don_id);
    if ($delete_stmt->execute()) {
        $success = "تم حذف التبرع بنجاح";
    }
}

// Handle update donation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_donation'])) {
    $id = $_POST['id'] ?? 0;
    $titre = $_POST['titre'] ?? '';
    $description = $_POST['description'] ?? '';
    $categorie = $_POST['categorie'] ?? '';
    $montant = $_POST['montant'] ?? null;
    $etat = $_POST['etat'] ?? '';
    $ville = $_POST['ville'] ?? '';
    $statut = $_POST['statut'] ?? '';
    
    $update_query = "UPDATE dons SET titre = :titre, description = :description, categorie = :categorie, 
                     montant = :montant, etat = :etat, ville = :ville, statut = :statut WHERE id = :id";
    $update_stmt = $db->prepare($update_query);
    $update_stmt->bindParam(':titre', $titre);
    $update_stmt->bindParam(':description', $description);
    $update_stmt->bindParam(':categorie', $categorie);
    $update_stmt->bindParam(':montant', $montant);
    $update_stmt->bindParam(':etat', $etat);
    $update_stmt->bindParam(':ville', $ville);
    $update_stmt->bindParam(':statut', $statut);
    $update_stmt->bindParam(':id', $id);
    
    if ($update_stmt->execute()) {
        $success = "تم تحديث التبرع بنجاح";
        header("Location: dons.php?updated=1");
        exit();
    }
}

// Handle add donation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_donation'])) {
    $titre = $_POST['titre'] ?? '';
    $description = $_POST['description'] ?? '';
    $categorie = $_POST['categorie'] ?? '';
    $montant = $_POST['montant'] ?? null;
    $etat = $_POST['etat'] ?? '';
    $ville = $_POST['ville'] ?? '';
    $donateur_id = $_POST['donateur_id'] ?? 0;
    $statut = $_POST['statut'] ?? 'disponible';
    
    $insert_query = "INSERT INTO dons (titre, description, categorie, montant, etat, ville, donateur_id, statut, created_at) 
                     VALUES (:titre, :description, :categorie, :montant, :etat, :ville, :donateur_id, :statut, NOW())";
    $insert_stmt = $db->prepare($insert_query);
    $insert_stmt->bindParam(':titre', $titre);
    $insert_stmt->bindParam(':description', $description);
    $insert_stmt->bindParam(':categorie', $categorie);
    $insert_stmt->bindParam(':montant', $montant);
    $insert_stmt->bindParam(':etat', $etat);
    $insert_stmt->bindParam(':ville', $ville);
    $insert_stmt->bindParam(':donateur_id', $donateur_id);
    $insert_stmt->bindParam(':statut', $statut);
    
    if ($insert_stmt->execute()) {
        $success = "تم إضافة التبرع بنجاح";
        header("Location: dons.php?added=1");
        exit();
    }
}

// Get view ID and edit ID
$view_id = isset($_GET['view']) ? (int)$_GET['view'] : 0;
$edit_id = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;

// If viewing single donation
if ($view_id > 0) {
    $view_query = "SELECT d.*, u.nom as donateur_nom, u.email as donateur_email, u.telephone as donateur_telephone 
                   FROM dons d 
                   INNER JOIN users u ON d.donateur_id = u.id 
                   WHERE d.id = :id";
    $view_stmt = $db->prepare($view_query);
    $view_stmt->bindParam(':id', $view_id);
    $view_stmt->execute();
    $donation_view = $view_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$donation_view) {
        header('Location: dons.php');
        exit();
    }
    
    // Get requests for this donation
    $requests_query = "SELECT de.*, u.nom as beneficiaire_nom, u.email as beneficiaire_email, u.telephone as beneficiaire_telephone
                       FROM demandes de
                       INNER JOIN users u ON de.beneficiaire_id = u.id
                       WHERE de.don_id = :don_id
                       ORDER BY de.created_at DESC";
    $requests_stmt = $db->prepare($requests_query);
    $requests_stmt->bindParam(':don_id', $view_id);
    $requests_stmt->execute();
    $donation_requests = $requests_stmt->fetchAll(PDO::FETCH_ASSOC);
}

// If editing donation
if ($edit_id > 0) {
    $edit_query = "SELECT * FROM dons WHERE id = :id";
    $edit_stmt = $db->prepare($edit_query);
    $edit_stmt->bindParam(':id', $edit_id);
    $edit_stmt->execute();
    $edit_don = $edit_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$edit_don) {
        header('Location: dons.php');
        exit();
    }
}

// Pagination for list view
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 15;
$offset = ($page - 1) * $limit;

// Filters
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$categorie_filter = $_GET['categorie'] ?? '';

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
$query = "SELECT d.*, u.nom as donateur_nom, u.email as donateur_email 
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

// Get statistics
$stats_query = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN statut = 'disponible' THEN 1 ELSE 0 END) as disponibles,
    SUM(CASE WHEN statut = 'réservé' THEN 1 ELSE 0 END) as reserves,
    SUM(CASE WHEN statut = 'completé' THEN 1 ELSE 0 END) as completes,
    SUM(CASE WHEN statut = 'annulé' THEN 1 ELSE 0 END) as annules,
    SUM(montant) as total_montant
    FROM dons";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->execute();
$don_stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Get categories for filter
$categories_query = "SELECT DISTINCT categorie FROM dons WHERE categorie IS NOT NULL AND categorie != ''";
$categories_stmt = $db->prepare($categories_query);
$categories_stmt->execute();
$categories = $categories_stmt->fetchAll(PDO::FETCH_COLUMN);

// Get donors for add form
$donors_query = "SELECT id, nom, email FROM users WHERE type IN ('donateur', 'admin') ORDER BY nom";
$donors_stmt = $db->prepare($donors_query);
$donors_stmt->execute();
$donors = $donors_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- Statistics Cards -->
<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="stat-card text-center">
            <div class="stat-number text-primary"><?php echo $don_stats['total'] ?? 0; ?></div>
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

<?php if(isset($_GET['added']) || isset($_GET['updated'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-2"></i>
        <?php echo isset($_GET['added']) ? 'تم إضافة التبرع بنجاح' : 'تم تحديث التبرع بنجاح'; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if(isset($success) && $success): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if($view_id > 0 && isset($donation_view)): ?>
    <!-- ============================================ -->
    <!-- VIEW DONATION DETAILS PAGE -->
    <!-- ============================================ -->
    <div class="card">
        <div class="card-header">
            <h5><i class="fas fa-eye me-2"></i>تفاصيل التبرع</h5>
            <div>
                <a href="dons.php?edit=<?php echo $donation_view['id']; ?>" class="btn btn-sm btn-warning">
                    <i class="fas fa-edit me-1"></i>تعديل
                </a>
                <a href="dons.php" class="btn btn-sm btn-secondary">
                    <i class="fas fa-arrow-right me-1"></i>رجوع
                </a>
            </div>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <table class="table table-borderless">
                        <tr>
                            <th width="150">العنوان:</th>
                            <td><strong><?php echo htmlspecialchars($donation_view['titre']); ?></strong></td>
                        </tr>
                        <tr>
                            <th>الوصف:</th>
                            <td><?php echo nl2br(htmlspecialchars($donation_view['description'] ?? '')); ?></td>
                        </tr>
                        <tr>
                            <th>الفئة:</th>
                            <td><span class="badge bg-info"><?php echo ucfirst($donation_view['categorie']); ?></span></td>
                        </tr>
                        <tr>
                            <th>المبلغ:</th>
                            <td class="text-success fw-bold"><?php echo $donation_view['montant'] ? number_format($donation_view['montant'], 2) . ' درهم' : 'غير محدد'; ?></td>
                        </tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <table class="table table-borderless">
                        <tr>
                            <th width="150">المتبرع:</th>
                            <td>
                                <strong><?php echo htmlspecialchars($donation_view['donateur_nom']); ?></strong><br>
                                <small class="text-muted"><?php echo htmlspecialchars($donation_view['donateur_email']); ?></small><br>
                                <small><?php echo htmlspecialchars($donation_view['donateur_telephone'] ?? ''); ?></small>
                            </td>
                        </tr>
                        <tr>
                            <th>الموقع:</th>
                            <td>
                                <?php echo htmlspecialchars($donation_view['ville'] ?? ''); ?>
                                <?php if(!empty($donation_view['etat'])): ?>
                                    , <?php echo htmlspecialchars($donation_view['etat']); ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th>الحالة:</th>
                            <td>
                                <?php
                                $status_badges = [
                                    'disponible' => '<span class="badge bg-success">متاحة</span>',
                                    'réservé' => '<span class="badge bg-warning">محجوزة</span>',
                                    'completé' => '<span class="badge bg-info">مكتملة</span>',
                                    'annulé' => '<span class="badge bg-danger">ملغية</span>'
                                ];
                                echo $status_badges[$donation_view['statut']] ?? '<span class="badge bg-secondary">غير محدد</span>';
                                ?>
                            </td>
                        </tr>
                        <tr>
                            <th>تاريخ الإضافة:</th>
                            <td><?php echo date('Y/m/d H:i', strtotime($donation_view['created_at'])); ?></td>
                        </tr>
                    </table>
                </div>
            </div>
            
            <hr>
            
            <h6 class="mt-3"><i class="fas fa-hand-paper me-2"></i>الطلبات المرتبطة بهذا التبرع</h6>
            <?php if(empty($donation_requests)): ?>
                <p class="text-muted">لا توجد طلبات لهذا التبرع</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>المستفيد</th>
                                <th>الكمية المطلوبة</th>
                                <th>السبب</th>
                                <th>الحالة</th>
                                <th>التاريخ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($donation_requests as $req): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($req['beneficiaire_nom']); ?></strong><br>
                                        <small><?php echo htmlspecialchars($req['beneficiaire_email']); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($req['quantite_demandee'] ?? '—'); ?></td>
                                    <td><?php echo substr(htmlspecialchars($req['raison'] ?? ''), 0, 50); ?></td>
                                    <td>
                                        <?php
                                        $req_status = [
                                            'en_attente' => '<span class="badge bg-warning">في الانتظار</span>',
                                            'accepté' => '<span class="badge bg-success">مقبول</span>',
                                            'refusé' => '<span class="badge bg-danger">مرفوض</span>'
                                        ];
                                        echo $req_status[$req['statut']] ?? '<span class="badge bg-secondary">غير محدد</span>';
                                        ?>
                                    </td>
                                    <td><?php echo date('Y/m/d', strtotime($req['created_at'])); ?></td>
                                </table>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

<?php elseif($edit_id > 0 && isset($edit_don)): ?>
    <!-- ============================================ -->
    <!-- EDIT DONATION FORM -->
    <!-- ============================================ -->
    <div class="card">
        <div class="card-header">
            <h5><i class="fas fa-edit me-2"></i>تعديل التبرع</h5>
            <a href="dons.php" class="btn btn-sm btn-secondary">
                <i class="fas fa-arrow-right me-1"></i>رجوع
            </a>
        </div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="update_donation" value="1">
                <input type="hidden" name="id" value="<?php echo $edit_don['id']; ?>">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">عنوان التبرع *</label>
                        <input type="text" name="titre" class="form-control" value="<?php echo htmlspecialchars($edit_don['titre']); ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">الفئة</label>
                        <select name="categorie" class="form-select">
                            <option value="nourriture" <?php echo $edit_don['categorie'] == 'nourriture' ? 'selected' : ''; ?>>مواد غذائية</option>
                            <option value="vêtements" <?php echo $edit_don['categorie'] == 'vêtements' ? 'selected' : ''; ?>>ملابس</option>
                            <option value="argent" <?php echo $edit_don['categorie'] == 'argent' ? 'selected' : ''; ?>>مال</option>
                            <option value="électroménager" <?php echo $edit_don['categorie'] == 'électroménager' ? 'selected' : ''; ?>>أجهزة منزلية</option>
                            <option value="mobilier" <?php echo $edit_don['categorie'] == 'mobilier' ? 'selected' : ''; ?>>أثاث</option>
                            <option value="autre" <?php echo $edit_don['categorie'] == 'autre' ? 'selected' : ''; ?>>أخرى</option>
                        </select>
                    </div>
                    <div class="col-12 mb-3">
                        <label class="form-label">الوصف</label>
                        <textarea name="description" class="form-control" rows="4"><?php echo htmlspecialchars($edit_don['description'] ?? ''); ?></textarea>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">المبلغ (درهم)</label>
                        <input type="number" name="montant" class="form-control" step="0.01" value="<?php echo $edit_don['montant']; ?>">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">الولاية</label>
                        <input type="text" name="etat" class="form-control" value="<?php echo htmlspecialchars($edit_don['etat'] ?? ''); ?>">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">المدينة</label>
                        <input type="text" name="ville" class="form-control" value="<?php echo htmlspecialchars($edit_don['ville'] ?? ''); ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">الحالة</label>
                        <select name="statut" class="form-select">
                            <option value="disponible" <?php echo $edit_don['statut'] == 'disponible' ? 'selected' : ''; ?>>متاحة</option>
                            <option value="réservé" <?php echo $edit_don['statut'] == 'réservé' ? 'selected' : ''; ?>>محجوزة</option>
                            <option value="completé" <?php echo $edit_don['statut'] == 'completé' ? 'selected' : ''; ?>>مكتملة</option>
                            <option value="annulé" <?php echo $edit_don['statut'] == 'annulé' ? 'selected' : ''; ?>>ملغية</option>
                        </select>
                    </div>
                </div>
                <div class="mt-3">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>حفظ التغييرات
                    </button>
                    <a href="dons.php" class="btn btn-secondary">إلغاء</a>
                </div>
            </form>
        </div>
    </div>

<?php else: ?>
    <!-- ============================================ -->
    <!-- LIST VIEW WITH FILTERS -->
    <!-- ============================================ -->
    <div class="card">
        <div class="card-header">
            <h5><i class="fas fa-gift me-2"></i>قائمة التبرعات</h5>
            <div>
                <button class="btn btn-sm btn-success me-2" onclick="exportData()">
                    <i class="fas fa-file-excel me-1"></i>تصدير
                </button>
                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addDonModal">
                    <i class="fas fa-plus me-1"></i>إضافة تبرع
                </button>
            </div>
        </div>
        <div class="card-body">
            <!-- Filter Section -->
            <div class="filter-section">
                <form method="GET" class="row g-3">
                    <div class="col-md-5">
                        <label class="form-label">بحث</label>
                        <input type="text" name="search" class="form-control" placeholder="بحث بالعنوان أو المتبرع..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">الحالة</label>
                        <select name="status" class="form-select">
                            <option value="">الكل</option>
                            <option value="disponible" <?php echo $status_filter == 'disponible' ? 'selected' : ''; ?>>متاحة</option>
                            <option value="réservé" <?php echo $status_filter == 'réservé' ? 'selected' : ''; ?>>محجوزة</option>
                            <option value="completé" <?php echo $status_filter == 'completé' ? 'selected' : ''; ?>>مكتملة</option>
                            <option value="annulé" <?php echo $status_filter == 'annulé' ? 'selected' : ''; ?>>ملغية</option>
                        </select>
                    </div>
                    <div class="col-md-3">
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
                    <button class="btn btn-primary mt-3" data-bs-toggle="modal" data-bs-target="#addDonModal">
                        <i class="fas fa-plus me-1"></i>أضف أول تبرع
                    </button>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
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
                                    <td><?php echo $offset + $index + 1; ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($don['titre']); ?></strong>
                                        <br>
                                        <small class="text-muted"><?php echo substr(htmlspecialchars($don['description'] ?? ''), 0, 40); ?></small>
                                    </td>
                                    <td><span class="badge bg-info"><?php echo ucfirst($don['categorie']); ?></span></td>
                                    <td>
                                        <?php if($don['montant']): ?>
                                            <strong class="text-success"><?php echo number_format($don['montant'], 2); ?> درهم</strong>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($don['donateur_nom']); ?>
                                        <br>
                                        <small class="text-muted"><?php echo htmlspecialchars($don['donateur_email']); ?></small>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($don['ville'] ?? '—'); ?>
                                        <?php if(!empty($don['etat'])): ?>
                                            <br><small><?php echo htmlspecialchars($don['etat']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $badges = [
                                            'disponible' => '<span class="badge bg-success">متاحة</span>',
                                            'réservé' => '<span class="badge bg-warning">محجوزة</span>',
                                            'completé' => '<span class="badge bg-info">مكتملة</span>',
                                            'annulé' => '<span class="badge bg-danger">ملغية</span>'
                                        ];
                                        echo $badges[$don['statut']] ?? '<span class="badge bg-secondary">غير محدد</span>';
                                        ?>
                                    </td>
                                    <td>
                                        <?php echo date('Y/m/d', strtotime($don['created_at'])); ?>
                                        <br>
                                        <small class="text-muted"><?php echo date('H:i', strtotime($don['created_at'])); ?></small>
                                    </td>
                                    <td class="action-buttons text-nowrap">
                                        <a href="dons.php?view=<?php echo $don['id']; ?>" class="btn btn-sm btn-info">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="dons.php?edit=<?php echo $don['id']; ?>" class="btn btn-sm btn-warning">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('هل أنت متأكد من حذف هذا التبرع؟')">
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
                
                <!-- Pagination -->
                <?php if($total_pages > 1): ?>
                    <nav aria-label="Page navigation">
                        <ul class="pagination justify-content-center">
                            <?php if($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>&categorie=<?php echo urlencode($categorie_filter); ?>">
                                        <i class="fas fa-chevron-right"></i> السابق
                                    </a>
                                </li>
                            <?php endif; ?>
                            
                            <?php for($i = max(1, $page-2); $i <= min($total_pages, $page+2); $i++): ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>&categorie=<?php echo urlencode($categorie_filter); ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>&categorie=<?php echo urlencode($categorie_filter); ?>">
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
<?php endif; ?>

<!-- ============================================ -->
<!-- ADD DONATION MODAL -->
<!-- ============================================ -->
<div class="modal fade" id="addDonModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-plus me-2"></i>إضافة تبرع جديد</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="add_donation" value="1">
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
                                <?php foreach($donors as $donor): ?>
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
function exportData() {
    var search = '<?php echo htmlspecialchars($search); ?>';
    var status = '<?php echo htmlspecialchars($status_filter); ?>';
    var categorie = '<?php echo htmlspecialchars($categorie_filter); ?>';
    window.location.href = 'ajax/export_all.php?type=dons&search=' + encodeURIComponent(search) + '&status=' + encodeURIComponent(status) + '&categorie=' + encodeURIComponent(categorie);
}
</script>

<?php require_once 'includes/admin_footer.php'; ?>
<?php
require_once 'includes/admin_header.php';

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 15;
$offset = ($page - 1) * $limit;

// Filters
$search = $_GET['search'] ?? '';
$type_filter = $_GET['type'] ?? '';
$status_filter = $_GET['status'] ?? '';

// Build query
$where_conditions = ["type != 'admin'"];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(nom LIKE :search OR email LIKE :search OR telephone LIKE :search)";
    $params[':search'] = "%$search%";
}
if (!empty($type_filter)) {
    $where_conditions[] = "type = :type";
    $params[':type'] = $type_filter;
}
if (!empty($status_filter)) {
    $where_conditions[] = "status = :status";
    $params[':status'] = $status_filter;
}

$where_clause = "WHERE " . implode(" AND ", $where_conditions);

// Get total count
$count_query = "SELECT COUNT(*) FROM users $where_clause";
$count_stmt = $db->prepare($count_query);
foreach ($params as $key => $value) {
    $count_stmt->bindValue($key, $value);
}
$count_stmt->execute();
$total_users = $count_stmt->fetchColumn();
$total_pages = ceil($total_users / $limit);

// Get users
$query = "SELECT * FROM users $where_clause ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle user actions
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $user_id = $_POST['user_id'] ?? 0;
    
    if ($action === 'toggle_status') {
        // Get current status
        $status_query = "SELECT status FROM users WHERE id = :id";
        $status_stmt = $db->prepare($status_query);
        $status_stmt->bindParam(':id', $user_id);
        $status_stmt->execute();
        $current_status = $status_stmt->fetchColumn();
        
        $new_status = $current_status === 'active' ? 'inactive' : 'active';
        $update_query = "UPDATE users SET status = :status WHERE id = :id";
        $update_stmt = $db->prepare($update_query);
        $update_stmt->bindParam(':status', $new_status);
        $update_stmt->bindParam(':id', $user_id);
        
        if ($update_stmt->execute()) {
            $success = "تم " . ($new_status === 'active' ? 'تفعيل' : 'تعطيل') . " المستخدم بنجاح";
        }
    } elseif ($action === 'delete') {
        $delete_query = "DELETE FROM users WHERE id = :id AND type != 'admin'";
        $delete_stmt = $db->prepare($delete_query);
        $delete_stmt->bindParam(':id', $user_id);
        
        if ($delete_stmt->execute()) {
            $success = "تم حذف المستخدم بنجاح";
        }
    } elseif ($action === 'reset_password') {
        $new_password = $_POST['new_password'] ?? '123456';
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $update_query = "UPDATE users SET password = :password WHERE id = :id";
        $update_stmt = $db->prepare($update_query);
        $update_stmt->bindParam(':password', $hashed_password);
        $update_stmt->bindParam(':id', $user_id);
        
        if ($update_stmt->execute()) {
            $success = "تم إعادة تعيين كلمة المرور بنجاح. كلمة المرور الجديدة: $new_password";
        }
    }
}

// Get user type counts for stats
$type_stats = [];
$type_query = "SELECT type, COUNT(*) as count FROM users WHERE type != 'admin' GROUP BY type";
$type_stmt = $db->prepare($type_query);
$type_stmt->execute();
$type_stats = $type_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="stat-card text-center">
            <div class="stat-number"><?php echo $total_users; ?></div>
            <div class="stat-label">إجمالي المستخدمين</div>
        </div>
    </div>
    <?php foreach($type_stats as $stat): ?>
        <div class="col-md-3">
            <div class="stat-card text-center">
                <div class="stat-number"><?php echo $stat['count']; ?></div>
                <div class="stat-label">
                    <?php 
                    $labels = ['donateur' => 'متبرع', 'beneficiaire' => 'مستفيد', 'livreur' => 'مساعد'];
                    echo $labels[$stat['type']] ?? $stat['type'];
                    ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="card">
    <div class="card-header">
        <h5><i class="fas fa-users me-2"></i>إدارة المستخدمين</h5>
        <a href="#" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
            <i class="fas fa-plus me-1"></i>إضافة مستخدم
        </a>
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
                    <label class="form-label">نوع المستخدم</label>
                    <select name="type" class="form-select">
                        <option value="">الكل</option>
                        <option value="donateur" <?php echo $type_filter == 'donateur' ? 'selected' : ''; ?>>متبرع</option>
                        <option value="beneficiaire" <?php echo $type_filter == 'beneficiaire' ? 'selected' : ''; ?>>مستفيد</option>
                        <option value="livreur" <?php echo $type_filter == 'livreur' ? 'selected' : ''; ?>>مساعد</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">الحالة</label>
                    <select name="status" class="form-select">
                        <option value="">الكل</option>
                        <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>نشط</option>
                        <option value="inactive" <?php echo $status_filter == 'inactive' ? 'selected' : ''; ?>>غير نشط</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-filter me-2"></i>تصفية
                    </button>
                </div>
            </form>
        </div>
        
        <?php if(empty($users)): ?>
            <div class="text-center py-5">
                <i class="fas fa-users fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">لا يوجد مستخدمين</h5>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>الاسم</th>
                            <th>البريد الإلكتروني</th>
                            <th>رقم الهاتف</th>
                            <th>النوع</th>
                            <th>الحالة</th>
                            <th>تاريخ التسجيل</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($users as $index => $user): ?>
                            <tr>
                                <td><?php echo $offset + $index + 1; ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($user['nom']); ?></strong>
                                </td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td><?php echo htmlspecialchars($user['telephone'] ?? '—'); ?></td>
                                <td>
                                    <?php
                                    $type_badges = [
                                        'donateur' => '<span class="badge bg-primary"><i class="fas fa-hand-holding-heart me-1"></i>متبرع</span>',
                                        'beneficiaire' => '<span class="badge bg-success"><i class="fas fa-hands-helping me-1"></i>مستفيد</span>',
                                        'livreur' => '<span class="badge bg-warning"><i class="fas fa-truck me-1"></i>مساعد</span>'
                                    ];
                                    echo $type_badges[$user['type']] ?? '<span class="badge bg-secondary">غير محدد</span>';
                                    ?>
                                </td>
                                <td>
                                    <span class="badge <?php echo $user['status'] == 'active' ? 'badge-success' : 'badge-danger'; ?>">
                                        <?php echo $user['status'] == 'active' ? 'نشط' : 'غير نشط'; ?>
                                    </span>
                                </td>
                                <td><?php echo date('Y/m/d', strtotime($user['created_at'])); ?></td>
                                <td class="action-buttons">
                                    <button class="btn btn-sm btn-info" onclick="viewUser(<?php echo $user['id']; ?>)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <form method="POST" class="d-inline" onsubmit="return confirmAction('هل أنت متأكد من تغيير حالة هذا المستخدم؟')">
                                        <input type="hidden" name="action" value="toggle_status">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <button type="submit" class="btn btn-sm <?php echo $user['status'] == 'active' ? 'btn-warning' : 'btn-success'; ?>">
                                            <i class="fas <?php echo $user['status'] == 'active' ? 'fa-ban' : 'fa-check'; ?>"></i>
                                        </button>
                                    </form>
                                    <button class="btn btn-sm btn-primary" onclick="resetPassword(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['nom']); ?>')">
                                        <i class="fas fa-key"></i>
                                    </button>
                                    <form method="POST" class="d-inline" onsubmit="return confirmAction('هل أنت متأكد من حذف هذا المستخدم؟')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
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
                                <a class="page-link" href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>&type=<?php echo $type_filter; ?>&status=<?php echo $status_filter; ?>">
                                    <i class="fas fa-chevron-right"></i> السابق
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <?php for($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&type=<?php echo $type_filter; ?>&status=<?php echo $status_filter; ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>&type=<?php echo $type_filter; ?>&status=<?php echo $status_filter; ?>">
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

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-user-plus me-2"></i>إضافة مستخدم جديد</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="ajax/add_user.php">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">الاسم الكامل</label>
                        <input type="text" name="nom" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">البريد الإلكتروني</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">رقم الهاتف</label>
                        <input type="tel" name="telephone" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">نوع المستخدم</label>
                        <select name="type" class="form-select" required>
                            <option value="donateur">متبرع</option>
                            <option value="beneficiaire">مستفيد</option>
                            <option value="livreur">مساعد</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">كلمة المرور</label>
                        <input type="password" name="password" class="form-control" value="123456">
                        <small class="text-muted">الافتراضي: 123456</small>
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

<!-- Reset Password Modal -->
<div class="modal fade" id="resetPasswordModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-key me-2"></i>إعادة تعيين كلمة المرور</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
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
function viewUser(id) {
    // Implement view user details
    window.location.href = 'utilisateur_details.php?id=' + id;
}

function resetPassword(id, name) {
    document.getElementById('reset_user_id').value = id;
    document.getElementById('reset_user_name').textContent = name;
    new bootstrap.Modal(document.getElementById('resetPasswordModal')).show();
}
</script>

<?php require_once 'includes/admin_footer.php'; ?>
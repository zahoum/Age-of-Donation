<?php
require_once 'includes/admin_header.php';

$database = new Database();
$db = $database->getConnection();

// Handle actions (delete, mark as read, reply, etc.)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $message_id = $_POST['message_id'] ?? 0;
        
        switch ($_POST['action']) {
            case 'mark_read':
                $sql = "UPDATE contact_messages SET status = 'read' WHERE id = :id";
                break;
            case 'mark_replied':
                $sql = "UPDATE contact_messages SET status = 'replied', replied_at = NOW() WHERE id = :id";
                break;
            case 'delete':
                $sql = "DELETE FROM contact_messages WHERE id = :id";
                break;
            case 'archive':
                $sql = "UPDATE contact_messages SET status = 'archived' WHERE id = :id";
                break;
            default:
                $sql = null;
                break;
        }
        
        if (isset($sql)) {
            $stmt = $db->prepare($sql);
            $stmt->bindParam(':id', $message_id, PDO::PARAM_INT);
            $stmt->execute();
            
            header('Location: messages.php');
            exit();
        }
    }
}

// Handle reply to contact message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_reply'])) {
    $message_id = $_POST['message_id'] ?? 0;
    $reply_message = $_POST['reply_message'] ?? '';
    $to_email = $_POST['to_email'] ?? '';
    $subject = $_POST['subject'] ?? '';
    
    $sql = "UPDATE contact_messages SET status = 'replied', reply_message = :reply, replied_at = NOW() WHERE id = :id";
    $stmt = $db->prepare($sql);
    $stmt->bindParam(':reply', $reply_message);
    $stmt->bindParam(':id', $message_id);
    $stmt->execute();
    
    // Here you can add mail() function to send actual email
    // mail($to_email, $subject, $reply_message, "From: admin@ageofdonnation.org");
    
    header('Location: messages.php?replied=1');
    exit();
}

// Handle send notification to users
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_notification'])) {
    $notification_type = $_POST['notification_type'] ?? '';
    $notification_title = $_POST['notification_title'] ?? '';
    $notification_message = $_POST['notification_message'] ?? '';
    $specific_user_id = $_POST['specific_user_id'] ?? 0;
    
    $target_users = [];
    
    // Get target users based on type
    if ($notification_type == 'all') {
        $query = "SELECT id FROM users WHERE type != 'admin'";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $target_users = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } elseif ($notification_type == 'donateurs') {
        $query = "SELECT id FROM users WHERE type = 'donateur'";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $target_users = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } elseif ($notification_type == 'beneficiaires') {
        $query = "SELECT id FROM users WHERE type = 'beneficiaire'";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $target_users = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } elseif ($notification_type == 'livreurs') {
        $query = "SELECT id FROM users WHERE type = 'livreur'";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $target_users = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } elseif ($notification_type == 'specific' && $specific_user_id > 0) {
        $target_users = [$specific_user_id];
    }
    
    // Insert notifications
    $success_count = 0;
    foreach ($target_users as $user_id) {
        $insert = "INSERT INTO notifications (user_id, type, titre, message, created_at) 
                   VALUES (:user_id, 'admin', :titre, :message, NOW())";
        $stmt = $db->prepare($insert);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':titre', $notification_title);
        $stmt->bindParam(':message', $notification_message);
        if ($stmt->execute()) {
            $success_count++;
        }
    }
    
    if ($success_count > 0) {
        header('Location: messages.php?notification_sent=1&count=' . $success_count);
        exit();
    }
}

// Get filter parameters
$filter_status = $_GET['status'] ?? 'all';
$filter_search = $_GET['search'] ?? '';

// Build query with filters
$query = "SELECT * FROM contact_messages WHERE 1=1";
$params = [];

if ($filter_status !== 'all') {
    $query .= " AND status = :status";
    $params[':status'] = $filter_status;
}

if (!empty($filter_search)) {
    $query .= " AND (name LIKE :search OR email LIKE :search OR subject LIKE :search OR message LIKE :search)";
    $params[':search'] = "%$filter_search%";
}

$query .= " ORDER BY 
    CASE WHEN status = 'new' THEN 1 ELSE 2 END,
    created_at DESC";

$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get total count for stats
$count_query = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'new' THEN 1 ELSE 0 END) as new_count,
    SUM(CASE WHEN status = 'read' THEN 1 ELSE 0 END) as read_count,
    SUM(CASE WHEN status = 'replied' THEN 1 ELSE 0 END) as replied_count
    FROM contact_messages";
$count_stmt = $db->prepare($count_query);
$count_stmt->execute();
$stats = $count_stmt->fetch(PDO::FETCH_ASSOC);

// Get users for specific notification
$users_query = "SELECT id, nom, email, type FROM users WHERE type != 'admin' ORDER BY nom";
$users_stmt = $db->prepare($users_query);
$users_stmt->execute();
$users_list = $users_stmt->fetchAll(PDO::FETCH_ASSOC);

// Function to get status badge
function getMessageStatusBadge($status) {
    $badges = [
        'new' => '<span class="badge bg-danger">جديد</span>',
        'read' => '<span class="badge bg-info">مقروء</span>',
        'replied' => '<span class="badge bg-success">تم الرد</span>',
        'archived' => '<span class="badge bg-secondary">مؤرشف</span>'
    ];
    return $badges[$status] ?? '<span class="badge bg-secondary">غير معروف</span>';
}
?>

<?php if(isset($_GET['replied'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-2"></i>تم إرسال الرد بنجاح
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if(isset($_GET['notification_sent'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-bell me-2"></i>تم إرسال الإشعار إلى <?php echo $_GET['count']; ?> مستخدم بنجاح
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="stat-card text-center">
            <div class="stat-number text-primary"><?php echo $stats['total'] ?? 0; ?></div>
            <div class="stat-label">إجمالي الرسائل</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card text-center">
            <div class="stat-number text-danger"><?php echo $stats['new_count'] ?? 0; ?></div>
            <div class="stat-label">رسائل جديدة</div>
            <small class="text-muted">بحاجة إلى رد</small>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card text-center">
            <div class="stat-number text-info"><?php echo $stats['read_count'] ?? 0; ?></div>
            <div class="stat-label">رسائل مقروءة</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card text-center">
            <div class="stat-number text-success"><?php echo $stats['replied_count'] ?? 0; ?></div>
            <div class="stat-label">تم الرد عليها</div>
        </div>
    </div>
</div>

<!-- Filter Section -->
<div class="filter-section">
    <form method="GET" class="row g-3">
        <div class="col-md-4">
            <label class="form-label">بحث</label>
            <input type="text" name="search" class="form-control" placeholder="ابحث بالاسم أو البريد أو الموضوع..." value="<?php echo htmlspecialchars($filter_search); ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label">الحالة</label>
            <select name="status" class="form-select">
                <option value="all" <?php echo $filter_status === 'all' ? 'selected' : ''; ?>>جميع الرسائل</option>
                <option value="new" <?php echo $filter_status === 'new' ? 'selected' : ''; ?>>جديدة</option>
                <option value="read" <?php echo $filter_status === 'read' ? 'selected' : ''; ?>>مقروءة</option>
                <option value="replied" <?php echo $filter_status === 'replied' ? 'selected' : ''; ?>>تم الرد</option>
                <option value="archived" <?php echo $filter_status === 'archived' ? 'selected' : ''; ?>>مؤرشفة</option>
            </select>
        </div>
        <div class="col-md-3 d-flex align-items-end">
            <button type="submit" class="btn btn-primary w-100">
                <i class="fas fa-filter me-2"></i>تصفية
            </button>
        </div>
    </form>
</div>

<div class="row g-4">
    <!-- Messages Column -->
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-envelope me-2"></i>قائمة الرسائل</h5>
                <small class="text-muted">إجمالي: <?php echo count($messages); ?> رسالة</small>
            </div>
            <div class="card-body">
                <?php if(empty($messages)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-envelope fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">لا توجد رسائل</h5>
                    </div>
                <?php else: ?>
                    <?php foreach($messages as $message): ?>
                        <div class="message-card <?php echo $message['status']; ?>">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="flex-grow-1">
                                    <div class="d-flex align-items-center mb-2">
                                        <i class="fas fa-user-circle fa-2x text-muted me-2"></i>
                                        <div>
                                            <h6 class="mb-0"><?php echo htmlspecialchars($message['name']); ?></h6>
                                            <small class="text-muted">
                                                <i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars($message['email']); ?>
                                                <?php if(!empty($message['phone'])): ?>
                                                    <i class="fas fa-phone ms-2 me-1"></i><?php echo htmlspecialchars($message['phone']); ?>
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                    </div>
                                    <div class="mb-2">
                                        <?php echo getMessageStatusBadge($message['status']); ?>
                                        <small class="text-muted me-2">
                                            <i class="fas fa-clock me-1"></i>
                                            <?php echo date('Y/m/d H:i', strtotime($message['created_at'])); ?>
                                        </small>
                                        <?php if(!empty($message['ip_address'])): ?>
                                            <small class="text-muted">
                                                <i class="fas fa-network-wired me-1"></i>
                                                IP: <?php echo $message['ip_address']; ?>
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                    <h6 class="text-primary mb-2"><?php echo htmlspecialchars($message['subject']); ?></h6>
                                    <p class="mb-2"><?php echo nl2br(htmlspecialchars($message['message'])); ?></p>
                                    
                                    <?php if(!empty($message['reply_message'])): ?>
                                        <hr>
                                        <div class="alert alert-success mt-2 mb-0">
                                            <strong><i class="fas fa-reply-all me-2"></i>الرد:</strong><br>
                                            <?php echo nl2br(htmlspecialchars($message['reply_message'])); ?>
                                            <br>
                                            <small class="text-muted">تم الرد في: <?php echo date('Y/m/d H:i', strtotime($message['replied_at'])); ?></small>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="action-buttons ms-3">
                                    <?php if($message['status'] === 'new'): ?>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="message_id" value="<?php echo $message['id']; ?>">
                                            <input type="hidden" name="action" value="mark_read">
                                            <button type="submit" class="btn btn-sm btn-outline-info" title="تحديد كمقروء">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    
                                    <button class="btn btn-sm btn-outline-primary" onclick="showReplyModal(
                                        <?php echo $message['id']; ?>,
                                        '<?php echo htmlspecialchars($message['email']); ?>',
                                        '<?php echo htmlspecialchars($message['subject']); ?>'
                                    )" title="رد">
                                        <i class="fas fa-reply"></i>
                                    </button>
                                    
                                    <form method="POST" class="d-inline" onsubmit="return confirm('هل أنت متأكد من حذف هذه الرسالة؟');">
                                        <input type="hidden" name="message_id" value="<?php echo $message['id']; ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="حذف">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Send Notification Column -->
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-bell me-2"></i>إرسال إشعار للمستخدمين</h5>
            </div>
            <div class="card-body">
                <form method="POST" id="notificationForm">
                    <input type="hidden" name="send_notification" value="1">
                    
                    <div class="mb-3">
                        <label class="form-label">إرسال إلى</label>
                        <select name="notification_type" id="notification_type" class="form-select" required>
                            <option value="">-- اختر المستخدمين --</option>
                            <option value="all">جميع المستخدمين</option>
                            <option value="donateurs">المتبرعين فقط</option>
                            <option value="beneficiaires">المستفيدين فقط</option>
                            <option value="livreurs">المساعدين فقط</option>
                            <option value="specific">مستخدم محدد</option>
                        </select>
                    </div>
                    
                    <div class="mb-3" id="specific_user_div" style="display: none;">
                        <label class="form-label">اختر المستخدم</label>
                        <select name="specific_user_id" class="form-select">
                            <option value="">-- اختر مستخدم --</option>
                            <?php foreach($users_list as $user): ?>
                                <option value="<?php echo $user['id']; ?>">
                                    <?php echo htmlspecialchars($user['nom']); ?> (<?php echo $user['email']; ?>) - <?php echo $user['type']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">عنوان الإشعار</label>
                        <input type="text" name="notification_title" class="form-control" required placeholder="مثال: عروض جديدة">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">محتوى الإشعار</label>
                        <textarea name="notification_message" class="form-control" rows="4" required placeholder="اكتب محتوى الإشعار هنا..."></textarea>
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100" onclick="return confirm('هل أنت متأكد من إرسال هذا الإشعار؟')">
                        <i class="fas fa-paper-plane me-2"></i>إرسال الإشعار
                    </button>
                </form>
            </div>
        </div>
        
        <!-- Quick Stats Card -->
        <div class="card mt-4">
            <div class="card-header">
                <h5><i class="fas fa-chart-pie me-2"></i>إحصائيات المستخدمين</h5>
            </div>
            <div class="card-body">
                <?php
                $user_stats = [];
                $stat_query = "SELECT type, COUNT(*) as count FROM users WHERE type != 'admin' GROUP BY type";
                $stat_stmt = $db->prepare($stat_query);
                $stat_stmt->execute();
                $user_stats = $stat_stmt->fetchAll(PDO::FETCH_ASSOC);
                ?>
                <div class="row text-center">
                    <?php foreach($user_stats as $stat): ?>
                        <div class="col-6 mb-3">
                            <h4 class="mb-1">
                                <?php
                                $icons = ['donateur' => '🤝', 'beneficiaire' => '🙏', 'livreur' => '🚚'];
                                echo $icons[$stat['type']] ?? '👤';
                                ?>
                            </h4>
                            <h3 class="text-primary"><?php echo $stat['count']; ?></h3>
                            <small class="text-muted">
                                <?php
                                $labels = ['donateur' => 'متبرع', 'beneficiaire' => 'مستفيد', 'livreur' => 'مساعد'];
                                echo $labels[$stat['type']] ?? $stat['type'];
                                ?>
                            </small>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Reply Modal -->
<div class="modal fade" id="replyModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-reply me-2"></i>الرد على الرسالة</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="send_reply" value="1">
                    <input type="hidden" name="message_id" id="reply_message_id">
                    <div class="mb-3">
                        <label class="form-label">إلى</label>
                        <input type="email" id="reply_to_email" name="to_email" class="form-control" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">الموضوع</label>
                        <input type="text" id="reply_subject" name="subject" class="form-control" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">الرسالة</label>
                        <textarea name="reply_message" class="form-control" rows="6" required placeholder="اكتب ردك هنا..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-paper-plane me-2"></i>إرسال الرد
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.message-card {
    background: white;
    border-radius: 15px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    border-right: 4px solid #ff6b6b;
    transition: all 0.3s;
}

.message-card:hover {
    box-shadow: 0 5px 20px rgba(0,0,0,0.1);
}

.message-card.new {
    border-right-color: #dc3545;
    background: #fff5f5;
}

.message-card.read {
    border-right-color: #17a2b8;
}

.message-card.replied {
    border-right-color: #28a745;
}

.filter-section {
    background: white;
    border-radius: 15px;
    padding: 20px;
    margin-bottom: 20px;
}

.action-buttons .btn {
    margin: 0 3px;
    padding: 5px 12px;
    font-size: 0.8rem;
    border-radius: 10px;
}
</style>

<script>
function showReplyModal(id, email, subject) {
    document.getElementById('reply_message_id').value = id;
    document.getElementById('reply_to_email').value = email;
    document.getElementById('reply_subject').value = 'Re: ' + subject;
    new bootstrap.Modal(document.getElementById('replyModal')).show();
}

// Show/hide specific user dropdown
document.getElementById('notification_type').addEventListener('change', function() {
    const specificDiv = document.getElementById('specific_user_div');
    if (this.value === 'specific') {
        specificDiv.style.display = 'block';
    } else {
        specificDiv.style.display = 'none';
    }
});

// Auto refresh new messages count every 30 seconds
setInterval(function() {
    fetch('ajax/check_new_messages.php')
        .then(response => response.json())
        .then(data => {
            const badge = document.querySelector('.sidebar .nav-link.active .badge');
            const statNumber = document.querySelector('.stat-number.text-danger');
            
            if (data.new_count > 0) {
                if (badge) {
                    badge.textContent = data.new_count;
                }
                if (statNumber) {
                    statNumber.textContent = data.new_count;
                }
            } else {
                if (badge) badge.remove();
                if (statNumber) statNumber.textContent = '0';
            }
        })
        .catch(error => console.error('Error fetching message count:', error));
}, 30000);
</script>

<?php require_once 'includes/admin_footer.php'; ?>
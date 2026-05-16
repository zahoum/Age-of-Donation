<?php
session_start();
require_once '../config/database.php';

// Check if user is admin
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}

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

// Handle reply
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_reply'])) {
    $message_id = $_POST['message_id'] ?? 0;
    $reply_message = $_POST['reply_message'] ?? '';
    $to_email = $_POST['to_email'] ?? '';
    $subject = $_POST['subject'] ?? '';
    
    // Here you can implement email sending functionality
    // For now, just mark as replied
    $sql = "UPDATE contact_messages SET status = 'replied', reply_message = :reply, replied_at = NOW() WHERE id = :id";
    $stmt = $db->prepare($sql);
    $stmt->bindParam(':reply', $reply_message);
    $stmt->bindParam(':id', $message_id);
    $stmt->execute();
    
    // You can add mail() function here to send actual email
    // mail($to_email, "Re: " . $subject, $reply_message, "From: admin@ageofdonnation.org");
    
    $success = "تم إرسال الرد بنجاح";
    header('Location: messages.php?replied=1');
    exit();
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

// Get messages
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

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة الرسائل - لوحة التحكم</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .sidebar .nav-link {
            color: rgba(255,255,255,.8);
            padding: 12px 20px;
            margin: 5px 0;
            border-radius: 10px;
            transition: all 0.3s;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            color: white;
            background: rgba(255,255,255,.1);
        }
        .main-content {
            margin-right: 280px;
            padding: 20px;
        }
        .navbar {
            box-shadow: 0 2px 10px rgba(0,0,0,.1);
            background: white;
            border-radius: 15px;
            padding: 15px 20px;
            margin-bottom: 20px;
        }
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,.05);
            transition: transform 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .message-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,.05);
            border-right: 4px solid #667eea;
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
        .badge {
            font-size: 0.8em;
        }
        .action-btn {
            padding: 5px 10px;
            font-size: 0.9em;
            margin: 0 3px;
        }
        @media (max-width: 768px) {
            .sidebar {
                position: fixed;
                width: 70px;
                overflow: hidden;
            }
            .sidebar span {
                display: none;
            }
            .main-content {
                margin-right: 70px;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-2 col-lg-2 p-0 sidebar">
                <div class="p-4">
                    <h4 class="text-center mb-4">لوحة التحكم</h4>
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard.php">
                                <i class="fas fa-tachometer-alt me-2"></i><span>لوحة التحكم</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="utilisateurs.php">
                                <i class="fas fa-users me-2"></i><span>المستخدمين</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="dons.php">
                                <i class="fas fa-hand-holding-heart me-2"></i><span>التبرعات</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="demandes.php">
                                <i class="fas fa-hand-paper me-2"></i><span>الطلبات</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="livreurs.php">
                                <i class="fas fa-truck me-2"></i><span>المساعدين</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="messages.php">
                                <i class="fas fa-envelope me-2"></i><span>الرسائل</span>
                                <?php if($stats['new_count'] > 0): ?>
                                    <span class="badge bg-danger float-start"><?php echo $stats['new_count']; ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="statistiques.php">
                                <i class="fas fa-chart-bar me-2"></i><span>الإحصائيات</span>
                            </a>
                        </li>
                        <li class="nav-item mt-5">
                            <a class="nav-link" href="../index.php">
                                <i class="fas fa-globe me-2"></i><span>الموقع العام</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-danger" href="../auth/logout.php">
                                <i class="fas fa-sign-out-alt me-2"></i><span>تسجيل الخروج</span>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-10 col-lg-10 p-0 main-content">
                <!-- Navbar -->
                <nav class="navbar">
                    <div class="container-fluid">
                        <h4 class="mb-0">إدارة الرسائل</h4>
                        <div class="d-flex align-items-center">
                            <span class="me-3">
                                <i class="fas fa-user-circle me-1"></i>
                                <?php echo $_SESSION['user_nom'] ?? 'المسؤول'; ?>
                            </span>
                        </div>
                    </div>
                </nav>

                <!-- Stats -->
                <div class="row g-4 mb-4">
                    <div class="col-md-3">
                        <div class="stat-card text-center">
                            <h1 class="text-primary"><?php echo $stats['total'] ?? 0; ?></h1>
                            <p class="text-muted mb-0">إجمالي الرسائل</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card text-center">
                            <h1 class="text-danger"><?php echo $stats['new_count'] ?? 0; ?></h1>
                            <p class="text-muted mb-0">رسائل جديدة</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card text-center">
                            <h1 class="text-info"><?php echo $stats['read_count'] ?? 0; ?></h1>
                            <p class="text-muted mb-0">رسائل مقروءة</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card text-center">
                            <h1 class="text-success"><?php echo $stats['replied_count'] ?? 0; ?></h1>
                            <p class="text-muted mb-0">تم الرد عليها</p>
                        </div>
                    </div>
                </div>

                <?php if(isset($_GET['replied'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i>تم إرسال الرد بنجاح
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
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
                            <div class="col-md-6">
                                <label class="form-label">بحث</label>
                                <input type="text" name="search" class="form-control" placeholder="ابحث بالاسم أو البريد أو الموضوع..." value="<?php echo htmlspecialchars($filter_search); ?>">
                            </div>
                            <div class="col-md-3 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-filter me-2"></i>تصفية
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Messages List -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">الرسائل</h5>
                        <small>إجمالي: <?php echo count($messages); ?> رسالة</small>
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
                                            <h6 class="mb-1">
                                                <?php echo htmlspecialchars($message['name']); ?>
                                                <?php echo getMessageStatusBadge($message['status']); ?>
                                            </h6>
                                            <p class="mb-1">
                                                <i class="fas fa-envelope me-1 text-muted"></i>
                                                <?php echo htmlspecialchars($message['email']); ?>
                                                <?php if(!empty($message['phone'])): ?>
                                                    <i class="fas fa-phone ms-3 me-1 text-muted"></i>
                                                    <?php echo htmlspecialchars($message['phone']); ?>
                                                <?php endif; ?>
                                            </p>
                                            <h6 class="text-primary mb-2"><?php echo htmlspecialchars($message['subject']); ?></h6>
                                            <p class="mb-2"><?php echo nl2br(htmlspecialchars($message['message'])); ?></p>
                                            <small class="text-muted">
                                                <i class="fas fa-clock me-1"></i>
                                                <?php echo date('Y/m/d H:i', strtotime($message['created_at'])); ?>
                                            </small>
                                        </div>
                                        <div class="btn-group ms-3">
                                            <?php if($message['status'] === 'new'): ?>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="message_id" value="<?php echo $message['id']; ?>">
                                                    <input type="hidden" name="action" value="mark_read">
                                                    <button type="submit" class="btn btn-sm btn-outline-info action-btn" title="تحديد كمقروء">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            
                                            <button class="btn btn-sm btn-outline-primary action-btn" onclick="showReplyModal(
                                                <?php echo $message['id']; ?>,
                                                '<?php echo htmlspecialchars($message['email']); ?>',
                                                '<?php echo htmlspecialchars($message['subject']); ?>'
                                            )" title="رد">
                                                <i class="fas fa-reply"></i>
                                            </button>
                                            
                                            <form method="POST" class="d-inline" onsubmit="return confirm('هل أنت متأكد من حذف هذه الرسالة؟');">
                                                <input type="hidden" name="message_id" value="<?php echo $message['id']; ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <button type="submit" class="btn btn-sm btn-outline-danger action-btn" title="حذف">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                    
                                    <?php if(!empty($message['reply_message'])): ?>
                                        <hr>
                                        <div class="alert alert-success mt-2">
                                            <strong><i class="fas fa-reply-all me-2"></i>الرد:</strong><br>
                                            <?php echo nl2br(htmlspecialchars($message['reply_message'])); ?>
                                            <br>
                                            <small class="text-muted">تم الرد في: <?php echo date('Y/m/d H:i', strtotime($message['replied_at'])); ?></small>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function showReplyModal(id, email, subject) {
            document.getElementById('reply_message_id').value = id;
            document.getElementById('reply_to_email').value = email;
            document.getElementById('reply_subject').value = 'Re: ' + subject;
            new bootstrap.Modal(document.getElementById('replyModal')).show();
        }
        
        // Auto refresh new messages count every 30 seconds
        setInterval(function() {
            fetch('ajax/check_new_messages.php')
                .then(response => response.json())
                .then(data => {
                    const badge = document.querySelector('.nav-link.active .badge');
                    if (data.new_count > 0) {
                        if (badge) {
                            badge.textContent = data.new_count;
                        } else {
                            const link = document.querySelector('.nav-link.active');
                            if(link) {
                                link.innerHTML += ' <span class="badge bg-danger float-start">' + data.new_count + '</span>';
                            }
                        }
                    } else if (badge) {
                        badge.remove();
                    }
                })
                .catch(error => console.error('Error fetching message count:', error));
        }, 30000);
    </script>
</body>
</html>
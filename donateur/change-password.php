<?php
// donateur/change-password.php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'donateur') {
    header('Location: ../auth/login.php');
    exit();
}

require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$user_id = $_SESSION['user_id'];
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Fetch current password hash from users table
    $query = "SELECT password FROM users WHERE id = :user_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":user_id", $user_id);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (password_verify($current_password, $result['password'])) {
        if ($new_password === $confirm_password) {
            if (strlen($new_password) >= 6) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                
                $update_query = "UPDATE users SET password = :password, updated_at = NOW() WHERE id = :user_id";
                $update_stmt = $db->prepare($update_query);
                $update_stmt->bindParam(":password", $hashed_password);
                $update_stmt->bindParam(":user_id", $user_id);
                
                if ($update_stmt->execute()) {
                    $message = 'تم تغيير كلمة المرور بنجاح';
                    $message_type = 'success';
                } else {
                    $message = 'حدث خطأ أثناء تغيير كلمة المرور';
                    $message_type = 'error';
                }
            } else {
                $message = 'كلمة المرور الجديدة يجب أن تكون 6 أحرف على الأقل';
                $message_type = 'error';
            }
        } else {
            $message = 'كلمات المرور الجديدة غير متطابقة';
            $message_type = 'error';
        }
    } else {
        $message = 'كلمة المرور الحالية غير صحيحة';
        $message_type = 'error';
    }
}

$page_title = 'تغيير كلمة المرور';
require_once '../includes/header.php';
?>

<div class="page-header">
    <h1><i class="fas fa-lock"></i> تغيير كلمة المرور</h1>
    <p>قم بتحديث كلمة مرور حسابك لحمايته</p>
</div>

<div class="row justify-content-center">
    <div class="col-6">
        <div class="card">
            <div class="card-body">
                <?php if (!empty($message)): ?>
                    <div class="alert alert-<?php echo $message_type == 'success' ? 'success' : 'danger'; ?>" style="margin-bottom: 20px;">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="current_password">كلمة المرور الحالية *</label>
                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="new_password">كلمة المرور الجديدة *</label>
                        <input type="password" class="form-control" id="new_password" name="new_password" required>
                        <small style="color: var(--secondary);">يجب أن تكون 6 أحرف على الأقل</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">تأكيد كلمة المرور الجديدة *</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                    </div>
                    
                    <div style="margin-top: 30px; text-align: left;">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-key"></i> تغيير كلمة المرور
                        </button>
                        <a href="profile.php" class="btn btn-outline" style="margin-right: 10px;">
                            <i class="fas fa-arrow-right"></i> العودة للملف الشخصي
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
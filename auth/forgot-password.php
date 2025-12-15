<?php
// auth/forgot-password.php
session_start();

// إذا كان المستخدم مسجل دخول، أرسله للصفحة الرئيسية
if (isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}

require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$error = '';
$success = '';

if ($_POST) {
    $email = trim($_POST['email']);
    
    $query = "SELECT id FROM users WHERE email = :email";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":email", $email);
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        $success = "تم إرسال رابط إعادة تعيين كلمة المرور إلى بريدك الإلكتروني.";
    } else {
        $error = "لا يوجد حساب مرتبط بهذا البريد الإلكتروني.";
    }
}

$page_title = 'نسيت كلمة المرور';
require_once '../includes/header.php';
?>

<div class="row">
    <div class="col-6" style="margin: 0 auto;">
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-key"></i> نسيت كلمة المرور</h3>
            </div>
            <div class="card-body">
                <?php if($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <p style="margin-bottom: 20px; color: #666;">
                    أدخل بريدك الإلكتروني وسنرسل لك رابطًا لإعادة تعيين كلمة المرور.
                </p>

                <form method="POST">
                    <div class="form-group">
                        <label class="form-label">البريد الإلكتروني *</label>
                        <input type="email" name="email" class="form-control" value="<?php echo $_POST['email'] ?? ''; ?>" required>
                    </div>
                    
                    <button type="submit" class="btn btn-primary" style="width: 100%;">
                        <i class="fas fa-paper-plane"></i> إرسال رابط التعيين
                    </button>
                </form>
                
                <div style="text-align: center; margin-top: 25px; padding-top: 20px; border-top: 1px solid #eee;">
                    <a href="login.php"><i class="fas fa-arrow-right"></i> العودة لتسجيل الدخول</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
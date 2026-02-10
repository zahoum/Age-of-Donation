<?php
// donateur/profile.php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'donateur') {
    header('Location: ../auth/login.php');
    exit();
}

require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$user_id = $_SESSION['user_id'];

// Fetch user information from users table
$query = "SELECT * FROM users WHERE id = :user_id AND type = 'donateur'";
$stmt = $db->prepare($query);
$stmt->bindParam(":user_id", $user_id);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle form submission for profile update
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nom = $_POST['nom'] ?? '';
    $email = $_POST['email'] ?? '';
    $telephone = $_POST['telephone'] ?? '';
    $adresse = $_POST['adresse'] ?? '';
    $ville = $_POST['ville'] ?? '';
    
    // Basic validation
    if (!empty($nom) && !empty($email)) {
        $update_query = "
            UPDATE users 
            SET nom = :nom, 
                email = :email, 
                telephone = :telephone, 
                adresse = :adresse, 
                ville = :ville,
                updated_at = NOW()
            WHERE id = :user_id
        ";
        
        $update_stmt = $db->prepare($update_query);
        $update_stmt->bindParam(":nom", $nom);
        $update_stmt->bindParam(":email", $email);
        $update_stmt->bindParam(":telephone", $telephone);
        $update_stmt->bindParam(":adresse", $adresse);
        $update_stmt->bindParam(":ville", $ville);
        $update_stmt->bindParam(":user_id", $user_id);
        
        if ($update_stmt->execute()) {
            // Update session variables
            $_SESSION['user_nom'] = $nom;
            $_SESSION['user_email'] = $email;
            
            $message = 'تم تحديث الملف الشخصي بنجاح!';
            $message_type = 'success';
            
            // Refresh user data
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            $message = 'حدث خطأ أثناء تحديث الملف الشخصي';
            $message_type = 'error';
        }
    } else {
        $message = 'الاسم والبريد الإلكتروني مطلوبان';
        $message_type = 'error';
    }
}

$page_title = 'الملف الشخصي - متبرع';
require_once '../includes/header.php';
?>

<!-- Page Header -->
<div class="page-header">
    <h1><i class="fas fa-user-cog"></i> الملف الشخصي</h1>
    <p>إدارة معلومات حسابك الشخصية</p>
</div>

<!-- Message Display -->
<?php if (!empty($message)): ?>
    <div class="alert alert-<?php echo $message_type == 'success' ? 'success' : 'danger'; ?>" style="margin-bottom: 20px;">
        <?php echo htmlspecialchars($message); ?>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-4">
        <!-- Profile Summary Card -->
        <div class="card" style="text-align: center;">
            <div class="card-body">
                <div style="width: 120px; height: 120px; background: linear-gradient(135deg, #74b9ff, #0984e3); 
                          border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; 
                          margin-bottom: 20px;">
                    <?php if ($user['type'] == 'donateur'): ?>
                        <i class="fas fa-hand-holding-heart" style="font-size: 50px; color: white;"></i>
                    <?php else: ?>
                        <i class="fas fa-user" style="font-size: 50px; color: white;"></i>
                    <?php endif; ?>
                </div>
                <h3><?php echo htmlspecialchars($user['nom']); ?></h3>
                <p style="color: var(--secondary);"><?php echo htmlspecialchars($user['email']); ?></p>
                
                <!-- User Type Badge -->
                <div style="margin-bottom: 20px;">
                    <?php 
                    $type_badge = [
                        'donateur' => ['label' => 'متبرع', 'color' => 'success'],
                        'beneficiaire' => ['label' => 'مستفيد', 'color' => 'primary'],
                        'livreur' => ['label' => 'موصل', 'color' => 'warning'],
                        'admin' => ['label' => 'مدير', 'color' => 'danger']
                    ];
                    $user_type = $user['type'] ?? 'donateur';
                    ?>
                    <span class="badge badge-<?php echo $type_badge[$user_type]['color']; ?>">
                        <i class="fas fa-user-tag"></i> <?php echo $type_badge[$user_type]['label']; ?>
                    </span>
                </div>
                
                <div style="margin-top: 25px; padding-top: 20px; border-top: 1px solid #eee;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                        <span>رقم العضوية:</span>
                        <strong>#<?php echo str_pad($user['id'], 6, '0', STR_PAD_LEFT); ?></strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                        <span>حالة الحساب:</span>
                        <strong>
                            <?php 
                            $status_text = [
                                'active' => 'نشط',
                                'inactive' => 'غير نشط', 
                                'pending' => 'قيد الانتظار'
                            ];
                            echo $status_text[$user['status']] ?? $user['status'];
                            ?>
                        </strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                        <span>تاريخ التسجيل:</span>
                        <strong><?php echo date('d/m/Y', strtotime($user['created_at'])); ?></strong>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span>آخر تحديث:</span>
                        <strong><?php echo date('d/m/Y', strtotime($user['updated_at'] ?? $user['created_at'])); ?></strong>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Navigation -->
        <div class="card" style="margin-top: 20px;">
            <div class="card-body">
                <h5 style="margin-bottom: 15px;"><i class="fas fa-cog"></i> إعدادات الحساب</h5>
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a href="dashboard.php" class="nav-link" style="padding: 10px 0; color: var(--primary);">
                            <i class="fas fa-tachometer-alt"></i> لوحة التحكم
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="profile.php" class="nav-link active" style="padding: 10px 0; color: var(--accent);">
                            <i class="fas fa-user-edit"></i> الملف الشخصي
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="change-password.php" class="nav-link" style="padding: 10px 0; color: var(--primary);">
                            <i class="fas fa-lock"></i> تغيير كلمة المرور
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../auth/logout.php" class="nav-link" style="padding: 10px 0; color: var(--danger);">
                            <i class="fas fa-sign-out-alt"></i> تسجيل الخروج
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </div>
    
    <div class="col-8">
        <!-- Profile Edit Form -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-edit"></i> تعديل المعلومات الشخصية</h3>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="nom">الاسم الكامل *</label>
                        <input type="text" class="form-control" id="nom" name="nom" 
                               value="<?php echo htmlspecialchars($user['nom']); ?>" required>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group col-6">
                            <label for="email">البريد الإلكتروني *</label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        </div>
                        <div class="form-group col-6">
                            <label for="telephone">رقم الهاتف</label>
                            <input type="tel" class="form-control" id="telephone" name="telephone" 
                                   value="<?php echo htmlspecialchars($user['telephone'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="adresse">العنوان</label>
                        <textarea class="form-control" id="adresse" name="adresse" rows="2"><?php echo htmlspecialchars($user['adresse'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group col-6">
                            <label for="ville">المدينة</label>
                            <input type="text" class="form-control" id="ville" name="ville" 
                                   value="<?php echo htmlspecialchars($user['ville'] ?? ''); ?>">
                        </div>
                        
                    </div>
                    
                    <div style="margin-top: 30px; text-align: left;">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> حفظ التغييرات
                        </button>
                        <a href="dashboard.php" class="btn btn-outline" style="margin-right: 10px;">
                            <i class="fas fa-times"></i> إلغاء
                        </a>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Donation Statistics -->
        <div class="card" style="margin-top: 20px;">
            <div class="card-header">
                <h3><i class="fas fa-chart-bar"></i> إحصائيات تبرعاتك</h3>
            </div>
            <div class="card-body">
                <?php
                // Fetch donation statistics
                $stats_query = "
                    SELECT 
                        COUNT(*) as total_dons,
                        SUM(CASE WHEN statut = 'donne' THEN 1 ELSE 0 END) as dons_termines,
                        SUM(CASE WHEN statut = 'disponible' THEN 1 ELSE 0 END) as dons_actifs,
                        SUM(CASE WHEN statut = 'reserve' THEN 1 ELSE 0 END) as dons_reserves
                    FROM dons 
                    WHERE donateur_id = :user_id
                ";
                $stats_stmt = $db->prepare($stats_query);
                $stats_stmt->bindParam(":user_id", $user_id);
                $stats_stmt->execute();
                $donation_stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
                ?>
                
                <div class="row text-center">
                    <div class="col-3">
                        <div class="stat-mini">
                            <h4><?php echo $donation_stats['total_dons'] ?? 0; ?></h4>
                            <p>إجمالي التبرعات</p>
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="stat-mini">
                            <h4 style="color: var(--success);"><?php echo $donation_stats['dons_actifs'] ?? 0; ?></h4>
                            <p>نشطة</p>
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="stat-mini">
                            <h4 style="color: var(--warning);"><?php echo $donation_stats['dons_reserves'] ?? 0; ?></h4>
                            <p>محجوزة</p>
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="stat-mini">
                            <h4 style="color: var(--info);"><?php echo $donation_stats['dons_termines'] ?? 0; ?></h4>
                            <p>مكتملة</p>
                        </div>
                    </div>
                </div>
                
                <div style="text-align: center; margin-top: 20px;">
                    <a href="mes-dons.php" class="btn btn-outline-primary">
                        <i class="fas fa-boxes"></i> عرض جميع تبرعاتي
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Account Security -->
        <div class="card" style="margin-top: 20px;">
            <div class="card-header">
                <h3><i class="fas fa-shield-alt"></i> أمان الحساب</h3>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> لضمان أمان حسابك، نوصي بما يلي:
                </div>
                <ul style="padding-right: 20px; color: var(--secondary);">
                    <li style="margin-bottom: 10px;">تغيير كلمة المرور بشكل دوري</li>
                    <li style="margin-bottom: 10px;">عدم مشاركة بيانات الدخول مع أي شخص</li>
                    <li style="margin-bottom: 10px;">استخدام كلمة مرور قوية تحتوي على أحرف وأرقام ورموز</li>
                    <li>التأكد من تسجيل الخروج عند استخدام أجهزة عامة</li>
                </ul>
                <div style="text-align: left; margin-top: 20px;">
                    <a href="change-password.php" class="btn btn-warning">
                        <i class="fas fa-lock"></i> تغيير كلمة المرور
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
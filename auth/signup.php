<?php
// auth/signup.php
session_start();

require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$error = '';
$success = '';
$type = $_GET['type'] ?? '';

// معالجة إنشاء الحساب
if ($_POST) {
    $nom = trim($_POST['nom']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $password_confirm = $_POST['password_confirm'];
    $telephone = trim($_POST['telephone'] ?? '');
    $ville = trim($_POST['ville'] ?? '');
    $user_type = trim($_POST['type'] ?? $type);
    
    // التحقق من الحقول
    if (empty($nom) || empty($email) || empty($password) || empty($user_type)) {
        $error = "جميع الحقول الإلزامية يجب تعبئتها";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "البريد الإلكتروني غير صالح";
    } elseif ($password !== $password_confirm) {
        $error = "كلمتا المرور غير متطابقتين";
    } elseif (strlen($password) < 6) {
        $error = "كلمة المرور يجب أن تكون على الأقل 6 أحرف";
    } else {
        // التحقق من عدم وجود البريد الإلكتروني مسبقًا
        $check_query = "SELECT id FROM users WHERE email = :email";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->bindParam(":email", $email);
        $check_stmt->execute();
        
        if ($check_stmt->rowCount() > 0) {
            $error = "البريد الإلكتروني مسجل مسبقًا";
        } else {
            try {
                // إنشاء المستخدم الجديد
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $status = 'active';
                
                $query = "INSERT INTO users (nom, email, password, telephone, ville, type, status, created_at) 
                          VALUES (:nom, :email, :password, :telephone, :ville, :type, :status, NOW())";
                
                $stmt = $db->prepare($query);
                $stmt->bindParam(":nom", $nom);
                $stmt->bindParam(":email", $email);
                $stmt->bindParam(":password", $hashed_password);
                $stmt->bindParam(":telephone", $telephone);
                $stmt->bindParam(":ville", $ville);
                $stmt->bindParam(":type", $user_type);
                $stmt->bindParam(":status", $status);
                
                if ($stmt->execute()) {
                    $user_id = $db->lastInsertId();
                    
                    // تسجيل الدخول تلقائيًا
                    $_SESSION['user_id'] = $user_id;
                    $_SESSION['user_nom'] = $nom;
                    $_SESSION['user_email'] = $email;
                    $_SESSION['user_type'] = $user_type;
                    
                    // إعادة التوجيه حسب نوع المستخدم
                    switch($user_type) {
                        case 'donateur':
                            header('Location: ../donateur/dashboard.php');
                            break;
                        case 'beneficiaire':
                            header('Location: ../beneficiaire/dashboard.php');
                            break;
                        case 'livreur':
                            header('Location: ../livreur/dashboard.php');
                            break;
                        default:
                            header('Location: ../index.php');
                    }
                    exit();
                } else {
                    $error = "حدث خطأ أثناء إنشاء الحساب";
                }
            } catch(PDOException $e) {
                $error = "خطأ في النظام: " . $e->getMessage();
            }
        }
    }
}

$page_title = 'إنشاء حساب';
?>
<!DOCTYPE html>
<html lang="fr" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - Age of Donnation</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Tajawal', sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .signup-container {
            width: 100%;
            max-width: 500px;
        }
        
        .signup-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
        }
        
        .signup-header {
            background: linear-gradient(135deg, #00b894, #00cec9);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .signup-header h1 {
            margin-bottom: 10px;
            font-size: 28px;
        }
        
        .signup-header p {
            opacity: 0.9;
        }
        
        .signup-body {
            padding: 30px;
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid;
        }
        
        .alert-danger {
            background: #f8d7da;
            border-color: #dc3545;
            color: #721c24;
        }
        
        .alert-success {
            background: #d4edda;
            border-color: #28a745;
            color: #155724;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #2d3436;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 15px;
            transition: border 0.3s;
        }
        
        .form-control:focus {
            border-color: #00b894;
            outline: none;
            box-shadow: 0 0 0 3px rgba(0, 184, 148, 0.2);
        }
        
        .select-user-type {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .type-option {
            flex: 1;
            text-align: center;
            padding: 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .type-option:hover {
            border-color: #00b894;
            background: rgba(0, 184, 148, 0.1);
        }
        
        .type-option.selected {
            border-color: #00b894;
            background: #00b894;
            color: white;
        }
        
        .type-option i {
            font-size: 24px;
            margin-bottom: 10px;
            display: block;
        }
        
        .type-option.donateur.selected {
            background: #0984e3;
            border-color: #0984e3;
        }
        
        .type-option.beneficiaire.selected {
            background: #00b894;
            border-color: #00b894;
        }
        
        .signup-btn {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #00b894, #00cec9);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .signup-btn:hover {
            background: linear-gradient(135deg, #00a085, #00b7a8);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 184, 148, 0.4);
        }
        
        .signup-footer {
            text-align: center;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            color: #636e72;
        }
        
        .signup-footer a {
            color: #00b894;
            text-decoration: none;
            font-weight: 500;
        }
        
        .signup-footer a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="signup-container">
        <div class="signup-card">
            <div class="signup-header">
                <h1><i class="fas fa-user-plus"></i> إنشاء حساب جديد</h1>
                <p>انضم إلى مجتمع Age of Donnation</p>
            </div>
            
            <div class="signup-body">
                <?php if($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>

                <form method="POST" id="signupForm">
                    <div class="select-user-type">
                        <div class="type-option donateur <?php echo ($type == 'donateur' || (!empty($_POST['type']) && $_POST['type'] == 'donateur')) ? 'selected' : ''; ?>" 
                             onclick="selectType('donateur')">
                            <i class="fas fa-gift"></i>
                            <div>متبرع</div>
                        </div>
                        <div class="type-option beneficiaire <?php echo ($type == 'beneficiaire' || (!empty($_POST['type']) && $_POST['type'] == 'beneficiaire')) ? 'selected' : ''; ?>" 
                             onclick="selectType('beneficiaire')">
                            <i class="fas fa-hands"></i>
                            <div>مستفيد</div>
                        </div>
                    </div>
                    
                    <input type="hidden" name="type" id="userType" value="<?php echo $type ?: 'donateur'; ?>">
                    
                    <div class="form-group">
                        <label class="form-label">الاسم الكامل *</label>
                        <input type="text" name="nom" class="form-control" value="<?php echo $_POST['nom'] ?? ''; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">البريد الإلكتروني *</label>
                        <input type="email" name="email" class="form-control" value="<?php echo $_POST['email'] ?? ''; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">رقم الهاتف</label>
                        <input type="tel" name="telephone" class="form-control" value="<?php echo $_POST['telephone'] ?? ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">المدينة</label>
                        <input type="text" name="ville" class="form-control" value="<?php echo $_POST['ville'] ?? ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">كلمة المرور *</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">تأكيد كلمة المرور *</label>
                        <input type="password" name="password_confirm" class="form-control" required>
                    </div>
                    
                    <button type="submit" class="signup-btn">
                        <i class="fas fa-user-plus"></i> إنشاء حساب
                    </button>
                </form>
                
                <div class="signup-footer">
                    <span style="color: #666;">لديك حساب بالفعل؟ </span><a href="login.php">تسجيل الدخول</a>
                </div>
            </div>
        </div>
    </div>

    <script>
    function selectType(type) {
        // تحديث الأزرار المحددة
        document.querySelectorAll('.type-option').forEach(option => {
            option.classList.remove('selected');
        });
        document.querySelector('.type-option.' + type).classList.add('selected');
        
        // تحديث الحقل المخفي
        document.getElementById('userType').value = type;
    }
    
    // اختيار النوع بناءً على الرابط
    document.addEventListener('DOMContentLoaded', function() {
        const urlParams = new URLSearchParams(window.location.search);
        const type = urlParams.get('type');
        if (type) {
            selectType(type);
        }
    });
    </script>
</body>
</html>
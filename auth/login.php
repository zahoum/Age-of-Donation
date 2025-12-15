<?php
// auth/login.php
session_start();

require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$error = '';
$success = '';

if ($_POST) {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    $query = "SELECT id, nom, email, password, type, status FROM users WHERE email = :email";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":email", $email);
    $stmt->execute();

    if ($stmt->rowCount() == 1) {
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (password_verify($password, $user['password'])) {
            if ($user['status'] == 'active') {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_nom'] = $user['nom'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_type'] = $user['type'];
                
                // Redirection selon le type d'utilisateur
                switch($user['type']) {
                    case 'donateur':
                        header('Location: ../donateur/dashboard.php');
                        break;
                    case 'beneficiaire':
                        header('Location: ../beneficiaire/dashboard.php');
                        break;
                    case 'livreur':
                        header('Location: ../livreur/dashboard.php');
                        break;
                    case 'admin':
                        header('Location: ../admin/dashboard.php');
                        break;
                    default:
                        header('Location: ../index.php');
                }
                exit();
            } else {
                $error = "حسابك غير نشط. يرجى التواصل مع الإدارة.";
            }
        } else {
            $error = "كلمة المرور غير صحيحة";
        }
    } else {
        $error = "لا يوجد حساب مرتبط بهذا البريد الإلكتروني";
    }
}

$page_title = 'تسجيل الدخول';
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
        
        .login-container {
            width: 100%;
            max-width: 450px;
        }
        
        .login-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
        }
        
        .login-header {
            background: linear-gradient(135deg, #0984e3, #74b9ff);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .login-header h1 {
            margin-bottom: 10px;
            font-size: 28px;
        }
        
        .login-header p {
            opacity: 0.9;
        }
        
        .login-body {
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
            margin-bottom: 25px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #2d3436;
        }
        
        .form-control {
            width: 100%;
            padding: 14px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 16px;
            transition: border 0.3s;
        }
        
        .form-control:focus {
            border-color: #0984e3;
            outline: none;
            box-shadow: 0 0 0 3px rgba(116, 185, 255, 0.2);
        }
        
        .login-btn {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #0984e3, #74b9ff);
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
        
        .login-btn:hover {
            background: linear-gradient(135deg, #0984e3, #0984e3);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(116, 185, 255, 0.4);
        }
        
        .login-footer {
            text-align: center;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            color: #636e72;
        }
        
        .login-footer a {
            color: #0984e3;
            text-decoration: none;
            font-weight: 500;
        }
        
        .login-footer a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <h1><i class="fas fa-sign-in-alt"></i> تسجيل الدخول</h1>
                <p>مرحبًا بك في Age of Donnation</p>
            </div>
            
            <div class="login-body">
                <?php if($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>

                <form method="POST">
                    <div class="form-group">
                        <label class="form-label">البريد الإلكتروني</label>
                        <input type="email" name="email" class="form-control" value="<?php echo $_POST['email'] ?? ''; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">كلمة المرور</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    
                    <button type="submit" class="login-btn">
                        <i class="fas fa-sign-in-alt"></i> تسجيل الدخول
                    </button>
                </form>
                
                <div class="login-footer">
                    <a href="forgot-password.php">نسيت كلمة المرور؟</a><br>
                    <span style="color: #666;">ليس لديك حساب؟ </span><a href="signup.php">إنشاء حساب</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
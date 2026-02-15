<?php
require_once '../config/database.php';
require_once '../includes/header.php';

$database = new Database();
$db = $database->getConnection();

$success = '';
$error = '';

if ($_POST) {
    $nom = trim($_POST['nom']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $telephone = trim($_POST['telephone']);
    $vehicule_type = $_POST['vehicule_type'];
    $plaque_immatriculation = trim($_POST['plaque_immatriculation']);
    $zone_intervention = trim($_POST['zone_intervention']);

    // Validation
    $errors = [];
    if(empty($nom)) $errors[] = "الاسم مطلوب";
    if(empty($email)) $errors[] = "البريد الإلكتروني مطلوب";
    if(empty($password)) $errors[] = "كلمة المرور مطلوبة";
    if($password !== $confirm_password) $errors[] = "كلمات المرور غير متطابقة";
    if(!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "صيغة البريد الإلكتروني غير صالحة";
    if(empty($vehicule_type)) $errors[] = "نوع المركبة مطلوب";
    if(empty($zone_intervention)) $errors[] = "منطقة التدخل مطلوبة";

    if(empty($errors)) {
        // Vérifier si l'email existe déjà
        $query = "SELECT id FROM users WHERE email = :email";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":email", $email);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $error = "هذا البريد الإلكتروني مستخدم بالفعل";
        } else {
            try {
                $db->beginTransaction();
                
                // Créer l'utilisateur
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $query = "INSERT INTO users (nom, email, password, type, telephone, status, created_at) 
                          VALUES (:nom, :email, :password, 'livreur', :telephone, 'pending', NOW())";
                
                $stmt = $db->prepare($query);
                $stmt->bindParam(":nom", $nom);
                $stmt->bindParam(":email", $email);
                $stmt->bindParam(":password", $hashed_password);
                $stmt->bindParam(":telephone", $telephone);
                $stmt->execute();
                
                $user_id = $db->lastInsertId();
                
                // Créer le profil livreur
                $query = "INSERT INTO livreurs (user_id, vehicule_type, plaque_immatriculation, zone_intervention, statut, created_at) 
                          VALUES (:user_id, :vehicule_type, :plaque_immatriculation, :zone_intervention, 'inactif', NOW())";
                
                $stmt = $db->prepare($query);
                $stmt->bindParam(":user_id", $user_id);
                $stmt->bindParam(":vehicule_type", $vehicule_type);
                $stmt->bindParam(":plaque_immatriculation", $plaque_immatriculation);
                $stmt->bindParam(":zone_intervention", $zone_intervention);
                $stmt->execute();
                
                $db->commit();
                
                $success = "تم إرسال طلب التسجيل بنجاح! سيتم تفعيل حسابك من قبل المسؤول.";
                
                // Vider le formulaire
                $_POST = array();
                
            } catch(PDOException $e) {
                $db->rollBack();
                $error = "خطأ في التسجيل: " . $e->getMessage();
            }
        }
    } else {
        $error = implode("<br>", $errors);
    }
}
?>

<div style="max-width: 800px; margin: 0 auto;">
    <div class="card">
        <div class="card-header">
            <h2 style="text-align: center; margin: 0;">
                <i class="fas fa-truck"></i> انضم إلينا كمندوب توصيل
            </h2>
        </div>
        <div class="card-body">
            <?php if($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>

            <form method="POST">
                <h3 style="margin-bottom: 1rem; color: var(--primary);">
                    <i class="fas fa-user"></i> المعلومات الشخصية
                </h3>
                
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">الاسم الكامل *</label>
                        <input type="text" name="nom" class="form-control" value="<?php echo htmlspecialchars($_POST['nom'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">البريد الإلكتروني *</label>
                        <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                    </div>
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">كلمة المرور *</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">تأكيد كلمة المرور *</label>
                        <input type="password" name="confirm_password" class="form-control" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">رقم الهاتف *</label>
                    <input type="tel" name="telephone" class="form-control" value="<?php echo htmlspecialchars($_POST['telephone'] ?? ''); ?>" required>
                </div>

                <h3 style="margin: 2rem 0 1rem 0; color: var(--primary);">
                    <i class="fas fa-truck"></i> معلومات المركبة
                </h3>
                
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">نوع المركبة *</label>
                        <select name="vehicule_type" class="form-control" required>
                            <option value="">اختر نوع المركبة</option>
                            <option value="velo" <?php echo ($_POST['vehicule_type'] ?? '') == 'velo' ? 'selected' : ''; ?>>دراجة هوائية</option>
                            <option value="moto" <?php echo ($_POST['vehicule_type'] ?? '') == 'moto' ? 'selected' : ''; ?>>دراجة نارية</option>
                            <option value="voiture" <?php echo ($_POST['vehicule_type'] ?? '') == 'voiture' ? 'selected' : ''; ?>>سيارة</option>
                            <option value="camion" <?php echo ($_POST['vehicule_type'] ?? '') == 'camion' ? 'selected' : ''; ?>>شاحنة</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">رقم اللوحة</label>
                        <input type="text" name="plaque_immatriculation" class="form-control" value="<?php echo htmlspecialchars($_POST['plaque_immatriculation'] ?? ''); ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">منطقة التدخل *</label>
                    <input type="text" name="zone_intervention" class="form-control" value="<?php echo htmlspecialchars($_POST['zone_intervention'] ?? ''); ?>" required placeholder="مثال: الدار البيضاء، الرباط، مراكش...">
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%;">
                    <i class="fas fa-paper-plane"></i> تسجيل كمندوب
                </button>
            </form>
            
            <div style="text-align: center; margin-top: 1.5rem;">
                لديك حساب بالفعل؟ <a href="../auth/login.php">تسجيل الدخول</a>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
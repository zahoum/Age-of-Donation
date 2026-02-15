<?php
require_once '../config/database.php';
require_once '../includes/header.php';

checkAuth(['livreur']);

$database = new Database();
$db = $database->getConnection();

$user_id = $_SESSION['user_id'];

// Récupérer les informations du livreur
$query = "
    SELECT u.*, l.vehicule_type, l.plaque_immatriculation, l.zone_intervention, 
           l.statut as livreur_statut, l.note_moyenne, l.nombre_livraisons
    FROM users u
    INNER JOIN livreurs l ON u.id = l.user_id
    WHERE u.id = :user_id
";
$stmt = $db->prepare($query);
$stmt->bindParam(":user_id", $user_id);
$stmt->execute();
$livreur = $stmt->fetch(PDO::FETCH_ASSOC);

$success = '';
$error = '';

if ($_POST) {
    $telephone = trim($_POST['telephone']);
    $vehicule_type = $_POST['vehicule_type'];
    $plaque_immatriculation = trim($_POST['plaque_immatriculation']);
    $zone_intervention = trim($_POST['zone_intervention']);
    
    // Validation
    $errors = [];
    if(empty($telephone)) $errors[] = "رقم الهاتف مطلوب";
    if(empty($vehicule_type)) $errors[] = "نوع المركبة مطلوب";
    if(empty($zone_intervention)) $errors[] = "منطقة التدخل مطلوبة";
    
    if(empty($errors)) {
        try {
            $db->beginTransaction();
            
            // Mettre à jour l'utilisateur
            $query = "UPDATE users SET telephone = :telephone, updated_at = NOW() WHERE id = :user_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(":telephone", $telephone);
            $stmt->bindParam(":user_id", $user_id);
            $stmt->execute();
            
            // Mettre à jour le livreur
            $query = "UPDATE livreurs SET vehicule_type = :vehicule_type, 
                      plaque_immatriculation = :plaque_immatriculation, 
                      zone_intervention = :zone_intervention, 
                      updated_at = NOW() 
                      WHERE user_id = :user_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(":vehicule_type", $vehicule_type);
            $stmt->bindParam(":plaque_immatriculation", $plaque_immatriculation);
            $stmt->bindParam(":zone_intervention", $zone_intervention);
            $stmt->bindParam(":user_id", $user_id);
            $stmt->execute();
            
            $db->commit();
            $success = "تم تحديث الملف الشخصي بنجاح!";
            
            // Recharger les données
            $stmt = $db->prepare($query);
            $stmt->bindParam(":user_id", $user_id);
            $stmt->execute();
            $livreur = $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch(PDOException $e) {
            $db->rollBack();
            $error = "خطأ في تحديث الملف: " . $e->getMessage();
        }
    } else {
        $error = implode("<br>", $errors);
    }
}

// Statistiques supplémentaires
$stats_query = "
    SELECT 
        COUNT(*) as total_missions,
        COUNT(CASE WHEN statut = 'livree' THEN 1 END) as missions_terminees
    FROM livraisons 
    WHERE livreur_id = :user_id
";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->bindParam(":user_id", $user_id);
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
?>

<div class="dashboard-header">
    <h1><i class="fas fa-user-circle"></i> ملفي الشخصي - مندوب</h1>
    <p>إدارة معلوماتك الشخصية والمهنية</p>
</div>

<?php if($success): ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
<?php endif; ?>

<?php if($error): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>

<div class="grid-2">
    <!-- Informations du profil -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-user-edit"></i> المعلومات الشخصية</h3>
        </div>
        <div class="card-body">
            <form method="POST">
                <div class="form-group">
                    <label class="form-label">الاسم الكامل</label>
                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($livreur['nom']); ?>" disabled>
                </div>
                
                <div class="form-group">
                    <label class="form-label">البريد الإلكتروني</label>
                    <input type="email" class="form-control" value="<?php echo htmlspecialchars($livreur['email']); ?>" disabled>
                </div>
                
                <div class="form-group">
                    <label class="form-label">رقم الهاتف *</label>
                    <input type="tel" name="telephone" class="form-control" value="<?php echo htmlspecialchars($livreur['telephone'] ?? ''); ?>" required>
                </div>

                <h4 style="margin: 2rem 0 1rem 0; color: var(--primary);">
                    <i class="fas fa-truck"></i> المعلومات المهنية
                </h4>
                
                <div class="form-group">
                    <label class="form-label">نوع المركبة *</label>
                    <select name="vehicule_type" class="form-control" required>
                        <option value="velo" <?php echo ($livreur['vehicule_type'] ?? '') == 'velo' ? 'selected' : ''; ?>>دراجة هوائية</option>
                        <option value="moto" <?php echo ($livreur['vehicule_type'] ?? '') == 'moto' ? 'selected' : ''; ?>>دراجة نارية</option>
                        <option value="voiture" <?php echo ($livreur['vehicule_type'] ?? '') == 'voiture' ? 'selected' : ''; ?>>سيارة</option>
                        <option value="camion" <?php echo ($livreur['vehicule_type'] ?? '') == 'camion' ? 'selected' : ''; ?>>شاحنة</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">رقم اللوحة</label>
                    <input type="text" name="plaque_immatriculation" class="form-control" value="<?php echo htmlspecialchars($livreur['plaque_immatriculation'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label class="form-label">منطقة التدخل *</label>
                    <input type="text" name="zone_intervention" class="form-control" value="<?php echo htmlspecialchars($livreur['zone_intervention'] ?? ''); ?>" required>
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> تحديث الملف الشخصي
                </button>
            </form>
        </div>
    </div>

    <!-- Statut et statistiques -->
    <div>
        <div class="card" style="margin-bottom: 20px;">
            <div class="card-header">
                <h3><i class="fas fa-chart-line"></i> أدائي</h3>
            </div>
            <div class="card-body">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; text-align: center;">
                    <div>
                        <div class="stat-number"><?php echo $livreur['note_moyenne'] ?? '5.0'; ?>/5</div>
                        <div class="stat-label">
                            <i class="fas fa-star" style="color: #ffc107;"></i> التقييم
                        </div>
                    </div>
                    <div>
                        <div class="stat-number"><?php echo $stats['total_missions'] ?? 0; ?></div>
                        <div class="stat-label">
                            <i class="fas fa-truck"></i> إجمالي المهام
                        </div>
                    </div>
                    <div>
                        <div class="stat-number"><?php echo $stats['missions_terminees'] ?? 0; ?></div>
                        <div class="stat-label">
                            <i class="fas fa-check-circle" style="color: #28a745;"></i> المهام المنجزة
                        </div>
                    </div>
                    <div>
                        <div class="stat-number"><?php echo $livreur['nombre_livraisons'] ?? 0; ?></div>
                        <div class="stat-label">
                            <i class="fas fa-gift"></i> التوصيلات
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-info-circle"></i> حالة الحساب</h3>
            </div>
            <div class="card-body">
                <div style="text-align: center;">
                    <div style="margin-bottom: 2rem;">
                        <h4>الحالة: 
                            <span class="badge <?php echo $livreur['livreur_statut'] == 'actif' ? 'badge-success' : 'badge-secondary'; ?>" style="font-size: 1rem; padding: 8px 20px;">
                                <?php echo $livreur['livreur_statut'] == 'actif' ? 'نشط' : 'قيد الانتظار'; ?>
                            </span>
                        </h4>
                        <?php if($livreur['livreur_statut'] == 'actif'): ?>
                            <p style="color: #28a745;">
                                <i class="fas fa-check-circle"></i> حسابك نشط
                            </p>
                        <?php else: ?>
                            <p style="color: #6c757d;">
                                <i class="fas fa-clock"></i> في انتظار التفعيل من قبل المسؤول
                            </p>
                        <?php endif; ?>
                    </div>
                    
                    <div style="border-top: 1px solid #eee; padding-top: 1.5rem;">
                        <p><strong><i class="fas fa-calendar-alt"></i> عضو منذ:</strong> <?php echo date('d/m/Y', strtotime($livreur['created_at'])); ?></p>
                        <p><strong><i class="fas fa-clock"></i> آخر تحديث:</strong> <?php echo date('d/m/Y', strtotime($livreur['updated_at'] ?? $livreur['created_at'])); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
<?php
// donateur/confirmer-commandes.php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'donateur') {
    header('Location: ../auth/login.php');
    exit();
}

require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$user_id = $_SESSION['user_id'];
$user_nom = $_SESSION['user_nom'];

$success = '';
$error = '';

// ========== تأكيد طلب ==========
if (isset($_GET['confirm']) && isset($_GET['demande_id'])) {
    $demande_id = $_GET['demande_id'];
    
    try {
        // تأكيد الطلب
        $query = "UPDATE demandes SET statut = 'acceptee' WHERE id = :demande_id AND don_id IN (SELECT id FROM dons WHERE donateur_id = :user_id)";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':demande_id', $demande_id);
        $stmt->bindParam(':user_id', $user_id);
        
        if ($stmt->execute()) {
            // تحديث حالة التبرع ليصبح محجوزاً
            $query_don = "UPDATE dons SET statut = 'reserve' WHERE id = (SELECT don_id FROM demandes WHERE id = :demande_id)";
            $stmt_don = $db->prepare($query_don);
            $stmt_don->bindParam(':demande_id', $demande_id);
            $stmt_don->execute();
            
            // رفض الطلبات الأخرى على نفس التبرع (إن وجدت)
            $query_refuse = "UPDATE demandes SET statut = 'refusee' WHERE don_id = (SELECT don_id FROM demandes WHERE id = :demande_id) AND id != :demande_id AND statut = 'en_attente'";
            $stmt_refuse = $db->prepare($query_refuse);
            $stmt_refuse->bindParam(':demande_id', $demande_id);
            $stmt_refuse->execute();
            
            $success = "✅ تم تأكيد الطلب بنجاح!";
        }
    } catch(PDOException $e) {
        $error = "❌ خطأ في تأكيد الطلب: " . $e->getMessage();
    }
}

// ========== رفض طلب ==========
if (isset($_GET['refuse']) && isset($_GET['demande_id'])) {
    $demande_id = $_GET['refuse'];
    
    try {
        $query = "UPDATE demandes SET statut = 'refusee' WHERE id = :demande_id AND don_id IN (SELECT id FROM dons WHERE donateur_id = :user_id)";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':demande_id', $demande_id);
        $stmt->bindParam(':user_id', $user_id);
        
        if ($stmt->execute()) {
            $success = "✅ تم رفض الطلب بنجاح";
        }
    } catch(PDOException $e) {
        $error = "❌ خطأ في رفض الطلب: " . $e->getMessage();
    }
}

// ========== جلب الطلبات المنتظرة ==========
$query_pending = "
    SELECT d.*, 
           u.nom as beneficiaire_nom, 
           u.email as beneficiaire_email,
           u.telephone as beneficiaire_telephone,
           u.ville as beneficiaire_ville,
           don.titre as don_titre,
           don.description as don_description,
           don.categorie,
           don.etat,
           don.ville as don_ville,
           don.adresse_retrait,
           don.statut as don_statut
    FROM demandes d
    INNER JOIN users u ON d.beneficiaire_id = u.id
    INNER JOIN dons don ON d.don_id = don.id
    WHERE don.donateur_id = :user_id 
    AND d.statut = 'en_attente'
    ORDER BY d.created_at DESC
";

$stmt_pending = $db->prepare($query_pending);
$stmt_pending->bindParam(':user_id', $user_id);
$stmt_pending->execute();
$pending_demandes = $stmt_pending->fetchAll(PDO::FETCH_ASSOC);

// ========== جلب الطلبات المؤكدة مؤخراً ==========
$query_confirmed = "
    SELECT d.*, 
           u.nom as beneficiaire_nom, 
           don.titre as don_titre,
           don.categorie
    FROM demandes d
    INNER JOIN users u ON d.beneficiaire_id = u.id
    INNER JOIN dons don ON d.don_id = don.id
    WHERE don.donateur_id = :user_id 
    AND d.statut = 'acceptee'
    ORDER BY d.created_at DESC
    LIMIT 5
";

$stmt_confirmed = $db->prepare($query_confirmed);
$stmt_confirmed->bindParam(':user_id', $user_id);
$stmt_confirmed->execute();
$confirmed_demandes = $stmt_confirmed->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'تأكيد الطلبات';
require_once '../includes/header.php';
?>

<!-- Page Header -->
<div class="page-header">
    <h1><i class="fas fa-check-circle"></i> تأكيد الطلبات</h1>
    <p>راجع وقم بتأكيد أو رفض طلبات المستفيدين على تبرعاتك</p>
</div>

<?php if($success): ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
<?php endif; ?>

<?php if($error): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>

<div class="row">
    <div class="col-8">
        <!-- Pending Demands -->
        <div class="card" style="margin-bottom: 25px;">
            <div class="card-header">
                <h3><i class="fas fa-clock"></i> الطلبات في الانتظار (<?php echo count($pending_demandes); ?>)</h3>
            </div>
            <div class="card-body">
                <?php if(empty($pending_demandes)): ?>
                    <div style="text-align: center; padding: 40px; color: #666;">
                        <i class="fas fa-check-circle" style="font-size: 60px; margin-bottom: 20px; opacity: 0.3; color: #00b894;"></i>
                        <p>لا توجد طلبات في الانتظار</p>
                        <p style="font-size: 14px; color: #888;">جميع الطلبات تمت معالجتها</p>
                    </div>
                <?php else: ?>
                    <?php foreach($pending_demandes as $demande): ?>
                    <div class="card" style="margin-bottom: 20px; border-left: 4px solid #fdcb6e;">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-3">
                                    <div style="text-align: center;">
                                        <div style="width: 60px; height: 60px; background: linear-gradient(135deg, #f59e0b, #d97706); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 20px; margin: 0 auto 10px;">
                                            <?php echo strtoupper(substr($demande['beneficiaire_nom'], 0, 1)); ?>
                                        </div>
                                        <h5 style="margin-bottom: 5px;"><?php echo htmlspecialchars($demande['beneficiaire_nom']); ?></h5>
                                        <?php if($demande['beneficiaire_ville']): ?>
                                            <div style="font-size: 13px; color: #666;">
                                                <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($demande['beneficiaire_ville']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="col-5">
                                    <h5 style="color: var(--dark); margin-bottom: 10px;">
                                        <i class="fas fa-gift"></i> <?php echo htmlspecialchars($demande['don_titre']); ?>
                                    </h5>
                                    
                                    <div style="margin-bottom: 10px;">
                                        <span class="badge badge-primary"><?php echo $demande['categorie']; ?></span>
                                        <span class="badge badge-success"><?php echo $demande['etat']; ?></span>
                                    </div>
                                    
                                    <p style="color: #666; font-size: 14px; margin-bottom: 15px;">
                                        <strong>رسالة المستفيد:</strong><br>
                                        <?php echo nl2br(htmlspecialchars($demande['message_demande'])); ?>
                                    </p>
                                    
                                    <div style="font-size: 13px; color: #888;">
                                        <i class="fas fa-calendar"></i> <?php echo date('d/m/Y H:i', strtotime($demande['created_at'])); ?>
                                    </div>
                                </div>
                                
                                <div class="col-4">
                                    <div style="text-align: center; margin-bottom: 15px;">
                                        <span class="badge" style="background: #fdcb6e20; color: #e17055; border: 1px solid #fdcb6e; padding: 8px 15px;">
                                            ⏳ في الانتظار
                                        </span>
                                    </div>
                                    
                                    <div style="display: flex; flex-direction: column; gap: 10px;">
                                        <a href="?confirm=1&demande_id=<?php echo $demande['id']; ?>" 
                                           class="btn btn-success"
                                           onclick="return confirm('هل أنت متأكد من تأكيد هذا الطلب؟')">
                                            <i class="fas fa-check"></i> تأكيد الطلب
                                        </a>
                                        
                                        <a href="?refuse=<?php echo $demande['id']; ?>" 
                                           class="btn btn-danger"
                                           onclick="return confirm('هل أنت متأكد من رفض هذا الطلب؟')">
                                            <i class="fas fa-times"></i> رفض الطلب
                                        </a>
                                        
                                        <a href="messagerie.php?user_id=<?php echo $demande['beneficiaire_id']; ?>" class="btn btn-outline">
                                            <i class="fas fa-comments"></i> التواصل
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Don Information -->
        <?php if(!empty($pending_demandes)): ?>
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-info-circle"></i> معلومات مهمة</h3>
            </div>
            <div class="card-body">
                <div style="color: #666; line-height: 1.6;">
                    <p><strong>نصائح للتعامل مع الطلبات:</strong></p>
                    <ul style="padding-right: 20px;">
                        <li>رد على الطلبات في أسرع وقت ممكن</li>
                        <li>تواصل مع المستفيد لترتيب موعد الاستلام</li>
                        <li>تأكد من أن المستفيد قادر على استلام التبرع</li>
                        <li>يمكنك التواصل عبر المراسلة أو الهاتف</li>
                        <li>بعد التأكيد، سيتم رفض الطلبات الأخرى تلقائياً</li>
                    </ul>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="col-4">
        <!-- Confirmed Demands -->
        <div class="card" style="margin-bottom: 25px;">
            <div class="card-header">
                <h3><i class="fas fa-check-circle"></i> الطلبات المؤكدة حديثاً</h3>
            </div>
            <div class="card-body">
                <?php if(empty($confirmed_demandes)): ?>
                    <div style="text-align: center; padding: 30px; color: #666;">
                        <i class="fas fa-inbox" style="font-size: 40px; margin-bottom: 15px; opacity: 0.3;"></i>
                        <p>لا توجد طلبات مؤكدة</p>
                    </div>
                <?php else: ?>
                    <?php foreach($confirmed_demandes as $demande): ?>
                    <div style="border: 1px solid #e0e0e0; border-radius: 8px; padding: 15px; margin-bottom: 15px;">
                        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                            <div style="width: 40px; height: 40px; background: linear-gradient(135deg, #f59e0b, #d97706); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 16px;">
                                <?php echo strtoupper(substr($demande['beneficiaire_nom'], 0, 1)); ?>
                            </div>
                            <div>
                                <strong style="font-size: 14px;"><?php echo htmlspecialchars($demande['beneficiaire_nom']); ?></strong>
                                <div style="font-size: 12px; color: #666;">
                                    <?php echo date('d/m/Y', strtotime($demande['created_at'])); ?>
                                </div>
                            </div>
                        </div>
                        <div style="font-size: 13px; color: #333;">
                            <i class="fas fa-gift"></i> <?php echo htmlspecialchars($demande['don_titre']); ?>
                        </div>
                        <div style="margin-top: 5px;">
                            <span class="badge badge-success" style="font-size: 11px;">مؤكد</span>
                            <span class="badge badge-primary" style="font-size: 11px;"><?php echo $demande['categorie']; ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="card" style="margin-bottom: 25px;">
            <div class="card-header">
                <h3><i class="fas fa-bolt"></i> إجراءات سريعة</h3>
            </div>
            <div class="card-body">
                <div style="display: flex; flex-direction: column; gap: 10px;">
                    <a href="mes-dons.php" class="btn btn-outline">
                        <i class="fas fa-boxes"></i> عرض تبرعاتي
                    </a>
                    <a href="messagerie.php" class="btn btn-outline">
                        <i class="fas fa-comments"></i> الذهاب للمراسلة
                    </a>
                    <a href="publier-don.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> نشر تبرع جديد
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Stats -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-chart-bar"></i> إحصائيات</h3>
            </div>
            <div class="card-body">
                <div style="text-align: center;">
                    <div style="font-size: 36px; color: var(--primary); font-weight: bold; margin-bottom: 10px;">
                        <?php echo count($pending_demandes); ?>
                    </div>
                    <div style="color: #666; font-size: 14px;">طلب في الانتظار</div>
                </div>
                
                <div style="margin-top: 20px; text-align: center;">
                    <div style="font-size: 24px; color: #00b894; font-weight: bold; margin-bottom: 5px;">
                        <?php echo count($confirmed_demandes); ?>
                    </div>
                    <div style="color: #666; font-size: 14px;">طلب مؤكد</div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
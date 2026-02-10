<?php
// donateur/voir-don.php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'donateur') {
    header('Location: ../auth/login.php');
    exit();
}

require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$user_id = $_SESSION['user_id'];
$don_id = $_GET['id'] ?? null;

if (!$don_id) {
    header('Location: mes-dons.php');
    exit();
}

// Récupérer les détails du don
$query = "
    SELECT d.*, 
           u.nom as donateur_nom,
           u.email as donateur_email,
           u.telephone as donateur_telephone
    FROM dons d
    INNER JOIN users u ON d.donateur_id = u.id
    WHERE d.id = :don_id AND d.donateur_id = :user_id
";

$stmt = $db->prepare($query);
$stmt->bindParam(":don_id", $don_id);
$stmt->bindParam(":user_id", $user_id);
$stmt->execute();
$don = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$don) {
    header('Location: mes-dons.php');
    exit();
}

// Récupérer les photos supplémentaires
$query_photos = "SELECT photo_path FROM don_photos WHERE don_id = :don_id ORDER BY id";
$stmt_photos = $db->prepare($query_photos);
$stmt_photos->bindParam(":don_id", $don_id);
$stmt_photos->execute();
$photos = $stmt_photos->fetchAll(PDO::FETCH_COLUMN);

// Récupérer les demandes pour ce don
$query_demandes = "
    SELECT d.*, u.nom as beneficiaire_nom, u.ville as beneficiaire_ville
    FROM demandes d
    INNER JOIN users u ON d.beneficiaire_id = u.id
    WHERE d.don_id = :don_id
    ORDER BY d.created_at DESC
";
$stmt_demandes = $db->prepare($query_demandes);
$stmt_demandes->bindParam(":don_id", $don_id);
$stmt_demandes->execute();
$demandes = $stmt_demandes->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'عرض التبرع';
require_once '../includes/header.php';
?>

<!-- Page Header -->
<div class="page-header">
    <h1><i class="fas fa-eye"></i> عرض التبرع</h1>
    <p>تفاصيل كاملة عن تبرعك</p>
</div>

<div class="row">
    <div class="col-8">
        <!-- Don Information -->
        <div class="card" style="margin-bottom: 25px;">
            <div class="card-header">
                <h3><i class="fas fa-gift"></i> <?php echo htmlspecialchars($don['titre']); ?></h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-5">
                        <!-- Don Image -->
                        <div style="height: 250px; background: #f8f9fa; border-radius: 10px; overflow: hidden; margin-bottom: 15px;">
                            <?php if(!empty($don['photo_principale'])): 
                                $image_path = '../' . $don['photo_principale'];
                                if(file_exists($image_path)): ?>
                                    <img src="<?php echo $image_path; ?>" 
                                         alt="<?php echo htmlspecialchars($don['titre']); ?>"
                                         style="width: 100%; height: 100%; object-fit: cover;">
                                <?php else: ?>
                                    <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; color: #aaa; font-size: 60px;">
                                        <?php 
                                        $defaultImages = [
                                            'vetements' => '👕',
                                            'nourriture' => '🍎',
                                            'meubles' => '🛋️',
                                            'livres' => '📚',
                                            'electromenager' => '🔌',
                                            'divers' => '📦'
                                        ];
                                        echo $defaultImages[$don['categorie']] ?? '📦';
                                        ?>
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; color: #aaa; font-size: 60px;">
                                    <?php 
                                    $defaultImages = [
                                        'vetements' => '👕',
                                        'nourriture' => '🍎',
                                        'meubles' => '🛋️',
                                        'livres' => '📚',
                                        'electromenager' => '🔌',
                                        'divers' => '📦'
                                    ];
                                    echo $defaultImages[$don['categorie']] ?? '📦';
                                    ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Additional Photos -->
                        <?php if(!empty($photos)): ?>
                            <div style="margin-top: 20px;">
                                <h5><i class="fas fa-images"></i> صور إضافية</h5>
                                <div style="display: flex; gap: 10px; flex-wrap: wrap; margin-top: 10px;">
                                    <?php foreach($photos as $photo): 
                                        $photo_path = '../' . $photo;
                                        if(file_exists($photo_path)): ?>
                                            <img src="<?php echo $photo_path; ?>" 
                                                 style="width: 80px; height: 80px; object-fit: cover; border-radius: 8px; cursor: pointer;"
                                                 onclick="openImageModal('<?php echo $photo_path; ?>')">
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="col-7">
                        <div style="margin-bottom: 20px;">
                            <div style="display: flex; gap: 10px; margin-bottom: 15px;">
                                <span class="badge badge-primary"><?php echo $don['categorie']; ?></span>
                                <span class="badge badge-success"><?php echo $don['etat']; ?></span>
                                <?php if($don['statut'] == 'disponible'): ?>
                                    <span class="badge badge-success">متاح</span>
                                <?php elseif($don['statut'] == 'reserve'): ?>
                                    <span class="badge badge-warning">محجوز</span>
                                <?php else: ?>
                                    <span class="badge badge-info">مكتمل</span>
                                <?php endif; ?>
                            </div>
                            
                            <h4 style="color: var(--dark); margin-bottom: 15px;">الوصف</h4>
                            <p style="color: #666; line-height: 1.6; background: #f8f9fa; padding: 15px; border-radius: 8px;">
                                <?php echo nl2br(htmlspecialchars($don['description'])); ?>
                            </p>
                        </div>
                        
                        <div style="background: #f8f9fa; padding: 20px; border-radius: 8px;">
                            <h5><i class="fas fa-map-marker-alt"></i> معلومات الاستلام</h5>
                            <div style="margin-top: 10px;">
                                <div style="display: flex; margin-bottom: 10px;">
                                    <strong style="width: 120px; color: #666;">المدينة:</strong>
                                    <span><?php echo htmlspecialchars($don['ville'] ?: 'غير محدد'); ?></span>
                                </div>
                                <div style="display: flex;">
                                    <strong style="width: 120px; color: #666;">العنوان:</strong>
                                    <span><?php echo htmlspecialchars($don['adresse_retrait'] ?: 'غير محدد'); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Demandes List -->
        <?php if(!empty($demandes)): ?>
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-file-alt"></i> الطلبات على هذا التبرع (<?php echo count($demandes); ?>)</h3>
            </div>
            <div class="card-body">
                <?php foreach($demandes as $demande): ?>
                    <div style="border: 1px solid #eee; border-radius: 10px; padding: 20px; margin-bottom: 15px;">
                        <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 15px;">
                            <div>
                                <h5 style="margin-bottom: 5px;">
                                    <i class="fas fa-user"></i> <?php echo htmlspecialchars($demande['beneficiaire_nom']); ?>
                                    <?php if($demande['beneficiaire_ville']): ?>
                                        <small style="color: #666; margin-right: 10px;">
                                            <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($demande['beneficiaire_ville']); ?>
                                        </small>
                                    <?php endif; ?>
                                </h5>
                                <span class="badge badge-<?php 
                                    echo $demande['statut'] == 'en_attente' ? 'warning' : 
                                         ($demande['statut'] == 'acceptee' ? 'success' : 'danger');
                                ?>">
                                    <?php echo $demande['statut']; ?>
                                </span>
                                <small style="color: #888; margin-right: 10px;">
                                    <i class="fas fa-calendar"></i> <?php echo date('d/m/Y H:i', strtotime($demande['created_at'])); ?>
                                </small>
                            </div>
                            <a href="demandes-don.php" class="btn btn-sm btn-outline">
                                <i class="fas fa-eye"></i> عرض التفاصيل
                            </a>
                        </div>
                        
                        <div style="background: #f8f9fa; padding: 15px; border-radius: 8px;">
                            <strong style="color: #666;">رسالة المستفيد:</strong>
                            <p style="margin-top: 10px; color: #333; line-height: 1.5;">
                                <?php echo nl2br(htmlspecialchars($demande['message_demande'])); ?>
                            </p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="col-4">
        <!-- Actions -->
        <div class="card" style="margin-bottom: 25px;">
            <div class="card-header">
                <h3><i class="fas fa-cogs"></i> الإجراءات</h3>
            </div>
            <div class="card-body">
                <div style="display: flex; flex-direction: column; gap: 10px;">
                    <a href="mes-dons.php" class="btn btn-outline">
                        <i class="fas fa-arrow-right"></i> العودة إلى قائمة التبرعات
                    </a>
                    
                    <?php if($don['statut'] == 'disponible'): ?>
                        <a href="modifier-don.php?id=<?php echo $don['id']; ?>" class="btn btn-warning">
                            <i class="fas fa-edit"></i> تعديل التبرع
                        </a>
                        
                        <a href="?action=delete&id=<?php echo $don['id']; ?>" 
                           class="btn btn-danger"
                           onclick="return confirm('هل أنت متأكد من حذف هذا التبرع؟')">
                            <i class="fas fa-trash"></i> حذف التبرع
                        </a>
                    <?php endif; ?>
                    
                    <?php if(!empty($demandes)): ?>
                        <a href="demandes-don.php?don_id=<?php echo $don['id']; ?>" class="btn btn-primary">
                            <i class="fas fa-file-alt"></i> إدارة الطلبات
                        </a>
                    <?php endif; ?>
                    
                    <a href="messagerie.php" class="btn btn-info">
                        <i class="fas fa-comments"></i> الذهاب للمراسلة
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Don Information -->
        <div class="card" style="margin-bottom: 25px;">
            <div class="card-header">
                <h3><i class="fas fa-info-circle"></i> معلومات النشر</h3>
            </div>
            <div class="card-body">
                <div style="margin-bottom: 15px;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                        <span style="color: #666;">تاريخ النشر:</span>
                        <span><?php echo date('d/m/Y', strtotime($don['created_at'])); ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                        <span style="color: #666;">آخر تحديث:</span>
                        <span><?php echo date('d/m/Y', strtotime($don['updated_at'])); ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: #666;">رقم التبرع:</span>
                        <span style="font-family: monospace; font-weight: bold;">#<?php echo $don['id']; ?></span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Contact Info -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-user-circle"></i> معلوماتك</h3>
            </div>
            <div class="card-body">
                <div style="text-align: center; margin-bottom: 15px;">
                    <div style="width: 70px; height: 70px; background: linear-gradient(135deg, #00b894, #00cec9); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 24px; margin: 0 auto 10px;">
                        <?php echo strtoupper(substr($don['donateur_nom'], 0, 1)); ?>
                    </div>
                    <h5 style="margin-bottom: 5px;"><?php echo htmlspecialchars($don['donateur_nom']); ?></h5>
                    <small style="color: #666;">متبرع</small>
                </div>
                
                <div style="background: #f8f9fa; padding: 15px; border-radius: 8px;">
                    <?php if($don['donateur_email']): ?>
                    <div style="margin-bottom: 10px;">
                        <i class="fas fa-envelope" style="color: #666; margin-left: 10px;"></i>
                        <span><?php echo htmlspecialchars($don['donateur_email']); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if($don['donateur_telephone']): ?>
                    <div>
                        <i class="fas fa-phone" style="color: #666; margin-left: 10px;"></i>
                        <span><?php echo htmlspecialchars($don['donateur_telephone']); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Image Modal -->
<div id="imageModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.8); z-index: 3000; justify-content: center; align-items: center;">
    <div style="position: relative; max-width: 90%; max-height: 90%;">
        <img id="modalImage" src="" style="max-width: 100%; max-height: 90vh; border-radius: 10px;">
        <button onclick="closeImageModal()" style="position: absolute; top: -40px; left: 0; background: none; border: none; color: white; font-size: 30px; cursor: pointer;">×</button>
    </div>
</div>

<script>
function openImageModal(imageSrc) {
    document.getElementById('modalImage').src = imageSrc;
    document.getElementById('imageModal').style.display = 'flex';
}

function closeImageModal() {
    document.getElementById('imageModal').style.display = 'none';
}

// Close modal when clicking outside
document.getElementById('imageModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeImageModal();
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>
<?php
// livreur/mission-details.php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'livreur') {
    header('Location: ../auth/login.php');
    exit();
}

require_once '../config/database.php';
require_once '../includes/header.php';

$database = new Database();
$db = $database->getConnection();

$user_id = $_SESSION['user_id'];
$mission_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$mission_id) {
    header('Location: missions.php');
    exit();
}

// Vérifier si le livreur est actif
$query_livreur = "SELECT * FROM livreurs WHERE user_id = :user_id AND statut = 'actif'";
$stmt_livreur = $db->prepare($query_livreur);
$stmt_livreur->bindParam(":user_id", $user_id);
$stmt_livreur->execute();
$livreur = $stmt_livreur->fetch(PDO::FETCH_ASSOC);

if (!$livreur) {
    echo '<div class="alert alert-warning">⚠️ حساب المندوب الخاص بك غير نشط</div>';
    include '../includes/footer.php';
    exit;
}

// Récupérer les détails de la mission
$query = "
    SELECT 
        l.*,
        d.titre as don_titre,
        d.description as don_description,
        d.categorie,
        d.etat,
        d.ville,
        d.adresse_retrait,
        d.photo_principale,
        d.livraison_option,
        u_beneficiaire.id as beneficiaire_id,
        u_beneficiaire.nom as beneficiaire_nom,
        u_beneficiaire.email as beneficiaire_email,
        u_beneficiaire.telephone as beneficiaire_telephone,
        u_beneficiaire.adresse as beneficiaire_adresse,
        u_beneficiaire.ville as beneficiaire_ville,
        u_donateur.id as donateur_id,
        u_donateur.nom as donateur_nom,
        u_donateur.email as donateur_email,
        u_donateur.telephone as donateur_telephone,
        u_donateur.adresse as donateur_adresse,
        u_donateur.ville as donateur_ville,
        de.id as demande_id,
        de.message_demande,
        de.created_at as demande_date,
        de.statut as demande_statut
    FROM livraisons l
    INNER JOIN demandes de ON l.demande_id = de.id
    INNER JOIN dons d ON de.don_id = d.id
    INNER JOIN users u_beneficiaire ON de.beneficiaire_id = u_beneficiaire.id
    INNER JOIN users u_donateur ON d.donateur_id = u_donateur.id
    WHERE l.id = :mission_id
";

$stmt = $db->prepare($query);
$stmt->bindParam(':mission_id', $mission_id);
$stmt->execute();
$mission = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$mission) {
    header('Location: missions.php?error=not_found');
    exit();
}

// Vérifier si le livreur a accès à cette mission
if ($mission['livreur_id'] !== null && $mission['livreur_id'] != $user_id) {
    header('Location: missions.php?error=access_denied');
    exit();
}

// Récupérer les photos supplémentaires du don
$query_photos = "SELECT * FROM don_photos WHERE don_id = (SELECT don_id FROM demandes WHERE id = :demande_id)";
$stmt_photos = $db->prepare($query_photos);
$stmt_photos->bindParam(':demande_id', $mission['demande_id']);
$stmt_photos->execute();
$don_photos = $stmt_photos->fetchAll(PDO::FETCH_ASSOC);

// Récupérer l'historique de la livraison
$query_historique = "
    SELECT h.*, u.nom as created_by_nom 
    FROM livraison_historique h
    LEFT JOIN users u ON h.created_by = u.id
    WHERE h.livraison_id = :mission_id
    ORDER BY h.created_at DESC
";
$stmt_historique = $db->prepare($query_historique);
$stmt_historique->bindParam(':mission_id', $mission_id);
$stmt_historique->execute();
$historique = $stmt_historique->fetchAll(PDO::FETCH_ASSOC);

// Traitement des actions
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_status' && $mission['livreur_id'] == $user_id) {
        $new_status = $_POST['status'] ?? '';
        
        try {
            $db->beginTransaction();
            
            $current_status = $mission['statut'];
            
            // Vérifier si le changement est valide
            $valid_transitions = [
                'assignee' => ['en_cours'],
                'en_cours' => ['livree']
            ];
            
            if (isset($valid_transitions[$current_status]) && in_array($new_status, $valid_transitions[$current_status])) {
                
                // Mettre à jour le statut
                $update = "UPDATE livraisons SET statut = :statut";
                
                if ($new_status == 'livree') {
                    $update .= ", date_livraison = NOW()";
                }
                
                $update .= " WHERE id = :mission_id AND livreur_id = :livreur_id";
                
                $stmt_update = $db->prepare($update);
                $stmt_update->bindParam(':statut', $new_status);
                $stmt_update->bindParam(':mission_id', $mission_id);
                $stmt_update->bindParam(':livreur_id', $user_id);
                $stmt_update->execute();
                
                // Ajouter à l'historique
                $historique_query = "INSERT INTO livraison_historique 
                                    (livraison_id, statut_ancien, statut_nouveau, commentaire, created_by, created_at) 
                                    VALUES 
                                    (:livraison_id, :ancien, :nouveau, :commentaire, :created_by, NOW())";
                $stmt_historique = $db->prepare($historique_query);
                $stmt_historique->bindParam(':livraison_id', $mission_id);
                $stmt_historique->bindParam(':ancien', $current_status);
                $stmt_historique->bindParam(':nouveau', $new_status);
                $stmt_historique->bindParam(':commentaire', $_POST['commentaire'] ?? '');
                $stmt_historique->bindParam(':created_by', $user_id);
                $stmt_historique->execute();
                
                // Si livrée, mettre à jour le compteur du livreur
                if ($new_status == 'livree') {
                    $update_livreur = "UPDATE livreurs SET nombre_livraisons = nombre_livraisons + 1 WHERE user_id = :user_id";
                    $stmt_livreur = $db->prepare($update_livreur);
                    $stmt_livreur->bindParam(':user_id', $user_id);
                    $stmt_livreur->execute();
                }
                
                $db->commit();
                $success = "✅ تم تحديث حالة المهمة بنجاح";
                
                // Rafraîchir les données
                $stmt->execute();
                $mission = $stmt->fetch(PDO::FETCH_ASSOC);
                
            } else {
                throw new Exception("تغيير الحالة غير مسموح به");
            }
            
        } catch(Exception $e) {
            $db->rollBack();
            $error = "❌ خطأ: " . $e->getMessage();
        }
    }
    
    if ($action === 'add_comment') {
        $commentaire = trim($_POST['commentaire'] ?? '');
        
        if (!empty($commentaire)) {
            try {
                $historique_query = "INSERT INTO livraison_historique 
                                    (livraison_id, statut_ancien, statut_nouveau, commentaire, created_by, created_at) 
                                    VALUES 
                                    (:livraison_id, :ancien, :nouveau, :commentaire, :created_by, NOW())";
                $stmt_historique = $db->prepare($historique_query);
                $stmt_historique->bindParam(':livraison_id', $mission_id);
                $stmt_historique->bindParam(':ancien', $mission['statut']);
                $stmt_historique->bindParam(':nouveau', $mission['statut']);
                $stmt_historique->bindParam(':commentaire', $commentaire);
                $stmt_historique->bindParam(':created_by', $user_id);
                $stmt_historique->execute();
                
                $success = "✅ تم إضافة التعليق بنجاح";
                
                // Rafraîchir l'historique
                $stmt_historique = $db->prepare($query_historique);
                $stmt_historique->bindParam(':mission_id', $mission_id);
                $stmt_historique->execute();
                $historique = $stmt_historique->fetchAll(PDO::FETCH_ASSOC);
                
            } catch(PDOException $e) {
                $error = "❌ خطأ: " . $e->getMessage();
            }
        }
    }
}

// Traductions
$categories = [
    'vetements' => 'ملابس',
    'nourriture' => 'طعام',
    'meubles' => 'أثاث',
    'livres' => 'كتب',
    'electromenager' => 'أجهزة كهربائية',
    'divers' => 'متنوع'
];

$etats = [
    'neuf' => 'جديد',
    'bon_etat' => 'حالة جيدة',
    'usage' => 'مستعمل'
];

$statuts_livraison = [
    'en_attente' => 'في انتظار مندوب',
    'assignee' => 'تم التعيين',
    'en_cours' => 'جارية',
    'livree' => 'تم التوصيل',
    'annulee' => 'ملغاة'
];

$statuts_couleurs = [
    'en_attente' => 'warning',
    'assignee' => 'info',
    'en_cours' => 'primary',
    'livree' => 'success',
    'annulee' => 'danger'
];

$livraison_options = [
    'none' => 'المستفيد يتحمل التوصيل',
    'fifty' => 'المتبرع يتحمل 50%',
    'full' => 'المتبرع يتحمل التوصيل كاملاً'
];
?>

<style>
.mission-details-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

/* En-tête */
.mission-header {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    padding: 30px;
    border-radius: 15px;
    margin-bottom: 30px;
    position: relative;
    overflow: hidden;
}

.mission-header::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
    animation: rotate 20s linear infinite;
}

@keyframes rotate {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.mission-title {
    position: relative;
    z-index: 1;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 20px;
}

.mission-title h1 {
    font-size: 28px;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 15px;
}

.mission-badge {
    padding: 8px 20px;
    border-radius: 30px;
    font-size: 14px;
    font-weight: 600;
    background: rgba(255,255,255,0.2);
    backdrop-filter: blur(10px);
}

/* Grille */
.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
    gap: 25px;
    margin-bottom: 25px;
}

.info-card {
    background: white;
    border-radius: 15px;
    padding: 25px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.05);
    transition: transform 0.3s, box-shadow 0.3s;
}

.info-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
}

.info-card h3 {
    color: var(--primary);
    margin-bottom: 20px;
    font-size: 18px;
    display: flex;
    align-items: center;
    gap: 10px;
    border-bottom: 2px solid #f0f0f0;
    padding-bottom: 15px;
}

.info-card h3 i {
    color: var(--accent);
}

.info-row {
    display: flex;
    margin-bottom: 15px;
    padding: 10px;
    background: #f8f9fa;
    border-radius: 8px;
}

.info-label {
    width: 120px;
    color: var(--secondary);
    font-weight: 500;
}

.info-value {
    flex: 1;
    color: var(--dark);
    font-weight: 500;
}

.info-value i {
    color: var(--accent);
    margin-left: 5px;
}

/* Photos */
.photo-gallery {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 15px;
    margin-top: 20px;
}

.photo-item {
    position: relative;
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 3px 10px rgba(0,0,0,0.1);
    cursor: pointer;
    transition: transform 0.3s;
}

.photo-item:hover {
    transform: scale(1.05);
}

.photo-item img {
    width: 100%;
    height: 150px;
    object-fit: cover;
}

.photo-item.main-photo {
    border: 3px solid var(--accent);
}

.photo-main-badge {
    position: absolute;
    top: 5px;
    right: 5px;
    background: var(--accent);
    color: white;
    padding: 3px 8px;
    border-radius: 15px;
    font-size: 11px;
}

/* Actions */
.actions-card {
    background: white;
    border-radius: 15px;
    padding: 25px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.05);
    margin-bottom: 25px;
}

.action-buttons {
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
    justify-content: center;
}

.action-buttons .btn {
    min-width: 200px;
    justify-content: center;
}

/* Boutons */
.btn {
    padding: 12px 25px;
    border-radius: 8px;
    border: none;
    cursor: pointer;
    font-weight: 500;
    transition: all 0.3s;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
    font-size: 15px;
}

.btn-sm {
    padding: 8px 15px;
    font-size: 13px;
}

.btn-primary {
    background: linear-gradient(135deg, #0984e3, #74b9ff);
    color: white;
}

.btn-primary:hover {
    background: linear-gradient(135deg, #0873c4, #0984e3);
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(9, 132, 227, 0.3);
}

.btn-success {
    background: linear-gradient(135deg, #00b894, #00cec9);
    color: white;
}

.btn-success:hover {
    background: linear-gradient(135deg, #00a085, #00b7a8);
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0, 184, 148, 0.3);
}

.btn-warning {
    background: linear-gradient(135deg, #fdcb6e, #f39c12);
    color: white;
}

.btn-warning:hover {
    background: linear-gradient(135deg, #fdb94e, #e67e22);
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(253, 203, 110, 0.3);
}

.btn-danger {
    background: linear-gradient(135deg, #d63031, #ff7675);
    color: white;
}

.btn-danger:hover {
    background: linear-gradient(135deg, #c0392b, #e17055);
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(214, 48, 49, 0.3);
}

.btn-outline {
    background: transparent;
    border: 2px solid var(--accent);
    color: var(--accent);
}

.btn-outline:hover {
    background: var(--accent);
    color: white;
}

.btn-info {
    background: linear-gradient(135deg, #00cec9, #00b894);
    color: white;
}

.btn-secondary {
    background: #6c757d;
    color: white;
}

.btn-secondary:hover {
    background: #5a6268;
}

/* Timeline */
.timeline {
    position: relative;
    padding: 20px 0;
}

.timeline::before {
    content: '';
    position: absolute;
    top: 0;
    bottom: 0;
    right: 20px;
    width: 2px;
    background: #e0e0e0;
}

.timeline-item {
    position: relative;
    padding-right: 50px;
    margin-bottom: 25px;
}

.timeline-dot {
    position: absolute;
    right: 15px;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: var(--accent);
    border: 2px solid white;
    box-shadow: 0 0 0 2px var(--accent);
}

.timeline-dot.warning { background: #fdcb6e; box-shadow: 0 0 0 2px #fdcb6e; }
.timeline-dot.success { background: #00b894; box-shadow: 0 0 0 2px #00b894; }
.timeline-dot.info { background: #0984e3; box-shadow: 0 0 0 2px #0984e3; }
.timeline-dot.primary { background: #6c5ce7; box-shadow: 0 0 0 2px #6c5ce7; }

.timeline-content {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 10px;
}

.timeline-date {
    font-size: 12px;
    color: var(--gray);
    margin-bottom: 5px;
}

.timeline-title {
    font-weight: 600;
    margin-bottom: 5px;
}

.timeline-text {
    color: var(--secondary);
    font-size: 14px;
}

/* Modal */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.5);
    z-index: 2000;
    justify-content: center;
    align-items: center;
}

.modal.active {
    display: flex;
}

.modal-content {
    background: white;
    border-radius: 15px;
    max-width: 500px;
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
}

.modal-header {
    padding: 20px 25px;
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-radius: 15px 15px 0 0;
}

.modal-header h3 {
    margin: 0;
    font-size: 18px;
}

.modal-close {
    background: none;
    border: none;
    color: white;
    font-size: 24px;
    cursor: pointer;
}

.modal-body {
    padding: 25px;
}

/* Form */
.form-group {
    margin-bottom: 20px;
}

.form-label {
    display: block;
    margin-bottom: 8px;
    color: var(--primary);
    font-weight: 500;
}

.form-control {
    width: 100%;
    padding: 12px 15px;
    border: 2px solid #e1e1e1;
    border-radius: 8px;
    font-size: 15px;
    transition: border 0.3s;
}

.form-control:focus {
    outline: none;
    border-color: var(--accent);
    box-shadow: 0 0 0 3px rgba(9, 132, 227, 0.1);
}

textarea.form-control {
    resize: vertical;
    min-height: 100px;
}

/* Badges */
.badge {
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
    display: inline-block;
}

.badge-warning {
    background: #fff3cd;
    color: #856404;
}

.badge-success {
    background: #d4edda;
    color: #155724;
}

.badge-info {
    background: #d1ecf1;
    color: #0c5460;
}

.badge-primary {
    background: #cce5ff;
    color: #004085;
}

.badge-danger {
    background: #f8d7da;
    color: #721c24;
}

/* Alert */
.alert {
    padding: 15px 20px;
    border-radius: 10px;
    margin-bottom: 20px;
    border-right: 4px solid;
}

.alert-success {
    background: #d4edda;
    border-right-color: #155724;
    color: #155724;
}

.alert-danger {
    background: #f8d7da;
    border-right-color: #721c24;
    color: #721c24;
}

/* Contact Info */
.contact-info {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.contact-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px;
    background: #f8f9fa;
    border-radius: 8px;
}

.contact-item i {
    width: 30px;
    height: 30px;
    background: var(--accent);
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.contact-item a {
    color: var(--accent);
    text-decoration: none;
}

.contact-item a:hover {
    text-decoration: underline;
}

/* Responsive */
@media (max-width: 768px) {
    .mission-title {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .info-grid {
        grid-template-columns: 1fr;
    }
    
    .action-buttons .btn {
        width: 100%;
    }
    
    .photo-gallery {
        grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
    }
    
    .info-row {
        flex-direction: column;
    }
    
    .info-label {
        width: 100%;
        margin-bottom: 5px;
    }
}
</style>

<div class="main-content">
    <div class="container">
        
        <!-- En-tête -->
        <div class="mission-header">
            <div class="mission-title">
                <h1>
                    <i class="fas fa-tasks"></i>
                    تفاصيل المهمة #<?php echo $mission_id; ?>
                </h1>
                <div class="mission-badge">
                    <i class="fas fa-<?php 
                        echo $mission['statut'] == 'en_attente' ? 'clock' : 
                            ($mission['statut'] == 'assignee' ? 'user-check' : 
                            ($mission['statut'] == 'en_cours' ? 'play-circle' : 
                            ($mission['statut'] == 'livree' ? 'check-circle' : 'ban')));
                    ?>"></i>
                    <?php echo $statuts_livraison[$mission['statut']] ?? $mission['statut']; ?>
                </div>
            </div>
        </div>

        <?php if($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <?php if($error): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <!-- Actions selon le statut -->
        <?php if($mission['livreur_id'] == $user_id && in_array($mission['statut'], ['assignee', 'en_cours'])): ?>
        <div class="actions-card">
            <h3 style="margin-bottom: 20px; color: var(--primary);">
                <i class="fas fa-cog"></i> إجراءات المهمة
            </h3>
            <div class="action-buttons">
                <?php if($mission['statut'] == 'assignee'): ?>
                    <button class="btn btn-primary" onclick="openStatusModal('en_cours')">
                        <i class="fas fa-play"></i> بدء التوصيل
                    </button>
                <?php elseif($mission['statut'] == 'en_cours'): ?>
                    <button class="btn btn-success" onclick="openStatusModal('livree')">
                        <i class="fas fa-check-circle"></i> تأكيد التوصيل
                    </button>
                <?php endif; ?>
                
                <button class="btn btn-info" onclick="openCommentModal()">
                    <i class="fas fa-comment"></i> إضافة تعليق
                </button>
                
                <a href="messagerie.php?user_id=<?php echo $mission['beneficiaire_id']; ?>" class="btn btn-outline">
                    <i class="fas fa-comments"></i> مراسلة المستفيد
                </a>
                
                <a href="messagerie.php?user_id=<?php echo $mission['donateur_id']; ?>" class="btn btn-outline">
                    <i class="fas fa-comments"></i> مراسلة المتبرع
                </a>
            </div>
        </div>
        <?php endif; ?>

        <!-- Grille d'information -->
        <div class="info-grid">
            <!-- Informations du don -->
            <div class="info-card">
                <h3><i class="fas fa-gift"></i> معلومات التبرع</h3>
                
                <div class="info-row">
                    <span class="info-label">العنوان:</span>
                    <span class="info-value"><?php echo htmlspecialchars($mission['don_titre']); ?></span>
                </div>
                
                <div class="info-row">
                    <span class="info-label">الوصف:</span>
                    <span class="info-value"><?php echo nl2br(htmlspecialchars($mission['don_description'])); ?></span>
                </div>
                
                <div class="info-row">
                    <span class="info-label">الفئة:</span>
                    <span class="info-value">
                        <span class="badge badge-primary">
                            <?php echo $categories[$mission['categorie']] ?? $mission['categorie']; ?>
                        </span>
                    </span>
                </div>
                
                <div class="info-row">
                    <span class="info-label">الحالة:</span>
                    <span class="info-value">
                        <span class="badge badge-success">
                            <?php echo $etats[$mission['etat']] ?? $mission['etat']; ?>
                        </span>
                    </span>
                </div>
                
                <div class="info-row">
                    <span class="info-label">التوصيل:</span>
                    <span class="info-value">
                        <span class="badge badge-info">
                            <?php echo $livraison_options[$mission['livraison_option']] ?? $mission['livraison_option']; ?>
                        </span>
                    </span>
                </div>
                
                <?php if($mission['frais_livraison'] > 0): ?>
                <div class="info-row">
                    <span class="info-label">رسوم التوصيل:</span>
                    <span class="info-value">
                        <strong style="color: var(--success);"><?php echo $mission['frais_livraison']; ?> درهم</strong>
                    </span>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Informations du bénéficiaire -->
            <div class="info-card">
                <h3><i class="fas fa-user"></i> المستفيد</h3>
                
                <div class="info-row">
                    <span class="info-label">الاسم:</span>
                    <span class="info-value"><?php echo htmlspecialchars($mission['beneficiaire_nom']); ?></span>
                </div>
                
                <div class="info-row">
                    <span class="info-label">الهاتف:</span>
                    <span class="info-value">
                        <i class="fas fa-phone"></i> 
                        <a href="tel:<?php echo $mission['beneficiaire_telephone']; ?>">
                            <?php echo htmlspecialchars($mission['beneficiaire_telephone'] ?? 'غير متوفر'); ?>
                        </a>
                    </span>
                </div>
                
                <div class="info-row">
                    <span class="info-label">البريد:</span>
                    <span class="info-value">
                        <i class="fas fa-envelope"></i> 
                        <a href="mailto:<?php echo $mission['beneficiaire_email']; ?>">
                            <?php echo htmlspecialchars($mission['beneficiaire_email']); ?>
                        </a>
                    </span>
                </div>
                
                <div class="info-row">
                    <span class="info-label">المدينة:</span>
                    <span class="info-value">
                        <i class="fas fa-map-marker-alt"></i> 
                        <?php echo htmlspecialchars($mission['beneficiaire_ville'] ?? 'غير محدد'); ?>
                    </span>
                </div>
                
                <?php if($mission['beneficiaire_adresse']): ?>
                <div class="info-row">
                    <span class="info-label">العنوان:</span>
                    <span class="info-value"><?php echo htmlspecialchars($mission['beneficiaire_adresse']); ?></span>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Informations du donateur -->
            <div class="info-card">
                <h3><i class="fas fa-user"></i> المتبرع</h3>
                
                <div class="info-row">
                    <span class="info-label">الاسم:</span>
                    <span class="info-value"><?php echo htmlspecialchars($mission['donateur_nom']); ?></span>
                </div>
                
                <div class="info-row">
                    <span class="info-label">الهاتف:</span>
                    <span class="info-value">
                        <i class="fas fa-phone"></i> 
                        <a href="tel:<?php echo $mission['donateur_telephone']; ?>">
                            <?php echo htmlspecialchars($mission['donateur_telephone'] ?? 'غير متوفر'); ?>
                        </a>
                    </span>
                </div>
                
                <div class="info-row">
                    <span class="info-label">البريد:</span>
                    <span class="info-value">
                        <i class="fas fa-envelope"></i> 
                        <a href="mailto:<?php echo $mission['donateur_email']; ?>">
                            <?php echo htmlspecialchars($mission['donateur_email']); ?>
                        </a>
                    </span>
                </div>
                
                <div class="info-row">
                    <span class="info-label">المدينة:</span>
                    <span class="info-value">
                        <i class="fas fa-map-marker-alt"></i> 
                        <?php echo htmlspecialchars($mission['donateur_ville'] ?? 'غير محدد'); ?>
                    </span>
                </div>
            </div>
            
            <!-- Adresse de retrait -->
            <div class="info-card">
                <h3><i class="fas fa-map-pin"></i> عنوان الاستلام</h3>
                
                <div class="info-row">
                    <span class="info-label">المدينة:</span>
                    <span class="info-value"><?php echo htmlspecialchars($mission['ville']); ?></span>
                </div>
                
                <div class="info-row">
                    <span class="info-label">العنوان:</span>
                    <span class="info-value"><?php echo htmlspecialchars($mission['adresse_retrait']); ?></span>
                </div>
                
                <?php if($mission['code_postal']): ?>
                <div class="info-row">
                    <span class="info-label">الرمز البريدي:</span>
                    <span class="info-value"><?php echo htmlspecialchars($mission['code_postal']); ?></span>
                </div>
                <?php endif; ?>
                
                <?php if($mission['instructions']): ?>
                <div class="info-row">
                    <span class="info-label">تعليمات:</span>
                    <span class="info-value"><?php echo nl2br(htmlspecialchars($mission['instructions'])); ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Photos du don -->
        <?php if(!empty($mission['photo_principale']) || !empty($don_photos)): ?>
        <div class="info-card">
            <h3><i class="fas fa-images"></i> صور التبرع</h3>
            
            <div class="photo-gallery">
                <?php if(!empty($mission['photo_principale'])): ?>
                <div class="photo-item main-photo" onclick="openImage('../<?php echo $mission['photo_principale']; ?>')">
                    <img src="../<?php echo $mission['photo_principale']; ?>" alt="الصورة الرئيسية">
                    <span class="photo-main-badge">الرئيسية</span>
                </div>
                <?php endif; ?>
                
                <?php foreach($don_photos as $photo): ?>
                <div class="photo-item" onclick="openImage('../<?php echo $photo['photo_path']; ?>')">
                    <img src="../<?php echo $photo['photo_path']; ?>" alt="صورة التبرع">
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Message de la demande -->
        <?php if($mission['message_demande']): ?>
        <div class="info-card">
            <h3><i class="fas fa-quote-right"></i> رسالة المستفيد</h3>
            <div style="background: #f8f9fa; padding: 20px; border-radius: 10px; border-right: 4px solid var(--accent);">
                <?php echo nl2br(htmlspecialchars($mission['message_demande'])); ?>
                <div style="margin-top: 10px; color: var(--gray); font-size: 12px;">
                    <i class="fas fa-clock"></i> <?php echo date('d/m/Y H:i', strtotime($mission['demande_date'])); ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Historique de la mission -->
        <div class="info-card">
            <h3><i class="fas fa-history"></i> سير المهمة</h3>
            
            <?php if(empty($historique)): ?>
                <div class="empty-state" style="padding: 30px;">
                    <i class="fas fa-history"></i>
                    <p>لا يوجد سجل للمهمة بعد</p>
                </div>
            <?php else: ?>
                <div class="timeline">
                    <?php foreach($historique as $entry): ?>
                    <div class="timeline-item">
                        <div class="timeline-dot <?php echo $statuts_couleurs[$entry['statut_nouveau']] ?? 'info'; ?>"></div>
                        <div class="timeline-content">
                            <div class="timeline-date">
                                <i class="fas fa-clock"></i> 
                                <?php echo date('d/m/Y H:i', strtotime($entry['created_at'])); ?>
                            </div>
                            <div class="timeline-title">
                                <?php 
                                if($entry['statut_ancien'] != $entry['statut_nouveau']) {
                                    echo "تغيير الحالة من " . ($statuts_livraison[$entry['statut_ancien']] ?? $entry['statut_ancien']) . " إلى " . ($statuts_livraison[$entry['statut_nouveau']] ?? $entry['statut_nouveau']);
                                } else {
                                    echo "تعليق على المهمة";
                                }
                                ?>
                            </div>
                            <?php if($entry['commentaire']): ?>
                            <div class="timeline-text">
                                <i class="fas fa-quote-right"></i> <?php echo nl2br(htmlspecialchars($entry['commentaire'])); ?>
                            </div>
                            <?php endif; ?>
                            <?php if($entry['created_by_nom']): ?>
                            <div style="margin-top: 8px; font-size: 12px; color: var(--gray);">
                                <i class="fas fa-user"></i> بواسطة: <?php echo htmlspecialchars($entry['created_by_nom']); ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Boutons de navigation -->
        <div style="display: flex; gap: 15px; justify-content: center; margin: 30px 0;">
            <a href="missions.php?filter=<?php 
                echo $mission['livreur_id'] == $user_id ? 'mes' : 
                    ($mission['statut'] == 'en_attente' ? 'disponibles' : 'toutes'); 
            ?>" class="btn btn-outline">
                <i class="fas fa-arrow-right"></i> العودة للقائمة
            </a>
            
            <?php if($mission['livreur_id'] == $user_id && $mission['statut'] == 'livree'): ?>
            <a href="note.php?mission_id=<?php echo $mission_id; ?>" class="btn btn-primary">
                <i class="fas fa-star"></i> تقييم التجربة
            </a>
            <?php endif; ?>
            
            <?php if($mission['livreur_id'] == $user_id && in_array($mission['statut'], ['assignee', 'en_cours'])): ?>
            <a href="map.php?mission_id=<?php echo $mission_id; ?>" class="btn btn-info" target="_blank">
                <i class="fas fa-map"></i> عرض على الخريطة
            </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal pour changer le statut -->
<div id="statusModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-edit"></i> <span id="statusModalTitle">تحديث حالة المهمة</span></h3>
            <button class="modal-close" onclick="closeModal('statusModal')">&times;</button>
        </div>
        <div class="modal-body">
            <form method="POST" id="statusForm">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="status" id="newStatus">
                
                <div class="form-group">
                    <label class="form-label">هل أنت متأكد من تحديث حالة هذه المهمة؟</label>
                    <p id="statusMessage" style="margin: 15px 0; padding: 15px; background: #f8f9fa; border-radius: 8px;"></p>
                </div>
                
                <div class="form-group">
                    <label class="form-label">تعليق (اختياري)</label>
                    <textarea name="commentaire" class="form-control" placeholder="أضف تعليقاً حول هذا التغيير..."></textarea>
                </div>
                
                <div style="display: flex; gap: 15px; justify-content: flex-end;">
                    <button type="button" class="btn btn-outline" onclick="closeModal('statusModal')">إلغاء</button>
                    <button type="submit" class="btn btn-success">تأكيد</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal pour ajouter un commentaire -->
<div id="commentModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-comment"></i> إضافة تعليق</h3>
            <button class="modal-close" onclick="closeModal('commentModal')">&times;</button>
        </div>
        <div class="modal-body">
            <form method="POST">
                <input type="hidden" name="action" value="add_comment">
                
                <div class="form-group">
                    <label class="form-label">التعليق</label>
                    <textarea name="commentaire" class="form-control" placeholder="اكتب تعليقك هنا..." required></textarea>
                </div>
                
                <div style="display: flex; gap: 15px; justify-content: flex-end;">
                    <button type="button" class="btn btn-outline" onclick="closeModal('commentModal')">إلغاء</button>
                    <button type="submit" class="btn btn-primary">إضافة</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal pour afficher les images -->
<div id="imageModal" class="modal" onclick="closeModal('imageModal')">
    <div class="modal-content" style="max-width: 800px; background: transparent; box-shadow: none;" onclick="event.stopPropagation()">
        <div style="position: relative;">
            <img id="modalImage" src="" alt="" style="width: 100%; border-radius: 10px;">
            <button class="modal-close" style="position: absolute; top: -40px; left: 0; background: rgba(0,0,0,0.5); border-radius: 50%; width: 40px; height: 40px;" onclick="closeModal('imageModal')">&times;</button>
        </div>
    </div>
</div>

<script>
// Fonctions pour les modals
function openModal(modalId) {
    document.getElementById(modalId).classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('active');
    document.body.style.overflow = 'auto';
}

function openStatusModal(newStatus) {
    const modal = document.getElementById('statusModal');
    const title = document.getElementById('statusModalTitle');
    const statusInput = document.getElementById('newStatus');
    const message = document.getElementById('statusMessage');
    
    statusInput.value = newStatus;
    
    if (newStatus === 'en_cours') {
        title.innerHTML = '<i class="fas fa-play"></i> بدء التوصيل';
        message.innerHTML = 'سيتم تغيير حالة المهمة إلى <strong>جارية</strong>. هل أنت متأكد من بدء هذه المهمة؟';
    } else if (newStatus === 'livree') {
        title.innerHTML = '<i class="fas fa-check-circle"></i> تأكيد التوصيل';
        message.innerHTML = 'سيتم تغيير حالة المهمة إلى <strong>تم التوصيل</strong>. هل أنت متأكد من إنجاز هذه المهمة؟';
    }
    
    openModal('statusModal');
}

function openCommentModal() {
    openModal('commentModal');
}

function openImage(src) {
    document.getElementById('modalImage').src = src;
    openModal('imageModal');
}

// Fermer les modals avec Escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeModal('statusModal');
        closeModal('commentModal');
        closeModal('imageModal');
    }
});

// Rafraîchir la page toutes les 30 secondes pour les missions en cours
<?php if(isset($mission) && in_array($mission['statut'], ['assignee', 'en_cours'])): ?>
setInterval(function() {
    location.reload();
}, 30000);
<?php endif; ?>
</script>

<?php include '../includes/footer.php'; ?>
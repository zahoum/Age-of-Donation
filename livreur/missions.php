<?php
// livreur/missions.php
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

// Vérifier si le livreur est actif
$query = "SELECT l.*, u.nom, u.email, u.telephone, u.ville 
          FROM livreurs l 
          INNER JOIN users u ON l.user_id = u.id 
          WHERE l.user_id = :user_id";
$stmt = $db->prepare($query);
$stmt->bindParam(":user_id", $user_id);
$stmt->execute();
$livreur = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$livreur) {
    echo '<div class="alert alert-warning" style="margin: 20px;">⚠️ حساب المندوب الخاص بك غير مكتمل. الرجاء إكمال ملفك الشخصي أولاً.</div>';
    echo '<div style="text-align: center; margin: 30px;"><a href="profile.php" class="btn btn-primary">إكمال الملف الشخصي</a></div>';
    include '../includes/footer.php';
    exit;
}

if ($livreur['statut'] != 'actif') {
    echo '<div class="alert alert-warning" style="margin: 20px;">⏳ حساب المندوب الخاص بك لم يتم تفعيله بعد من قبل المسؤول</div>';
    include '../includes/footer.php';
    exit;
}

$success = '';
$error = '';

// ========== معالجة قبول مهمة ==========
if (isset($_GET['accept']) && isset($_GET['mission_id'])) {
    $mission_id = $_GET['accept'];
    
    try {
        $db->beginTransaction();
        
        // Vérifier que la mission est disponible
        $check_query = "SELECT l.*, d.ville, d.donateur_id, de.beneficiaire_id,
                               d.titre as don_titre, d.adresse_retrait,
                               u_beneficiaire.nom as beneficiaire_nom,
                               u_beneficiaire.telephone as beneficiaire_telephone,
                               u_donateur.nom as donateur_nom,
                               u_donateur.telephone as donateur_telephone
                       FROM livraisons l
                       INNER JOIN demandes de ON l.demande_id = de.id
                       INNER JOIN dons d ON de.don_id = d.id
                       INNER JOIN users u_beneficiaire ON de.beneficiaire_id = u_beneficiaire.id
                       INNER JOIN users u_donateur ON d.donateur_id = u_donateur.id
                       WHERE l.id = :mission_id 
                       AND l.livreur_id IS NULL 
                       AND l.statut = 'en_attente'
                       AND (d.is_deleted IS NULL OR d.is_deleted = 0)";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->bindParam(':mission_id', $mission_id);
        $check_stmt->execute();
        $mission = $check_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$mission) {
            throw new Exception("هذه المهمة غير متاحة");
        }
        
        // Accepter la mission
        $update = "UPDATE livraisons SET livreur_id = :livreur_id, statut = 'assignee' WHERE id = :mission_id";
        $stmt = $db->prepare($update);
        $stmt->bindParam(':livreur_id', $user_id);
        $stmt->bindParam(':mission_id', $mission_id);
        $stmt->execute();
        
        // Ajouter à l'historique
        $historique_query = "INSERT INTO livraison_historique 
                            (livraison_id, statut_ancien, statut_nouveau, commentaire, created_by, created_at) 
                            VALUES 
                            (:livraison_id, 'en_attente', 'assignee', 'تم قبول المهمة من قبل المندوب', :created_by, NOW())";
        $stmt_historique = $db->prepare($historique_query);
        $stmt_historique->bindParam(':livraison_id', $mission_id);
        $stmt_historique->bindParam(':created_by', $user_id);
        $stmt_historique->execute();
        
        // Créer une notification pour le bénéficiaire et le donateur
        // Notification pour le bénéficiaire
        $notif_benef = "INSERT INTO notifications (user_id, type, titre, message, lien, created_at) 
                        VALUES (:user_id, 'mission', 'تم قبول مهمة التوصيل', 
                        'تم قبول مهمة توصيل التبرع: " . $mission['don_titre'] . " من قبل مندوب', 
                        'livreur/mission-details.php?id=" . $mission_id . "', NOW())";
        $stmt_notif_benef = $db->prepare($notif_benef);
        $stmt_notif_benef->bindParam(':user_id', $mission['beneficiaire_id']);
        $stmt_notif_benef->execute();
        
        // Notification pour le donateur
        $notif_don = "INSERT INTO notifications (user_id, type, titre, message, lien, created_at) 
                     VALUES (:user_id, 'mission', 'تم قبول مهمة التوصيل', 
                     'تم قبول مهمة توصيل التبرع: " . $mission['don_titre'] . " من قبل مندوب', 
                     'donateur/mission-details.php?id=" . $mission_id . "', NOW())";
        $stmt_notif_don = $db->prepare($notif_don);
        $stmt_notif_don->bindParam(':user_id', $mission['donateur_id']);
        $stmt_notif_don->execute();
        
        $db->commit();
        
        // تخزين معلومات المهمة في الجلسة لعرضها في صفحة التفاصيل
        $_SESSION['accepted_mission'] = [
            'id' => $mission_id,
            'don_titre' => $mission['don_titre'],
            'adresse_retrait' => $mission['adresse_retrait'],
            'ville' => $mission['ville'],
            'beneficiaire_nom' => $mission['beneficiaire_nom'],
            'beneficiaire_telephone' => $mission['beneficiaire_telephone'],
            'donateur_nom' => $mission['donateur_nom'],
            'donateur_telephone' => $mission['donateur_telephone']
        ];
        
        $success = "✅ تم قبول المهمة بنجاح";
        
    } catch(Exception $e) {
        $db->rollBack();
        $error = "❌ خطأ: " . $e->getMessage();
    }
}

// ========== معالجة بدء مهمة ==========
if (isset($_GET['start']) && isset($_GET['mission_id'])) {
    $mission_id = $_GET['start'];
    
    try {
        $db->beginTransaction();
        
        // Vérifier que la mission est assignée à ce livreur
        $check_query = "SELECT * FROM livraisons WHERE id = :mission_id AND livreur_id = :user_id AND statut = 'assignee'";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->bindParam(':mission_id', $mission_id);
        $check_stmt->bindParam(':user_id', $user_id);
        $check_stmt->execute();
        
        if ($check_stmt->rowCount() == 0) {
            throw new Exception("لا يمكن بدء هذه المهمة");
        }
        
        $query = "UPDATE livraisons 
                  SET statut = 'en_cours' 
                  WHERE id = :mission_id 
                  AND livreur_id = :user_id 
                  AND statut = 'assignee'";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':mission_id', $mission_id);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        
        // Ajouter à l'historique
        $historique_query = "INSERT INTO livraison_historique 
                            (livraison_id, statut_ancien, statut_nouveau, commentaire, created_by, created_at) 
                            VALUES 
                            (:livraison_id, 'assignee', 'en_cours', 'تم بدء التوصيل', :created_by, NOW())";
        $stmt_historique = $db->prepare($historique_query);
        $stmt_historique->bindParam(':livraison_id', $mission_id);
        $stmt_historique->bindParam(':created_by', $user_id);
        $stmt_historique->execute();
        
        $db->commit();
        $success = "✅ تم بدء المهمة بنجاح";
        
    } catch(Exception $e) {
        $db->rollBack();
        $error = "❌ خطأ: " . $e->getMessage();
    }
}

// ========== معالجة إنهاء مهمة ==========
if (isset($_GET['complete']) && isset($_GET['mission_id'])) {
    $mission_id = $_GET['complete'];
    
    try {
        $db->beginTransaction();
        
        // Vérifier que la mission est en cours par ce livreur
        $check_query = "SELECT * FROM livraisons WHERE id = :mission_id AND livreur_id = :user_id AND statut = 'en_cours'";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->bindParam(':mission_id', $mission_id);
        $check_stmt->bindParam(':user_id', $user_id);
        $check_stmt->execute();
        
        if ($check_stmt->rowCount() == 0) {
            throw new Exception("لا يمكن إنهاء هذه المهمة");
        }
        
        $query = "UPDATE livraisons 
                  SET statut = 'livree', date_livraison = NOW() 
                  WHERE id = :mission_id 
                  AND livreur_id = :user_id 
                  AND statut = 'en_cours'";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':mission_id', $mission_id);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        
        // Ajouter à l'historique
        $historique_query = "INSERT INTO livraison_historique 
                            (livraison_id, statut_ancien, statut_nouveau, commentaire, created_by, created_at) 
                            VALUES 
                            (:livraison_id, 'en_cours', 'livree', 'تم إكمال التوصيل بنجاح', :created_by, NOW())";
        $stmt_historique = $db->prepare($historique_query);
        $stmt_historique->bindParam(':livraison_id', $mission_id);
        $stmt_historique->bindParam(':created_by', $user_id);
        $stmt_historique->execute();
        
        // Mettre à jour le compteur de livraisons du livreur
        $update_livreur = "UPDATE livreurs SET nombre_livraisons = nombre_livraisons + 1 WHERE user_id = :user_id";
        $stmt_livreur = $db->prepare($update_livreur);
        $stmt_livreur->bindParam(':user_id', $user_id);
        $stmt_livreur->execute();
        
        $db->commit();
        $success = "✅ تم إكمال المهمة بنجاح! شكراً لك";
        
    } catch(Exception $e) {
        $db->rollBack();
        $error = "❌ خطأ: " . $e->getMessage();
    }
}

// Récupérer le filtre et la ville
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'disponibles';
$selected_ville = isset($_GET['ville']) ? $_GET['ville'] : '';

// Récupérer toutes les villes disponibles
$ville_query = "SELECT DISTINCT d.ville 
                FROM dons d
                INNER JOIN demandes de ON d.id = de.don_id
                INNER JOIN livraisons l ON de.id = l.demande_id
                WHERE d.ville IS NOT NULL 
                AND d.ville != ''
                AND (d.is_deleted IS NULL OR d.is_deleted = 0)
                ORDER BY d.ville";
$ville_stmt = $db->prepare($ville_query);
$ville_stmt->execute();
$villes = $ville_stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les missions avec toutes les informations nécessaires
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
    WHERE (d.is_deleted IS NULL OR d.is_deleted = 0)
";

// Construction dynamique de la requête selon les filtres
$params = [];

if($filter == 'disponibles') {
    $query .= " AND l.livreur_id IS NULL AND l.statut = 'en_attente'";
    
    // Filtre par ville pour les missions disponibles
    if(!empty($selected_ville)) {
        $query .= " AND d.ville = :ville";
        $params[':ville'] = $selected_ville;
    }
} elseif($filter == 'en_cours') {
    $query .= " AND l.livreur_id = :user_id AND l.statut IN ('assignee', 'en_cours')";
    $params[':user_id'] = $user_id;
} elseif($filter == 'terminees') {
    $query .= " AND l.livreur_id = :user_id AND l.statut = 'livree'";
    $params[':user_id'] = $user_id;
} elseif($filter == 'mes') {
    $query .= " AND l.livreur_id = :user_id";
    $params[':user_id'] = $user_id;
} elseif($filter == 'toutes') {
    if(!empty($selected_ville)) {
        $query .= " AND d.ville = :ville";
        $params[':ville'] = $selected_ville;
    }
}

$query .= " ORDER BY 
    CASE 
        WHEN l.statut = 'en_attente' THEN 0
        WHEN l.statut = 'assignee' THEN 1
        WHEN l.statut = 'en_cours' THEN 2
        ELSE 3
    END,
    l.created_at DESC";

$stmt = $db->prepare($query);

// Binder les paramètres
foreach($params as $key => $value) {
    $stmt->bindValue($key, $value);
}

$stmt->execute();
$missions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Compter les missions par statut
$count_query = "
    SELECT 
        COUNT(CASE WHEN l.livreur_id IS NULL AND l.statut = 'en_attente' THEN 1 END) as disponibles,
        COUNT(CASE WHEN l.livreur_id = :user_id AND l.statut IN ('assignee', 'en_cours') THEN 1 END) as en_cours,
        COUNT(CASE WHEN l.livreur_id = :user_id AND l.statut = 'livree' THEN 1 END) as terminees,
        COUNT(CASE WHEN l.livreur_id = :user_id THEN 1 END) as total_mes
    FROM livraisons l
    INNER JOIN demandes de ON l.demande_id = de.id
    INNER JOIN dons d ON de.don_id = d.id
    WHERE (d.is_deleted IS NULL OR d.is_deleted = 0)
";
$count_stmt = $db->prepare($count_query);
$count_stmt->bindParam(":user_id", $user_id);
$count_stmt->execute();
$counts = $count_stmt->fetch(PDO::FETCH_ASSOC);

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

$livraison_options = [
    'none' => 'المستفيد يتحمل التوصيل',
    'fifty' => 'المتبرع يتحمل 50%',
    'full' => 'المتبرع يتحمل التوصيل كاملاً'
];
?>

<style>
/* Styles spécifiques pour la page des missions */
.missions-filters {
    background: white;
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    margin-bottom: 25px;
}

.city-filter {
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid #eee;
    display: flex;
    align-items: center;
    gap: 15px;
    flex-wrap: wrap;
}

.city-select {
    padding: 10px 15px;
    border: 1px solid #ddd;
    border-radius: 8px;
    font-size: 14px;
    min-width: 250px;
    font-family: 'Tajawal', sans-serif;
    background-color: white;
}

.city-select:focus {
    outline: none;
    border-color: var(--accent);
    box-shadow: 0 0 0 3px rgba(9, 132, 227, 0.1);
}

.mission-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 15px;
    border: 1px solid #eee;
    transition: all 0.3s;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 20px;
}

.mission-card:hover {
    box-shadow: 0 5px 20px rgba(0,0,0,0.1);
    border-color: var(--accent);
}

.mission-card.disponible {
    border-right: 4px solid #00b894;
}

.mission-card.en-cours {
    border-right: 4px solid #fdcb6e;
}

.mission-card.terminee {
    border-right: 4px solid #6c5ce7;
    opacity: 0.9;
    background: #f8f9fa;
}

.mission-info {
    flex: 1;
}

.mission-info h3 {
    margin: 0 0 10px 0;
    color: var(--primary);
    font-size: 18px;
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.mission-details {
    display: flex;
    gap: 20px;
    color: var(--secondary);
    font-size: 14px;
    flex-wrap: wrap;
    margin-bottom: 10px;
}

.mission-details i {
    color: var(--accent);
    width: 20px;
}

.mission-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    min-width: 200px;
    justify-content: flex-end;
}

.mission-badge {
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    display: inline-block;
}

.ville-tag {
    background: #e3f2fd;
    color: #1976d2;
    padding: 3px 10px;
    border-radius: 15px;
    font-size: 12px;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.contact-info {
    background: #f8f9fa;
    padding: 10px;
    border-radius: 8px;
    margin-top: 10px;
    font-size: 13px;
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
}

.contact-info span {
    display: flex;
    align-items: center;
    gap: 5px;
}

.contact-info i {
    color: var(--accent);
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 25px;
}

.stat-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
    display: flex;
    align-items: center;
    gap: 15px;
    transition: transform 0.3s;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.1);
}

.stat-icon {
    width: 50px;
    height: 50px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    color: white;
}

.stat-content h3 {
    font-size: 24px;
    margin: 0;
    color: var(--primary);
}

.stat-content p {
    margin: 5px 0 0;
    color: var(--secondary);
    font-size: 14px;
}

.btn {
    padding: 10px 20px;
    border-radius: 8px;
    border: none;
    cursor: pointer;
    font-weight: 500;
    transition: all 0.3s;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
    font-size: 14px;
}

.btn-sm {
    padding: 5px 15px;
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

.badge-secondary {
    background: #e2e3e5;
    color: #383d41;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
}

.empty-state i {
    font-size: 60px;
    color: #ccc;
    margin-bottom: 20px;
}

@media (max-width: 768px) {
    .mission-card {
        flex-direction: column;
        align-items: stretch;
    }
    
    .mission-actions {
        justify-content: flex-start;
        min-width: auto;
    }
    
    .mission-details {
        flex-direction: column;
        gap: 8px;
    }
    
    .city-filter {
        flex-direction: column;
        align-items: stretch;
    }
    
    .city-select {
        width: 100%;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .contact-info {
        flex-direction: column;
        gap: 8px;
    }
}
</style>

<div class="main-content">
    <div class="container">
        <!-- Welcome Section -->
        <div class="welcome-section">
            <div class="welcome-content">
                <div class="welcome-icon">
                    <i class="fas fa-tasks"></i>                    
                </div>
                <div class="welcome-text">
                    <h1>مهام التوصيل</h1>
                    <p>مرحباً <?php echo htmlspecialchars($livreur['nom']); ?>، قم بإدارة مهامك واقبل مهام جديدة</p>
                </div>
            </div>
        </div>

        <?php if($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo $success; ?>
                <?php if(isset($_SESSION['accepted_mission'])): ?>
                <div style="margin-top: 10px; padding: 10px; background: rgba(255,255,255,0.2); border-radius: 5px;">
                    <strong>مهمة مقبولة:</strong> <?php echo $_SESSION['accepted_mission']['don_titre']; ?>
                    <br>
                    <a href="mission-details.php?id=<?php echo $_SESSION['accepted_mission']['id']; ?>" class="btn btn-sm btn-primary" style="margin-top: 10px;">
                        <i class="fas fa-eye"></i> عرض التفاصيل
                    </a>
                </div>
                <?php unset($_SESSION['accepted_mission']); ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if($error): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <!-- Statistiques rapides -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #667eea, #764ba2);">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $counts['disponibles'] ?? 0; ?></h3>
                    <p>مهام متاحة</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #f093fb, #f5576c);">
                    <i class="fas fa-play-circle"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $counts['en_cours'] ?? 0; ?></h3>
                    <p>مهام جارية</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #4facfe, #00f2fe);">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $counts['terminees'] ?? 0; ?></h3>
                    <p>مهام منجزة</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #00b894, #00cec9);">
                    <i class="fas fa-user"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $livreur['nombre_livraisons'] ?? 0; ?></h3>
                    <p>إجمالي توصيلاتي</p>
                </div>
            </div>
        </div>

        <!-- Filtres -->
        <div class="missions-filters">
            <div style="display: flex; gap: 10px; flex-wrap: wrap; justify-content: center;">
                <a href="?filter=toutes<?php echo !empty($selected_ville) ? '&ville='.urlencode($selected_ville) : ''; ?>" 
                   class="btn <?php echo $filter == 'toutes' ? 'btn-primary' : 'btn-outline'; ?>">
                    <i class="fas fa-list"></i> جميع المهام
                </a>
                <a href="?filter=disponibles<?php echo !empty($selected_ville) ? '&ville='.urlencode($selected_ville) : ''; ?>" 
                   class="btn <?php echo $filter == 'disponibles' ? 'btn-primary' : 'btn-outline'; ?>">
                    <i class="fas fa-clock"></i> المتاحة (<?php echo $counts['disponibles'] ?? 0; ?>)
                </a>
                <a href="?filter=en_cours" class="btn <?php echo $filter == 'en_cours' ? 'btn-primary' : 'btn-outline'; ?>">
                    <i class="fas fa-play-circle"></i> الجارية (<?php echo $counts['en_cours'] ?? 0; ?>)
                </a>
                <a href="?filter=terminees" class="btn <?php echo $filter == 'terminees' ? 'btn-primary' : 'btn-outline'; ?>">
                    <i class="fas fa-check-circle"></i> المنجزة (<?php echo $counts['terminees'] ?? 0; ?>)
                </a>
                <a href="?filter=mes" class="btn <?php echo $filter == 'mes' ? 'btn-primary' : 'btn-outline'; ?>">
                    <i class="fas fa-user"></i> مهامي (<?php echo $counts['total_mes'] ?? 0; ?>)
                </a>
            </div>
            
            <!-- Filtre par ville -->
            <?php if(in_array($filter, ['disponibles', 'toutes'])): ?>
            <div class="city-filter">
                <i class="fas fa-filter" style="color: var(--accent);"></i>
                <span style="font-weight: 500;">تصفية حسب المدينة:</span>
                <form method="GET" style="display: flex; gap: 10px; flex: 1; flex-wrap: wrap;">
                    <input type="hidden" name="filter" value="<?php echo $filter; ?>">
                    <select name="ville" class="city-select" onchange="this.form.submit()">
                        <option value="">جميع المدن</option>
                        <?php foreach($villes as $ville): ?>
                            <option value="<?php echo htmlspecialchars($ville['ville']); ?>" 
                                    <?php echo $selected_ville == $ville['ville'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($ville['ville']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if(!empty($selected_ville)): ?>
                        <a href="?filter=<?php echo $filter; ?>" class="btn btn-outline btn-sm">
                            <i class="fas fa-times"></i> إلغاء الفلتر
                        </a>
                    <?php endif; ?>
                </form>
            </div>
            <?php endif; ?>
        </div>

        <!-- Liste des missions -->
        <div class="card">
            <div class="card-header">
                <h3>
                    <?php 
                    switch($filter) {
                        case 'disponibles': 
                            echo '<i class="fas fa-clock" style="color: #00b894;"></i> المهام المتاحة';
                            if(!empty($selected_ville)) {
                                echo ' في ' . htmlspecialchars($selected_ville);
                            }
                            break;
                        case 'en_cours': 
                            echo '<i class="fas fa-play-circle" style="color: #fdcb6e;"></i> المهام الجارية'; 
                            break;
                        case 'terminees': 
                            echo '<i class="fas fa-check-circle" style="color: #6c5ce7;"></i> المهام المنجزة'; 
                            break;
                        case 'mes': 
                            echo '<i class="fas fa-user" style="color: #00b894;"></i> مهامي'; 
                            break;
                        default: 
                            echo '<i class="fas fa-list" style="color: var(--accent);"></i> جميع المهام';
                            if(!empty($selected_ville)) {
                                echo ' في ' . htmlspecialchars($selected_ville);
                            }
                    }
                    ?>
                </h3>
                <span class="badge badge-primary"><?php echo count($missions); ?> مهمة</span>
            </div>
            <div class="card-body">
                <?php if(empty($missions)): ?>
                    <div class="empty-state">
                        <i class="fas fa-box-open"></i>
                        <h3>لا توجد مهام</h3>
                        <p>
                            <?php 
                            if($filter == 'disponibles') {
                                echo 'لا توجد مهام متاحة حالياً';
                            } elseif($filter == 'en_cours') {
                                echo 'ليس لديك مهام جارية حالياً';
                            } elseif($filter == 'terminees') {
                                echo 'لم تقم بإنجاز أي مهمة بعد';
                            } else {
                                echo 'لا توجد مهام حالياً';
                            }
                            ?>
                        </p>
                        <?php if($filter != 'disponibles'): ?>
                            <a href="?filter=disponibles" class="btn btn-primary">
                                <i class="fas fa-clock"></i> عرض المهام المتاحة
                            </a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <?php foreach($missions as $mission): 
                        $status_class = '';
                        $status_text = '';
                        $card_class = '';
                        
                        switch($mission['statut']) {
                            case 'en_attente':
                                $status_class = 'badge-warning';
                                $status_text = 'في انتظار مندوب';
                                $card_class = 'disponible';
                                break;
                            case 'assignee':
                                $status_class = 'badge-info';
                                $status_text = 'تم التعيين';
                                $card_class = 'en-cours';
                                break;
                            case 'en_cours':
                                $status_class = 'badge-primary';
                                $status_text = 'جارية';
                                $card_class = 'en-cours';
                                break;
                            case 'livree':
                                $status_class = 'badge-success';
                                $status_text = 'تم التوصيل';
                                $card_class = 'terminee';
                                break;
                            default:
                                $status_class = 'badge-secondary';
                                $status_text = $mission['statut'];
                        }
                    ?>
                        <div class="mission-card <?php echo $card_class; ?>">
                            <div class="mission-info">
                                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px; flex-wrap: wrap;">
                                    <span class="badge <?php echo $status_class; ?> mission-badge"><?php echo $status_text; ?></span>
                                    <?php if($mission['frais_livraison'] > 0): ?>
                                        <span class="badge badge-success" style="background: #00b894;">
                                            <i class="fas fa-money-bill"></i> رسوم: <?php echo $mission['frais_livraison']; ?> درهم
                                        </span>
                                    <?php endif; ?>
                                    <?php if($mission['livraison_option'] != 'none'): ?>
                                        <span class="badge badge-info">
                                            <i class="fas fa-truck"></i> 
                                            <?php echo $livraison_options[$mission['livraison_option']] ?? ''; ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                
                                <h3>
                                    <?php echo htmlspecialchars($mission['don_titre']); ?>
                                    <span class="ville-tag">
                                        <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($mission['ville']); ?>
                                    </span>
                                </h3>
                                
                                <div class="mission-details">
                                    <span><i class="fas fa-user"></i> المستفيد: <?php echo htmlspecialchars($mission['beneficiaire_nom']); ?></span>
                                    <span><i class="fas fa-user"></i> المتبرع: <?php echo htmlspecialchars($mission['donateur_nom']); ?></span>
                                    <span><i class="fas fa-tag"></i> <?php echo $categories[$mission['categorie']] ?? $mission['categorie']; ?></span>
                                    <span><i class="fas fa-calendar"></i> <?php echo date('d/m/Y', strtotime($mission['created_at'])); ?></span>
                                </div>
                                
                                <?php if($mission['adresse_retrait']): ?>
                                <div style="margin-top: 8px; color: var(--secondary); font-size: 13px;">
                                    <i class="fas fa-map-pin"></i> 
                                    <strong>عنوان الاستلام:</strong> <?php echo htmlspecialchars($mission['adresse_retrait']); ?>
                                </div>
                                <?php endif; ?>
                                
                                <!-- Informations de contact pour les missions acceptées -->
                                <?php if(in_array($mission['statut'], ['assignee', 'en_cours']) && $mission['livreur_id'] == $user_id): ?>
                                <div class="contact-info">
                                    <span><i class="fas fa-phone"></i> المتبرع: 
                                        <a href="tel:<?php echo $mission['donateur_telephone']; ?>">
                                            <?php echo htmlspecialchars($mission['donateur_telephone'] ?? 'غير متوفر'); ?>
                                        </a>
                                    </span>
                                    <span><i class="fas fa-phone"></i> المستفيد: 
                                        <a href="tel:<?php echo $mission['beneficiaire_telephone']; ?>">
                                            <?php echo htmlspecialchars($mission['beneficiaire_telephone'] ?? 'غير متوفر'); ?>
                                        </a>
                                    </span>
                                </div>
                                <?php endif; ?>
                                
                                <?php if($mission['message_demande'] && $filter != 'terminees'): ?>
                                <div style="margin-top: 10px; background: #f8f9fa; padding: 10px; border-radius: 8px; font-size: 13px; border-right: 3px solid var(--accent);">
                                    <i class="fas fa-quote-right" style="color: var(--accent);"></i>
                                    <?php echo nl2br(htmlspecialchars(substr($mission['message_demande'], 0, 150))); ?>
                                    <?php if(strlen($mission['message_demande']) > 150): ?>...<?php endif; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="mission-actions">
                                <?php if($mission['statut'] == 'en_attente' && !$mission['livreur_id']): ?>
                                    <a href="?accept=1&mission_id=<?php echo $mission['id']; ?>" 
                                       class="btn btn-success" 
                                       onclick="return confirm('هل تريد قبول هذه المهمة؟\n\nالمنطقة: <?php echo $mission['ville']; ?>\nالعنوان: <?php echo $mission['adresse_retrait']; ?>')">
                                        <i class="fas fa-check"></i> قبول المهمة
                                    </a>
                                <?php elseif($mission['livreur_id'] == $user_id && $mission['statut'] == 'assignee'): ?>
                                    <a href="?start=1&mission_id=<?php echo $mission['id']; ?>" 
                                       class="btn btn-primary"
                                       onclick="return confirm('بدء هذه المهمة الآن؟')">
                                        <i class="fas fa-play"></i> بدء التوصيل
                                    </a>
                                <?php elseif($mission['livreur_id'] == $user_id && $mission['statut'] == 'en_cours'): ?>
                                    <a href="?complete=1&mission_id=<?php echo $mission['id']; ?>" 
                                       class="btn btn-warning"
                                       onclick="return confirm('تأكيد إنجاز المهمة؟')">
                                        <i class="fas fa-check-circle"></i> إنهاء المهمة
                                    </a>
                                <?php elseif($mission['livreur_id'] == $user_id && $mission['statut'] == 'livree'): ?>
                                    <span class="btn btn-success" style="opacity: 0.8; cursor: default;">
                                        <i class="fas fa-check"></i> تم التسليم
                                    </span>
                                <?php endif; ?>
                                
                                <a href="mission-details.php?id=<?php echo $mission['id']; ?>" 
                                   class="btn btn-outline">
                                    <i class="fas fa-eye"></i> تفاصيل
                                </a>
                                
                                <?php if($mission['livreur_id'] == $user_id && $mission['statut'] == 'livree'): ?>
                                <a href="note.php?mission_id=<?php echo $mission['id']; ?>" 
                                   class="btn btn-info">
                                    <i class="fas fa-star"></i> تقييم
                                </a>
                                <?php endif; ?>
                                
                                <?php if($mission['livreur_id'] == $user_id && in_array($mission['statut'], ['assignee', 'en_cours'])): ?>
                                <a href="messagerie.php?user_id=<?php echo $mission['beneficiaire_id']; ?>" 
                                   class="btn btn-outline" title="مراسلة المستفيد">
                                    <i class="fas fa-comment"></i>
                                </a>
                                <a href="messagerie.php?user_id=<?php echo $mission['donateur_id']; ?>" 
                                   class="btn btn-outline" title="مراسلة المتبرع">
                                    <i class="fas fa-user"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Guide rapide -->
        <div class="card" style="background: linear-gradient(135deg, #667eea10, #764ba210);">
            <div class="card-body">
                <h4 style="color: var(--accent); margin-bottom: 15px; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-info-circle"></i> كيفية عمل المهام
                </h4>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                    <div style="display: flex; gap: 10px;">
                        <div style="width: 30px; height: 30px; background: #00b894; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold;">1</div>
                        <div>
                            <strong>المهام المتاحة</strong>
                            <p style="font-size: 13px; color: #666;">اختر المهام التي تناسب منطقتك واضغط قبول</p>
                        </div>
                    </div>
                    <div style="display: flex; gap: 10px;">
                        <div style="width: 30px; height: 30px; background: #fdcb6e; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold;">2</div>
                        <div>
                            <strong>بدء المهمة</strong>
                            <p style="font-size: 13px; color: #666;">بعد القبول، اضغط بدء التوصيل للبدء في المهمة</p>
                        </div>
                    </div>
                    <div style="display: flex; gap: 10px;">
                        <div style="width: 30px; height: 30px; background: #6c5ce7; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold;">3</div>
                        <div>
                            <strong>إنهاء المهمة</strong>
                            <p style="font-size: 13px; color: #666;">بعد التوصيل، اضغط إنهاء لتأكيد إنجاز المهمة</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function toggleMenu() {
    const navLinks = document.getElementById('navLinks');
    navLinks.classList.toggle('active');
}

function toggleDropdown() {
    const dropdown = document.getElementById('userDropdown');
    dropdown.classList.toggle('active');
}

document.addEventListener('click', function(event) {
    const navLinks = document.getElementById('navLinks');
    const menuToggle = document.querySelector('.menu-toggle');
    const userDropdown = document.getElementById('userDropdown');
    const userAvatar = document.querySelector('.user-avatar');
    
    if (!navLinks.contains(event.target) && !menuToggle.contains(event.target)) {
        navLinks.classList.remove('active');
    }
    
    if (!userDropdown.contains(event.target) && !userAvatar.contains(event.target)) {
        userDropdown.classList.remove('active');
    }
});

// Auto-refresh toutes les 30 secondes pour les missions en cours
<?php if($filter == 'en_cours'): ?>
setInterval(function() {
    location.reload();
}, 30000);
<?php endif; ?>
</script>

<?php include '../includes/footer.php'; ?>
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
        $check_query = "SELECT l.*, d.ville, d.donateur_id, de.beneficiaire_id 
                       FROM livraisons l
                       INNER JOIN demandes de ON l.demande_id = de.id
                       INNER JOIN dons d ON de.don_id = d.id
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
        
        // Créer une notification (optionnel)
        // ...
        
        $db->commit();
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
        $query = "UPDATE livraisons 
                  SET statut = 'en_cours' 
                  WHERE id = :mission_id 
                  AND livreur_id = :user_id 
                  AND statut = 'assignee'";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':mission_id', $mission_id);
        $stmt->bindParam(':user_id', $user_id);
        
        if ($stmt->execute() && $stmt->rowCount() > 0) {
            $success = "✅ تم بدء المهمة بنجاح";
        } else {
            $error = "❌ لا يمكن بدء هذه المهمة";
        }
    } catch(PDOException $e) {
        $error = "❌ خطأ في قاعدة البيانات: " . $e->getMessage();
    }
}

// ========== معالجة إنهاء مهمة ==========
if (isset($_GET['complete']) && isset($_GET['mission_id'])) {
    $mission_id = $_GET['complete'];
    
    try {
        $db->beginTransaction();
        
        $query = "UPDATE livraisons 
                  SET statut = 'livree', date_livraison = NOW() 
                  WHERE id = :mission_id 
                  AND livreur_id = :user_id 
                  AND statut = 'en_cours'";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':mission_id', $mission_id);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            // Mettre à jour le compteur de livraisons du livreur
            $update_livreur = "UPDATE livreurs SET nombre_livraisons = nombre_livraisons + 1 WHERE user_id = :user_id";
            $stmt_livreur = $db->prepare($update_livreur);
            $stmt_livreur->bindParam(':user_id', $user_id);
            $stmt_livreur->execute();
            
            $db->commit();
            $success = "✅ تم إكمال المهمة بنجاح! شكراً لك";
        } else {
            $db->rollBack();
            $error = "❌ لا يمكن إنهاء هذه المهمة";
        }
    } catch(PDOException $e) {
        $db->rollBack();
        $error = "❌ خطأ في قاعدة البيانات: " . $e->getMessage();
    }
}

// Récupérer le filtre et la ville
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'disponibles';
$selected_ville = isset($_GET['ville']) ? $_GET['ville'] : '';

// Récupérer toutes les villes disponibles (uniquement pour les dons non supprimés)
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

// Récupérer les missions
$query = "
    SELECT l.*, 
           d.titre as don_titre, 
           d.ville,
           d.adresse_retrait,
           d.categorie,
           d.etat,
           u.nom as beneficiaire_nom, 
           u.telephone as beneficiaire_telephone,
           u.adresse as beneficiaire_adresse,
           u2.nom as donateur_nom,
           u2.telephone as donateur_telephone,
           de.message_demande,
           de.beneficiaire_id,
           d.donateur_id
    FROM livraisons l
    INNER JOIN demandes de ON l.demande_id = de.id
    INNER JOIN dons d ON de.don_id = d.id
    INNER JOIN users u ON de.beneficiaire_id = u.id
    INNER JOIN users u2 ON d.donateur_id = u2.id
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
    // Pas de filtre spécifique
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
            
            <!-- Filtre par ville (visible seulement pour les onglets qui affichent les missions disponibles) -->
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
                                    <span><i class="fas fa-tag"></i> <?php echo $mission['categorie']; ?></span>
                                    <span><i class="fas fa-calendar"></i> <?php echo date('d/m/Y', strtotime($mission['created_at'])); ?></span>
                                </div>
                                
                                <?php if($mission['adresse_retrait']): ?>
                                <div style="margin-top: 8px; color: var(--secondary); font-size: 13px;">
                                    <i class="fas fa-map-pin"></i> 
                                    <strong>عنوان الاستلام:</strong> <?php echo htmlspecialchars($mission['adresse_retrait']); ?>
                                </div>
                                <?php endif; ?>
                                
                                <!-- Informations de contact (pour les missions acceptées) -->
                                <?php if(in_array($mission['statut'], ['assignee', 'en_cours']) && $mission['livreur_id'] == $user_id): ?>
                                <div class="contact-info">
                                    <span><i class="fas fa-phone"></i> المتبرع: <?php echo htmlspecialchars($mission['donateur_telephone'] ?? 'غير متوفر'); ?></span>
                                    <span><i class="fas fa-phone"></i> المستفيد: <?php echo htmlspecialchars($mission['beneficiaire_telephone'] ?? 'غير متوفر'); ?></span>
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
                                       onclick="return confirm('هل تريد قبول هذه المهمة؟')">
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
                                <?php endif; ?>
                                
                                <a href="mission-details.php?id=<?php echo $mission['id']; ?>" 
                                   class="btn btn-outline">
                                    <i class="fas fa-eye"></i> تفاصيل
                                </a>
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
</script>

<?php include '../includes/footer.php'; ?>
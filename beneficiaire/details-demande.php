<?php
require_once '../config/database.php';
checkAuth(['beneficiaire']);

$database = new Database();
$db = $database->getConnection();

$user_id = $_SESSION['user_id'];
$demande_id = $_GET['id'] ?? null;

if (!$demande_id) {
    header('Location: mes-demandes.php');
    exit();
}

// Récupérer les détails complets de la demande
$query = "
    SELECT d.*, 
           don.titre as don_titre, 
           don.description as don_description,
           don.photo_principale,
           don.categorie,
           don.etat,
           don.adresse_retrait,
           don.ville,
           don.created_at as don_date_publication,
           u.nom as donateur_nom,
           u.email as donateur_email,
           u.telephone as donateur_telephone
    FROM demandes d
    INNER JOIN dons don ON d.don_id = don.id
    INNER JOIN users u ON don.donateur_id = u.id
    WHERE d.id = :demande_id AND d.beneficiaire_id = :user_id
";

$stmt = $db->prepare($query);
$stmt->bindParam(":demande_id", $demande_id);
$stmt->bindParam(":user_id", $user_id);
$stmt->execute();
$demande = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$demande) {
    header('Location: mes-demandes.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Détails de la demande - Age of Donnation</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .detail-section {
            margin-bottom: 2rem;
            padding: 1.5rem;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .detail-header {
            display: flex;
            justify-content: between;
            align-items: start;
            margin-bottom: 1.5rem;
        }
        .don-image {
            width: 100%;
            max-width: 300px;
            height: 200px;
            object-fit: cover;
            border-radius: 8px;
        }
        .don-placeholder {
            width: 100%;
            max-width: 300px;
            height: 200px;
            background: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            font-size: 1.5rem;
            color: #6c757d;
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-top: 1rem;
        }
        .info-card {
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid #007bff;
        }
        .status-timeline {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            margin-top: 1rem;
        }
        .timeline-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0.5rem;
            border-radius: 5px;
        }
        .timeline-item.active {
            background: #e7f3ff;
            border-left: 4px solid #007bff;
        }
        .timeline-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #6c757d;
        }
        .timeline-item.active .timeline-dot {
            background: #007bff;
        }
    </style>
</head>
<body>
    <header class="header">
        <nav class="nav">
            <a href="../index.php" class="logo">Age of Donnation</a>
            <ul class="nav-links">
                <li><a href="dashboard.php">Tableau de bord</a></li>
                <li><a href="catalogue.php">Catalogue des dons</a></li>
                <li><a href="mes-demandes.php" class="active">Mes demandes</a></li>
                <li><a href="messagerie.php">Messagerie</a></li>
            </ul>
            <div class="auth-buttons">
                <span style="color: #333; margin-right: 1rem;"><?php echo $_SESSION['user_nom']; ?></span>
                <a href="../auth/logout.php" class="btn btn-outline">Déconnexion</a>
            </div>
        </nav>
    </header>

    <div class="container">
        <div class="dashboard-header">
            <div style="display: flex; justify-content: between; align-items: center;">
                <div>
                    <h1>Détails de la demande</h1>
                    <p>Informations complètes sur votre demande de don</p>
                </div>
                <a href="mes-demandes.php" class="btn btn-outline">← Retour aux demandes</a>
            </div>
        </div>

        <div class="grid-2">
            <!-- Informations du don -->
            <div class="detail-section">
                <h3>📦 Informations du don</h3>
                
                <!-- Image du don -->
                <div style="text-align: center; margin-bottom: 1.5rem;">
                    <?php if(!empty($demande['photo_principale']) && file_exists('../' . $demande['photo_principale'])): ?>
                        <img src="../<?php echo $demande['photo_principale']; ?>" alt="<?php echo htmlspecialchars($demande['don_titre']); ?>" class="don-image">
                    <?php else: ?>
                        <div class="don-placeholder">
                            <?php 
                            $defaultImages = [
                                'vetements' => '👕',
                                'nourriture' => '🍎',
                                'meubles' => '🛋️',
                                'livres' => '📚',
                                'electromenager' => '🔌',
                                'divers' => '📦'
                            ];
                            echo $defaultImages[$demande['categorie']] ?? '📦';
                            ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="info-grid">
                    <div class="info-card">
                        <strong>Titre:</strong><br>
                        <?php echo htmlspecialchars($demande['don_titre']); ?>
                    </div>
                    <div class="info-card">
                        <strong>Catégorie:</strong><br>
                        <?php echo getCategorieLabel($demande['categorie']); ?>
                    </div>
                    <div class="info-card">
                        <strong>État:</strong><br>
                        <?php echo getEtatLabel($demande['etat']); ?>
                    </div>
                    <div class="info-card">
                        <strong>Lieu de retrait:</strong><br>
                        <?php echo htmlspecialchars($demande['ville']); ?><br>
                        <small style="color: #666;"><?php echo htmlspecialchars($demande['adresse_retrait']); ?></small>
                    </div>
                </div>

                <div style="margin-top: 1.5rem;">
                    <strong>Description:</strong>
                    <p style="color: #666; margin-top: 0.5rem;"><?php echo nl2br(htmlspecialchars($demande['don_description'])); ?></p>
                </div>
            </div>

            <!-- Informations de la demande -->
            <div class="detail-section">
                <h3>📝 Votre demande</h3>
                
                <div class="info-grid">
                    <div class="info-card">
                        <strong>Statut:</strong><br>
                        <?php echo getStatusBadge($demande['statut']); ?>
                    </div>
                    <div class="info-card">
                        <strong>Date de demande:</strong><br>
                        <?php echo date('d/m/Y à H:i', strtotime($demande['created_at'])); ?>
                    </div>
                    <div class="info-card">
                        <strong>Don publié le:</strong><br>
                        <?php echo date('d/m/Y', strtotime($demande['don_date_publication'])); ?>
                    </div>
                </div>

                <div style="margin-top: 1.5rem;">
                    <strong>Votre message au donateur:</strong>
                    <div style="background: #f8f9fa; padding: 1rem; border-radius: 5px; margin-top: 0.5rem;">
                        <?php echo nl2br(htmlspecialchars($demande['message_demande'])); ?>
                    </div>
                </div>
            </div>

            <!-- Informations du donateur -->
            <div class="detail-section">
                <h3>👤 Informations du donateur</h3>
                
                <div class="info-grid">
                    <div class="info-card">
                        <strong>Nom:</strong><br>
                        <?php echo htmlspecialchars($demande['donateur_nom']); ?>
                    </div>
                    <div class="info-card">
                        <strong>Email:</strong><br>
                        <?php echo htmlspecialchars($demande['donateur_email']); ?>
                    </div>
                    <?php if($demande['donateur_telephone']): ?>
                    <div class="info-card">
                        <strong>Téléphone:</strong><br>
                        <?php echo htmlspecialchars($demande['donateur_telephone']); ?>
                    </div>
                    <?php endif; ?>
                </div>

                <div style="margin-top: 1.5rem; text-align: center;">
                    <a href="messagerie.php" class="btn btn-primary">💬 Contacter le donateur</a>
                </div>
            </div>

            <!-- Suivi de statut -->
            <div class="detail-section">
                <h3>📊 Suivi de votre demande</h3>
                
                <div class="status-timeline">
                    <?php
                    $statuses = [
                        'en_attente' => ['icon' => '⏳', 'label' => 'Demande envoyée', 'description' => 'Votre demande a été envoyée au donateur'],
                        'acceptee' => ['icon' => '✅', 'label' => 'Demande acceptée', 'description' => 'Le donateur a accepté votre demande'],
                        'refusee' => ['icon' => '❌', 'label' => 'Demande refusée', 'description' => 'Le donateur a refusé votre demande'],
                        'annulee' => ['icon' => '🚫', 'label' => 'Demande annulée', 'description' => 'La demande a été annulée']
                    ];

                    $currentStatus = $demande['statut'];
                    
                    foreach($statuses as $status => $info): 
                        $isActive = $status === $currentStatus;
                        $isPast = array_search($status, array_keys($statuses)) <= array_search($currentStatus, array_keys($statuses));
                    ?>
                        <div class="timeline-item <?php echo $isActive ? 'active' : ''; ?>" style="opacity: <?php echo $isPast ? '1' : '0.5'; ?>;">
                            <div class="timeline-dot"></div>
                            <div style="flex: 1;">
                                <strong><?php echo $info['icon'] . ' ' . $info['label']; ?></strong>
                                <br>
                                <small style="color: #666;"><?php echo $info['description']; ?></small>
                                <?php if($isActive): ?>
                                    <br><small style="color: #007bff; font-weight: bold;">Statut actuel</small>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Actions possibles -->
                <div style="margin-top: 2rem; text-align: center;">
                    <?php if($demande['statut'] == 'en_attente'): ?>
                        <button onclick="annulerDemande(<?php echo $demande['id']; ?>)" class="btn btn-danger">Annuler la demande</button>
                    <?php elseif($demande['statut'] == 'acceptee'): ?>
                        <button class="btn btn-success" disabled>✅ Demande acceptée</button>
                        <p style="color: #28a745; margin-top: 0.5rem;">Le donateur vous contactera pour organiser le retrait</p>
                    <?php elseif($demande['statut'] == 'refusee'): ?>
                        <button class="btn btn-danger" disabled>❌ Demande refusée</button>
                        <p style="color: #dc3545; margin-top: 0.5rem;">Ne vous découragez pas, d'autres dons vous attendent</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
    function annulerDemande(demandeId) {
        if (confirm('Êtes-vous sûr de vouloir annuler cette demande ?')) {
            // Redirection vers une page d'annulation (à créer)
            window.location.href = 'annuler-demande.php?id=' + demandeId;
        }
    }
    </script>
</body>
</html>
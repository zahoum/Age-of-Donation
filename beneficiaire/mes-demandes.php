<?php
require_once '../config/database.php';
checkAuth(['beneficiaire']);

$database = new Database();
$db = $database->getConnection();

$user_id = $_SESSION['user_id'];

$success = '';
$error = '';

// Gérer l'annulation et SUPPRESSION de demande
if (isset($_GET['action']) && $_GET['action'] == 'annuler' && isset($_GET['id'])) {
    $demande_id = $_GET['id'];
    
    // Validation de l'ID
    if (!is_numeric($demande_id) || $demande_id <= 0) {
        $error = "ID de demande invalide";
    } else {
        try {
            // Vérifier que la demande appartient bien à l'utilisateur et est en attente
            $check_query = "SELECT id, statut FROM demandes WHERE id = :id AND beneficiaire_id = :user_id";
            $check_stmt = $db->prepare($check_query);
            $check_stmt->bindParam(":id", $demande_id, PDO::PARAM_INT);
            $check_stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
            $check_stmt->execute();
            
            if ($check_stmt->rowCount() > 0) {
                $demande_data = $check_stmt->fetch(PDO::FETCH_ASSOC);
                
                // Vérifier que la demande est bien en attente
                if ($demande_data['statut'] == 'en_attente') {
                    // SUPPRIMER COMPLÈTEMENT la demande
                    $delete_query = "DELETE FROM demandes WHERE id = :id";
                    $delete_stmt = $db->prepare($delete_query);
                    $delete_stmt->bindParam(":id", $demande_id, PDO::PARAM_INT);
                    
                    if ($delete_stmt->execute()) {
                        $success = "✅ Demande annulée et supprimée avec succès";
                        
                        // Redirection automatique après 1 seconde
                        echo "<script>
                            setTimeout(function() {
                                window.location.href = 'mes-demandes.php';
                            }, 1000);
                        </script>";
                    } else {
                        $error = "❌ Erreur lors de la suppression de la demande";
                    }
                } else {
                    $error = "❌ Impossible d'annuler cette demande. Statut actuel: " . $demande_data['statut'];
                }
            } else {
                $error = "❌ Demande non trouvée ou vous n'avez pas l'autorisation de l'annuler";
            }
        } catch(PDOException $e) {
            $error = "❌ Erreur base de données: " . $e->getMessage();
        }
    }
}

// Récupérer les demandes du bénéficiaire (EXCLURE les annulées)
$query = "
    SELECT d.*, 
           don.titre as don_titre, 
           don.description as don_description,
           don.photo_principale,
           don.categorie,
           don.etat,
           don.adresse_retrait,
           don.ville,
           u.nom as donateur_nom,
           u.email as donateur_email,
           u.telephone as donateur_telephone
    FROM demandes d
    INNER JOIN dons don ON d.don_id = don.id
    INNER JOIN users u ON don.donateur_id = u.id
    WHERE d.beneficiaire_id = :user_id 
    AND d.statut != 'annulee'  -- EXCLURE les demandes annulées
    ORDER BY 
        CASE 
            WHEN d.statut = 'en_attente' THEN 1
            WHEN d.statut = 'acceptee' THEN 2
            WHEN d.statut = 'refusee' THEN 3
            ELSE 4
        END,
        d.created_at DESC
";

$stmt = $db->prepare($query);
$stmt->bindParam(":user_id", $user_id);
$stmt->execute();
$demandes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Statistiques des demandes (exclure les annulées)
$stats_query = "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN statut = 'en_attente' THEN 1 ELSE 0 END) as en_attente,
        SUM(CASE WHEN statut = 'acceptee' THEN 1 ELSE 0 END) as acceptees,
        SUM(CASE WHEN statut = 'refusee' THEN 1 ELSE 0 END) as refusees
    FROM demandes 
    WHERE beneficiaire_id = :user_id 
    AND statut != 'annulee'  -- Exclure les annulées des statistiques
";

$stats_stmt = $db->prepare($stats_query);
$stats_stmt->bindParam(":user_id", $user_id);
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes demandes - Age of Donnation</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .demande-card {
            transition: transform 0.3s, box-shadow 0.3s;
            margin-bottom: 1.5rem;
        }
        .demande-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .don-image {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 8px;
        }
        .don-placeholder {
            width: 80px;
            height: 80px;
            background: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            font-size: 1.2rem;
        }
        .stat-card {
            text-align: center;
            padding: 1.5rem;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            display: block;
        }
        .stat-label {
            color: #666;
            font-size: 0.9rem;
        }
        .filters {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #666;
        }
        .empty-state-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
        }
        .status-badge-large {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: bold;
            font-size: 0.9rem;
        }
        .action-buttons {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        .message-preview {
            color: #666;
            font-size: 0.9rem;
            margin-top: 0.5rem;
            line-height: 1.4;
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
            <h1>📋 Mes demandes</h1>
            <p>Suivez l'état de vos demandes de dons</p>
        </div>

        <?php if($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <!-- Statistiques -->
        <div class="grid-4">
            <div class="stat-card">
                <span class="stat-number"><?php echo $stats['total'] ?? 0; ?></span>
                <span class="stat-label">Total</span>
            </div>
            <div class="stat-card">
                <span class="stat-number" style="color: #ffc107;"><?php echo $stats['en_attente'] ?? 0; ?></span>
                <span class="stat-label">En attente</span>
            </div>
            <div class="stat-card">
                <span class="stat-number" style="color: #28a745;"><?php echo $stats['acceptees'] ?? 0; ?></span>
                <span class="stat-label">Acceptées</span>
            </div>
            <div class="stat-card">
                <span class="stat-number" style="color: #dc3545;"><?php echo $stats['refusees'] ?? 0; ?></span>
                <span class="stat-label">Refusées</span>
            </div>
        </div>
            <br><br>
        <!-- Filtres -->
        <div class="filters">
            <div class="grid-3">
                <div class="form-group">
                    <label class="form-label">Filtrer par statut</label>
                    <select id="statusFilter" class="form-control">
                        <option value="">Tous les statuts</option>
                        <option value="en_attente">En attente</option>
                        <option value="acceptee">Acceptée</option>
                        <option value="refusee">Refusée</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Filtrer par catégorie</label>
                    <select id="categoryFilter" class="form-control">
                        <option value="">Toutes les catégories</option>
                        <option value="vetements">Vêtements</option>
                        <option value="nourriture">Nourriture</option>
                        <option value="meubles">Meubles</option>
                        <option value="livres">Livres</option>
                        <option value="electromenager">Électroménager</option>
                        <option value="divers">Divers</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Rechercher</label>
                    <input type="text" id="searchInput" class="form-control" placeholder="Rechercher un don...">
                </div>
            </div>
        </div>
            
        <!-- Liste des demandes -->
        <div class="card">
            <div class="card-header" style="display: flex; justify-content: between; align-items: center;gap: 100px;">
                <h3 style="margin: 0;">Historique de vos demandes</h3>
                <a href="catalogue.php" class="btn btn-primary">➕ Nouvelle demande</a>
            </div>
            <div class="card-body">
                <?php if(empty($demandes)): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">📭</div>
                        <h3 style="color: #666; margin-bottom: 1rem;">Aucune demande</h3>
                        <p style="color: #888; margin-bottom: 2rem;">Vous n'avez encore fait aucune demande de don</p>
                        <a href="catalogue.php" class="btn btn-primary">Parcourir le catalogue</a>
                    </div>
                <?php else: ?>
                    <div id="demandesContainer">
                        <?php foreach($demandes as $demande): ?>
                            <div class="demande-card card" 
                                 data-statut="<?php echo $demande['statut']; ?>" 
                                 data-categorie="<?php echo $demande['categorie']; ?>"
                                 data-titre="<?php echo htmlspecialchars(strtolower($demande['don_titre'])); ?>">
                                <div class="card-body">
                                    <div style="display: flex; gap: 1.5rem; align-items: start;">
                                        <!-- Image du don -->
                                        <div>
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

                                        <!-- Informations principales -->
                                        <div style="flex: 1;">
                                            <div style="display: flex; justify-content: between; align-items: start; margin-bottom: 1rem;">
                                                <div>
                                                    <h4 style="margin: 0 0 0.5rem 0;"><?php echo htmlspecialchars($demande['don_titre']); ?></h4>
                                                    <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                                                        <span class="badge badge-secondary"><?php echo getCategorieLabel($demande['categorie']); ?></span>
                                                        <span class="badge badge-info"><?php echo getEtatLabel($demande['etat']); ?></span>
                                                        <span style="color: #666;">
                                                            📍 <?php echo htmlspecialchars($demande['ville']); ?>
                                                        </span>
                                                    </div>
                                                </div>
                                                <div style="text-align: right;">
                                                    <div class="status-badge-large" style="background: <?php 
                                                        switch($demande['statut']) {
                                                            case 'en_attente': echo '#fff3cd; color: #856404; border: 1px solid #ffeaa7;';
                                                                break;
                                                            case 'acceptee': echo '#d4edda; color: #155724; border: 1px solid #c3e6cb;';
                                                                break;
                                                            case 'refusee': echo '#f8d7da; color: #721c24; border: 1px solid #f5c6cb;';
                                                                break;
                                                            default: echo '#f8f9fa; color: #6c757d; border: 1px solid #e9ecef;';
                                                        }
                                                    ?>">
                                                        <?php 
                                                        $statusIcons = [
                                                            'en_attente' => '⏳',
                                                            'acceptee' => '✅',
                                                            'refusee' => '❌'
                                                        ];
                                                        echo ($statusIcons[$demande['statut']] ?? '📝') . ' ' . ucfirst($demande['statut']);
                                                        ?>
                                                    </div>
                                                    <small style="color: #666; display: block; margin-top: 0.5rem;">
                                                        <?php echo date('d/m/Y à H:i', strtotime($demande['created_at'])); ?>
                                                    </small>
                                                </div>
                                            </div>

                                            <!-- Message de demande -->
                                            <?php if($demande['message_demande']): ?>
                                                <div class="message-preview">
                                                    <strong>Votre message:</strong> 
                                                    "<?php echo strlen($demande['message_demande']) > 150 ? substr(htmlspecialchars($demande['message_demande']), 0, 150) . '...' : htmlspecialchars($demande['message_demande']); ?>"
                                                </div>
                                            <?php endif; ?>

                                            <!-- Informations du donateur -->
                                            <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #eee;">
                                                <small style="color: #666;">
                                                    <strong>Donateur:</strong> <?php echo htmlspecialchars($demande['donateur_nom']); ?>
                                                    <?php if($demande['donateur_telephone']): ?>
                                                        • 📞 <?php echo htmlspecialchars($demande['donateur_telephone']); ?>
                                                    <?php endif; ?>
                                                    <?php if($demande['donateur_email']): ?>
                                                        • 📧 <?php echo htmlspecialchars($demande['donateur_email']); ?>
                                                    <?php endif; ?>
                                                </small>
                                            </div>

                                            <!-- Actions -->
                                            <div style="margin-top: 1rem;">
                                                <div class="action-buttons">
                                                    <a href="details-demande.php?id=<?php echo $demande['id']; ?>" class="btn btn-outline">
                                                        👁️ Détails complets
                                                    </a>
                                                    
                                                    <?php if($demande['statut'] == 'en_attente'): ?>
                                                        <a href="?action=annuler&id=<?php echo $demande['id']; ?>" 
                                                           class="btn btn-danger"
                                                           onclick="return confirm('Êtes-vous sûr de vouloir annuler cette demande ? Elle sera définitivement supprimée.')">
                                                            ❌ Annuler
                                                        </a>
                                                    <?php elseif($demande['statut'] == 'acceptee'): ?>
                                                        <a href="messagerie.php" class="btn btn-success">
                                                            💬 Contacter
                                                        </a>
                                                    <?php endif; ?>
                                                    
                                                    <a href="catalogue.php" class="btn btn-primary">
                                                        🔍 Voir d'autres dons
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Résumé -->
                    <div style="margin-top: 2rem; padding: 1.5rem; background: #f8f9fa; border-radius: 10px;">
                        <h4>📊 Résumé de vos demandes</h4><br>
                        <div style="display: flex; gap: 2rem; flex-wrap: wrap;">
                            <div><strong>Total:</strong> <?php echo $stats['total'] ?? 0; ?> demandes</div><br>
                            <div><strong>En attente:</strong> <?php echo $stats['en_attente'] ?? 0; ?></div><br>
                            <div><strong>Acceptées:</strong> <?php echo $stats['acceptees'] ?? 0; ?></div><br>
                            <div><strong>Refusées:</strong> <?php echo $stats['refusees'] ?? 0; ?></div><br>
                            <?php 
                            $tauxSuccess = $stats['total'] > 0 ? round(($stats['acceptees'] / $stats['total']) * 100, 1) : 0;
                            ?>
                            <div><strong>Taux de réussite:</strong> <?php echo $tauxSuccess; ?>%</div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
    // Filtres et recherche
    document.addEventListener('DOMContentLoaded', function() {
        const statusFilter = document.getElementById('statusFilter');
        const categoryFilter = document.getElementById('categoryFilter');
        const searchInput = document.getElementById('searchInput');
        const demandesContainer = document.getElementById('demandesContainer');
        
        function filterDemandes() {
            const selectedStatus = statusFilter.value;
            const selectedCategory = categoryFilter.value;
            const searchTerm = searchInput.value.toLowerCase();
            
            const demandes = demandesContainer.getElementsByClassName('demande-card');
            
            for (let demande of demandes) {
                const statut = demande.getAttribute('data-statut');
                const categorie = demande.getAttribute('data-categorie');
                const titre = demande.getAttribute('data-titre');
                
                const matchesStatus = !selectedStatus || statut === selectedStatus;
                const matchesCategory = !selectedCategory || categorie === selectedCategory;
                const matchesSearch = !searchTerm || titre.includes(searchTerm);
                
                if (matchesStatus && matchesCategory && matchesSearch) {
                    demande.style.display = 'block';
                } else {
                    demande.style.display = 'none';
                }
            }
        }
        
        statusFilter.addEventListener('change', filterDemandes);
        categoryFilter.addEventListener('change', filterDemandes);
        searchInput.addEventListener('input', filterDemandes);
    });

    // Confirmation pour l'annulation
    function confirmAnnulation() {
        return confirm('Êtes-vous sûr de vouloir annuler cette demande ? Cette action est irréversible.');
    }
    </script>
</body>
</html>
<?php
require_once '../config/database.php';
checkAuth(['beneficiaire']);

$database = new Database();
$db = $database->getConnection();

// Récupérer tous les dons disponibles
$query = "
    SELECT d.*, u.nom as donateur_nom, u.telephone as donateur_telephone
    FROM dons d 
    INNER JOIN users u ON d.donateur_id = u.id 
    WHERE d.statut = 'disponible' 
    ORDER BY d.created_at DESC
";
$stmt = $db->prepare($query);
$stmt->execute();
$dons = $stmt->fetchAll(PDO::FETCH_ASSOC);

$success = '';
$error = '';

// Traitement de la demande
if ($_POST && isset($_POST['don_id'])) {
    $don_id = $_POST['don_id'];
    $message_demande = trim($_POST['message_demande']);
    
    // Vérifier si une demande existe déjà
    $check_query = "SELECT id FROM demandes WHERE beneficiaire_id = :beneficiaire_id AND don_id = :don_id";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->bindParam(":beneficiaire_id", $_SESSION['user_id']);
    $check_stmt->bindParam(":don_id", $don_id);
    $check_stmt->execute();
    
    if ($check_stmt->rowCount() > 0) {
        $error = "Vous avez déjà fait une demande pour ce don";
    } else {
        try {
            $query = "INSERT INTO demandes (beneficiaire_id, don_id, message_demande, statut, created_at) 
                      VALUES (:beneficiaire_id, :don_id, :message_demande, 'en_attente', NOW())";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(":beneficiaire_id", $_SESSION['user_id']);
            $stmt->bindParam(":don_id", $don_id);
            $stmt->bindParam(":message_demande", $message_demande);
            
            if ($stmt->execute()) {
                $success = "Votre demande a été envoyée avec succès! Le donateur vous contactera bientôt.";
            } else {
                $error = "Erreur lors de l'envoi de la demande";
            }
        } catch(PDOException $e) {
            $error = "Erreur: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catalogue des dons - Age of Donnation</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .don-card {
            transition: transform 0.3s, box-shadow 0.3s;
            height: 100%;
        }
        .don-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        .don-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
            border-radius: 8px 8px 0 0;
        }
        .don-placeholder {
            width: 100%;
            height: 200px;
            background: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px 8px 0 0;
            font-size: 1.5rem;
            color: #6c757d;
            text-align: center;
            padding: 1rem;
        }
        .modal-backdrop {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 2000;
            justify-content: center;
            align-items: center;
        }
        .modal-content {
            background: white;
            border-radius: 10px;
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }
        .filters {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <?php 
    $pageTitle = "Catalogue des dons";
    include '../includes/header.php'; 
    ?>

    <div class="container">
        <div class="dashboard-header">
            <h1>📦 Catalogue des dons</h1>
            <p>Découvrez tous les dons disponibles près de chez vous</p>
        </div>

        <!-- Filtres et recherche -->
        <div class="filters">
            <div class="grid-3">
                <div class="form-group">
                    <input type="text" id="searchInput" class="form-control" placeholder="🔍 Rechercher un don...">
                </div>
                <div class="form-group">
                    <select id="categorieFilter" class="form-control">
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
                    <select id="villeFilter" class="form-control">
                        <option value="">Toutes les villes</option>
                        <?php
                        $villes_query = "SELECT DISTINCT ville FROM dons WHERE ville IS NOT NULL ORDER BY ville";
                        $villes_stmt = $db->prepare($villes_query);
                        $villes_stmt->execute();
                        $villes = $villes_stmt->fetchAll(PDO::FETCH_COLUMN);
                        foreach($villes as $ville): 
                        ?>
                            <option value="<?php echo htmlspecialchars($ville); ?>"><?php echo htmlspecialchars($ville); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <?php if($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if(empty($dons)): ?>
            <div class="card">
                <div class="card-body" style="text-align: center; padding: 3rem;">
                    <h3 style="color: #666; margin-bottom: 1rem;">Aucun don disponible</h3>
                    <p style="color: #888; margin-bottom: 2rem;">Revenez plus tard pour découvrir de nouveaux dons</p>
                    <div style="font-size: 4rem; margin-bottom: 1rem;">📭</div>
                    <p>Les donateurs publient régulièrement de nouveaux dons.</p>
                </div>
            </div>
        <?php else: ?>
            <div class="grid-3" id="donsContainer">
                <?php foreach($dons as $don): ?>
                    <div class="don-card card" id="don-<?php echo $don['id']; ?>" 
                         data-categorie="<?php echo $don['categorie']; ?>" 
                         data-ville="<?php echo htmlspecialchars($don['ville']); ?>"
                         data-titre="<?php echo htmlspecialchars(strtolower($don['titre'])); ?>"
                         data-description="<?php echo htmlspecialchars(strtolower($don['description'])); ?>">
                        <div class="card-body">
                            <!-- IMAGE DU DON - VERSION CORRIGÉE -->
                            <?php echo getDonImage($don); ?>
                            
                            <h4 style="margin: 1rem 0 0.5rem 0;"><?php echo htmlspecialchars($don['titre']); ?></h4>
                            
                            <p style="color: #666; margin-bottom: 1rem; font-size: 0.9rem; line-height: 1.4;">
                                <?php echo htmlspecialchars($don['description']); ?>
                            </p>
                            
                            <!-- Badges -->
                            <div style="margin-bottom: 1rem;">
                                <span class="badge badge-secondary"><?php echo getCategorieLabel($don['categorie']); ?></span>
                                <span class="badge badge-info"><?php echo getEtatLabel($don['etat']); ?></span>
                                <span class="badge badge-success">Disponible</span>
                            </div>
                            
                            <!-- Informations -->
                            <div style="font-size: 0.85rem; color: #666; margin-bottom: 1rem;">
                                <div style="display: flex; align-items: center; margin-bottom: 0.3rem;">
                                    <span style="margin-right: 0.5rem;">📍</span>
                                    <strong>Lieu:</strong> <?php echo htmlspecialchars($don['ville']); ?>
                                </div>
                                <div style="display: flex; align-items: center; margin-bottom: 0.3rem;">
                                    <span style="margin-right: 0.5rem;">👤</span>
                                    <strong>Donateur:</strong> <?php echo htmlspecialchars($don['donateur_nom']); ?>
                                </div>
                                <div style="display: flex; align-items: center;">
                                    <span style="margin-right: 0.5rem;">📅</span>
                                    <strong>Publié:</strong> <?php echo date('d/m/Y', strtotime($don['created_at'])); ?>
                                </div>
                            </div>
                            
                            <!-- Bouton demande -->
                            <button onclick="openRequestModal(<?php echo $don['id']; ?>, '<?php echo htmlspecialchars(addslashes($don['titre'])); ?>')" 
                                    class="btn btn-primary" style="width: 100%;">
                                ✉️ Faire une demande
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Statistiques -->
            <div style="margin-top: 2rem; padding: 1.5rem; background: #f8f9fa; border-radius: 10px;">
                <h4>📊 Résumé du catalogue</h4>
                <div style="display: flex; gap: 2rem; flex-wrap: wrap;">
                    <div><strong>Total:</strong> <?php echo count($dons); ?> dons disponibles</div>
                    <?php
                    $categories_count = [];
                    foreach($dons as $don) {
                        $categories_count[$don['categorie']] = ($categories_count[$don['categorie']] ?? 0) + 1;
                    }
                    foreach($categories_count as $categorie => $count):
                    ?>
                        <div><strong><?php echo getCategorieLabel($categorie); ?>:</strong> <?php echo $count; ?></div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Modal de demande -->
    <div id="requestModal" class="modal-backdrop">
        <div class="modal-content">
            <div class="card-header" style="display: flex; justify-content: between; align-items: center;">
                <h3 style="margin: 0;">Faire une demande</h3>
                <button onclick="closeRequestModal()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #666;">&times;</button>
            </div>
            <div class="card-body">
                <form method="POST" id="requestForm">
                    <input type="hidden" name="don_id" id="don_id">
                    <div class="form-group">
                        <label class="form-label">Don sélectionné:</label>
                        <p id="don_titre" style="font-weight: bold; padding: 0.8rem; background: #f8f9fa; border-radius: 5px; margin: 0;"></p>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Message au donateur *</label>
                        <textarea name="message_demande" class="form-control" required 
                                  placeholder="Expliquez pourquoi vous avez besoin de ce don, comment vous comptez l'utiliser, et proposez une date de retrait..."
                                  rows="5"></textarea>
                        <small style="color: #666;">Votre message doit être poli et expliquer votre situation.</small>
                    </div>
                    <div style="display: flex; gap: 1rem;">
                        <button type="submit" class="btn btn-primary">📤 Envoyer la demande</button>
                        <button type="button" onclick="closeRequestModal()" class="btn btn-outline">Annuler</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    // Modal functions
    function openRequestModal(donId, donTitre) {
        document.getElementById('don_id').value = donId;
        document.getElementById('don_titre').textContent = donTitre;
        document.getElementById('requestModal').style.display = 'flex';
    }
    
    function closeRequestModal() {
        document.getElementById('requestModal').style.display = 'none';
        document.getElementById('requestForm').reset();
    }
    
    // Fermer la modal en cliquant à l'extérieur
    document.getElementById('requestModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeRequestModal();
        }
    });

    // Filtres et recherche
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('searchInput');
        const categorieFilter = document.getElementById('categorieFilter');
        const villeFilter = document.getElementById('villeFilter');
        const donsContainer = document.getElementById('donsContainer');
        
        function filterDons() {
            const searchTerm = searchInput.value.toLowerCase();
            const selectedCategorie = categorieFilter.value;
            const selectedVille = villeFilter.value;
            
            const dons = donsContainer.getElementsByClassName('don-card');
            
            for (let don of dons) {
                const titre = don.getAttribute('data-titre');
                const description = don.getAttribute('data-description');
                const categorie = don.getAttribute('data-categorie');
                const ville = don.getAttribute('data-ville');
                
                const matchesSearch = !searchTerm || 
                    titre.includes(searchTerm) || 
                    description.includes(searchTerm);
                
                const matchesCategorie = !selectedCategorie || categorie === selectedCategorie;
                const matchesVille = !selectedVille || ville === selectedVille;
                
                if (matchesSearch && matchesCategorie && matchesVille) {
                    don.style.display = 'block';
                } else {
                    don.style.display = 'none';
                }
            }
        }
        
        searchInput.addEventListener('input', filterDons);
        categorieFilter.addEventListener('change', filterDons);
        villeFilter.addEventListener('change', filterDons);
    });
    </script>

    <?php include '../includes/footer.php'; ?>
</body>
</html>
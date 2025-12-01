<?php
require_once '../config/database.php';
checkAuth(['donateur']);

$database = new Database();
$db = $database->getConnection();

$success = '';
$error = '';

if ($_POST) {
    $titre = trim($_POST['titre']);
    $description = trim($_POST['description']);
    $categorie = $_POST['categorie'];
    $etat = $_POST['etat'];
    $adresse_retrait = trim($_POST['adresse_retrait']);
    $ville = trim($_POST['ville']);
    
    // Gestion de l'upload de photo
    $photo_principale = '';
    if (isset($_FILES['photo_principale']) && $_FILES['photo_principale']['error'] === 0) {
        $uploadDir = '../uploads/dons/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $fileExtension = pathinfo($_FILES['photo_principale']['name'], PATHINFO_EXTENSION);
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (in_array(strtolower($fileExtension), $allowedExtensions)) {
            $fileName = uniqid() . '_' . time() . '.' . $fileExtension;
            $filePath = $uploadDir . $fileName;
            
            if (move_uploaded_file($_FILES['photo_principale']['tmp_name'], $filePath)) {
                $photo_principale = 'uploads/dons/' . $fileName;
            } else {
                $error = "Erreur lors de l'upload de la photo";
            }
        } else {
            $error = "Format de photo non supporté. Utilisez JPG, JPEG, PNG ou GIF.";
        }
    }
    
    if (!$error) {
        try {
            $query = "INSERT INTO dons (donateur_id, titre, description, photo_principale, categorie, etat, adresse_retrait, ville, statut, created_at) 
                      VALUES (:donateur_id, :titre, :description, :photo_principale, :categorie, :etat, :adresse_retrait, :ville, 'disponible', NOW())";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(":donateur_id", $_SESSION['user_id']);
            $stmt->bindParam(":titre", $titre);
            $stmt->bindParam(":description", $description);
            $stmt->bindParam(":photo_principale", $photo_principale);
            $stmt->bindParam(":categorie", $categorie);
            $stmt->bindParam(":etat", $etat);
            $stmt->bindParam(":adresse_retrait", $adresse_retrait);
            $stmt->bindParam(":ville", $ville);
            
            if ($stmt->execute()) {
                $don_id = $db->lastInsertId();
                
                // Gestion des photos supplémentaires
                if (!empty($_FILES['photos_supplementaires']['name'][0])) {
                    foreach ($_FILES['photos_supplementaires']['tmp_name'] as $key => $tmp_name) {
                        if ($_FILES['photos_supplementaires']['error'][$key] === 0) {
                            $fileExtension = pathinfo($_FILES['photos_supplementaires']['name'][$key], PATHINFO_EXTENSION);
                            if (in_array(strtolower($fileExtension), $allowedExtensions)) {
                                $fileName = uniqid() . '_' . time() . '_' . $key . '.' . $fileExtension;
                                $filePath = $uploadDir . $fileName;
                                
                                if (move_uploaded_file($tmp_name, $filePath)) {
                                    $photoQuery = "INSERT INTO don_photos (don_id, photo_path) VALUES (:don_id, :photo_path)";
                                    $photoStmt = $db->prepare($photoQuery);
                                    $photoStmt->bindParam(":don_id", $don_id);
                                    $photoPath = 'uploads/dons/' . $fileName;
                                    $photoStmt->bindParam(":photo_path", $photoPath);
                                    $photoStmt->execute();
                                }
                            }
                        }
                    }
                }
                
                $success = "Don publié avec succès!";
                $_POST = array();
            } else {
                $error = "Erreur lors de la publication du don";
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
    <title>Publier un don - Age of Donnation</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .photo-preview {
            max-width: 200px;
            max-height: 200px;
            margin: 10px;
            border: 2px dashed #ddd;
            padding: 5px;
        }
        .upload-area {
            border: 2px dashed #007bff;
            padding: 20px;
            text-align: center;
            margin: 10px 0;
            border-radius: 5px;
            cursor: pointer;
        }
        .upload-area:hover {
            background: #f8f9fa;
        }
    </style>
</head>
<body>
    <?php 
    $pageTitle = "Publier un don";
    include '../includes/header.php'; 
    ?>

    <div class="container">
        <div class="card">
            <div class="card-header">
                <h1>📦 Publier un don</h1>
            </div>
            <div class="card-body">
                <?php if($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <?php if($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data">
                    <div class="grid-2">
                        <div class="form-group">
                            <label class="form-label">Titre du don *</label>
                            <input type="text" name="titre" class="form-control" value="<?php echo $_POST['titre'] ?? ''; ?>" required placeholder="Ex: Livres d'occasion, Vêtements enfants...">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Catégorie *</label>
                            <select name="categorie" class="form-control" required>
                                <option value="">Choisir une catégorie</option>
                                <option value="vetements" <?php echo ($_POST['categorie'] ?? '') == 'vetements' ? 'selected' : ''; ?>>Vêtements</option>
                                <option value="nourriture" <?php echo ($_POST['categorie'] ?? '') == 'nourriture' ? 'selected' : ''; ?>>Nourriture</option>
                                <option value="meubles" <?php echo ($_POST['categorie'] ?? '') == 'meubles' ? 'selected' : ''; ?>>Meubles</option>
                                <option value="livres" <?php echo ($_POST['categorie'] ?? '') == 'livres' ? 'selected' : ''; ?>>Livres</option>
                                <option value="electromenager" <?php echo ($_POST['categorie'] ?? '') == 'electromenager' ? 'selected' : ''; ?>>Électroménager</option>
                                <option value="divers" <?php echo ($_POST['categorie'] ?? '') == 'divers' ? 'selected' : ''; ?>>Divers</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Description détaillée *</label>
                        <textarea name="description" class="form-control" required placeholder="Décrivez l'objet, ses caractéristiques, son état, dimensions si nécessaire..."><?php echo $_POST['description'] ?? ''; ?></textarea>
                    </div>

                    <!-- SECTION PHOTOS -->
                    <div class="form-group">
                        <label class="form-label">Photo principale *</label>
                        <div class="upload-area" onclick="document.getElementById('photo_principale').click()">
                            <p>📷 Cliquez pour ajouter une photo principale</p>
                            <small>Formats acceptés: JPG, PNG, GIF (Max 5MB)</small>
                            <input type="file" id="photo_principale" name="photo_principale" accept="image/*" style="display: none;" onchange="previewPhoto(this, 'preview-principal')">
                        </div>
                        <div id="preview-principal" class="photo-preview-container"></div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Photos supplémentaires</label>
                        <div class="upload-area" onclick="document.getElementById('photos_supplementaires').click()">
                            <p>🖼️ Cliquez pour ajouter des photos supplémentaires</p>
                            <small>Vous pouvez sélectionner plusieurs photos</small>
                            <input type="file" id="photos_supplementaires" name="photos_supplementaires[]" multiple accept="image/*" style="display: none;" onchange="previewMultiplePhotos(this, 'preview-supplementaires')">
                        </div>
                        <div id="preview-supplementaires" class="photo-preview-container" style="display: flex; flex-wrap: wrap;"></div>
                    </div>

                    <div class="grid-2">
                        <div class="form-group">
                            <label class="form-label">État *</label>
                            <select name="etat" class="form-control" required>
                                <option value="">Choisir l'état</option>
                                <option value="neuf" <?php echo ($_POST['etat'] ?? '') == 'neuf' ? 'selected' : ''; ?>>Neuf</option>
                                <option value="bon_etat" <?php echo ($_POST['etat'] ?? '') == 'bon_etat' ? 'selected' : ''; ?>>Bon état</option>
                                <option value="usage" <?php echo ($_POST['etat'] ?? '') == 'usage' ? 'selected' : ''; ?>>État d'usage</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Ville *</label>
                            <input type="text" name="ville" class="form-control" value="<?php echo $_POST['ville'] ?? ''; ?>" required placeholder="Ville">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Adresse de retrait *</label>
                        <input type="text" name="adresse_retrait" class="form-control" value="<?php echo $_POST['adresse_retrait'] ?? ''; ?>" required placeholder="Adresse complète où récupérer le don">
                    </div>

                    <button type="submit" class="btn btn-primary">Publier le don</button>
                    <a href="dashboard.php" class="btn btn-outline" style="margin-left: 1rem;">Annuler</a>
                </form>
            </div>
        </div>
    </div>

    <script>
    function previewPhoto(input, previewId) {
        const preview = document.getElementById(previewId);
        preview.innerHTML = '';
        
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            
            reader.onload = function(e) {
                const img = document.createElement('img');
                img.src = e.target.result;
                img.className = 'photo-preview';
                preview.appendChild(img);
            }
            
            reader.readAsDataURL(input.files[0]);
        }
    }
    
    function previewMultiplePhotos(input, previewId) {
        const preview = document.getElementById(previewId);
        preview.innerHTML = '';
        
        if (input.files) {
            for (let i = 0; i < input.files.length; i++) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    const img = document.createElement('img');
                    img.src = e.target.result;
                    img.className = 'photo-preview';
                    preview.appendChild(img);
                }
                
                reader.readAsDataURL(input.files[i]);
            }
        }
    }
    
    // Empêcher l'upload de fichiers trop lourds
    document.querySelectorAll('input[type="file"]').forEach(input => {
        input.addEventListener('change', function() {
            const files = this.files;
            for (let i = 0; i < files.length; i++) {
                if (files[i].size > 5 * 1024 * 1024) { // 5MB
                    alert('Le fichier ' + files[i].name + ' est trop volumineux. Maximum 5MB.');
                    this.value = '';
                    return;
                }
            }
        });
    });
    </script>

    <?php include '../includes/footer.php'; ?>
</body>
</html>
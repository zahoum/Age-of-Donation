<?php
// donateur/publier-don.php
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
    
    // التحقق من الحقول الإلزامية
    if (empty($titre) || empty($description) || empty($categorie) || empty($etat) || empty($adresse_retrait) || empty($ville)) {
        $error = "جميع الحقول الإلزامية يجب تعبئتها";
    } else {
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
                    $error = "خطأ أثناء رفع الصورة";
                }
            } else {
                $error = "صيغة الصورة غير مدعومة. استخدم JPG، JPEG، PNG أو GIF";
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
                    
                    // Photos supplémentaires
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
                    
                    $success = "✅ تم نشر التبرع بنجاح!";
                    $_POST = array();
                } else {
                    $error = "❌ حدث خطأ أثناء نشر التبرع";
                }
            } catch(PDOException $e) {
                $error = "❌ خطأ: " . $e->getMessage();
            }
        }
    }
}

$page_title = 'نشر تبرع جديد';
require_once '../includes/header.php';
?>

<!-- Page Header -->
<div class="page-header">
    <h1><i class="fas fa-gift"></i> نشر تبرع جديد</h1>
    <p>شارك ما لم تعد بحاجة إليه وساعد الآخرين</p>
</div>

<div class="row">
    <div class="col-8" style="margin: 0 auto;">
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-edit"></i> معلومات التبرع</h3>
            </div>

            <div class="card-body">
                <?php if($success): ?>
                    <div class="alert alert-success">
                        <?php echo $success; ?>
                        <br>
                        <a href="mes-dons.php" class="btn btn-sm btn-success" style="margin-top: 10px;">
                            <i class="fas fa-boxes"></i> عرض تبرعاتي
                        </a>
                    </div>
                <?php endif; ?>

                <?php if($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label class="form-label">عنوان التبرع *</label>
                        <input type="text" name="titre" class="form-control" 
                               value="<?php echo htmlspecialchars($_POST['titre'] ?? ''); ?>" 
                               placeholder="مثال: كتب للأطفال، ملابس شتوية، أثاث..." required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">وصف مفصل *</label>
                        <textarea name="description" class="form-control" rows="4" 
                                  placeholder="صف التبرع بشكل مفصل (الحالة، المقاسات، الملاحظات الهامة...)" required><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                    </div>

                    <div class="row">
                        <div class="col-6">
                            <div class="form-group">
                                <label class="form-label">الفئة *</label>
                                <select name="categorie" class="form-control" required>
                                    <option value="">اختر الفئة</option>
                                    <option value="vetements" <?php echo ($_POST['categorie'] ?? '') == 'vetements' ? 'selected' : ''; ?>>ملابس</option>
                                    <option value="nourriture" <?php echo ($_POST['categorie'] ?? '') == 'nourriture' ? 'selected' : ''; ?>>طعام</option>
                                    <option value="meubles" <?php echo ($_POST['categorie'] ?? '') == 'meubles' ? 'selected' : ''; ?>>أثاث</option>
                                    <option value="livres" <?php echo ($_POST['categorie'] ?? '') == 'livres' ? 'selected' : ''; ?>>كتب</option>
                                    <option value="electromenager" <?php echo ($_POST['categorie'] ?? '') == 'electromenager' ? 'selected' : ''; ?>>أجهزة كهربائية</option>
                                    <option value="divers" <?php echo ($_POST['categorie'] ?? '') == 'divers' ? 'selected' : ''; ?>>متنوع</option>
                                </select>
                            </div>
                        </div>

                        <div class="col-6">
                            <div class="form-group">
                                <label class="form-label">الحالة *</label>
                                <select name="etat" class="form-control" required>
                                    <option value="">اختر الحالة</option>
                                    <option value="neuf" <?php echo ($_POST['etat'] ?? '') == 'neuf' ? 'selected' : ''; ?>>جديد</option>
                                    <option value="bon_etat" <?php echo ($_POST['etat'] ?? '') == 'bon_etat' ? 'selected' : ''; ?>>حالة جيدة</option>
                                    <option value="usage" <?php echo ($_POST['etat'] ?? '') == 'usage' ? 'selected' : ''; ?>>مستعمل</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Photos -->
                    <div class="row">
                        <div class="col-6">
                            <div class="form-group">
                                <label class="form-label">الصورة الرئيسية *</label>
                                <div style="border: 2px dashed #ddd; padding: 20px; text-align: center; border-radius: 8px; cursor: pointer;"
                                     onclick="document.getElementById('photo_principale').click()">
                                    <i class="fas fa-camera" style="font-size: 40px; color: #aaa; margin-bottom: 10px;"></i>
                                    <p style="color: #666; margin: 0;">انقر لاختيار صورة</p>
                                    <small style="color: #888;">(JPG, JPEG, PNG, GIF)</small>
                                    <input type="file" id="photo_principale" name="photo_principale" 
                                           accept="image/*" style="display:none" onchange="previewPhoto(this, 'preview-principal')">
                                </div>
                                <div id="preview-principal" style="margin-top: 10px;"></div>
                            </div>
                        </div>
                        
                        <div class="col-6">
                            <div class="form-group">
                                <label class="form-label">صور إضافية (اختياري)</label>
                                <div style="border: 2px dashed #ddd; padding: 20px; text-align: center; border-radius: 8px; cursor: pointer;"
                                     onclick="document.getElementById('photos_supplementaires').click()">
                                    <i class="fas fa-images" style="font-size: 40px; color: #aaa; margin-bottom: 10px;"></i>
                                    <p style="color: #666; margin: 0;">انقر لإضافة صور</p>
                                    <small style="color: #888;">(حد أقصى 5 صور)</small>
                                    <input type="file" id="photos_supplementaires" name="photos_supplementaires[]" 
                                           multiple accept="image/*" style="display:none" onchange="previewMultiplePhotos(this, 'preview-supplementaires')">
                                </div>
                                <div id="preview-supplementaires" style="display:flex; flex-wrap:wrap; gap: 10px; margin-top: 10px;"></div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-6">
                            <div class="form-group">
                                <label class="form-label">المدينة *</label>
                                <input type="text" name="ville" class="form-control" 
                                       value="<?php echo htmlspecialchars($_POST['ville'] ?? ''); ?>" 
                                       placeholder="مثال: الدار البيضاء، الرباط..." required>
                            </div>
                        </div>

                        <div class="col-6">
                            <div class="form-group">
                                <label class="form-label">عنوان الاستلام *</label>
                                <input type="text" name="adresse_retrait" class="form-control" 
                                       value="<?php echo htmlspecialchars($_POST['adresse_retrait'] ?? ''); ?>" 
                                       placeholder="العنوان الكامل للاستلام" required>
                            </div>
                        </div>
                    </div>

                    <div style="display: flex; gap: 15px; margin-top: 30px;">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i> نشر التبرع
                        </button>
                        <a href="dashboard.php" class="btn btn-outline">إلغاء والعودة</a>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Information Card -->
        <div class="card" style="margin-top: 20px; background: #f8f9fa;">
            <div class="card-body">
                <h4><i class="fas fa-info-circle"></i> معلومات مهمة</h4>
                <ul style="color: #666; margin-top: 10px;">
                    <li>تأكد من أن التبرع في حالة جيدة ومناسبة للاستخدام</li>
                    <li>كن دقيقًا في الوصف لتجنب سوء الفهم</li>
                    <li>الصور الجيدة تزيد من فرص قبول التبرع</li>
                    <li>كن متاحًا للرد على استفسارات المستفيدين</li>
                    <li>يمكنك تحديث أو حذف التبرع في أي وقت من صفحة "تبرعاتي"</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
// Photo preview functions
function previewPhoto(input, previewId) {
    const preview = document.getElementById(previewId);
    preview.innerHTML = '';
    
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            const img = document.createElement('img');
            img.src = e.target.result;
            img.style.maxWidth = '150px';
            img.style.maxHeight = '150px';
            img.style.borderRadius = '8px';
            img.style.marginTop = '10px';
            preview.appendChild(img);
        }
        
        reader.readAsDataURL(input.files[0]);
    }
}

function previewMultiplePhotos(input, previewId) {
    const preview = document.getElementById(previewId);
    preview.innerHTML = '';
    
    const files = Array.from(input.files).slice(0, 5); // Limit to 5 images
    
    files.forEach((file, index) => {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            const imgContainer = document.createElement('div');
            imgContainer.style.position = 'relative';
            
            const img = document.createElement('img');
            img.src = e.target.result;
            img.style.width = '80px';
            img.style.height = '80px';
            img.style.objectFit = 'cover';
            img.style.borderRadius = '8px';
            img.style.border = '1px solid #ddd';
            
            const removeBtn = document.createElement('button');
            removeBtn.innerHTML = '×';
            removeBtn.style.position = 'absolute';
            removeBtn.style.top = '-5px';
            removeBtn.style.right = '-5px';
            removeBtn.style.background = '#ff7675';
            removeBtn.style.color = 'white';
            removeBtn.style.border = 'none';
            removeBtn.style.borderRadius = '50%';
            removeBtn.style.width = '20px';
            removeBtn.style.height = '20px';
            removeBtn.style.cursor = 'pointer';
            removeBtn.style.fontSize = '12px';
            removeBtn.onclick = function(e) {
                e.preventDefault();
                imgContainer.remove();
                // Create new file list without removed file
                const dt = new DataTransfer();
                const filesArray = Array.from(input.files);
                filesArray.splice(index, 1);
                filesArray.forEach(f => dt.items.add(f));
                input.files = dt.files;
            };
            
            imgContainer.appendChild(img);
            imgContainer.appendChild(removeBtn);
            preview.appendChild(imgContainer);
        }
        
        reader.readAsDataURL(file);
    });
}

// Form validation
document.querySelector('form').addEventListener('submit', function(e) {
    const requiredFields = this.querySelectorAll('[required]');
    let isValid = true;
    
    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            isValid = false;
            field.style.borderColor = '#ff7675';
        } else {
            field.style.borderColor = '';
        }
    });
    
    if (!isValid) {
        e.preventDefault();
        alert('يرجى ملء جميع الحقول الإلزامية');
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>
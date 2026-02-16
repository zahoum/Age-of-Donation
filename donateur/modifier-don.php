<?php
// donateur/modifier-don.php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'donateur') {
    header('Location: ../auth/login.php');
    exit();
}

require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$user_id = $_SESSION['user_id'];
$don_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$don_id) {
    header('Location: mes-dons.php');
    exit();
}

// جلب معلومات المستخدم الحالي
$query_user = "SELECT * FROM users WHERE id = :user_id";
$stmt_user = $db->prepare($query_user);
$stmt_user->bindParam(":user_id", $user_id);
$stmt_user->execute();
$current_user = $stmt_user->fetch(PDO::FETCH_ASSOC);

// جلب معلومات التبرع والتأكد من ملكيته
$query_don = "SELECT * FROM dons WHERE id = :don_id AND donateur_id = :user_id AND (is_deleted IS NULL OR is_deleted = 0)";
$stmt_don = $db->prepare($query_don);
$stmt_don->bindParam(':don_id', $don_id);
$stmt_don->bindParam(':user_id', $user_id);
$stmt_don->execute();
$don = $stmt_don->fetch(PDO::FETCH_ASSOC);

if (!$don) {
    header('Location: mes-dons.php?error=not_found');
    exit();
}

// التحقق من إمكانية التعديل (فقط إذا كان التبرع متاحاً)
if ($don['statut'] != 'disponible') {
    header('Location: mes-dons.php?error=not_editable');
    exit();
}

$success = '';
$error = '';

// ========== معالجة تحديث التبرع ==========
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $titre = trim($_POST['titre'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $categorie = $_POST['categorie'] ?? '';
    $etat = $_POST['etat'] ?? '';
    $ville = trim($_POST['ville'] ?? '');
    $adresse_retrait = trim($_POST['adresse_retrait'] ?? '');
    $livraison_option = $_POST['livraison_option'] ?? 'none';
    
    $errors = [];
    
    // التحقق من صحة المدخلات
    if (empty($titre)) {
        $errors[] = "عنوان التبرع مطلوب";
    } elseif (strlen($titre) < 5) {
        $errors[] = "عنوان التبرع يجب أن يكون على الأقل 5 أحرف";
    } elseif (strlen($titre) > 200) {
        $errors[] = "عنوان التبرع طويل جداً (الحد الأقصى 200 حرف)";
    }
    
    if (empty($description)) {
        $errors[] = "وصف التبرع مطلوب";
    } elseif (strlen($description) < 10) {
        $errors[] = "وصف التبرع يجب أن يكون على الأقل 10 أحرف";
    } elseif (strlen($description) > 5000) {
        $errors[] = "وصف التبرع طويل جداً (الحد الأقصى 5000 حرف)";
    }
    
    if (empty($categorie)) {
        $errors[] = "الرجاء اختيار فئة التبرع";
    }
    
    if (empty($etat)) {
        $errors[] = "الرجاء اختيار حالة التبرع";
    }
    
    if (empty($ville)) {
        $errors[] = "المدينة مطلوبة";
    }
    
    if (empty($adresse_retrait)) {
        $errors[] = "عنوان الاستلام مطلوب";
    }
    
    // معالجة رفع الصور الجديدة
    $uploaded_photos = [];
    if (isset($_FILES['photos']) && !empty($_FILES['photos']['name'][0])) {
        $target_dir = "../uploads/dons/";
        
        // إنشاء المجلد إذا لم يكن موجوداً
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $max_size = 5 * 1024 * 1024; // 5 Mo
        
        foreach ($_FILES['photos']['tmp_name'] as $key => $tmp_name) {
            if ($_FILES['photos']['error'][$key] == 0) {
                $file_name = $_FILES['photos']['name'][$key];
                $file_size = $_FILES['photos']['size'][$key];
                $file_type = $_FILES['photos']['type'][$key];
                
                // التحقق من نوع الملف
                if (!in_array($file_type, $allowed_types)) {
                    $errors[] = "الملف '$file_name' غير مدعوم. الأنواع المسموحة: JPG, PNG, GIF, WEBP";
                    continue;
                }
                
                // التحقق من الحجم
                if ($file_size > $max_size) {
                    $errors[] = "الملف '$file_name' كبير جداً (الحد الأقصى 5 ميجابايت)";
                    continue;
                }
                
                // إنشاء اسم فريد للملف
                $extension = pathinfo($file_name, PATHINFO_EXTENSION);
                $new_filename = uniqid() . '_' . time() . '.' . $extension;
                $target_file = $target_dir . $new_filename;
                
                if (move_uploaded_file($tmp_name, $target_file)) {
                    $uploaded_photos[] = 'uploads/dons/' . $new_filename;
                } else {
                    $errors[] = "حدث خطأ أثناء رفع الملف '$file_name'";
                }
            }
        }
    }
    
    // معالجة الصورة الرئيسية
    $photo_principale = $don['photo_principale']; // الاحتفاظ بالصورة القديمة افتراضياً
    
    if (isset($_FILES['photo_principale']) && $_FILES['photo_principale']['error'] == 0) {
        $file = $_FILES['photo_principale'];
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $max_size = 5 * 1024 * 1024; // 5 Mo
        
        if (!in_array($file['type'], $allowed_types)) {
            $errors[] = "نوع الصورة الرئيسية غير مدعوم. الأنواع المسموحة: JPG, PNG, GIF, WEBP";
        } elseif ($file['size'] > $max_size) {
            $errors[] = "الصورة الرئيسية كبيرة جداً (الحد الأقصى 5 ميجابايت)";
        } else {
            $target_dir = "../uploads/dons/";
            if (!file_exists($target_dir)) {
                mkdir($target_dir, 0777, true);
            }
            
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $new_filename = 'main_' . uniqid() . '_' . time() . '.' . $extension;
            $target_file = $target_dir . $new_filename;
            
            if (move_uploaded_file($file['tmp_name'], $target_file)) {
                // حذف الصورة القديمة إذا كانت موجودة
                if (!empty($don['photo_principale']) && file_exists('../' . $don['photo_principale'])) {
                    unlink('../' . $don['photo_principale']);
                }
                $photo_principale = 'uploads/dons/' . $new_filename;
            } else {
                $errors[] = "حدث خطأ أثناء رفع الصورة الرئيسية";
            }
        }
    } elseif (isset($_POST['remove_main_photo']) && $_POST['remove_main_photo'] == '1') {
        // حذف الصورة الرئيسية إذا طلب المستخدم
        if (!empty($don['photo_principale']) && file_exists('../' . $don['photo_principale'])) {
            unlink('../' . $don['photo_principale']);
        }
        $photo_principale = null;
    }
    
    // معالجة حذف الصور المحددة
    if (isset($_POST['delete_photos']) && is_array($_POST['delete_photos'])) {
        foreach ($_POST['delete_photos'] as $photo_id) {
            $photo_id = intval($photo_id);
            
            // جلب مسار الصورة
            $query_photo = "SELECT photo_path FROM don_photos WHERE id = :id AND don_id = :don_id";
            $stmt_photo = $db->prepare($query_photo);
            $stmt_photo->bindParam(':id', $photo_id);
            $stmt_photo->bindParam(':don_id', $don_id);
            $stmt_photo->execute();
            $photo = $stmt_photo->fetch(PDO::FETCH_ASSOC);
            
            if ($photo) {
                // حذف الملف
                if (file_exists('../' . $photo['photo_path'])) {
                    unlink('../' . $photo['photo_path']);
                }
                
                // حذف من قاعدة البيانات
                $delete_photo = "DELETE FROM don_photos WHERE id = :id";
                $stmt_delete = $db->prepare($delete_photo);
                $stmt_delete->bindParam(':id', $photo_id);
                $stmt_delete->execute();
            }
        }
    }
    
    if (empty($errors)) {
        try {
            $db->beginTransaction();
            
            // تحديث معلومات التبرع
            $update_query = "
                UPDATE dons SET 
                    titre = :titre,
                    description = :description,
                    categorie = :categorie,
                    etat = :etat,
                    ville = :ville,
                    adresse_retrait = :adresse_retrait,
                    livraison_option = :livraison_option,
                    photo_principale = :photo_principale,
                    updated_at = NOW()
                WHERE id = :don_id AND donateur_id = :user_id
            ";
            
            $update_stmt = $db->prepare($update_query);
            $update_stmt->bindParam(':titre', $titre);
            $update_stmt->bindParam(':description', $description);
            $update_stmt->bindParam(':categorie', $categorie);
            $update_stmt->bindParam(':etat', $etat);
            $update_stmt->bindParam(':ville', $ville);
            $update_stmt->bindParam(':adresse_retrait', $adresse_retrait);
            $update_stmt->bindParam(':livraison_option', $livraison_option);
            $update_stmt->bindParam(':photo_principale', $photo_principale);
            $update_stmt->bindParam(':don_id', $don_id);
            $update_stmt->bindParam(':user_id', $user_id);
            $update_stmt->execute();
            
            // إضافة الصور الجديدة
            if (!empty($uploaded_photos)) {
                $photo_query = "INSERT INTO don_photos (don_id, photo_path) VALUES (:don_id, :photo_path)";
                $photo_stmt = $db->prepare($photo_query);
                
                foreach ($uploaded_photos as $photo_path) {
                    $photo_stmt->bindParam(':don_id', $don_id);
                    $photo_stmt->bindParam(':photo_path', $photo_path);
                    $photo_stmt->execute();
                }
            }
            
            $db->commit();
            
            // إعادة جلب معلومات التبرع المحدثة
            $stmt_don->execute();
            $don = $stmt_don->fetch(PDO::FETCH_ASSOC);
            
            $success = "✅ تم تحديث التبرع بنجاح!";
            
        } catch(PDOException $e) {
            $db->rollBack();
            $error = "❌ حدث خطأ أثناء تحديث التبرع: " . $e->getMessage();
        }
    } else {
        $error = implode('<br>', $errors);
    }
}

// جلب صور التبرع الإضافية
$query_photos = "SELECT * FROM don_photos WHERE don_id = :don_id ORDER BY id";
$stmt_photos = $db->prepare($query_photos);
$stmt_photos->bindParam(':don_id', $don_id);
$stmt_photos->execute();
$don_photos = $stmt_photos->fetchAll(PDO::FETCH_ASSOC);

// التحقق من وجود طلبات على هذا التبرع
$query_demandes = "SELECT COUNT(*) as nb_demandes FROM demandes WHERE don_id = :don_id AND statut != 'refusee'";
$stmt_demandes = $db->prepare($query_demandes);
$stmt_demandes->bindParam(':don_id', $don_id);
$stmt_demandes->execute();
$nb_demandes = $stmt_demandes->fetch(PDO::FETCH_ASSOC)['nb_demandes'];

// تجهيز الفئات والحالات للعرض
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

$livraison_options = [
    'none' => 'المستفيد يتحمل التوصيل',
    'fifty' => 'المتبرع يتحمل 50% من التوصيل',
    'full' => 'المتبرع يتحمل التوصيل كاملاً'
];

$page_title = 'تعديل التبرع';
?>

<!DOCTYPE html>
<html lang="fr" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - Age of Donnation</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #2d3436;
            --secondary: #636e72;
            --accent: #0984e3;
            --light: #f5f6fa;
            --dark: #2d3436;
            --success: #00b894;
            --danger: #d63031;
            --warning: #fdcb6e;
            --info: #00cec9;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Tajawal', sans-serif;
        }
        
        body {
            background-color: #f8f9fa;
            color: #333;
            line-height: 1.6;
        }
        
        /* Navbar */
        .navbar {
            background: white;
            box-shadow: 0 2px 15px rgba(0,0,0,0.08);
            padding: 0 20px;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: 70px;
        }
        
        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            color: var(--primary);
            font-weight: 700;
            font-size: 24px;
        }
        
        .logo-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--accent), #74b9ff);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
        }
        
        .nav-links {
            display: flex;
            gap: 5px;
            list-style: none;
            margin: 0;
            padding: 0;
        }
        
        .nav-item {
            position: relative;
        }
        
        .nav-link {
            text-decoration: none;
            color: var(--secondary);
            padding: 10px 20px;
            border-radius: 8px;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
        }
        
        .nav-link:hover {
            background: #f1f2f6;
            color: var(--accent);
        }
        
        .nav-link.active {
            background: linear-gradient(135deg, var(--accent), #74b9ff);
            color: white;
            box-shadow: 0 4px 12px rgba(116, 185, 255, 0.3);
        }
        
        .user-menu {
            display: flex;
            align-items: center;
            gap: 15px;
            position: relative;
        }
        
        .user-avatar {
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, #00b894, #00cec9);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 18px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .user-avatar:hover {
            transform: scale(1.05);
            box-shadow: 0 5px 15px rgba(0, 184, 148, 0.3);
        }
        
        .user-dropdown {
            position: absolute;
            top: 60px;
            left: 0;
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
            min-width: 200px;
            display: none;
            z-index: 1000;
            overflow: hidden;
        }
        
        .user-dropdown.active {
            display: block;
        }
        
        .user-dropdown-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 15px 20px;
            text-decoration: none;
            color: var(--dark);
            border-bottom: 1px solid #f1f2f6;
            transition: all 0.3s;
        }
        
        .user-dropdown-item:hover {
            background: #f8f9fa;
            color: var(--accent);
        }
        
        .user-dropdown-item:last-child {
            border-bottom: none;
            color: var(--danger);
        }
        
        .user-dropdown-item:last-child:hover {
            background: #ffebee;
        }
        
        /* Main Content */
        .main-content {
            margin-top: 90px;
            padding: 20px;
            min-height: calc(100vh - 160px);
        }
        
        /* Container */
        .container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 0 15px;
        }
        
        /* Welcome Section */
        .welcome-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
        }

        .welcome-section::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: rotate 20s linear infinite;
        }

        .welcome-section::after {
            content: '';
            position: absolute;
            bottom: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.05) 0%, transparent 60%);
            animation: rotate 25s linear infinite reverse;
        }

        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .welcome-content {
            position: relative;
            z-index: 1;
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .welcome-icon {
            width: 60px;
            height: 60px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 30px;
            color: white;
            backdrop-filter: blur(10px);
            border: 2px solid rgba(255, 255, 255, 0.3);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        .welcome-text h1 {
            font-size: 28px;
            margin-bottom: 5px;
            font-weight: 700;
        }

        .welcome-text p {
            font-size: 16px;
            opacity: 0.95;
            margin: 0;
        }
        
        /* Cards */
        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            margin-bottom: 25px;
            overflow: hidden;
        }
        
        .card-header {
            padding: 20px 25px;
            background: #f8f9fa;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .card-header h3 {
            margin: 0;
            color: var(--primary);
            font-size: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .card-body {
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
        
        .form-label i {
            color: var(--accent);
            margin-left: 5px;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e1e1e1;
            border-radius: 8px;
            font-size: 15px;
            transition: all 0.3s;
            font-family: 'Tajawal', sans-serif;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(9, 132, 227, 0.1);
        }
        
        textarea.form-control {
            resize: vertical;
            min-height: 150px;
        }
        
        select.form-control {
            appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: left 15px center;
            background-size: 15px;
        }
        
        .form-row {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }
        
        .form-row .form-group {
            flex: 1;
            min-width: 250px;
        }
        
        /* File Upload */
        .file-upload {
            border: 2px dashed #ddd;
            border-radius: 8px;
            padding: 30px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            margin-bottom: 20px;
        }
        
        .file-upload:hover {
            border-color: var(--accent);
            background: #f8f9fa;
        }
        
        .file-upload i {
            font-size: 40px;
            color: var(--accent);
            margin-bottom: 10px;
        }
        
        .file-upload p {
            color: #666;
        }
        
        .file-upload small {
            color: #999;
        }
        
        #file-input {
            display: none;
        }
        
        /* Photo Gallery */
        .photo-gallery {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        
        .photo-item {
            position: relative;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
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
            font-weight: 500;
        }
        
        .photo-delete {
            position: absolute;
            top: 5px;
            left: 5px;
            width: 30px;
            height: 30px;
            background: rgba(214, 48, 49, 0.9);
            color: white;
            border: none;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
        }
        
        .photo-delete:hover {
            background: #c0392b;
            transform: scale(1.1);
        }
        
        .photo-checkbox {
            position: absolute;
            bottom: 5px;
            left: 5px;
            background: white;
            padding: 5px;
            border-radius: 3px;
        }
        
        /* Buttons */
        .btn {
            padding: 12px 30px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            font-size: 16px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--accent), #74b9ff);
            color: white;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #0984e3, #0984e3);
            box-shadow: 0 5px 15px rgba(116, 185, 255, 0.4);
            transform: translateY(-2px);
        }
        
        .btn-success {
            background: linear-gradient(135deg, #00b894, #00cec9);
            color: white;
        }
        
        .btn-success:hover {
            background: linear-gradient(135deg, #00a085, #00b7a8);
            box-shadow: 0 5px 15px rgba(0, 184, 148, 0.3);
            transform: translateY(-2px);
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #d63031, #ff7675);
            color: white;
        }
        
        .btn-danger:hover {
            background: linear-gradient(135deg, #c0392b, #e17055);
            box-shadow: 0 5px 15px rgba(214, 48, 49, 0.3);
            transform: translateY(-2px);
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
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .btn-sm {
            padding: 8px 15px;
            font-size: 14px;
        }
        
        .form-actions {
            display: flex;
            gap: 15px;
            justify-content: flex-start;
            margin-top: 30px;
            flex-wrap: wrap;
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
        
        .alert-warning {
            background: #fff3cd;
            border-right-color: #856404;
            color: #856404;
        }
        
        .alert-info {
            background: #d1ecf1;
            border-right-color: #0c5460;
            color: #0c5460;
        }
        
        /* Info Box */
        .info-box {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            border-right: 3px solid var(--accent);
        }
        
        .info-box i {
            color: var(--accent);
            margin-left: 5px;
        }
        
        .info-box ul {
            margin-right: 20px;
            color: #666;
        }
        
        .info-box li {
            margin-bottom: 5px;
        }
        
        /* Mobile Menu Toggle */
        .menu-toggle {
            display: none;
            background: none;
            border: none;
            font-size: 24px;
            color: var(--primary);
            cursor: pointer;
        }
        
        @media (max-width: 768px) {
            .menu-toggle {
                display: block;
            }
            
            .nav-links {
                display: none;
            }
            
            .nav-links.active {
                display: flex;
                flex-direction: column;
                position: absolute;
                top: 70px;
                left: 0;
                right: 0;
                background: white;
                box-shadow: 0 5px 15px rgba(0,0,0,0.1);
                padding: 20px;
            }
            
            .main-content {
                margin-top: 80px;
                padding: 15px;
            }
            
            .form-row {
                flex-direction: column;
                gap: 0;
            }
            
            .photo-gallery {
                grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .form-actions .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <a href="../index.php" class="logo">
            <div class="logo-icon">
                <i class="fas fa-hands-helping"></i>
            </div>
            <span>Age of Donnation</span>
        </a>
        
        <button class="menu-toggle" onclick="toggleMenu()">
            <i class="fas fa-bars"></i>
        </button>
        
        <ul class="nav-links" id="navLinks">
            <li class="nav-item"><a href="dashboard.php" class="nav-link"><i class="fas fa-home"></i> لوحة التحكم</a></li>
            <li class="nav-item"><a href="publier-don.php" class="nav-link"><i class="fas fa-plus-circle"></i> نشر تبرع</a></li>
            <li class="nav-item"><a href="mes-dons.php" class="nav-link"><i class="fas fa-boxes"></i> تبرعاتي</a></li>
            <li class="nav-item"><a href="confirmer-commandes.php" class="nav-link"><i class="fas fa-check-circle"></i> تأكيد الطلبات</a></li>
            <li class="nav-item"><a href="messagerie.php" class="nav-link"><i class="fas fa-comments"></i> المراسلة</a></li>
        </ul>
        
        <div class="user-menu">
            <div class="user-avatar" onclick="toggleDropdown()" title="الملف الشخصي">
                <?php echo strtoupper(substr($current_user['nom'], 0, 1)); ?>
            </div>
            <div class="user-dropdown" id="userDropdown">
                <a href="profile.php" class="user-dropdown-item">
                    <i class="fas fa-user"></i> الملف الشخصي
                </a>
                <a href="mes-dons.php" class="user-dropdown-item">
                    <i class="fas fa-boxes"></i> تبرعاتي
                </a>
                <a href="messagerie.php" class="user-dropdown-item">
                    <i class="fas fa-comments"></i> المراسلة
                </a>
                <a href="../auth/logout.php" class="user-dropdown-item">
                    <i class="fas fa-sign-out-alt"></i> تسجيل الخروج
                </a>
            </div>
        </div>
    </nav>
    
    <!-- Main Content -->
    <div class="main-content">
        <div class="container">
            <!-- Welcome Section -->
            <div class="welcome-section">
                <div class="welcome-content">
                    <div class="welcome-icon">
                        <i class="fas fa-edit"></i>                    
                    </div>
                    <div class="welcome-text">
                        <h1>تعديل التبرع</h1>
                        <p>قم بتحديث معلومات تبرعك</p>
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

            <?php if($nb_demandes > 0): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    يوجد <?php echo $nb_demandes; ?> طلب (طلبات) على هذا التبرع. يرجى مراجعة الطلبات في صفحة تأكيد الطلبات.
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-edit"></i> تعديل: <?php echo htmlspecialchars($don['titre']); ?></h3>
                    <a href="mes-dons.php" class="btn btn-outline btn-sm">
                        <i class="fas fa-arrow-right"></i> العودة
                    </a>
                </div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data" id="donForm">
                        <div class="info-box">
                            <i class="fas fa-info-circle"></i>
                            <strong>معلومات مهمة:</strong>
                            <ul>
                                <li>يمكنك تعديل التبرع فقط إذا كان لا يزال متاحاً</li>
                                <li>تأكد من دقة المعلومات لتجنب إرباك المستفيدين</li>
                                <li>يمكنك إضافة أو حذف الصور حسب الحاجة</li>
                            </ul>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-heading"></i> عنوان التبرع *
                            </label>
                            <input type="text" name="titre" class="form-control" 
                                   value="<?php echo htmlspecialchars($don['titre']); ?>" 
                                   required maxlength="200" 
                                   placeholder="مثال: كتب أطفال، ملابس شتوية، ...">
                            <small style="color: #666;">اختر عنواناً واضحاً يعبر عن محتوى التبرع</small>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-align-left"></i> وصف التبرع *
                            </label>
                            <textarea name="description" class="form-control" 
                                      required minlength="10" 
                                      placeholder="اكتب وصفاً مفصلاً للتبرع..."><?php echo htmlspecialchars($don['description']); ?></textarea>
                            <small style="color: #666;">اذكر تفاصيل مهمة مثل: الحجم، الكمية، العلامة التجارية، ...</small>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-tag"></i> الفئة *
                                </label>
                                <select name="categorie" class="form-control" required>
                                    <option value="">اختر الفئة</option>
                                    <?php foreach($categories as $value => $label): ?>
                                        <option value="<?php echo $value; ?>" <?php echo $don['categorie'] == $value ? 'selected' : ''; ?>>
                                            <?php echo $label; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-star"></i> الحالة *
                                </label>
                                <select name="etat" class="form-control" required>
                                    <option value="">اختر الحالة</option>
                                    <?php foreach($etats as $value => $label): ?>
                                        <option value="<?php echo $value; ?>" <?php echo $don['etat'] == $value ? 'selected' : ''; ?>>
                                            <?php echo $label; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-city"></i> المدينة *
                                </label>
                                <input type="text" name="ville" class="form-control" 
                                       value="<?php echo htmlspecialchars($don['ville']); ?>" 
                                       required placeholder="مثال: الدار البيضاء، الرباط، ...">
                            </div>

                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-map-marker-alt"></i> عنوان الاستلام *
                                </label>
                                <input type="text" name="adresse_retrait" class="form-control" 
                                       value="<?php echo htmlspecialchars($don['adresse_retrait']); ?>" 
                                       required placeholder="العنوان الكامل للاستلام">
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-truck"></i> خيار التوصيل *
                            </label>
                            <select name="livraison_option" class="form-control" required>
                                <?php foreach($livraison_options as $value => $label): ?>
                                    <option value="<?php echo $value; ?>" <?php echo $don['livraison_option'] == $value ? 'selected' : ''; ?>>
                                        <?php echo $label; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small style="color: #666;">اختر كيفية تغطية تكاليف التوصيل</small>
                        </div>

                        <hr style="margin: 30px 0; border: none; border-top: 1px solid #eee;">

                        <!-- Section des photos -->
                        <h4 style="margin-bottom: 20px; color: var(--primary);">
                            <i class="fas fa-images"></i> الصور
                        </h4>

                        <!-- الصورة الرئيسية -->
                        <div style="margin-bottom: 30px;">
                            <label class="form-label">
                                <i class="fas fa-camera"></i> الصورة الرئيسية
                            </label>
                            
                            <?php if(!empty($don['photo_principale'])): ?>
                                <div style="margin-bottom: 15px;">
                                    <div style="max-width: 300px; position: relative;">
                                        <img src="../<?php echo $don['photo_principale']; ?>" 
                                             alt="الصورة الرئيسية" 
                                             style="width: 100%; border-radius: 8px; border: 3px solid var(--accent);">
                                        <span style="position: absolute; top: 10px; right: 10px; background: var(--accent); color: white; padding: 5px 10px; border-radius: 20px; font-size: 12px;">
                                            <i class="fas fa-star"></i> الصورة الرئيسية
                                        </span>
                                    </div>
                                    <label style="display: flex; align-items: center; gap: 10px; margin-top: 10px;">
                                        <input type="checkbox" name="remove_main_photo" value="1">
                                        <span style="color: var(--danger);">حذف الصورة الرئيسية</span>
                                    </label>
                                </div>
                            <?php endif; ?>

                            <div class="file-upload" onclick="document.getElementById('main-photo-input').click()">
                                <i class="fas fa-cloud-upload-alt"></i>
                                <p>اضغط لاختيار صورة رئيسية جديدة</p>
                                <small>JPG, PNG, GIF, WEBP (حد أقصى 5 ميجابايت)</small>
                            </div>
                            <input type="file" id="main-photo-input" name="photo_principale" accept="image/*" style="display: none;">
                        </div>

                        <!-- الصور الإضافية -->
                        <?php if(!empty($don_photos)): ?>
                            <div style="margin-bottom: 20px;">
                                <label class="form-label">الصور الإضافية الحالية</label>
                                <div class="photo-gallery">
                                    <?php foreach($don_photos as $photo): ?>
                                        <div class="photo-item">
                                            <img src="../<?php echo $photo['photo_path']; ?>" alt="صورة التبرع">
                                            <label class="photo-checkbox">
                                                <input type="checkbox" name="delete_photos[]" value="<?php echo $photo['id']; ?>">
                                                <small>حذف</small>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <small style="color: #666;">حدد الصور التي تريد حذفها</small>
                            </div>
                        <?php endif; ?>

                        <div style="margin-bottom: 20px;">
                            <label class="form-label">إضافة صور جديدة</label>
                            <div class="file-upload" onclick="document.getElementById('photos-input').click()">
                                <i class="fas fa-cloud-upload-alt"></i>
                                <p>اضغط لاختيار صور إضافية</p>
                                <small>يمكنك اختيار عدة صور (JPG, PNG, GIF, WEBP - حد أقصى 5 ميجابايت لكل صورة)</small>
                            </div>
                            <input type="file" id="photos-input" name="photos[]" accept="image/*" multiple style="display: none;">
                            <div id="selected-files" style="margin-top: 15px;"></div>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-save"></i> حفظ التعديلات
                            </button>
                            <a href="mes-dons.php" class="btn btn-outline">
                                <i class="fas fa-times"></i> إلغاء
                            </a>
                            <a href="voir-don.php?id=<?php echo $don_id; ?>" class="btn btn-secondary" target="_blank">
                                <i class="fas fa-eye"></i> معاينة
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- نصائح -->
            <div class="card" style="background: #f8f9fa;">
                <div class="card-body">
                    <h4 style="color: var(--accent); margin-bottom: 15px;">
                        <i class="fas fa-lightbulb"></i> نصائح لتحسين تبرعك
                    </h4>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                        <div style="display: flex; gap: 10px;">
                            <i class="fas fa-camera" style="color: var(--accent); font-size: 24px;"></i>
                            <div>
                                <strong>صور واضحة</strong>
                                <p style="font-size: 13px; color: #666;">أضف صوراً واضحة للتبرع تظهر حالته الحقيقية</p>
                            </div>
                        </div>
                        <div style="display: flex; gap: 10px;">
                            <i class="fas fa-align-left" style="color: var(--accent); font-size: 24px;"></i>
                            <div>
                                <strong>وصف دقيق</strong>
                                <p style="font-size: 13px; color: #666;">اكتب وصفاً دقيقاً يوضح كل تفاصيل التبرع</p>
                            </div>
                        </div>
                        <div style="display: flex; gap: 10px;">
                            <i class="fas fa-map-marker-alt" style="color: var(--accent); font-size: 24px;"></i>
                            <div>
                                <strong>عنوان واضح</strong>
                                <p style="font-size: 13px; color: #666;">تأكد من كتابة عنوان كامل يسهل الوصول إليه</p>
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
    
    // عرض أسماء الملفات المختارة
    document.getElementById('photos-input').addEventListener('change', function(e) {
        const container = document.getElementById('selected-files');
        container.innerHTML = '';
        
        if (this.files.length > 0) {
            const div = document.createElement('div');
            div.className = 'alert alert-info';
            div.innerHTML = '<i class="fas fa-check-circle"></i> الملفات المختارة: ' + this.files.length;
            
            const list = document.createElement('ul');
            list.style.marginTop = '10px';
            list.style.marginRight = '20px';
            
            for (let i = 0; i < this.files.length; i++) {
                const item = document.createElement('li');
                item.style.fontSize = '13px';
                item.style.color = '#666';
                item.textContent = this.files[i].name + ' (' + (this.files[i].size / 1024).toFixed(1) + ' KB)';
                list.appendChild(item);
            }
            
            div.appendChild(list);
            container.appendChild(div);
        }
    });
    
    // عرض اسم الملف الرئيسي
    document.getElementById('main-photo-input').addEventListener('change', function(e) {
        if (this.files.length > 0) {
            alert('تم اختيار الصورة: ' + this.files[0].name);
        }
    });
    
    // تأكيد الحذف
    document.querySelectorAll('input[name="delete_photos[]"]').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            if (this.checked) {
                if (!confirm('هل أنت متأكد من حذف هذه الصورة؟')) {
                    this.checked = false;
                }
            }
        });
    });
    
    // تأكيد إزالة الصورة الرئيسية
    document.querySelector('input[name="remove_main_photo"]')?.addEventListener('change', function() {
        if (this.checked) {
            if (!confirm('هل أنت متأكد من حذف الصورة الرئيسية؟')) {
                this.checked = false;
            }
        }
    });
    
    // تأكيد قبل حفظ التغييرات
    document.getElementById('donForm').addEventListener('submit', function(e) {
        if (!confirm('هل أنت متأكد من حفظ التغييرات؟')) {
            e.preventDefault();
        }
    });
    </script>
</body>
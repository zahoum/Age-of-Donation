<?php
// includes/contact-us.php

session_start();

$page_title = 'اتصل بنا';
require_once 'header.php';

// =========================
// CSRF TOKEN
// =========================
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// =========================
// HELPERS
// =========================
function getUserIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    }
    return $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
}

$success = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_form'])) {
    // CSRF Check
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = 'خطأ في الأمان، يرجى تحديث الصفحة والمحاولة مرة أخرى';
    } else {
        // Honeypot check
        if (!empty($_POST['website'])) {
            $error = 'Spam detected';
        } else {
            // Rate limiting
            if (!isset($_SESSION['contact_last_submit'])) {
                $_SESSION['contact_last_submit'] = 0;
            }
            
            if (time() - $_SESSION['contact_last_submit'] < 30) {
                $error = 'يرجى الانتظار 30 ثانية قبل إرسال رسالة أخرى';
            } else {
                $_SESSION['contact_last_submit'] = time();
                
                // Get and sanitize data
                $name = trim($_POST['name'] ?? '');
                $email = trim($_POST['email'] ?? '');
                $subject = trim($_POST['subject'] ?? '');
                $message = trim($_POST['message'] ?? '');
                $phone = trim($_POST['phone'] ?? '');
                
                // Validation
                if (empty($name) || empty($email) || empty($subject) || empty($message)) {
                    $error = 'جميع الحقول المطلوبة يجب ملؤها';
                } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $error = 'البريد الإلكتروني غير صحيح';
                } elseif (strlen($message) < 10) {
                    $error = 'الرسالة قصيرة جداً (الحد الأدنى 10 أحرف)';
                } else {
                    // Save to database
                    try {
                        require_once '../config/database.php';
                        
                        $database = new Database();
                        $db = $database->getConnection();
                        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                        
                        $ip_address = getUserIP();
                        
                        $sql = "INSERT INTO contact_messages (name, email, phone, subject, message, ip_address, created_at)
                                VALUES (:name, :email, :phone, :subject, :message, :ip_address, NOW())";
                        
                        $stmt = $db->prepare($sql);
                        $stmt->execute([
                            ':name' => htmlspecialchars($name),
                            ':email' => $email,
                            ':phone' => htmlspecialchars($phone),
                            ':subject' => htmlspecialchars($subject),
                            ':message' => htmlspecialchars($message),
                            ':ip_address' => $ip_address
                        ]);
                        
                        // Send email using FormSubmit (server-side)
                        $formsubmitData = [
                            'name' => $name,
                            'email' => $email,
                            'phone' => $phone,
                            'subject' => $subject,
                            'message' => $message,
                            '_subject' => 'رسالة جديدة من Age of Donation - ' . $subject,
                            '_replyto' => $email,
                            '_captcha' => 'false',
                            '_template' => 'table'
                        ];
                        
                        $ch = curl_init('https://formsubmit.co/ajax/aissazahoum6@gmail.com');
                        curl_setopt($ch, CURLOPT_POST, true);
                        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($formsubmitData));
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
                        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                        
                        $response = curl_exec($ch);
                        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                        curl_close($ch);
                        
                        if ($httpCode === 200) {
                            $success = 'تم إرسال رسالتك بنجاح! سنقوم بالرد عليك قريباً.';
                            // Clear form data
                            $_POST = array();
                        } else {
                            $error = 'حدث خطأ في إرسال البريد الإلكتروني. يرجى المحاولة مرة أخرى.';
                        }
                        
                    } catch (PDOException $e) {
                        error_log($e->getMessage());
                        $error = 'حدث خطأ في النظام. يرجى المحاولة مرة أخرى.';
                    }
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>اتصل بنا - Age of Donation</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #667eea;
            --secondary: #764ba2;
            --light: #f8f9ff;
            --dark: #2d3748;
            --border: #e9ecf5;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: radial-gradient(circle at top right, #eef2ff 0%, #f8f9fc 40%, #ffffff 100%);
            font-family: 'Tajawal', 'Cairo', sans-serif;
            min-height: 100vh;
        }

        .contact-wrapper {
            max-width: 1300px;
            margin: 0 auto;
            padding: 50px 20px;
        }

        .main-card {
            border: none;
            border-radius: 30px;
            overflow: hidden;
            background: #ffffff;
            box-shadow: 0 20px 60px rgba(0,0,0,0.08);
        }

        .card-header-custom {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            padding: 40px;
            color: white;
            text-align: center;
        }

        .card-header-custom h1 {
            font-size: 2rem;
            margin-bottom: 10px;
        }

        .card-header-custom p {
            opacity: 0.9;
            font-size: 1.1rem;
        }

        .card-body-custom {
            padding: 50px;
        }

        .info-card {
            background: linear-gradient(180deg, #ffffff 0%, #f8f9ff 100%);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 30px;
            height: 100%;
        }

        .info-item {
            margin-bottom: 30px;
        }

        .info-item h5 {
            color: var(--primary);
            font-weight: 600;
            margin-bottom: 15px;
        }

        .info-item h5 i {
            margin-left: 10px;
        }

        .info-item p {
            color: #4a5568;
            margin-bottom: 5px;
            font-size: 0.95rem;
        }

        .form-control, .form-select {
            border: 2px solid var(--border);
            border-radius: 12px;
            padding: 12px 16px;
            transition: all 0.3s ease;
            font-size: 1rem;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(102,126,234,0.1);
            outline: none;
        }

        .form-label {
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 8px;
        }

        .form-label .required {
            color: #e53e3e;
        }

        textarea.form-control {
            min-height: 150px;
            resize: vertical;
        }

        .btn-submit {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            border: none;
            padding: 14px 35px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 1.1rem;
            transition: all 0.3s ease;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102,126,234,0.3);
        }

        .btn-submit:disabled {
            opacity: 0.7;
            transform: none;
        }

        .btn-reset {
            background: #edf2f7;
            color: #4a5568;
            border: none;
            padding: 14px 30px;
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-reset:hover {
            background: #e2e8f0;
        }

        .alert-custom {
            border-radius: 12px;
            padding: 15px 20px;
            margin-bottom: 25px;
            animation: slideDown 0.5s ease;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .map-container {
            border-radius: 20px;
            overflow: hidden;
            margin-top: 30px;
        }

        .map-container iframe {
            width: 100%;
            height: 350px;
            border: none;
        }

        .footer-card {
            background: #f7fafc;
            padding: 20px;
            text-align: center;
            border-top: 1px solid var(--border);
        }

        @media (max-width: 768px) {
            .card-body-custom {
                padding: 25px;
            }
            
            .btn-submit, .btn-reset {
                width: 100%;
                margin-bottom: 10px;
            }
            
            .card-header-custom h1 {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="contact-wrapper">
        <div class="main-card">
            <div class="card-header-custom">
                <h1>
                    <i class="fas fa-headset"></i> اتصل بنا
                </h1>
                <p>نحن هنا للإجابة على استفساراتك ومساعدتك</p>
            </div>
            
            <div class="card-body-custom">
                <div class="row g-4">
                    <!-- Information Column -->
                    <div class="col-lg-4">
                        <div class="info-card">
                            <div class="info-item">
                                <h5>
                                    <i class="fas fa-phone-alt"></i> الهاتف
                                </h5>
                                <p>📞 +212 649 33 99 48</p>
                            </div>
                            
                            <div class="info-item">
                                <h5>
                                    <i class="fas fa-envelope"></i> البريد الإلكتروني
                                </h5>
                                <p>📧 aissazahoum6@gmail.com</p>
                                <p>📧 admin@ageofdonnation.org</p>
                            </div>
                            
                            <div class="info-item">
                                <h5>
                                    <i class="fas fa-clock"></i> ساعات العمل
                                </h5>
                                <p>🕐 الإثنين - الجمعة: 9ص - 5م</p>
                                <p>🕐 السبت: 10ص - 2م</p>
                                <p>📅 الأحد: مغلق</p>
                            </div>
                            
                            <div class="info-item">
                                <h5>
                                    <i class="fas fa-map-marker-alt"></i> العنوان
                                </h5>
                                <p>📍 سيدي عابد، الحسيمة، المغرب</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Form Column -->
                    <div class="col-lg-8">
                        <?php if($success): ?>
                            <div class="alert alert-success alert-custom">
                                <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if($error): ?>
                            <div class="alert alert-danger alert-custom">
                                <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="" id="contactForm">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <input type="hidden" name="submit_form" value="1">
                            
                            <!-- Honeypot field -->
                            <div style="display: none;">
                                <input type="text" name="website" id="website">
                            </div>
                            
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">
                                        الاسم الكامل <span class="required">*</span>
                                    </label>
                                    <input 
                                        type="text" 
                                        class="form-control" 
                                        name="name" 
                                        required
                                        placeholder="أدخل اسمك الكامل"
                                        value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label">
                                        البريد الإلكتروني <span class="required">*</span>
                                    </label>
                                    <input 
                                        type="email" 
                                        class="form-control" 
                                        name="email" 
                                        required
                                        placeholder="example@domain.com"
                                        value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label">
                                        رقم الهاتف
                                    </label>
                                    <input 
                                        type="tel" 
                                        class="form-control" 
                                        name="phone"
                                        placeholder="+212XXXXXXXXX"
                                        value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label">
                                        الموضوع <span class="required">*</span>
                                    </label>
                                    <select class="form-select" name="subject" required>
                                        <option value="">-- اختر الموضوع --</option>
                                        <option value="استفسار عام" <?php echo (($_POST['subject'] ?? '') == 'استفسار عام') ? 'selected' : ''; ?>>📝 استفسار عام</option>
                                        <option value="الدعم الفني" <?php echo (($_POST['subject'] ?? '') == 'الدعم الفني') ? 'selected' : ''; ?>>🔧 الدعم الفني</option>
                                        <option value="التبرعات" <?php echo (($_POST['subject'] ?? '') == 'التبرعات') ? 'selected' : ''; ?>>💝 التبرعات</option>
                                        <option value="شكوى" <?php echo (($_POST['subject'] ?? '') == 'شكوى') ? 'selected' : ''; ?>>⚠️ شكوى</option>
                                        <option value="اقتراح" <?php echo (($_POST['subject'] ?? '') == 'اقتراح') ? 'selected' : ''; ?>>💡 اقتراح</option>
                                        <option value="شراكة" <?php echo (($_POST['subject'] ?? '') == 'شراكة') ? 'selected' : ''; ?>>🤝 شراكة</option>
                                    </select>
                                </div>
                                
                                <div class="col-12">
                                    <label class="form-label">
                                        الرسالة <span class="required">*</span>
                                    </label>
                                    <textarea 
                                        class="form-control" 
                                        name="message" 
                                        required
                                        placeholder="اكتب رسالتك هنا... (الحد الأدنى 10 أحرف)"><?php echo htmlspecialchars($_POST['message'] ?? ''); ?></textarea>
                                    <small class="text-muted">الحد الأدنى 10 أحرف</small>
                                </div>
                                
                                <div class="col-12">
                                    <button type="submit" class="btn-submit" id="submitBtn">
                                        <i class="fas fa-paper-plane"></i> إرسال الرسالة
                                    </button>
                                    <button type="button" class="btn-reset" id="resetBtn">
                                        <i class="fas fa-undo-alt"></i> مسح النموذج
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Map Section -->
                <div class="map-container">
                    <iframe 
                        src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3238.614456789012!2d-3.937623!3d35.250000!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x0%3A0x0!2zMzXCsDE1JzAwLjAiTiAzwrA1NicxNS40Ilc!5e0!3m2!1sen!2sma!4v1234567890123!5m2!1sen!2sma"
                        allowfullscreen="" 
                        loading="lazy">
                    </iframe>
                </div>
            </div>
            
            <div class="footer-card">
                <small>
                    <i class="far fa-copyright"></i> <?php echo date('Y'); ?> Age of Donation - جميع الحقوق محفوظة
                </small>
            </div>
        </div>
    </div>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('contactForm');
        const submitBtn = document.getElementById('submitBtn');
        const resetBtn = document.getElementById('resetBtn');
        
        // Reset form
        resetBtn.addEventListener('click', function() {
            if (confirm('هل أنت متأكد من مسح جميع البيانات؟')) {
                form.reset();
            }
        });
        
        // Form validation before submit
        form.addEventListener('submit', function(e) {
            const name = form.querySelector('input[name="name"]').value.trim();
            const email = form.querySelector('input[name="email"]').value.trim();
            const subject = form.querySelector('select[name="subject"]').value;
            const message = form.querySelector('textarea[name="message"]').value.trim();
            
            if (name.length < 3) {
                e.preventDefault();
                alert('الرجاء إدخال الاسم الكامل (3 أحرف على الأقل)');
                return false;
            }
            
            if (!email.match(/^[^\s@]+@[^\s@]+\.[^\s@]+$/)) {
                e.preventDefault();
                alert('الرجاء إدخال بريد إلكتروني صحيح');
                return false;
            }
            
            if (!subject) {
                e.preventDefault();
                alert('الرجاء اختيار الموضوع');
                return false;
            }
            
            if (message.length < 10) {
                e.preventDefault();
                alert('الرسالة قصيرة جداً (الحد الأدنى 10 أحرف)');
                return false;
            }
            
            // Disable button on submit
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جاري الإرسال...';
            
            // Re-enable after 30 seconds
            setTimeout(() => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> إرسال الرسالة';
            }, 30000);
        });
        
        // Real-time character counter for message
        const messageField = form.querySelector('textarea[name="message"]');
        const counter = document.createElement('small');
        counter.className = 'text-muted mt-1 d-block';
        messageField.parentNode.appendChild(counter);
        
        messageField.addEventListener('input', function() {
            const length = this.value.length;
            if (length < 10 && length > 0) {
                counter.innerHTML = `⚠️ عدد الأحرف: ${length}/10 - الرسالة قصيرة جداً`;
                counter.style.color = '#e53e3e';
            } else if (length >= 10) {
                counter.innerHTML = `✓ عدد الأحرف: ${length} - جيد`;
                counter.style.color = '#38a169';
            } else {
                counter.innerHTML = 'الحد الأدنى 10 أحرف';
                counter.style.color = '#718096';
            }
        });
    });
    </script>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php require_once 'footer.php'; ?>
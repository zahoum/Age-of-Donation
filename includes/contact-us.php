<?php
// includes/contact-us.php
$page_title = 'اتصل بنا';
require_once 'header.php';

// Handle form submission
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    
    // Validation
    if (empty($name) || empty($email) || empty($subject) || empty($message)) {
        $error = 'جميع الحقول الإلزامية (*) مطلوبة.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'البريد الإلكتروني غير صالح.';
    } elseif (strlen($message) < 10) {
        $error = 'الرسالة قصيرة جداً. يرجى كتابة تفاصيل أكثر.';
    } else {
        try {
            // Get database connection
            require_once '../config/database.php';
            $database = new Database();
            $db = $database->getConnection();
            
            // Get user IP address
            $ip_address = $_SERVER['REMOTE_ADDR'];
            
            // Prepare SQL statement
            $sql = "INSERT INTO contact_messages (name, email, phone, subject, message, ip_address, created_at) 
                    VALUES (:name, :email, :phone, :subject, :message, :ip_address, NOW())";
            
            $stmt = $db->prepare($sql);
            
            // Bind parameters
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':phone', $phone);
            $stmt->bindParam(':subject', $subject);
            $stmt->bindParam(':message', $message);
            $stmt->bindParam(':ip_address', $ip_address);
            
            // Execute query
            if ($stmt->execute()) {
                $message_id = $db->lastInsertId();
                $success = 'شكراً لتواصلك معنا! سنرد على رسالتك في أقرب وقت ممكن. رقم الرسالة: #' . $message_id;
                
                // Send email notification to admin (optional)
                sendEmailToAdmin($message_id, $name, $email, $phone, $subject, $message);
                
                // Clear form
                $_POST = [];
            } else {
                $error = 'حدث خطأ أثناء حفظ الرسالة. يرجى المحاولة مرة أخرى.';
            }
            
        } catch (PDOException $e) {
            $error = 'حدث خطأ في النظام. يرجى المحاولة لاحقاً.';
            error_log("Contact Form Error: " . $e->getMessage());
        }
    }
}

// Function to send email notification to admin
function sendEmailToAdmin($message_id, $name, $email, $phone, $subject, $message_content) {
    // Load email helper
    require_once 'email_helper.php';
    
    // Admin email (you can get this from database or config)
    $admin_email = 'admin@ageofdonnation.org';
    $admin_name = 'مسؤول Age of Donnation';
    
    // Prepare email content
    $email_subject = "رسالة جديدة - Age of Donnation (#$message_id)";
    
    $email_body = '
    <!DOCTYPE html>
    <html dir="rtl">
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; background: #f9f9f9; }
            .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
            .content { background: white; padding: 30px; border-radius: 0 0 10px 10px; }
            .info-box { background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 15px 0; }
            .btn { display: inline-block; background: #667eea; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-top: 15px; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h2>Age of Donnation</h2>
                <p>رسالة جديدة من نموذج الاتصال</p>
            </div>
            <div class="content">
                <h3>تفاصيل الرسالة</h3>
                
                <div class="info-box">
                    <p><strong>رقم الرسالة:</strong> #' . $message_id . '</p>
                    <p><strong>الاسم:</strong> ' . htmlspecialchars($name) . '</p>
                    <p><strong>البريد الإلكتروني:</strong> ' . htmlspecialchars($email) . '</p>
                    <p><strong>رقم الهاتف:</strong> ' . htmlspecialchars($phone) . '</p>
                    <p><strong>الموضوع:</strong> ' . htmlspecialchars($subject) . '</p>
                </div>
                
                <h4>الرسالة:</h4>
                <p>' . nl2br(htmlspecialchars($message_content)) . '</p>
                
                <p><strong>وقت الإرسال:</strong> ' . date('Y/m/d H:i:s') . '</p>
                
                <a href="' . $_SERVER['HTTP_ORIGIN'] . '/admin/messages.php?action=view&id=' . $message_id . '" class="btn">
                    عرض الرسالة في لوحة التحكم
                </a>
            </div>
        </div>
    </body>
    </html>
    ';
    
    // Send email using your existing sendVerificationEmail function or create a new one
    // Since sendVerificationEmail expects specific parameters, let's create a direct send
    
    try {
        // If you have PHPMailer configured, use it directly
        require_once '../vendor/autoload.php';
        
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        
        // Configure your SMTP settings (same as in email_helper.php)
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'your-email@gmail.com';  // Your email
        $mail->Password = 'your-app-password';      // App password
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        $mail->CharSet = 'UTF-8';
        
        // Sender and recipient
        $mail->setFrom('noreply@ageofdonnation.org', 'Age of Donnation');
        $mail->addAddress($admin_email, $admin_name);
        
        // Email content
        $mail->isHTML(true);
        $mail->Subject = $email_subject;
        $mail->Body = $email_body;
        
        // Plain text version
        $plain_text = "رسالة جديدة من موقع Age of Donnation\n\n";
        $plain_text .= "رقم الرسالة: #$message_id\n";
        $plain_text .= "الاسم: $name\n";
        $plain_text .= "البريد الإلكتروني: $email\n";
        $plain_text .= "رقم الهاتف: $phone\n";
        $plain_text .= "الموضوع: $subject\n\n";
        $plain_text .= "الرسالة:\n$message_content\n\n";
        $plain_text .= "وقت الإرسال: " . date('Y/m/d H:i:s') . "\n";
        $plain_text .= "عرض الرسالة: " . $_SERVER['HTTP_ORIGIN'] . "/admin/messages.php?action=view&id=$message_id";
        
        $mail->AltBody = $plain_text;
        
        // Send email
        if ($mail->send()) {
            error_log("Admin notification email sent successfully for message #$message_id");
            return true;
        } else {
            error_log("Failed to send admin notification email: " . $mail->ErrorInfo);
            return false;
        }
        
    } catch (Exception $e) {
        error_log("Error sending admin email: " . $e->getMessage());
        return false;
    }
}
?>
<!-- Rest of your contact-us.php HTML remains the same -->
<!-- Rest of your contact-us.php remains the same -->

<div class="container py-5">
    <div class="row">
        <div class="col-lg-10 mx-auto">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h1 class="h3 mb-0"><i class="fas fa-headset me-2"></i>اتصل بنا</h1>
                    <p class="mb-0 opacity-75">نحن هنا للإجابة على استفساراتك ومساعدتك</p>
                </div>
                <div class="card-body">
                    
                    <div class="row">
                        <!-- Contact Information -->
                        <div class="col-md-4 mb-4 mb-md-0">
                            <div class="contact-info p-4" style="background: #f8f9fa; border-radius: 10px;">
                                <h4 class="text-primary mb-4"><i class="fas fa-map-marker-alt me-2"></i>معلومات الاتصال</h4>
                                
                                <div class="mb-4">
                                    <h5><i class="fas fa-phone text-primary me-2"></i>الهاتف</h5>
                                    <p class="mb-1">الدعم الفني: <strong>+212 649 33 99 48</strong></p>
                                    <p>الشؤون الإدارية: <strong>+212 649 33 99 48</strong></p>
                                </div>
                                
                                <div class="mb-4">
                                    <h5><i class="fas fa-envelope text-primary me-2"></i>البريد الإلكتروني</h5>
                                    <p class="mb-1">العامة: <strong><a href="mailto:aissazahoum6@gmail.com">admin@ageOfDonation</a></strong></p>
                                    <p class="mb-1">الدعم: <strong>--------</strong></p>
                                    <p>التبرعات: <strong><a href="mailto:a.zahoum8425@uca.ac.ma">admin@ageOfDonation</a></strong></p>
                                </div>
                                
                                <div class="mb-4">
                                    <h5><i class="fas fa-clock text-primary me-2"></i>ساعات العمل</h5>
                                    <p class="mb-1">الأحد - الخميس: 9 صباحاً - 5 مساءً</p>
                                    <p>الجمعة - السبت: مغلق</p>
                                </div>
                                
                                <div class="mb-4">
                                </div>
                                
                                <div class="social-links mt-4">
                                    <h5><i class="fas fa-share-alt text-primary me-2"></i>تابعنا</h5>
                                    <div class="d-flex gap-3 mt-3">
                                        <a href="#" class="btn btn-outline-primary btn-sm">
                                            <i class="fab fa-facebook-f"></i>
                                        </a>
                                        <a href="#" class="btn btn-outline-primary btn-sm">
                                            <i class="fab fa-twitter"></i>
                                        </a>
                                        <a href="#" class="btn btn-outline-primary btn-sm">
                                            <i class="fab fa-instagram"></i>
                                        </a>
                                        <a href="#" class="btn btn-outline-primary btn-sm">
                                            <i class="fab fa-linkedin-in"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Contact Form -->
                        <div class="col-md-8">
                            <div class="p-3">
                                <?php if($success): ?>
                                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                                        <i class="fas fa-check-circle me-2"></i> <?php echo $success; ?>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if($error): ?>
                                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                        <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                    </div>
                                <?php endif; ?>
                                
                                <h4 class="text-primary mb-4"><i class="fas fa-paper-plane me-2"></i>أرسل رسالة</h4>
                                <p class="text-muted mb-4">يرجى ملء النموذج أدناه وسنقوم بالرد عليك في غضون 24-48 ساعة عمل.</p>
                                
                                <form method="POST" id="contactForm" novalidate>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="name" class="form-label">الاسم الكامل <span class="text-danger">*</span></label>
                                            <input type="text" 
                                                   class="form-control" 
                                                   id="name" 
                                                   name="name" 
                                                   value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>"
                                                   required>
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <label for="email" class="form-label">البريد الإلكتروني <span class="text-danger">*</span></label>
                                            <input type="email" 
                                                   class="form-control" 
                                                   id="email" 
                                                   name="email" 
                                                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                                                   required>
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <label for="phone" class="form-label">رقم الهاتف</label>
                                            <input type="tel" 
                                                   class="form-control" 
                                                   id="phone" 
                                                   name="phone" 
                                                   value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <label for="subject" class="form-label">الموضوع <span class="text-danger">*</span></label>
                                            <select class="form-select" id="subject" name="subject" required>
                                                <option value="">اختر الموضوع</option>
                                                <option value="استفسار عام" <?php echo ($_POST['subject'] ?? '') == 'استفسار عام' ? 'selected' : ''; ?>>استفسار عام</option>
                                                <option value="الدعم الفني" <?php echo ($_POST['subject'] ?? '') == 'الدعم الفني' ? 'selected' : ''; ?>>الدعم الفني</option>
                                                <option value="استفسار عن التبرعات" <?php echo ($_POST['subject'] ?? '') == 'استفسار عن التبرعات' ? 'selected' : ''; ?>>استفسار عن التبرعات</option>
                                                <option value="شكوى أو اقتراح" <?php echo ($_POST['subject'] ?? '') == 'شكوى أو اقتراح' ? 'selected' : ''; ?>>شكوى أو اقتراح</option>
                                                <option value="شراكة أو تعاون" <?php echo ($_POST['subject'] ?? '') == 'شراكة أو تعاون' ? 'selected' : ''; ?>>شراكة أو تعاون</option>
                                                <option value="آخر" <?php echo ($_POST['subject'] ?? '') == 'آخر' ? 'selected' : ''; ?>>آخر</option>
                                            </select>
                                        </div>
                                        
                                        <div class="col-12 mb-4">
                                            <label for="message" class="form-label">الرسالة <span class="text-danger">*</span></label>
                                            <textarea class="form-control" 
                                                      id="message" 
                                                      name="message" 
                                                      rows="6" 
                                                      required
                                                      placeholder="اكتب رسالتك هنا..."><?php echo htmlspecialchars($_POST['message'] ?? ''); ?></textarea>
                                            <div class="form-text">الحد الأدنى: 10 أحرف</div>
                                        </div>
                                        
                                        <div class="col-12">
                                            <button type="submit" class="btn btn-primary btn-lg px-5">
                                                <i class="fas fa-paper-plane me-2"></i>إرسال الرسالة
                                            </button>
                                            <button type="reset" class="btn btn-outline-secondary me-3">
                                                <i class="fas fa-redo me-2"></i>مسح النموذج
                                            </button>
                                        </div>
                                    </div>
                                </form>
                                
                                <!-- FAQ Section -->
                                <div class="mt-5 pt-4 border-top">
                                    <h5 class="text-primary mb-3"><i class="fas fa-question-circle me-2"></i>أسئلة متكررة</h5>
                                    <div class="accordion" id="faqAccordion">
                                        <div class="accordion-item">
                                            <h2 class="accordion-header" id="headingOne">
                                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne">
                                                    كم من الوقت يستغرق الرد على رسالتي؟
                                                </button>
                                            </h2>
                                            <div id="collapseOne" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                                <div class="accordion-body">
                                                    نهدف للرد على جميع الرسائل خلال 24-48 ساعة عمل. قد تستغرق الرسائل المعقدة وقتاً أطول.
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="accordion-item">
                                            <h2 class="accordion-header" id="headingTwo">
                                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTwo">
                                                    كيف يمكنني متابعة تبرعي؟
                                                </button>
                                            </h2>
                                            <div id="collapseTwo" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                                <div class="accordion-body">
                                                    يمكنك متابعة تبرعاتك من خلال حسابك الشخصي على الموقع، أو الاتصال بفريق التبرعات على donations@ageofdonnation.org
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="accordion-item">
                                            <h2 class="accordion-header" id="headingThree">
                                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseThree">
                                                    هل أتلقى إيصالاً عن تبرعاتي؟
                                                </button>
                                            </h2>
                                            <div id="collapseThree" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                                <div class="accordion-body">
                                                    نعم، نرسل إيصالات إلكترونية لجميع التبرعات. يمكنك طلب إيصال ورقي أيضاً.
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Map Placeholder -->
                    <div class="row mt-5">
                        <div class="col-12">
                            <div class="card border">
                                <div class="card-body text-center p-5">
                                    <i class="fas fa-map-marked-alt fa-3x text-primary mb-3"></i>
                                    <h5 class="text-primary">موقعنا على الخريطة</h5>
                                    <p class="text-muted">شارع محمد الخامس، الرباط، المغرب</p>
                                    <div style="height: 300px; background: #f1f1f1; border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                                        <div class="text-center">
                                            <i class="fas fa-map fa-2x text-muted mb-2"></i>
                                            <p class="text-muted">خريطة الموقع</p>
                                            <small class="text-muted">(في التطبيق الفعلي، سيتم وضع خريطة جوجل هنا)</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer text-muted text-center">
                    <small>© <?php echo date('Y'); ?> Age of Donnation. جميع الحقوق محفوظة.</small>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.card {
    border-radius: 15px;
    border: none;
}

.card-header {
    border-radius: 15px 15px 0 0 !important;
}

.contact-info {
    height: 100%;
}

.form-control, .form-select {
    border-radius: 10px;
    padding: 12px 15px;
}

.form-control:focus, .form-select:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 0.25rem rgba(102, 126, 234, 0.25);
}

.accordion-button {
    border-radius: 10px !important;
    margin-bottom: 5px;
}

.accordion-button:not(.collapsed) {
    background-color: rgba(102, 126, 234, 0.1);
    color: #667eea;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('contactForm');
    const submitBtn = form.querySelector('button[type="submit"]');
    
    // Form validation
    form.addEventListener('submit', function(e) {
        const name = document.getElementById('name').value.trim();
        const email = document.getElementById('email').value.trim();
        const subject = document.getElementById('subject').value;
        const message = document.getElementById('message').value.trim();
        
        let isValid = true;
        
        // Reset previous errors
        document.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
        
        if (!name) {
            showError('name', 'الاسم مطلوب');
            isValid = false;
        }
        
        if (!email || !validateEmail(email)) {
            showError('email', 'بريد إلكتروني صالح مطلوب');
            isValid = false;
        }
        
        if (!subject) {
            showError('subject', 'الرجاء اختيار موضوع');
            isValid = false;
        }
        
        if (!message || message.length < 10) {
            showError('message', 'الرسالة يجب أن تكون 10 أحرف على الأقل');
            isValid = false;
        }
        
        if (!isValid) {
            e.preventDefault();
        } else {
            // Show loading
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>جارٍ الإرسال...';
        }
    });
    
    function showError(fieldId, message) {
        const field = document.getElementById(fieldId);
        field.classList.add('is-invalid');
        
        let feedback = field.parentNode.querySelector('.invalid-feedback');
        if (!feedback) {
            feedback = document.createElement('div');
            feedback.className = 'invalid-feedback';
            field.parentNode.appendChild(feedback);
        }
        feedback.textContent = message;
    }
    
    function validateEmail(email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    }
    
    // Auto-clear validation on input
    document.querySelectorAll('input, textarea, select').forEach(input => {
        input.addEventListener('input', function() {
            this.classList.remove('is-invalid');
        });
    });
    
    // Prevent form resubmission
    if (window.history.replaceState) {
        window.history.replaceState(null, null, window.location.href);
    }
});
</script>

<?php require_once 'footer.php'; ?>
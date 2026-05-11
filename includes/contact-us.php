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

// =========================
// FORM HANDLING
// =========================
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // =========================
    // CSRF CHECK
    // =========================
    if (
        !isset($_POST['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        die('Invalid CSRF token');
    }

    // =========================
    // HONEYPOT SPAM CHECK
    // =========================
    if (!empty($_POST['website'])) {
        die('Spam detected');
    }

    // =========================
    // RATE LIMITING
    // =========================
    if (!isset($_SESSION['contact_last_submit'])) {
        $_SESSION['contact_last_submit'] = 0;
    }

    if (time() - $_SESSION['contact_last_submit'] < 30) {
        $error = 'يرجى الانتظار 30 ثانية قبل إرسال رسالة أخرى.';
    } else {

        $_SESSION['contact_last_submit'] = time();

        // =========================
        // GET & SANITIZE DATA
        // =========================
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $subject = trim($_POST['subject'] ?? '');
        $message = trim($_POST['message'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        // Sanitization
        $name = htmlspecialchars(strip_tags($name));
        $subject = htmlspecialchars(strip_tags($subject));
        $phone = htmlspecialchars(strip_tags($phone));
        $email = filter_var($email, FILTER_SANITIZE_EMAIL);

        // Prevent Header Injection
        $name = str_replace(["\r", "\n"], '', $name);
        $email = str_replace(["\r", "\n"], '', $email);

        // =========================
        // VALIDATION
        // =========================
        if (empty($name) || empty($email) || empty($subject) || empty($message)) {

            $error = 'جميع الحقول الإلزامية (*) مطلوبة.';

        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

            $error = 'البريد الإلكتروني غير صالح.';

        } elseif (mb_strlen($message, 'UTF-8') < 10) {

            $error = 'الرسالة قصيرة جداً. يرجى كتابة تفاصيل أكثر.';

        } else {

            try {

                // =========================
                // DATABASE
                // =========================
                require_once '../config/database.php';

                $database = new Database();
                $db = $database->getConnection();

                $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                // =========================
                // USER IP
                // =========================
                $ip_address = getUserIP();

                // =========================
                // INSERT MESSAGE
                // =========================
                $sql = "
                    INSERT INTO contact_messages 
                    (
                        name,
                        email,
                        phone,
                        subject,
                        message,
                        ip_address,
                        created_at
                    )
                    VALUES
                    (
                        :name,
                        :email,
                        :phone,
                        :subject,
                        :message,
                        :ip_address,
                        NOW()
                    )
                ";

                $stmt = $db->prepare($sql);

                $stmt->bindParam(':name', $name);
                $stmt->bindParam(':email', $email);
                $stmt->bindParam(':phone', $phone);
                $stmt->bindParam(':subject', $subject);
                $stmt->bindParam(':message', $message);
                $stmt->bindParam(':ip_address', $ip_address);

                if ($stmt->execute()) {

                    $message_id = $db->lastInsertId();

                    $success = 'شكراً لتواصلك معنا! سنرد عليك قريباً. رقم الرسالة: #' . $message_id;

                    // Send admin email
                    sendEmailToAdmin(
                        $message_id,
                        $name,
                        $email,
                        $phone,
                        $subject,
                        $message
                    );

                    // Clear form
                    $_POST = [];

                } else {

                    $error = 'حدث خطأ أثناء حفظ الرسالة.';

                }

            } catch (PDOException $e) {

                $error = 'حدث خطأ في النظام. يرجى المحاولة لاحقاً.';

                error_log("Contact Form Error: " . $e->getMessage());
            }
        }
    }
}

// =========================
// EMAIL FUNCTION
// =========================
function sendEmailToAdmin(
    $message_id,
    $name,
    $email,
    $phone,
    $subject,
    $message_content
) {

    try {

        require_once '../vendor/autoload.php';

        $mail = new PHPMailer\PHPMailer\PHPMailer(true);

        // =========================
        // SMTP CONFIG
        // =========================
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;

        // Use ENV VARIABLES
        $mail->Username = $_ENV['SMTP_EMAIL'];
        $mail->Password = $_ENV['SMTP_PASSWORD'];

        $mail->SMTPSecure =
            PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;

        $mail->Port = 587;
        $mail->CharSet = 'UTF-8';

        // =========================
        // SENDER
        // =========================
        $mail->setFrom(
            'noreply@ageofdonnation.org',
            'Age of Donation'
        );

        // =========================
        // RECEIVER
        // =========================
        $mail->addAddress(
            'admin@ageofdonnation.org',
            'Admin'
        );

        // =========================
        // EMAIL CONTENT
        // =========================
        $mail->isHTML(true);

        $mail->Subject =
            "رسالة جديدة - Age of Donation (#{$message_id})";

        $email_body = '
        <!DOCTYPE html>
        <html dir="rtl">
        <head>
            <meta charset="UTF-8">

            <style>

                * {
                    box-sizing: border-box;
                }

                body {
                    font-family: Arial, sans-serif;
                    background: #f5f5f5;
                    color: #333;
                    line-height: 1.8;
                    margin: 0;
                    padding: 20px;
                }

                .container {
                    max-width: 650px;
                    margin: auto;
                    background: #fff;
                    border-radius: 15px;
                    overflow: hidden;
                    box-shadow: 0 10px 25px rgba(0,0,0,.08);
                }

                .header {
                    background: linear-gradient(135deg, #667eea, #764ba2);
                    color: white;
                    padding: 30px;
                    text-align: center;
                }

                .content {
                    padding: 30px;
                }

                .info-box {
                    background: #f8f9fa;
                    border-radius: 10px;
                    padding: 20px;
                    margin-bottom: 20px;
                }

                .btn {
                    display: inline-block;
                    background: #667eea;
                    color: white;
                    padding: 12px 20px;
                    text-decoration: none;
                    border-radius: 8px;
                    margin-top: 20px;
                }

                img {
                    max-width: 100%;
                }

            </style>

        </head>

        <body>

            <div class="container">

                <div class="header">
                    <h2>Age of Donation</h2>
                    <p>رسالة جديدة من نموذج الاتصال</p>
                </div>

                <div class="content">

                    <div class="info-box">

                        <p><strong>رقم الرسالة:</strong> #' . $message_id . '</p>
                        <p><strong>الاسم:</strong> ' . $name . '</p>
                        <p><strong>البريد:</strong> ' . $email . '</p>
                        <p><strong>الهاتف:</strong> ' . $phone . '</p>
                        <p><strong>الموضوع:</strong> ' . $subject . '</p>

                    </div>

                    <h3>الرسالة</h3>

                    <p>' . nl2br($message_content) . '</p>

                    <p>
                        <strong>وقت الإرسال:</strong>
                        ' . date('Y/m/d H:i:s') . '
                    </p>

                </div>

            </div>

        </body>
        </html>
        ';

        $mail->Body = $email_body;

        // Plain text
        $mail->AltBody =
            "رسالة جديدة\n\n" .
            "الاسم: $name\n" .
            "البريد: $email\n" .
            "الهاتف: $phone\n" .
            "الموضوع: $subject\n\n" .
            "الرسالة:\n$message_content";

        return $mail->send();

    } catch (Exception $e) {

        error_log("Email Error: " . $e->getMessage());

        return false;
    }
}
?>

<div class="container py-5">

    <div class="row">

        <div class="col-lg-10 mx-auto">

            <div class="card shadow-sm">

                <div class="card-header bg-primary text-white">

                    <h1 class="h3 mb-0">
                        <i class="fas fa-headset me-2"></i>
                        اتصل بنا
                    </h1>

                    <p class="mb-0 opacity-75">
                        نحن هنا للإجابة على استفساراتك ومساعدتك
                    </p>

                </div>

                <div class="card-body">

                    <div class="row">

                        <!-- CONTACT INFO -->
                        <div class="col-md-4 mb-4 mb-md-0">

                            <div class="contact-info p-4">

                                <h4 class="text-primary mb-4">
                                    <i class="fas fa-map-marker-alt me-2"></i>
                                    معلومات الاتصال
                                </h4>

                                <div class="mb-4">

                                    <h5>
                                        <i class="fas fa-phone text-primary me-2"></i>
                                        الهاتف
                                    </h5>

                                    <p>
                                        <strong>+212 649 33 99 48</strong>
                                    </p>

                                </div>

                                <div class="mb-4">

                                    <h5>
                                        <i class="fas fa-envelope text-primary me-2"></i>
                                        البريد الإلكتروني
                                    </h5>

                                    <p>
                                        <a href="mailto:admin@ageofdonnation.org">
                                            admin@ageofdonnation.org
                                        </a>
                                    </p>

                                    <p>
                                        <a href="mailto:donations@ageofdonnation.org">
                                            donations@ageofdonnation.org
                                        </a>
                                    </p>

                                </div>

                            </div>

                        </div>

                        <!-- FORM -->
                        <div class="col-md-8">

                            <?php if($success): ?>

                                <div class="alert alert-success alert-dismissible fade show">

                                    <i class="fas fa-check-circle me-2"></i>

                                    <?php echo $success; ?>

                                    <button
                                        type="button"
                                        class="btn-close"
                                        data-bs-dismiss="alert">
                                    </button>

                                </div>

                            <?php endif; ?>

                            <?php if($error): ?>

                                <div class="alert alert-danger alert-dismissible fade show">

                                    <i class="fas fa-exclamation-circle me-2"></i>

                                    <?php echo $error; ?>

                                    <button
                                        type="button"
                                        class="btn-close"
                                        data-bs-dismiss="alert">
                                    </button>

                                </div>

                            <?php endif; ?>

                            <form method="POST" id="contactForm" novalidate>

                                <!-- CSRF -->
                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?php echo $_SESSION['csrf_token']; ?>">

                                <!-- HONEYPOT -->
                                <div style="display:none;">
                                    <input type="text" name="website">
                                </div>

                                <div class="row">

                                    <!-- NAME -->
                                    <div class="col-md-6 mb-3">

                                        <label class="form-label">
                                            الاسم الكامل *
                                        </label>

                                        <input
                                            type="text"
                                            class="form-control"
                                            id="name"
                                            name="name"
                                            placeholder="أدخل اسمك الكامل"
                                            aria-label="الاسم الكامل"
                                            value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>"
                                            required>

                                    </div>

                                    <!-- EMAIL -->
                                    <div class="col-md-6 mb-3">

                                        <label class="form-label">
                                            البريد الإلكتروني *
                                        </label>

                                        <input
                                            type="email"
                                            class="form-control"
                                            id="email"
                                            name="email"
                                            placeholder="example@email.com"
                                            aria-label="البريد الإلكتروني"
                                            value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                                            required>

                                    </div>

                                    <!-- PHONE -->
                                    <div class="col-md-6 mb-3">

                                        <label class="form-label">
                                            رقم الهاتف
                                        </label>

                                        <input
                                            type="tel"
                                            class="form-control"
                                            id="phone"
                                            name="phone"
                                            placeholder="06XXXXXXXX"
                                            value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">

                                    </div>

                                    <!-- SUBJECT -->
                                    <div class="col-md-6 mb-3">

                                        <label class="form-label">
                                            الموضوع *
                                        </label>

                                        <select
                                            class="form-select"
                                            id="subject"
                                            name="subject"
                                            required>

                                            <option value="">
                                                اختر الموضوع
                                            </option>

                                            <option value="استفسار عام">
                                                استفسار عام
                                            </option>

                                            <option value="الدعم الفني">
                                                الدعم الفني
                                            </option>

                                            <option value="التبرعات">
                                                التبرعات
                                            </option>

                                        </select>

                                    </div>

                                    <!-- MESSAGE -->
                                    <div class="col-12 mb-4">

                                        <label class="form-label">
                                            الرسالة *
                                        </label>

                                        <textarea
                                            class="form-control"
                                            id="message"
                                            name="message"
                                            rows="6"
                                            placeholder="اكتب رسالتك هنا..."
                                            required><?php echo htmlspecialchars($_POST['message'] ?? ''); ?></textarea>

                                        <div class="d-flex justify-content-between mt-2">

                                            <small class="text-muted">
                                                الحد الأدنى: 10 أحرف
                                            </small>

                                            <small class="text-muted">
                                                <span id="charCount">0</span>/1000
                                            </small>

                                        </div>

                                    </div>

                                    <!-- BUTTONS -->
                                    <div class="col-12">

                                        <button
                                            type="submit"
                                            class="btn btn-primary btn-lg px-5">

                                            <i class="fas fa-paper-plane me-2"></i>

                                            إرسال الرسالة

                                        </button>

                                        <button
                                            type="reset"
                                            class="btn btn-outline-secondary me-3">

                                            <i class="fas fa-redo me-2"></i>

                                            مسح النموذج

                                        </button>

                                    </div>

                                </div>

                            </form>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </div>

</div>

<style>

.card {
    border-radius: 20px;
    border: none;
    overflow: hidden;
    box-shadow: 0 10px 30px rgba(0,0,0,.08);
}

.card-header {
    border-radius: 20px 20px 0 0 !important;
}

.contact-info {
    background: #f8f9fa;
    border-radius: 15px;
    height: 100%;
}

.form-control,
.form-select {
    border-radius: 12px;
    padding: 12px 15px;
}

.form-control:focus,
.form-select:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 .25rem rgba(102,126,234,.25);
}

.btn-primary {
    transition: all .3s ease;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(102,126,234,.4);
}

.alert-success {
    animation: fadeIn .5s ease;
}

@keyframes fadeIn {

    from {
        opacity: 0;
        transform: translateY(-10px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@media (max-width: 768px) {

    .card-body {
        padding: 20px;
    }

    .btn-lg {
        width: 100%;
        margin-bottom: 10px;
    }

}

</style>

<script>

document.addEventListener('DOMContentLoaded', function () {

    const form = document.getElementById('contactForm');

    const submitBtn =
        form.querySelector('button[type="submit"]');

    const textarea =
        document.getElementById('message');

    const charCount =
        document.getElementById('charCount');

    // =========================
    // CHARACTER COUNTER
    // =========================
    textarea.addEventListener('input', function () {

        charCount.textContent = this.value.length;

        // Auto resize
        this.style.height = 'auto';
        this.style.height = this.scrollHeight + 'px';

    });

    // =========================
    // VALIDATION
    // =========================
    form.addEventListener('submit', function (e) {

        const name =
            document.getElementById('name').value.trim();

        const email =
            document.getElementById('email').value.trim();

        const subject =
            document.getElementById('subject').value;

        const message =
            document.getElementById('message').value.trim();

        let isValid = true;

        document.querySelectorAll('.is-invalid')
            .forEach(el => el.classList.remove('is-invalid'));

        if (!name) {

            showError('name');
            isValid = false;

        }

        if (!validateEmail(email)) {

            showError('email');
            isValid = false;

        }

        if (!subject) {

            showError('subject');
            isValid = false;

        }

        if (message.length < 10) {

            showError('message');
            isValid = false;

        }

        if (!isValid) {

            e.preventDefault();

        } else {

            submitBtn.disabled = true;

            submitBtn.innerHTML = `
                <span class="spinner-border spinner-border-sm me-2"></span>
                جارٍ الإرسال...
            `;

        }

    });

    function showError(id) {

        document
            .getElementById(id)
            .classList.add('is-invalid');

    }

    function validateEmail(email) {

        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

        return re.test(email);

    }

});

</script>

<?php require_once 'footer.php'; ?>
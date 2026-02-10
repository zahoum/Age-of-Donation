<?php
// includes/email_helper.php

// Load PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Include PHPMailer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Configuration
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'aissazahoum6@gmail.com');  // Change this
define('SMTP_PASSWORD', 'a.zahoum8425@uca.ac');     // Change this
define('SMTP_FROM_EMAIL', 'a.zahoum8425@uca.ac.ma');
define('SMTP_FROM_NAME', 'Age of Donnation');
define('ADMIN_EMAIL', 'a.zahoum8425@uca.ac.ma'); // Admin email for notifications

/**
 * Initialize PHPMailer with SMTP configuration
 * 
 * @return PHPMailer
 */
function initMailer() {
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;
        $mail->CharSet    = 'UTF-8';
        
        // Debug mode (disable in production)
        // $mail->SMTPDebug = SMTP::DEBUG_SERVER;
        
        // Default sender
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        
        return $mail;
    } catch (Exception $e) {
        error_log("PHPMailer initialization failed: " . $e->getMessage());
        return null;
    }
}

/**
 * Send verification email for password reset
 * 
 * @param string $to Recipient email
 * @param string $verification_code 6-digit verification code
 * @param string $user_name User's name
 * @return bool True if sent successfully
 */
function sendVerificationEmail($to, $verification_code, $user_name = '') {
    $subject = "=?UTF-8?B?" . base64_encode('رمز التحقق - إعادة تعيين كلمة المرور - Age of Donnation') . "?=";
    
    $message = '
    <!DOCTYPE html>
    <html dir="rtl">
    <head>
        <meta charset="UTF-8">
        <style>
            body { 
                font-family: Arial, sans-serif; 
                line-height: 1.6; 
                color: #333; 
                margin: 0;
                padding: 0;
            }
            .container { 
                max-width: 600px; 
                margin: 0 auto; 
                padding: 20px; 
                background: #f9f9f9; 
            }
            .header { 
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
                color: white; 
                padding: 30px; 
                text-align: center; 
                border-radius: 10px 10px 0 0; 
            }
            .content { 
                background: white; 
                padding: 30px; 
                border-radius: 0 0 10px 10px; 
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            }
            .code-box { 
                background: #f0f0f0; 
                padding: 20px; 
                text-align: center; 
                font-size: 32px; 
                font-weight: bold; 
                letter-spacing: 10px; 
                margin: 25px 0; 
                border-radius: 10px;
                color: #333;
                border: 2px dashed #667eea;
            }
            .footer {
                margin-top: 30px;
                padding-top: 20px;
                border-top: 1px solid #eee;
                color: #666;
                font-size: 14px;
            }
            .warning {
                background: #fff3cd;
                border: 1px solid #ffeaa7;
                color: #856404;
                padding: 15px;
                border-radius: 5px;
                margin: 20px 0;
            }
            @media (max-width: 600px) {
                .container {
                    padding: 10px;
                }
                .code-box {
                    font-size: 24px;
                    letter-spacing: 5px;
                    padding: 15px;
                }
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1 style="margin: 0; font-size: 28px;">Age of Donnation</h1>
                <p style="margin: 10px 0 0 0; opacity: 0.9;">إعادة تعيين كلمة المرور</p>
            </div>
            <div class="content">
                <h2 style="color: #333; margin-top: 0;">مرحبًا ' . htmlspecialchars($user_name) . '</h2>
                <p style="color: #666;">لقد طلبت إعادة تعيين كلمة المرور لحسابك. استخدم الرمز أدناه للتحقق:</p>
                
                <div class="code-box">
                    ' . $verification_code . '
                </div>
                
                <div class="warning">
                    <strong>⚠️ ملاحظة مهمة:</strong><br>
                    هذا الرمز سري للغاية. لا تشاركه مع أي شخص.<br>
                    صلاحية الرمز: <strong>5 دقائق</strong>
                </div>
                
                <p style="color: #666;">
                    <strong>رمز التحقق:</strong> ' . $verification_code . '<br>
                    <strong>صالح لمدة:</strong> 5 دقائق فقط<br>
                    <strong>طلب من:</strong> ' . $_SERVER['REMOTE_ADDR'] . '<br>
                    <strong>الوقت:</strong> ' . date('Y/m/d H:i:s') . '
                </p>
                
                <p style="color: #666;">
                    إذا لم تطلب إعادة تعيين كلمة المرور، يرجى تجاهل هذا البريد الإلكتروني أو 
                    <a href="mailto:' . ADMIN_EMAIL . '" style="color: #667eea;">الاتصال بالدعم</a>.
                </p>
                
                <div class="footer">
                    <p style="margin: 0;">
                        مع خالص التحيات،<br>
                        <strong>فريق Age of Donnation</strong>
                    </p>
                    <p style="margin: 10px 0 0 0; font-size: 12px; opacity: 0.7;">
                        هذه رسالة آلية، يرجى عدم الرد على هذا البريد الإلكتروني.<br>
                        © ' . date('Y') . ' Age of Donnation. جميع الحقوق محفوظة.
                    </p>
                </div>
            </div>
        </div>
    </body>
    </html>
    ';
    
    $plain_text = "رمز التحقق: $verification_code\n\n";
    $plain_text .= "مرحبًا $user_name،\n\n";
    $plain_text .= "لقد طلبت إعادة تعيين كلمة المرور لحسابك. استخدم الرمز أدناه للتحقق:\n\n";
    $plain_text .= "رمز التحقق: $verification_code\n";
    $plain_text .= "صالح لمدة: 5 دقائق فقط\n\n";
    $plain_text .= "إذا لم تطلب إعادة تعيين كلمة المرور، يرجى تجاهل هذا البريد الإلكتروني.\n\n";
    $plain_text .= "مع خالص التحيات،\n";
    $plain_text .= "فريق Age of Donnation\n\n";
    $plain_text .= date('Y/m/d H:i:s') . " | " . $_SERVER['REMOTE_ADDR'];
    
    return sendEmail($to, $subject, $message, $plain_text);
}

/**
 * Send email to admin about new contact message
 * 
 * @param int $message_id Message ID
 * @param string $name Sender name
 * @param string $email Sender email
 * @param string $phone Sender phone
 * @param string $subject Message subject
 * @param string $message_content Message content
 * @return bool True if sent successfully
 */
function sendAdminNotification($message_id, $name, $email, $phone, $subject, $message_content) {
    $admin_subject = "رسالة جديدة - Age of Donnation (#$message_id)";
    
    $html_message = '
    <!DOCTYPE html>
    <html dir="rtl">
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 700px; margin: 0 auto; padding: 20px; background: #f9f9f9; }
            .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 25px; text-align: center; border-radius: 10px 10px 0 0; }
            .content { background: white; padding: 30px; border-radius: 0 0 10px 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
            .info-box { background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0; border-right: 4px solid #667eea; }
            .btn { display: inline-block; background: #667eea; color: white; padding: 12px 25px; text-decoration: none; border-radius: 6px; margin-top: 15px; font-weight: bold; }
            .message-box { background: #f1f8ff; padding: 20px; border-radius: 8px; border: 1px solid #d1e7ff; margin: 20px 0; }
            .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; color: #666; font-size: 14px; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1 style="margin: 0; font-size: 26px;">Age of Donnation</h1>
                <p style="margin: 10px 0 0 0; opacity: 0.9;">رسالة جديدة من نموذج الاتصال</p>
            </div>
            <div class="content">
                <h2 style="color: #333; margin-top: 0;">تفاصيل الرسالة</h2>
                
                <div class="info-box">
                    <p style="margin: 10px 0;"><strong>📝 رقم الرسالة:</strong> #' . $message_id . '</p>
                    <p style="margin: 10px 0;"><strong>👤 الاسم:</strong> ' . htmlspecialchars($name) . '</p>
                    <p style="margin: 10px 0;"><strong>📧 البريد الإلكتروني:</strong> ' . htmlspecialchars($email) . '</p>
                    <p style="margin: 10px 0;"><strong>📞 رقم الهاتف:</strong> ' . htmlspecialchars($phone) . '</p>
                    <p style="margin: 10px 0;"><strong>📌 الموضوع:</strong> ' . htmlspecialchars($subject) . '</p>
                    <p style="margin: 10px 0;"><strong>🕒 وقت الإرسال:</strong> ' . date('Y/m/d H:i:s') . '</p>
                    <p style="margin: 10px 0;"><strong>📍 IP Address:</strong> ' . $_SERVER['REMOTE_ADDR'] . '</p>
                </div>
                
                <h3 style="color: #333;">📩 محتوى الرسالة:</h3>
                <div class="message-box">
                    ' . nl2br(htmlspecialchars($message_content)) . '
                </div>
                
                <a href="' . getBaseUrl() . '/admin/messages.php?action=view&id=' . $message_id . '" class="btn">
                    👁️ عرض الرسالة في لوحة التحكم
                </a>
                
                <div class="footer">
                    <p style="margin: 0;">
                        يمكنك الرد على هذه الرسالة من خلال لوحة التحكم أو عبر البريد الإلكتروني.<br>
                        <a href="mailto:' . $email . '" style="color: #667eea;">📧 الرد مباشرة إلى المرسل</a>
                    </p>
                    <p style="margin: 10px 0 0 0; font-size: 12px; opacity: 0.7;">
                        إشعار تلقائي | نظام إدارة Age of Donnation<br>
                        ' . date('Y/m/d H:i:s') . '
                    </p>
                </div>
            </div>
        </div>
    </body>
    </html>
    ';
    
    $plain_text = "📨 رسالة جديدة - Age of Donnation\n\n";
    $plain_text .= "رقم الرسالة: #$message_id\n";
    $plain_text .= "الاسم: $name\n";
    $plain_text .= "البريد الإلكتروني: $email\n";
    $plain_text .= "رقم الهاتف: $phone\n";
    $plain_text .= "الموضوع: $subject\n";
    $plain_text .= "وقت الإرسال: " . date('Y/m/d H:i:s') . "\n";
    $plain_text .= "IP Address: " . $_SERVER['REMOTE_ADDR'] . "\n\n";
    $plain_text .= "محتوى الرسالة:\n";
    $plain_text .= str_repeat("=", 50) . "\n";
    $plain_text .= $message_content . "\n";
    $plain_text .= str_repeat("=", 50) . "\n\n";
    $plain_text .= "عرض الرسالة في لوحة التحكم:\n";
    $plain_text .= getBaseUrl() . "/admin/messages.php?action=view&id=" . $message_id . "\n\n";
    $plain_text .= "الرد على المرسل: mailto:$email\n\n";
    $plain_text .= "إشعار تلقائي - نظام إدارة Age of Donnation";
    
    return sendEmail(ADMIN_EMAIL, $admin_subject, $html_message, $plain_text);
}

/**
 * Send welcome email to new user
 * 
 * @param string $to Recipient email
 * @param string $user_name User's name
 * @return bool True if sent successfully
 */
function sendWelcomeEmail($to, $user_name) {
    $subject = "=?UTF-8?B?" . base64_encode('مرحباً بك في Age of Donnation') . "?=";
    
    $html_message = '
    <!DOCTYPE html>
    <html dir="rtl">
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; background: #f9f9f9; }
            .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
            .content { background: white; padding: 30px; border-radius: 0 0 10px 10px; }
            .btn { display: inline-block; background: #667eea; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px; margin: 15px 0; }
            .features { display: flex; flex-wrap: wrap; gap: 15px; margin: 20px 0; }
            .feature { flex: 1; min-width: 150px; background: #f8f9fa; padding: 15px; border-radius: 8px; text-align: center; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1 style="margin: 0;">Age of Donnation</h1>
                <p style="margin: 10px 0 0 0; opacity: 0.9;">منصة التبرعات الأولى في المغرب</p>
            </div>
            <div class="content">
                <h2 style="color: #333;">مرحباً بك ' . htmlspecialchars($user_name) . ' 👋</h2>
                <p>نشكرك على انضمامك إلى مجتمع Age of Donnation. أنت الآن جزء من منصة تساعد في تغيير حياة الكثيرين.</p>
                
                <div class="features">
                    <div class="feature">
                        <strong>🎁 تبرع بسهولة</strong>
                        <p>تبرع بالأشياء التي لم تعد تحتاجها</p>
                    </div>
                    <div class="feature">
                        <strong>🤲 احصل على مساعدة</strong>
                        <p>اطلب ما تحتاجه من المجتمع</p>
                    </div>
                    <div class="feature">
                        <strong>🚚 توصيل مجاني</strong>
                        <p>مساعدون لنقل التبرعات</p>
                    </div>
                </div>
                
                <a href="' . getBaseUrl() . '/dashboard.php" class="btn">
                    ابدأ رحلتك
                </a>
                
                <p>إذا كان لديك أي استفسار، لا تتردد في <a href="' . getBaseUrl() . '/contact-us.php" style="color: #667eea;">الاتصال بنا</a>.</p>
                
                <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee;">
                    <p style="margin: 0;">مع خالص التحيات،<br><strong>فريق Age of Donnation</strong></p>
                </div>
            </div>
        </div>
    </body>
    </html>
    ';
    
    $plain_text = "مرحباً بك في Age of Donnation\n\n";
    $plain_text .= "مرحباً بك $user_name،\n\n";
    $plain_text .= "نشكرك على انضمامك إلى مجتمع Age of Donnation. أنت الآن جزء من منصة تساعد في تغيير حياة الكثيرين.\n\n";
    $plain_text .= "مميزات المنصة:\n";
    $plain_text .= "• تبرع بسهولة بالأشياء التي لم تعد تحتاجها\n";
    $plain_text .= "• احصل على مساعدة من المجتمع\n";
    $plain_text .= "• توصيل مجاني بواسطة مساعدين\n\n";
    $plain_text .= "ابدأ رحلتك: " . getBaseUrl() . "/dashboard.php\n\n";
    $plain_text .= "مع خالص التحيات،\n";
    $plain_text .= "فريق Age of Donnation";
    
    return sendEmail($to, $subject, $html_message, $plain_text);
}

/**
 * Send donation status update email
 * 
 * @param string $to Recipient email
 * @param string $user_name User's name
 * @param string $donation_title Donation title
 * @param string $status New status
 * @param string $notes Additional notes
 * @return bool True if sent successfully
 */
function sendDonationStatusEmail($to, $user_name, $donation_title, $status, $notes = '') {
    $status_texts = [
        'disponible' => 'متاح',
        'réservé' => 'محجوز',
        'completé' => 'مكتمل',
        'annulé' => 'ملغي'
    ];
    
    $status_color = [
        'disponible' => '#28a745',
        'réservé' => '#ffc107',
        'completé' => '#007bff',
        'annulé' => '#dc3545'
    ];
    
    $status_text = $status_texts[$status] ?? $status;
    $status_color = $status_color[$status] ?? '#666';
    
    $subject = "=?UTF-8?B?" . base64_encode('تحديث حالة تبرعك - Age of Donnation') . "?=";
    
    $html_message = '
    <!DOCTYPE html>
    <html dir="rtl">
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; background: #f9f9f9; }
            .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
            .content { background: white; padding: 30px; border-radius: 0 0 10px 10px; }
            .status-badge { display: inline-block; padding: 8px 20px; border-radius: 20px; color: white; font-weight: bold; margin: 10px 0; }
            .info-box { background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1 style="margin: 0;">Age of Donnation</h1>
                <p style="margin: 10px 0 0 0; opacity: 0.9;">تحديث حالة التبرع</p>
            </div>
            <div class="content">
                <h2 style="color: #333;">مرحباً ' . htmlspecialchars($user_name) . '</h2>
                <p>تم تحديث حالة تبرعك:</p>
                
                <div class="info-box">
                    <p><strong>🎁 التبرع:</strong> ' . htmlspecialchars($donation_title) . '</p>
                    <p><strong>📊 الحالة الجديدة:</strong> 
                        <span class="status-badge" style="background: ' . $status_color . ';">
                            ' . $status_text . '
                        </span>
                    </p>
                    <p><strong>🕒 وقت التحديث:</strong> ' . date('Y/m/d H:i:s') . '</p>
                </div>';
    
    if (!empty($notes)) {
        $html_message .= '
                <h3 style="color: #333;">📝 ملاحظات إضافية:</h3>
                <div style="background: #f1f8ff; padding: 15px; border-radius: 8px;">
                    ' . nl2br(htmlspecialchars($notes)) . '
                </div>';
    }
    
    $html_message .= '
                <p>يمكنك متابعة تبرعاتك من خلال <a href="' . getBaseUrl() . '/dashboard.php" style="color: #667eea;">لوحة التحكم</a>.</p>
                
                <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee;">
                    <p style="margin: 0;">مع خالص التحيات،<br><strong>فريق Age of Donnation</strong></p>
                </div>
            </div>
        </div>
    </body>
    </html>
    ';
    
    $plain_text = "تحديث حالة تبرعك - Age of Donnation\n\n";
    $plain_text .= "مرحباً $user_name،\n\n";
    $plain_text .= "تم تحديث حالة تبرعك:\n\n";
    $plain_text .= "التبرع: $donation_title\n";
    $plain_text .= "الحالة الجديدة: $status_text\n";
    $plain_text .= "وقت التحديث: " . date('Y/m/d H:i:s') . "\n\n";
    
    if (!empty($notes)) {
        $plain_text .= "ملاحظات إضافية:\n";
        $plain_text .= str_repeat("-", 30) . "\n";
        $plain_text .= $notes . "\n";
        $plain_text .= str_repeat("-", 30) . "\n\n";
    }
    
    $plain_text .= "يمكنك متابعة تبرعاتك من خلال: " . getBaseUrl() . "/dashboard.php\n\n";
    $plain_text .= "مع خالص التحيات،\n";
    $plain_text .= "فريق Age of Donnation";
    
    return sendEmail($to, $subject, $html_message, $plain_text);
}

/**
 * Send general email (for custom purposes)
 * 
 * @param string $to Recipient email
 * @param string $subject Email subject
 * @param string $html_body HTML email body
 * @param string $plain_text Plain text version
 * @param string $from_name Sender name
 * @param string $from_email Sender email
 * @return bool True if sent successfully
 */
function sendEmail($to, $subject, $html_body, $plain_text = '', $from_name = SMTP_FROM_NAME, $from_email = SMTP_FROM_EMAIL) {
    $mail = initMailer();
    
    if (!$mail) {
        return false;
    }
    
    try {
        // Set sender and recipient
        $mail->setFrom($from_email, $from_name);
        $mail->addAddress($to);
        
        // Set email content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $html_body;
        
        // Add plain text version if provided
        if (!empty($plain_text)) {
            $mail->AltBody = $plain_text;
        }
        
        // Send email
        if ($mail->send()) {
            error_log("Email sent successfully to: $to");
            return true;
        } else {
            error_log("Failed to send email to: $to - Error: " . $mail->ErrorInfo);
            return false;
        }
    } catch (Exception $e) {
        error_log("Email sending failed for $to: " . $e->getMessage());
        return false;
    }
}

/**
 * Get base URL for links in emails
 * 
 * @return string Base URL
 */
function getBaseUrl() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'];
    
    // Remove port if it's standard
    $host = preg_replace('/:\d+$/', '', $host);
    
    return $protocol . $host;
}

/**
 * Test email configuration
 * 
 * @param string $test_email Email to send test to
 * @return array Test results
 */
function testEmailConfiguration($test_email) {
    $results = [
        'smtp_connection' => false,
        'authentication' => false,
        'email_sent' => false,
        'errors' => []
    ];
    
    try {
        $mail = initMailer();
        
        if (!$mail) {
            $results['errors'][] = 'فشل تهيئة PHPMailer';
            return $results;
        }
        
        // Test SMTP connection
        $results['smtp_connection'] = true;
        
        // Test authentication
        $results['authentication'] = true;
        
        // Test sending
        $mail->addAddress($test_email);
        $mail->Subject = 'اختبار إرسال البريد - Age of Donnation';
        $mail->Body = '<h1>اختبار ناجح!</h1><p>تم إرسال هذا البريد بنجاح.</p>';
        $mail->AltBody = 'اختبار ناجح! تم إرسال هذا البريد بنجاح.';
        
        if ($mail->send()) {
            $results['email_sent'] = true;
        } else {
            $results['errors'][] = $mail->ErrorInfo;
        }
        
    } catch (Exception $e) {
        $results['errors'][] = $e->getMessage();
    }
    
    return $results;
}

/**
 * Send bulk email to multiple recipients
 * 
 * @param array $recipients Array of ['email' => '', 'name' => '']
 * @param string $subject Email subject
 * @param string $html_template HTML template with placeholders {name}, {email}
 * @param array $common_data Common data for all recipients
 * @return array Results
 */
function sendBulkEmail($recipients, $subject, $html_template, $common_data = []) {
    $results = [
        'total' => count($recipients),
        'success' => 0,
        'failed' => 0,
        'errors' => []
    ];
    
    foreach ($recipients as $recipient) {
        try {
            // Prepare personalized content
            $html_content = $html_template;
            $html_content = str_replace('{name}', htmlspecialchars($recipient['name']), $html_content);
            $html_content = str_replace('{email}', htmlspecialchars($recipient['email']), $html_content);
            
            // Replace common data placeholders
            foreach ($common_data as $key => $value) {
                $html_content = str_replace('{' . $key . '}', htmlspecialchars($value), $html_content);
            }
            
            // Send email
            if (sendEmail($recipient['email'], $subject, $html_content)) {
                $results['success']++;
            } else {
                $results['failed']++;
                $results['errors'][] = "فشل إرسال إلى: " . $recipient['email'];
            }
            
            // Small delay to avoid rate limiting
            usleep(100000); // 0.1 second
            
        } catch (Exception $e) {
            $results['failed']++;
            $results['errors'][] = "خطأ مع " . $recipient['email'] . ": " . $e->getMessage();
        }
    }
    
    return $results;
}
?>
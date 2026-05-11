<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$conn = mysqli_connect("localhost", "root", "", "age_of_donnation");
if (mysqli_connect_errno()) {
    die("Failed to connect to MySQL: " . mysqli_connect_error());
}

date_default_timezone_set('Africa/Casablanca');
$error = '';
?>

<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <title>Password Recovery</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
</head>
<body>

<div class="container">
    <div class="row">
        <div class="col-md-4"></div>
        <div class="col-md-4">

            <h2>استعادة كلمة المرور</h2>

<?php
if (isset($_POST["email"]) && !empty($_POST["email"])) {

    $email = filter_var($_POST["email"], FILTER_SANITIZE_EMAIL);
    $email = filter_var($email, FILTER_VALIDATE_EMAIL);

    if (!$email) {
        $error = "البريد الإلكتروني غير صالح";
    } else {
        // Fix: Check if users table exists first
        $check_table = mysqli_query($conn, "SHOW TABLES LIKE 'users'");
        if (mysqli_num_rows($check_table) == 0) {
            $error = "جدول المستخدمين غير موجود";
        } else {
            // secure query - Fixed the bind_param issue
            $stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE email = ?");
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "s", $email);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);

                if (mysqli_num_rows($result) == 0) {
                    $error = "لا يوجد مستخدم بهذا البريد الإلكتروني";
                }
                mysqli_stmt_close($stmt);
            } else {
                $error = "خطأ في الاستعلام";
            }
        }
    }

    if ($error != "") {
        echo '<div class="alert alert-danger"><strong>خطأ!</strong> '.$error.'</div>';
    } else {

        $expDate = date("Y-m-d H:i:s", strtotime("+1 day"));
        $key = md5(time()) . substr(md5(uniqid(rand(),1)),3,10);

        // Insert into password_reset_temp
        $insert_stmt = mysqli_prepare($conn, "INSERT INTO password_reset_temp (email, `key`, expDate) VALUES (?, ?, ?)");
        if ($insert_stmt) {
            mysqli_stmt_bind_param($insert_stmt, "sss", $email, $key, $expDate);
            mysqli_stmt_execute($insert_stmt);
            mysqli_stmt_close($insert_stmt);
        }

        $reset_link = "http://localhost/Age-of-Donation/auth/reset-password.php?key=$key&email=$email&action=reset";

        $body = "
            <html>
            <head>
                <style>
                    body{font-family:Arial,sans-serif;}
                    .container{padding:20px;background:#f4f4f4;}
                    .content{background:#fff;padding:20px;border-radius:5px;}
                    .button{display:inline-block;padding:10px 20px;background:#667eea;color:#fff;text-decoration:none;border-radius:5px;}
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='content'>
                        <h2>استعادة كلمة المرور</h2>
                        <p>تم إرسال هذا البريد لاستعادة كلمة المرور الخاصة بك.</p>
                        <p>لإعادة تعيين كلمة المرور، اضغط على الرابط التالي:</p>
                        <p><a href='$reset_link' class='button'>إعادة تعيين كلمة المرور</a></p>
                        <p>أو انسخ الرابط التالي:</p>
                        <p>$reset_link</p>
                        <p>هذا الرابط صالح لمدة 24 ساعة.</p>
                        <hr>
                        <p>إذا لم تطلب استعادة كلمة المرور، يرجى تجاهل هذا البريد.</p>
                    </div>
                </div>
            </body>
            </html>
        ";

        require '../vendor/autoload.php';

        $mail = new PHPMailer(true);

        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'aissazahoum6@gmail.com';  // Your new Gmail
            $mail->Password = 'dhcs iuxn bbol mvlf';  // Replace with your app password
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;
            
            // Fix SSL certificate issues
            $mail->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                )
            );

            $mail->CharSet = 'UTF-8';
            $mail->setFrom('aissazahoum6@gmail.com', 'Age of Donation Support');
            $mail->addAddress($email);
            $mail->isHTML(true);
            $mail->Subject = 'استعادة كلمة المرور';
            $mail->Body = $body;
            $mail->AltBody = strip_tags($body);

            if ($mail->send()) {
                echo '<div class="alert alert-success"><strong>نجاح!</strong> تم إرسال رابط استعادة كلمة المرور.</div>';
            } else {
                echo '<div class="alert alert-danger"><strong>خطأ!</strong> '.$mail->ErrorInfo.'</div>';
            }
        } catch (Exception $e) {
            echo '<div class="alert alert-danger"><strong>خطأ!</strong> '.$mail->ErrorInfo.'</div>';
        }
    }
}
?>

            <form method="post">
                <div class="form-group">
                    <label>البريد الإلكتروني:</label>
                    <input type="email" name="email" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-primary">
                    إرسال رابط استعادة كلمة المرور
                </button>
            </form>

        </div>
        <div class="col-md-4"></div>
    </div>
</div>

</body>
</html>
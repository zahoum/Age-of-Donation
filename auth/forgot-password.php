<?php
use PHPMailer\PHPMailer\PHPMailer;

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
        // secure query
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows == 0) {
            $error = "لا يوجد مستخدم بهذا البريد الإلكتروني";
        }
    }

    if ($error != "") {
        echo '<div class="alert alert-danger"><strong>خطأ!</strong> '.$error.'</div>';
    } else {

        $expDate = date("Y-m-d H:i:s", strtotime("+1 day"));
        $key = md5(time()) . substr(md5(uniqid(rand(),1)),3,10);

        mysqli_query(
            $conn,
            "INSERT INTO password_reset_temp (email, `key`, expDate)
             VALUES ('$email', '$key', '$expDate')"
        );

        $reset_link = "http://localhost/Age-of-Donation/auth/reset-password.php?key=$key&email=$email&action=reset";

        $body = "
            <p>تم إرسال رابط استعادة كلمة المرور.</p>
            <p><a href='$reset_link'>$reset_link</a></p>
        ";

        require '../vendor/autoload.php';

        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'a.zahoum8425@uca.ac.ma';
        $mail->Password = 'qezu unae pfrl vxbm';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->CharSet = 'UTF-8';
        $mail->setFrom('a.zahoum8425@uca.ac.ma', 'Age of Donation Support');
        $mail->addAddress($email);
        $mail->isHTML(true);
        $mail->Subject = 'استعادة كلمة المرور';
        $mail->Body = $body;

        if (!$mail->send()) {
            echo '<div class="alert alert-danger"><strong>خطأ!</strong> '.$mail->ErrorInfo.'</div>';
        } else {
            echo '<div class="alert alert-success"><strong>نجاح!</strong> تم إرسال رابط استعادة كلمة المرور.</div>';
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

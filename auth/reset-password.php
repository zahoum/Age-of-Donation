<?php
$conn = mysqli_connect("localhost", "root", "", "age_of_donnation");
if (mysqli_connect_errno()) {
    die("Failed to connect to MySQL: " . mysqli_connect_error());
}

date_default_timezone_set('Africa/Casablanca');
$error = '';
$success = '';
?>
<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إعادة تعيين كلمة المرور - Age of Donation</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
    <style>
        body {
            background: #f5f5f5;
        }
        .panel {
            margin-top: 50px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .panel-heading {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px 10px 0 0;
            padding: 15px;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row">
            <div class="col-md-4 col-md-offset-4">
                <div class="panel panel-default">
                    <div class="panel-heading text-center">
                        <h3>إعادة تعيين كلمة المرور</h3>
                    </div>
                    <div class="panel-body">
                        <?php 
                        // Check if table exists first
                        $table_check = mysqli_query($conn, "SHOW TABLES LIKE 'password_reset_temp'");
                        if (mysqli_num_rows($table_check) == 0) {
                            echo '<div class="alert alert-danger">
                                    <strong>خطأ في النظام!</strong> 
                                    <br>الجدول المطلوب غير موجود. يرجى الاتصال بالدعم الفني.
                                  </div>';
                        } else {
                            // Take the key email and section to send 
                            if(isset($_GET["key"]) && isset($_GET["email"]) && isset($_GET["action"]) && ($_GET["action"] == "reset")) {
                                $key = mysqli_real_escape_string($conn, $_GET["key"]);
                                $email = mysqli_real_escape_string($conn, $_GET["email"]);
                                $curDate = date("Y-m-d H:i:s");
                                
                                // Check if key exists
                                $sel_query = "SELECT * FROM `password_reset_temp` WHERE `key`='$key' AND `email`='$email'";
                                $result = mysqli_query($conn, $sel_query);
                                
                                if (!$result) {
                                    echo '<div class="alert alert-danger">
                                            <strong>خطأ!</strong> خطأ في الاستعلام: ' . mysqli_error($conn) . '
                                          </div>';
                                } else {
                                    $row_count = mysqli_num_rows($result);
                                    
                                    if ($row_count == 0) {
                                        echo '<div class="alert alert-danger">
                                                <strong>خطأ!</strong> رابط غير صالح أو منتهي الصلاحية.
                                              </div>';
                                    } else {
                                        $row_data = mysqli_fetch_assoc($result);
                                        $expDate = $row_data['expDate'];
                                        
                                        if ($expDate >= $curDate) {
                                            ?>
                                            <form method="post" action="" name="update">
                                                <input type="hidden" name="action" value="update"/>
                                                <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
                                                <input type="hidden" name="key" value="<?php echo htmlspecialchars($key); ?>">
                                                
                                                <div class="form-group">
                                                    <label>كلمة المرور الجديدة</label>
                                                    <input type="password" name="pass1" class="form-control" placeholder="أدخل كلمة المرور الجديدة" required />
                                                </div>
                                                <div class="form-group">
                                                    <label>تأكيد كلمة المرور الجديدة</label>
                                                    <input type="password" name="pass2" class="form-control" placeholder="أكد كلمة المرور الجديدة" required />
                                                </div>
                                                <button type="submit" class="btn btn-primary btn-block">
                                                    تحديث كلمة المرور
                                                </button>
                                            </form>
                                            <?php
                                        } else {
                                            echo '<div class="alert alert-danger">
                                                    <strong>خطأ!</strong> انتهت صلاحية رابط إعادة التعيين.
                                                    <br><a href="forgot-password.php" class="btn btn-default btn-sm" style="margin-top:10px;">
                                                        طلب رابط جديد
                                                    </a>
                                                  </div>';
                                        }
                                    }
                                }
                            }
                            
                            // Handle password update
                            if(isset($_POST["email"]) && isset($_POST["action"]) && ($_POST["action"] == "update")) {
                                $pass1 = $_POST["pass1"];
                                $pass2 = $_POST["pass2"];
                                $email = mysqli_real_escape_string($conn, $_POST["email"]);
                                $key = mysqli_real_escape_string($conn, $_POST["key"]);
                                
                                if (empty($pass1) || empty($pass2)) {
                                    echo '<div class="alert alert-danger">الرجاء إدخال كلمة المرور</div>';
                                } elseif ($pass1 != $pass2) {
                                    echo '<div class="alert alert-danger">كلمات المرور غير متطابقة</div>';
                                } elseif (strlen($pass1) < 6) {
                                    echo '<div class="alert alert-danger">كلمة المرور يجب أن تكون 6 أحرف على الأقل</div>';
                                } else {
                                    // Hash the password for security
                                    $hashed_password = password_hash($pass1, PASSWORD_DEFAULT);
                                    
                                    // Update user password
                                    $update_query = "UPDATE users SET password='$hashed_password' WHERE email='$email'";
                                    
                                    if (mysqli_query($conn, $update_query)) {
                                        // Delete the used reset token
                                        mysqli_query($conn, "DELETE FROM `password_reset_temp` WHERE `email`='$email'");
                                        
                                        echo '<div class="alert alert-success">
                                                <strong>تم بنجاح!</strong> تم تحديث كلمة المرور.
                                                <br><br>
                                                <a href="login.php" class="btn btn-primary btn-sm">تسجيل الدخول</a>
                                              </div>';
                                    } else {
                                        echo '<div class="alert alert-danger">
                                                <strong>خطأ!</strong> فشل تحديث كلمة المرور: ' . mysqli_error($conn) . '
                                              </div>';
                                    }
                                }
                            }
                        }
                        ?>
                        <hr>
                        <div class="text-center">
                            <a href="login.php">العودة إلى صفحة تسجيل الدخول</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
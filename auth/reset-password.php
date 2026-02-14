<?php

$conn = mysqli_connect("localhost", "root", "", "age_of_donnation");
if (mysqli_connect_errno()) {
    die("Failed to connect to MySQL: " . mysqli_connect_error());
}

date_default_timezone_set('Africa/Casablanca');
$error = '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-4"></div>
            <div class="col-md-4">
                <h2>إعادة تعيين كلمة المرور</h2>
                <?php 
                // Take the key email and section to send 
                    if(isset($_GET["key"]) && isset($_GET["email"]) && isset($_GET["action"]) && ($_GET["action"] == "reset")) {
                        $key = $_GET["key"];
                        $email  = $_GET["email"];
                        $curDate = date("Y-m-d H:i:s");
                        $sel_query = 'SELECT * FROM `password_reset_temp` WHERE `key`="'.$key.'" AND `email`="'.$email.'"';
                        $result = mysqli_query($conn, $sel_query);
                        $row = mysqli_num_rows($result);

                        if ($row == 0) {
                            echo '<div class="alert alert-danger"><strong>خطأ!</strong> رابط غير صالح.</div>';
                        } else {
                            $row = mysqli_fetch_assoc($result);
                            $expDate = $row['expDate'];
                            if ($expDate >= $curDate) {
                                ?>
                                <h2>إعادة تعيين كلمة المرور</h2>
                                <form method="post" action="" name="update">
                                    <input type="hidden" name="action" value="update" class="form-control"/>
                                    
                                    <div class="form-group">
                                        <label for="password">كلمة المرور الجديدة</label>
                                        <input type="password" name="pass1" value="update" class="form-control" placeholder="كلمة المرور الجديدة"  />
                                    </div>
                                    <div class="form-group">
                                        <label for="password">تأكيد كلمة المرور الجديدة</label>
                                        <input type="password" name="pass2" value="update" class="form-control" placeholder="تأكيد كلمة المرور الجديدة"  />
                                    </div>
                                    <input type="hidden" name="email" value="<?php echo $email;?>">
                                    <input type="submit" id="reset" value="تحديث كلمة المرور" class="btn btn-primary"/>
                                </form>
                                <?php
                            } else {
                                $error.= '<div class="alert alert-danger"><strong>خطأ!</strong> انتهت صلاحية الرابط.</div>';
                            }
                        }if ($error!=""){
                            echo "<div class='error'>".$error."</div><br />";
                        }
                    }
                    if(isset($_POST["email"]) && isset($_POST["action"]) && ($_POST["action"] == "update")) {
                        $error="";
                        $pass1 = mysqli_real_escape_string($conn, $_POST["pass1"]);
                        $pass2 = mysqli_real_escape_string($conn, $_POST["pass2"]);
                        $email = $_POST["email"];
                        $curDate = date("Y-m-d H:i:s");
                        if ($pass1 != $pass2) {
                            $error.= '<div class="alert alert-danger"><strong>خطأ!</strong> كلمات المرور غير متطابقة.</div>';
                        } 
                        if ($error!=""){
                            echo "<div class='error'>".$error."</div><br />";
                        }else{
                            mysqli_query($conn, "UPDATE users SET password='" . $pass1 . "' WHERE email='" . $email . "'");

                            mysqli_query($conn, "DELETE FROM `password_reset_temp` WHERE `email`='" . $email . "'");
                            echo '<div class="alert alert-success"><strong>نجاح!</strong> تم تحديث كلمة المرور بنجاح.</div>';
                        }
                    }
                    ?>
            </div>
            
            <div class="col-md-4"></div>
        </div>
    </div>
</body>
</html>

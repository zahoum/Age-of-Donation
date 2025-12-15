<?php
// auth/logout.php
session_start();

// تأكد أن ملف database.php موجود في المسار الصحيح
require_once '../config/database.php';

// إغلاق الجلسة بشكل صحيح
$_SESSION = array();

// إذا كنت تريد تدمير الجلسة تمامًا
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();

// إعادة التوجيه لصفحة تسجيل الدخول
header('Location: login.php');
exit();
?>
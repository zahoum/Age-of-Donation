<?php
// config/database.php

// بدء الجلسة فقط إذا لم تكن قد بدأت
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

class Database {
    private $host = 'localhost';
    private $dbname = 'age_of_donnation';
    private $username = 'root';
    private $password = '';
    public $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->dbname, $this->username, $this->password);
            $this->conn->exec("set names utf8");
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $exception) {
            echo "Erreur de connexion: " . $exception->getMessage();
        }
        return $this->conn;
    }
}

function redirect($url) {
    header("Location: $url");
    exit();
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function getUserType() {
    return $_SESSION['user_type'] ?? null;
}

function checkAuth($allowed_types = []) {
    if (!isLoggedIn()) {
        redirect('../auth/login.php');
    }
    
    if (!empty($allowed_types)) {
        $user_type = getUserType();
        if (!in_array($user_type, $allowed_types)) {
            http_response_code(403);
            die('Accès non autorisé. Vous n\'avez pas les permissions nécessaires. Type utilisateur: ' . $user_type);
        }
    }
}
?>
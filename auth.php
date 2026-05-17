<?php
session_start();
ini_set('display_errors', 0);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}

$login = trim($_POST['login'] ?? '');
$password = $_POST['password'] ?? '';

$host = 'localhost';
$dbname = 'u82360';
$username = 'u82360';
$dbpassword = '8271934';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $dbpassword);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Защита от SQL Injection через prepare
    $stmt = $pdo->prepare("SELECT id, password_hash FROM applications WHERE login = ?");
    $stmt->execute([$login]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user_id'] = $user['id'];
        header('Location: index.php');
    } else {
        header('Location: login.php?error=Неверный логин или пароль');
    }
} catch (PDOException $e) {
    error_log($e->getMessage());
    header('Location: login.php?error=Ошибка базы данных');
}
?>

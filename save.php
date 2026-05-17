<?php
session_start();

// Защита от Information Disclosure
ini_set('display_errors', 0);
ini_set('log_errors', 1);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

// Защита от CSRF
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    die("Ошибка CSRF: неверный токен");
}

$host = 'localhost';
$dbname = 'u82360';
$username = 'u82360';
$password = '8271934';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    error_log($e->getMessage());
    die("Ошибка подключения к БД");
}

$full_name = trim($_POST['full_name'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$email = trim($_POST['email'] ?? '');
$birth_date = $_POST['birth_date'] ?? '';
$gender = $_POST['gender'] ?? '';
$languages = $_POST['languages'] ?? [];
$biography = trim($_POST['biography'] ?? '');
$contract = isset($_POST['contract']) ? 1 : 0;

$errors = [];

// Валидация с защитой от XSS (все данные будут экранированы при выводе)
if (!preg_match('/^[a-zA-Zа-яА-ЯёЁ\s\-]{1,150}$/u', $full_name)) {
    $errors['full_name'] = "ФИО должно содержать только буквы, пробелы и дефисы";
}
if (!preg_match('/^[\+\d\s\-\(\)]{5,20}$/', $phone)) {
    $errors['phone'] = "Неверный формат телефона";
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = "Неверный email";
}
if (!$birth_date || $birth_date > date('Y-m-d')) {
    $errors['birth_date'] = "Неверная дата рождения";
}
if (!in_array($gender, ['male', 'female', 'other'])) {
    $errors['gender'] = "Выберите пол";
}
$allowedLangs = ['Pascal','C','C++','JavaScript','PHP','Python','Java','Haskel','Clojure','Prolog','Scala','Go'];
if (empty($languages)) {
    $errors['languages'] = "Выберите хотя бы один язык";
}
if (!$contract) {
    $errors['contract'] = "Подтвердите контракт";
}

if (!empty($errors)) {
    $_SESSION['form_errors'] = $errors;
    $params = http_build_query($_POST);
    header("Location: index.php?$params");
    exit;
}

function generateLogin($name) {
    $translit = [
        'а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ё'=>'e',
        'ж'=>'zh','з'=>'z','и'=>'i','й'=>'y','к'=>'k','л'=>'l','м'=>'m',
        'н'=>'n','о'=>'o','п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u',
        'ф'=>'f','х'=>'h','ц'=>'ts','ч'=>'ch','ш'=>'sh','щ'=>'sch','ъ'=>'',
        'ы'=>'y','ь'=>'','э'=>'e','ю'=>'yu','я'=>'ya'
    ];
    $name = strtr($name, $translit);
    $name = preg_replace('/[^a-zA-Z0-9]/', '', $name);
    $name = strtolower(substr($name, 0, 20));
    return $name . rand(100, 9999);
}

function generatePassword() {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    return substr(str_shuffle($chars), 0, 10);
}

try {
    $pdo->beginTransaction();
    
    $isEdit = isset($_SESSION['user_id']);
    $generatedLogin = '';
    $generatedPass = '';
    $appId = null;
    
    if ($isEdit) {
        // Защита от SQL Injection через prepare
        $stmt = $pdo->prepare("UPDATE applications SET 
            full_name = ?, phone = ?, email = ?, birth_date = ?, 
            gender = ?, biography = ?, contract_agreed = ? 
            WHERE id = ?");
        $stmt->execute([$full_name, $phone, $email, $birth_date, $gender, $biography, $contract, $_SESSION['user_id']]);
        $appId = $_SESSION['user_id'];
        $pdo->prepare("DELETE FROM application_languages WHERE application_id = ?")->execute([$appId]);
    } else {
        $generatedLogin = generateLogin($full_name);
        $generatedPass = generatePassword();
        $hashedPassword = password_hash($generatedPass, PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("INSERT INTO applications 
            (login, password_hash, full_name, phone, email, birth_date, gender, biography, contract_agreed) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$generatedLogin, $hashedPassword, $full_name, $phone, $email, $birth_date, $gender, $biography, $contract]);
        $appId = $pdo->lastInsertId();
    }
    
    // Сохраняем языки (защита от SQL Injection через prepare)
    if ($appId && !empty($languages)) {
        $placeholders = implode(',', array_fill(0, count($languages), '?'));
        $stmtLang = $pdo->prepare("SELECT id, name FROM programming_languages WHERE name IN ($placeholders)");
        $stmtLang->execute($languages);
        $langMap = $stmtLang->fetchAll(PDO::FETCH_KEY_PAIR);
        
        $stmtRel = $pdo->prepare("INSERT INTO application_languages (application_id, language_id) VALUES (?, ?)");
        foreach ($languages as $langName) {
            if (isset($langMap[$langName])) {
                $stmtRel->execute([$appId, $langMap[$langName]]);
            }
        }
    }
    
    $pdo->commit();
    
    $successData = [
        'full_name' => $full_name, 'phone' => $phone, 'email' => $email,
        'birth_date' => $birth_date, 'gender' => $gender,
        'languages' => implode(',', $languages), 'biography' => $biography, 'contract' => $contract
    ];
    setcookie('saved_form_data', json_encode($successData), time() + 365*24*3600, '/');
    
    if ($isEdit) {
        header("Location: index.php?status=success");
    } else {
        header("Location: index.php?status=success&login=" . urlencode($generatedLogin) . "&pass=" . urlencode($generatedPass));
    }
    exit;
    
} catch (Exception $e) {
    $pdo->rollBack();
    error_log($e->getMessage());
    $_SESSION['form_errors'] = ['database' => "Ошибка сохранения данных"];
    header("Location: index.php");
    exit;
}
?>

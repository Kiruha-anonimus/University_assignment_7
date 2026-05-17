<?php
session_start();

// Защита от Information Disclosure
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Генерация CSRF-токена
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$isLoggedIn = isset($_SESSION['user_id']);
$userData = [];

if ($isLoggedIn) {
    $host = 'localhost';
    $dbname = 'u82360';
    $username = 'u82360';
    $password = '8271934';
    
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        // Защита от SQL Injection
        $stmt = $pdo->prepare("SELECT * FROM applications WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $userData = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log($e->getMessage());
    }
}

$savedData = [];
if (isset($_COOKIE['saved_form_data'])) {
    $savedData = json_decode($_COOKIE['saved_form_data'], true);
}

$errors = isset($_SESSION['form_errors']) ? $_SESSION['form_errors'] : [];
unset($_SESSION['form_errors']);
$status = $_GET['status'] ?? '';
$generatedLogin = $_GET['login'] ?? '';
$generatedPass = $_GET['pass'] ?? '';
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Анкета разработчика - Задание 7</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 40px 20px;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .form-content { padding: 30px; }
        .form-group { margin-bottom: 25px; }
        label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: #333;
        }
        input, select, textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 16px;
        }
        input.error-input, select.error-input, textarea.error-input {
            border-color: #c00;
            background-color: #fff0f0;
        }
        .radio-group {
            display: flex;
            gap: 20px;
            margin-top: 8px;
        }
        .radio-group label {
            display: flex;
            align-items: center;
            font-weight: normal;
            gap: 8px;
        }
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .error-message {
            background: #fee;
            color: #c00;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #c00;
        }
        .success-message {
            background: #e8f5e9;
            color: #2e7d32;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #2e7d32;
        }
        .field-error {
            color: #c00;
            font-size: 14px;
            margin-top: 5px;
        }
        button {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 14px 30px;
            font-size: 18px;
            border-radius: 8px;
            cursor: pointer;
            width: 100%;
        }
        select[multiple] { min-height: 150px; }
        small { color: #666; font-size: 12px; }
        .auth-bar {
            background: #f0f0f0;
            padding: 10px 20px;
            text-align: right;
            border-bottom: 1px solid #ddd;
        }
        .auth-bar a { color: #667eea; text-decoration: none; }
    </style>
</head>
<body>
<div class="container">
    <div class="auth-bar">
        <?php if ($isLoggedIn): ?>
            Вы вошли как <?= htmlspecialchars($userData['login'] ?? '', ENT_QUOTES, 'UTF-8') ?> | 
            <a href="logout.php">Выйти</a>
        <?php else: ?>
            <a href="login.php">Войти</a> (для редактирования)
        <?php endif; ?>
    </div>
    <div class="header">
        <h1>📝 Анкета разработчика - Защищённая версия</h1>
        <p>Заполните форму - при первой отправке получите логин и пароль</p>
    </div>
    <div class="form-content">
        
        <?php if ($status === 'success' && $generatedLogin && $generatedPass): ?>
            <div class="success-message">
                ✅ Данные успешно сохранены!<br>
                <strong>Ваш логин: <?= htmlspecialchars($generatedLogin, ENT_QUOTES, 'UTF-8') ?></strong><br>
                <strong>Ваш пароль: <?= htmlspecialchars($generatedPass, ENT_QUOTES, 'UTF-8') ?></strong><br>
                Сохраните их для редактирования анкеты!
            </div>
        <?php elseif ($status === 'success'): ?>
            <div class="success-message">✅ Данные успешно сохранены!</div>
        <?php endif; ?>
        
        <?php if (!empty($errors)): ?>
            <div class="error-message">
                <strong>❌ Исправьте следующие ошибки:</strong>
                <ul style="margin-top: 10px; margin-left: 20px;">
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <form action="save.php" method="POST">
            <!-- CSRF токен -->
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            
            <div class="form-group">
                <label>ФИО *</label>
                <input type="text" name="full_name" 
                       class="<?= isset($errors['full_name']) ? 'error-input' : '' ?>"
                       value="<?= htmlspecialchars($userData['full_name'] ?? $savedData['full_name'] ?? $_GET['full_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                <small>Допустимы: буквы, пробелы, дефисы</small>
            </div>
            
            <div class="form-group">
                <label>Телефон *</label>
                <input type="tel" name="phone" 
                       class="<?= isset($errors['phone']) ? 'error-input' : '' ?>"
                       value="<?= htmlspecialchars($userData['phone'] ?? $savedData['phone'] ?? $_GET['phone'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                <small>Допустимы: цифры, +, -, пробелы, скобки</small>
            </div>
            
            <div class="form-group">
                <label>E-mail *</label>
                <input type="email" name="email" 
                       class="<?= isset($errors['email']) ? 'error-input' : '' ?>"
                       value="<?= htmlspecialchars($userData['email'] ?? $savedData['email'] ?? $_GET['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                <small>Формат: user@example.com</small>
            </div>
            
            <div class="form-group">
                <label>Дата рождения *</label>
                <input type="date" name="birth_date" 
                       class="<?= isset($errors['birth_date']) ? 'error-input' : '' ?>"
                       value="<?= htmlspecialchars($userData['birth_date'] ?? $savedData['birth_date'] ?? $_GET['birth_date'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            </div>
            
            <div class="form-group">
                <label>Пол *</label>
                <div class="radio-group">
                    <label><input type="radio" name="gender" value="male" <?= (($userData['gender'] ?? $savedData['gender'] ?? $_GET['gender'] ?? '') === 'male') ? 'checked' : '' ?>> Мужской</label>
                    <label><input type="radio" name="gender" value="female" <?= (($userData['gender'] ?? $savedData['gender'] ?? $_GET['gender'] ?? '') === 'female') ? 'checked' : '' ?>> Женский</label>
                    <label><input type="radio" name="gender" value="other" <?= (($userData['gender'] ?? $savedData['gender'] ?? $_GET['gender'] ?? '') === 'other') ? 'checked' : '' ?>> Другой</label>
                </div>
            </div>
            
            <div class="form-group">
                <label>Языки программирования *</label>
                <select name="languages[]" multiple size="6">
                    <?php
                    $langs = ['Pascal','C','C++','JavaScript','PHP','Python','Java','Haskel','Clojure','Prolog','Scala','Go'];
                    $selectedLangs = explode(',', $userData['languages'] ?? $savedData['languages'] ?? $_GET['languages'] ?? '');
                    foreach ($langs as $lang): ?>
                        <option value="<?= htmlspecialchars($lang, ENT_QUOTES, 'UTF-8') ?>" 
                            <?= in_array($lang, $selectedLangs) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($lang, ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <small>Зажмите Ctrl для выбора нескольких</small>
            </div>
            
            <div class="form-group">
                <label>Биография</label>
                <textarea name="biography" rows="5"><?= htmlspecialchars($userData['biography'] ?? $savedData['biography'] ?? $_GET['biography'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
            </div>
            
            <div class="form-group">
                <div class="checkbox-group">
                    <input type="checkbox" name="contract" value="1" required id="contract"
                           <?= (($userData['contract_agreed'] ?? $savedData['contract'] ?? $_GET['contract'] ?? '') == 1) ? 'checked' : '' ?>>
                    <label for="contract">Я ознакомлен(а) с контрактом *</label>
                </div>
            </div>
            
            <button type="submit">💾 Сохранить</button>
        </form>
    </div>
</div>
</body>
</html>

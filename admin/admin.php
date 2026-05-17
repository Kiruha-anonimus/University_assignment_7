<?php
session_start();
$host = 'localhost';
$dbname = 'u82360';
$username = 'u82360';
$password = '8271934';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Ошибка подключения: " . $e->getMessage());
}

// Обработка удаления
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM applications WHERE id = ?");
    $stmt->execute([$id]);
    header("Location: admin.php");
    exit;
}

// Обработка редактирования
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_id'])) {
    $id = (int)$_POST['edit_id'];
    $full_name = trim($_POST['full_name']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $birth_date = $_POST['birth_date'];
    $gender = $_POST['gender'];
    $biography = trim($_POST['biography']);
    $contract_agreed = isset($_POST['contract_agreed']) ? 1 : 0;
    
    $stmt = $pdo->prepare("UPDATE applications SET 
        full_name = ?, phone = ?, email = ?, birth_date = ?, 
        gender = ?, biography = ?, contract_agreed = ? WHERE id = ?");
    $stmt->execute([$full_name, $phone, $email, $birth_date, $gender, $biography, $contract_agreed, $id]);
    
    // Обновляем языки
    $languages = $_POST['languages'] ?? [];
    $pdo->prepare("DELETE FROM application_languages WHERE application_id = ?")->execute([$id]);
    
    $placeholders = implode(',', array_fill(0, count($languages), '?'));
    $stmtLang = $pdo->prepare("SELECT id, name FROM programming_languages WHERE name IN ($placeholders)");
    $stmtLang->execute($languages);
    $langMap = $stmtLang->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $stmtRel = $pdo->prepare("INSERT INTO application_languages (application_id, language_id) VALUES (?, ?)");
    foreach ($languages as $langName) {
        if (isset($langMap[$langName])) {
            $stmtRel->execute([$id, $langMap[$langName]]);
        }
    }
    
    header("Location: admin.php");
    exit;
}

// Получаем всех пользователей
$users = $pdo->query("SELECT * FROM applications ORDER BY id DESC")->fetchAll();

// Статистика по языкам
$langStats = $pdo->query("
    SELECT pl.name, COUNT(al.language_id) as count 
    FROM programming_languages pl
    LEFT JOIN application_languages al ON pl.id = al.language_id
    GROUP BY pl.id
    ORDER BY count DESC
")->fetchAll();

$editUser = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM applications WHERE id = ?");
    $stmt->execute([$editId]);
    $editUser = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($editUser) {
        $langStmt = $pdo->prepare("SELECT pl.name FROM application_languages al 
            JOIN programming_languages pl ON al.language_id = pl.id 
            WHERE al.application_id = ?");
        $langStmt->execute([$editId]);
        $editUser['languages'] = $langStmt->fetchAll(PDO::FETCH_COLUMN);
    }
}

$langsList = $pdo->query("SELECT name FROM programming_languages ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Панель администратора</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #1a1a1a;
            min-height: 100vh;
            padding: 40px 20px;
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        .header {
            background: #000;
            color: white;
            padding: 30px;
            text-align: center;
        }
        .content { padding: 30px; }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
        }
        th {
            background: #000;
            color: white;
        }
        .stats {
            background: #f4f4f4;
            padding: 20px;
            margin-bottom: 30px;
            border-left: 5px solid #000;
        }
        .stats h3 { margin-bottom: 15px; }
        .stats ul { list-style: none; }
        .stats li { padding: 5px 0; }
        .btn {
            display: inline-block;
            padding: 5px 10px;
            background: #000;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 2px;
        }
        .btn-danger { background: #c00; }
        .btn-edit { background: #444; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        select[multiple] { min-height: 100px; }
        .admin-link {
            background: #000;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            display: inline-block;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>👑 Панель администратора</h1>
        <p>Управление данными пользователей</p>
    </div>
    <div class="content">
        <a href="admin.php" class="admin-link">← Назад к списку</a>
        
        <?php if ($editUser): ?>
            <h2>✏️ Редактирование: <?= htmlspecialchars($editUser['full_name']) ?></h2>
            <form method="POST">
                <input type="hidden" name="edit_id" value="<?= $editUser['id'] ?>">
                <div class="form-group">
                    <label>ФИО</label>
                    <input type="text" name="full_name" value="<?= htmlspecialchars($editUser['full_name']) ?>" required>
                </div>
                <div class="form-group">
                    <label>Телефон</label>
                    <input type="text" name="phone" value="<?= htmlspecialchars($editUser['phone']) ?>" required>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" value="<?= htmlspecialchars($editUser['email']) ?>" required>
                </div>
                <div class="form-group">
                    <label>Дата рождения</label>
                    <input type="date" name="birth_date" value="<?= $editUser['birth_date'] ?>" required>
                </div>
                <div class="form-group">
                    <label>Пол</label>
                    <select name="gender">
                        <option value="male" <?= $editUser['gender'] == 'male' ? 'selected' : '' ?>>Мужской</option>
                        <option value="female" <?= $editUser['gender'] == 'female' ? 'selected' : '' ?>>Женский</option>
                        <option value="other" <?= $editUser['gender'] == 'other' ? 'selected' : '' ?>>Другой</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Языки программирования</label>
                    <select name="languages[]" multiple>
                        <?php foreach ($langsList as $lang): ?>
                            <option value="<?= $lang ?>" <?= in_array($lang, $editUser['languages'] ?? []) ? 'selected' : '' ?>><?= $lang ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Биография</label>
                    <textarea name="biography" rows="4"><?= htmlspecialchars($editUser['biography']) ?></textarea>
                </div>
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="contract_agreed" value="1" <?= $editUser['contract_agreed'] ? 'checked' : '' ?>>
                        Контракт ознакомлен
                    </label>
                </div>
                <button type="submit" class="btn">💾 Сохранить</button>
            </form>
        <?php else: ?>
            <div class="stats">
                <h3>📊 Статистика по языкам программирования</h3>
                <ul>
                    <?php foreach ($langStats as $stat): ?>
                        <li><strong><?= htmlspecialchars($stat['name']) ?>:</strong> <?= $stat['count'] ?> пользователей</li>
                    <?php endforeach; ?>
                </ul>
                <p><strong>Всего пользователей:</strong> <?= count($users) ?></p>
            </div>
            
            <h3>📋 Все пользователи</h3>
            <table>
                <thead>
                    <tr><th>ID</th><th>Логин</th><th>ФИО</th><th>Телефон</th><th>Email</th><th>Действия</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?= $user['id'] ?></td>
                            <td><?= htmlspecialchars($user['login'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($user['full_name']) ?></td>
                            <td><?= htmlspecialchars($user['phone']) ?></td>
                            <td><?= htmlspecialchars($user['email']) ?></td>
                            <td>
                                <a href="admin.php?edit=<?= $user['id'] ?>" class="btn btn-edit">✏️</a>
                                <a href="admin.php?delete=<?= $user['id'] ?>" class="btn btn-danger" onclick="return confirm('Удалить?')">🗑️</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
</body>
</html>

<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/functions.php';
header('Content-Type: text/html; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<h2>Спроба створити підфорум:</h2>";
    
    try {
        $catId = (int)($_POST['cat_id'] ?? 1);
        $name = trim($_POST['name'] ?? 'Тестовий підфорум');
        
        echo "<p>cat_id = $catId</p>";
        echo "<p>name = $name</p>";
        
        // Перевірка категорії
        $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
        $stmt->execute([$catId]);
        $cat = $stmt->fetch();
        if (!$cat) throw new Exception("Категорія #$catId не існує");
        echo "<p>✅ Категорія: {$cat['name']}</p>";
        
        // Спроба вставки
        echo "<p>Виконую INSERT...</p>";
        $stmt = $pdo->prepare("INSERT INTO subforums (category_id, parent_id, name, description, icon, color, sort_order, depth) VALUES (?,?,?,?,?,?,?,?)");
        $stmt->execute([$catId, null, $name, 'Тестовий опис', 'hash', '#5b6ee1', 0, 1]);
        $newId = $pdo->lastInsertId();
        echo "<p style='color:green'>✅ Створено підфорум #$newId</p>";
        
        // Перевірка
        $stmt = $pdo->prepare("SELECT * FROM subforums WHERE id = ?");
        $stmt->execute([$newId]);
        $created = $stmt->fetch();
        echo "<pre>" . print_r($created, true) . "</pre>";
        
    } catch (Throwable $e) {
        echo "<p style='color:red'>❌ Помилка: " . htmlspecialchars($e->getMessage()) . "</p>";
        echo "<pre>Файл: " . $e->getFile() . "\nРядок: " . $e->getLine() . "</pre>";
    }
}
?><!DOCTYPE html>
<html><head><meta charset="utf-8"><style>
body{font-family:monospace;background:#0a0a0b;color:#ebedef;padding:20px;line-height:1.7}
.btn{background:#5b6ee1;color:#fff;padding:10px 20px;border:none;border-radius:8px;cursor:pointer;font-size:14px}
input{padding:10px;background:#1c1d1f;color:#fff;border:1px solid #262729;border-radius:8px;font-family:inherit;margin:5px 0}
pre{background:#1c1d1f;padding:12px;border-radius:6px;font-size:12px;overflow:auto}
</style></head><body>
<h2>Створення підфоруму (тест)</h2>
<form method="post">
    <input type="number" name="cat_id" value="1" placeholder="ID категорії"><br>
    <input type="text" name="name" value="Тестовий підфорум" size="50"><br>
    <button type="submit" class="btn">Створити</button>
</form>
<p style="color:#f87171"><b>Видали цей файл після тесту!</b></p>
</body></html>
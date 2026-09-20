<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html><html><head><meta charset='utf-8'>";
echo "<style>body{font-family:monospace;background:#0a0a0b;color:#ebedef;padding:20px;line-height:1.7}";
echo ".ok{color:#4ade80}.err{color:#f87171}.box{background:#151617;border:1px solid #262729;padding:14px;border-radius:8px;margin:10px 0}";
echo "pre{background:#1c1d1f;padding:10px;border-radius:6px;color:#fca5a5;overflow:auto;font-size:12px}</style></head><body>";
echo "<h2>🔍 Тест сторінок</h2>";

echo "<div class='box'><b>1. functions.php:</b><br>";
try {
    require_once __DIR__ . '/functions.php';
    echo "<span class='ok'>✅ OK</span>";
} catch (Throwable $e) {
    echo "<span class='err'>❌ " . htmlspecialchars($e->getMessage()) . "</span>";
    echo "<pre>Файл: " . $e->getFile() . "\nРядок: " . $e->getLine() . "</pre>";
    echo "</body></html>";
    exit;
}
echo "</div>";

echo "<div class='box'><b>2. Таблиця subforums:</b><br>";
try {
    $rows = $pdo->query("SELECT id, name, category_id, parent_id FROM subforums LIMIT 10")->fetchAll();
    echo "<span class='ok'>✅ Записів: " . count($rows) . "</span><br>";
    foreach ($rows as $r) {
        echo "&nbsp;• #{$r['id']} «{$r['name']}» (кат: {$r['category_id']}, батько: " . ($r['parent_id'] ?? '—') . ")<br>";
    }
} catch (Throwable $e) {
    echo "<span class='err'>❌ " . htmlspecialchars($e->getMessage()) . "</span>";
}
echo "</div>";

echo "<div class='box'><b>3. Таблиця subcategories:</b><br>";
try {
    $rows = $pdo->query("SELECT id, name, category_id FROM subcategories LIMIT 10")->fetchAll();
    echo "<span class='ok'>✅ Записів: " . count($rows) . "</span><br>";
    foreach ($rows as $r) {
        echo "&nbsp;• #{$r['id']} «{$r['name']}» (кат: {$r['category_id']})<br>";
    }
} catch (Throwable $e) {
    echo "<span class='err'>❌ " . htmlspecialchars($e->getMessage()) . "</span>";
}
echo "</div>";

echo "<div class='box'><b>4. Функція getSubforum(1):</b><br>";
try {
    if (function_exists('getSubforum')) {
        $sf = getSubforum(1);
        echo $sf ? "<span class='ok'>✅ Знайдено: {$sf['name']}</span>" : "<span class='err'>❌ null</span>";
    } else {
        echo "<span class='err'>❌ Функція не існує</span>";
    }
} catch (Throwable $e) {
    echo "<span class='err'>❌ " . htmlspecialchars($e->getMessage()) . "</span>";
}
echo "</div>";

echo "<div class='box'><b>5. Функція getSubcategories(1):</b><br>";
try {
    if (function_exists('getSubcategories')) {
        $subs = getSubcategories(1, false);
        echo "<span class='ok'>✅ Знайдено: " . count($subs) . "</span><br>";
        foreach ($subs as $s) {
            echo "&nbsp;• #{$s['id']} «{$s['name']}»<br>";
        }
    } else {
        echo "<span class='err'>❌ Функція не існує</span>";
    }
} catch (Throwable $e) {
    echo "<span class='err'>❌ " . htmlspecialchars($e->getMessage()) . "</span>";
}
echo "</div>";

echo "<div class='box'><b>6. SQL підкатегорій (як у category.php):</b><br>";
try {
    $stmt = $pdo->prepare("SELECT s.*, (SELECT COUNT(*) FROM topics WHERE subcategory_id = s.id AND is_deleted = 0) AS topic_count FROM subcategories s WHERE s.category_id = ? ORDER BY s.sort_order, s.id");
    $stmt->execute([1]);
    $subs = $stmt->fetchAll();
    echo "<span class='ok'>✅ OK: " . count($subs) . "</span>";
} catch (Throwable $e) {
    echo "<span class='err'>❌ " . htmlspecialchars($e->getMessage()) . "</span>";
}
echo "</div>";

echo "<div class='box'><b>7. Колонка topics.subcategory_id:</b><br>";
try {
    $cols = $pdo->query("SHOW COLUMNS FROM topics")->fetchAll(PDO::FETCH_COLUMN);
    echo in_array('subcategory_id', $cols)
        ? "<span class='ok'>✅ Існує</span>"
        : "<span class='err'>❌ НЕМАЄ — додай: ALTER TABLE topics ADD COLUMN subcategory_id INT DEFAULT NULL;</span>";
} catch (Throwable $e) {
    echo "<span class='err'>❌ " . htmlspecialchars($e->getMessage()) . "</span>";
}
echo "</div>";

echo "<div class='box'><b>8. Колонка topics.subforum_id:</b><br>";
try {
    $cols = $pdo->query("SHOW COLUMNS FROM topics")->fetchAll(PDO::FETCH_COLUMN);
    echo in_array('subforum_id', $cols)
        ? "<span class='ok'>✅ Існує</span>"
        : "<span class='err'>❌ НЕМАЄ — додай: ALTER TABLE topics ADD COLUMN subforum_id INT DEFAULT NULL;</span>";
} catch (Throwable $e) {
    echo "<span class='err'>❌ " . htmlspecialchars($e->getMessage()) . "</span>";
}
echo "</div>";

echo "<p class='err'><b>Видали цей файл після перевірки!</b></p>";
echo "</body></html>";
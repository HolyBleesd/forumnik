<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

echo "<!DOCTYPE html><html><head><meta charset='utf-8'>";
echo "<style>body{font-family:monospace;background:#0a0a0b;color:#ebedef;padding:20px;line-height:1.7}";
echo ".ok{color:#4ade80}.err{color:#f87171}.warn{color:#fbbf24}";
echo ".box{background:#151617;border:1px solid #262729;padding:14px;border-radius:8px;margin:10px 0}";
echo "pre{background:#1c1d1f;padding:10px;border-radius:6px;color:#fca5a5;overflow:auto;font-size:12px}</style></head><body>";

echo "<h2>🔍 Діагностика banned.php</h2>";

echo "<div class='box'><b>1. Функції:</b><br>";
try {
    require_once __DIR__ . '/functions.php';
    echo "<span class='ok'>✅ functions.php OK</span><br>";
} catch (Throwable $e) {
    echo "<span class='err'>❌ " . htmlspecialchars($e->getMessage()) . "</span>";
    echo "<pre>Файл: " . $e->getFile() . "\nРядок: " . $e->getLine() . "</pre>";
    echo "</div></body></html>";
    exit;
}

$funcs = ['realDateTime','realDateTimeShort','icon','e','avatarUrl','isUserBanned','currentUser'];
foreach ($funcs as $f) {
    echo function_exists($f)
        ? "<span class='ok'>✅ $f()</span><br>"
        : "<span class='err'>❌ $f() НЕ ІСНУЄ</span><br>";
}
echo "</div>";

echo "<div class='box'><b>2. Таблиця user_punishments:</b><br>";
try {
    $cols = $pdo->query("SHOW COLUMNS FROM user_punishments")->fetchAll(PDO::FETCH_COLUMN);
    echo "<span class='ok'>✅ Таблиця існує</span><br>";
    foreach (['id','user_id','type','reason','issued_by','issued_at','expires_at','is_active','revoked_by','revoked_at'] as $c) {
        echo in_array($c, $cols)
            ? "<span class='ok'>✅ $c</span><br>"
            : "<span class='err'>❌ $c — НЕМАЄ</span><br>";
    }
} catch (Throwable $e) {
    echo "<span class='err'>❌ " . htmlspecialchars($e->getMessage()) . "</span>";
}
echo "</div>";

echo "<div class='box'><b>3. Таблиця appeals:</b><br>";
try {
    $cols = $pdo->query("SHOW COLUMNS FROM appeals")->fetchAll(PDO::FETCH_COLUMN);
    echo "<span class='ok'>✅ Таблиця існує</span><br>";
    foreach (['id','punishment_id','user_id','status','created_at','last_user_msg_at','last_staff_msg_at'] as $c) {
        echo in_array($c, $cols)
            ? "<span class='ok'>✅ $c</span><br>"
            : "<span class='warn'>⚠️ $c — НЕМАЄ (треба додати SQL)</span><br>";
    }
} catch (Throwable $e) {
    echo "<span class='err'>❌ " . htmlspecialchars($e->getMessage()) . "</span>";
}
echo "</div>";

echo "<div class='box'><b>4. Спроба відкрити banned.php (симуляція):</b><br>";
try {
    // Симулюємо: забанений юзер #3
    $_SESSION['banned_user_id'] = 3;
    $_SERVER['REQUEST_URI'] = '/banned.php';

    ob_start();
    // Читаємо banned.php але тільки перші рядки
    $code = file_get_contents(__DIR__ . '/banned.php');
    // Витягуємо PHP-частину до <!DOCTYPE
    $cutPos = strpos($code, '<!DOCTYPE');
    if ($cutPos !== false) {
        $phpPart = substr($code, 0, $cutPos);
        eval('?>' . $phpPart);
        $output = ob_get_clean();
        echo "<span class='ok'>✅ PHP-частина виконана без помилок</span>";
    }
} catch (Throwable $e) {
    if (ob_get_level()) ob_end_clean();
    echo "<span class='err'>❌ " . htmlspecialchars($e->getMessage()) . "</span>";
    echo "<pre>Файл: " . $e->getFile() . "\nРядок: " . $e->getLine() . "</pre>";
}
echo "</div>";

echo "<p class='err'><b>Видали цей файл після перевірки!</b></p>";
echo "</body></html>";
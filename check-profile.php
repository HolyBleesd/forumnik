<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html><html><head><meta charset='utf-8'>";
echo "<style>body{font-family:monospace;background:#0a0a0b;color:#ebedef;padding:20px;line-height:1.7}";
echo ".ok{color:#4ade80}.err{color:#f87171}.warn{color:#fbbf24}";
echo ".box{background:#151617;border:1px solid #262729;padding:14px;border-radius:8px;margin:10px 0}";
echo "pre{background:#1c1d1f;padding:10px;border-radius:6px;color:#fca5a5;overflow:auto;font-size:12px}</style></head><body>";

echo "<h2>🔍 Діагностика profile.php</h2>";

echo "<div class='box'><b>1. functions.php:</b><br>";
try {
    require_once __DIR__ . '/functions.php';
    echo "<span class='ok'>✅ Завантажено</span>";
} catch (Throwable $e) {
    echo "<span class='err'>❌ " . htmlspecialchars($e->getMessage()) . "</span>";
    echo "<pre>Файл: " . $e->getFile() . "\nРядок: " . $e->getLine() . "</pre>";
    echo "</div></body></html>";
    exit;
}
echo "</div>";

echo "<div class='box'><b>2. Обов'язкові функції:</b><br>";
$funcs = ['e','icon','socialIcon','csrf','checkCsrf','currentUser','isLoggedIn','isAdmin',
          'avatarUrl','timeAgo','flash','redirect','getUserRole','getSetting','setSetting',
          'getAllSettings','getActiveSocials','notify','paginate'];
foreach ($funcs as $f) {
    echo function_exists($f)
        ? "<span class='ok'>✅ $f()</span><br>"
        : "<span class='err'>❌ $f() НЕ ІСНУЄ</span><br>";
}
echo "</div>";

echo "<div class='box'><b>3. Підключення БД і отримання юзера:</b><br>";
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([1]);
    $u = $stmt->fetch();
    if ($u) {
        echo "<span class='ok'>✅ Юзер #1: " . htmlspecialchars($u['username']) . "</span><br>";
    } else {
        echo "<span class='warn'>⚠️ Юзер #1 не знайдено</span>";
    }
} catch (Throwable $e) {
    echo "<span class='err'>❌ " . htmlspecialchars($e->getMessage()) . "</span>";
}
echo "</div>";

echo "<div class='box'><b>4. Таблиця settings:</b><br>";
try {
    $r = $pdo->query("SELECT COUNT(*) FROM settings")->fetchColumn();
    echo "<span class='ok'>✅ Записів: $r</span>";
} catch (Throwable $e) {
    echo "<span class='err'>❌ " . htmlspecialchars($e->getMessage()) . "</span>";
}
echo "</div>";

echo "<div class='box'><b>5. Функція getUserRole():</b><br>";
try {
    $role = getUserRole(['id' => 1, 'role_id' => 3, 'role' => 'admin']);
    echo $role 
        ? "<span class='ok'>✅ Роль: " . htmlspecialchars($role['name']) . "</span>"
        : "<span class='warn'>⚠️ null</span>";
} catch (Throwable $e) {
    echo "<span class='err'>❌ " . htmlspecialchars($e->getMessage()) . "</span>";
}
echo "</div>";

echo "<div class='box'><b>6. Спроба require profile.php:</b><br>";
try {
    $_GET['id'] = 1;
    ob_start();
    require __DIR__ . '/profile.php';
    $output = ob_get_clean();
    echo "<span class='ok'>✅ profile.php виконано (довжина виводу: " . strlen($output) . " символів)</span>";
} catch (Throwable $e) {
    echo "<span class='err'>❌ " . htmlspecialchars($e->getMessage()) . "</span>";
    echo "<pre>Файл: " . $e->getFile() . "\nРядок: " . $e->getLine() . "\n\n";
    echo $e->getTraceAsString() . "</pre>";
}
echo "</div>";

echo "<p class='err'><b>Видали цей файл після перевірки!</b></p>";
echo "</body></html>";
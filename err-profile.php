<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

echo "<!DOCTYPE html><html><head><meta charset='utf-8'>";
echo "<style>body{font-family:monospace;background:#0a0a0b;color:#ebedef;padding:20px;line-height:1.7}";
echo ".ok{color:#4ade80}.err{color:#f87171}";
echo ".box{background:#151617;border:1px solid #262729;padding:14px;border-radius:8px;margin:10px 0}";
echo "pre{background:#1c1d1f;padding:10px;border-radius:6px;color:#fca5a5;overflow:auto;font-size:12px}</style></head><body>";

echo "<h2>🔍 Перевірка функцій для модалки</h2>";

require_once __DIR__ . '/functions.php';

echo "<div class='box'><b>Функції, які використовує нова модалка:</b><br>";
$funcs = ['getUserRole', 'getRolePermissions', 'hasPermission', 'canInCategory', 'canAccessAdmin', 'getUserPunishments'];
foreach ($funcs as $f) {
    echo function_exists($f)
        ? "<span class='ok'>✅ $f()</span><br>"
        : "<span class='err'>❌ $f() — НЕ ІСНУЄ → ЦЕ ПРИЧИНА 500</span><br>";
}
echo "</div>";

echo "<div class='box'><b>Перевірка PHP-синтаксису profile.php:</b><br>";
$file = __DIR__ . '/profile.php';
if (!file_exists($file)) {
    echo "<span class='err'>❌ profile.php не знайдено</span>";
} else {
    // Читаємо і шукаємо типові проблеми
    $content = file_get_contents($file);
    
    // Підрахунок дужок
    $open = substr_count($content, '{');
    $close = substr_count($content, '}');
    echo "{ = $open<br>} = $close<br>";
    echo $open === $close 
        ? "<span class='ok'>✅ Дужки збалансовані</span>" 
        : "<span class='err'>❌ Дужки НЕ збалансовані! Різниця: " . ($open - $close) . "</span>";
    echo "<br>";
    
    // Підрахунок <?php
    $phpOpen = substr_count($content, '<?php');
    $phpClose = substr_count($content, '?>');
    echo "&lt;?php = $phpOpen, ?&gt; = $phpClose<br>";
    
    // Перші 20 рядків
    echo "<br><b>Перші 20 рядків:</b><br><pre>";
    $lines = explode("\n", $content);
    for ($i = 0; $i < min(20, count($lines)); $i++) {
        echo ($i + 1) . ": " . htmlspecialchars($lines[$i]) . "\n";
    }
    echo "</pre>";
}
echo "</div>";

echo "<p class='err'><b>Видали цей файл після перевірки!</b></p>";
echo "</body></html>";
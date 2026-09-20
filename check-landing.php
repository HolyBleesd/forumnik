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

echo "<h2>🔍 Діагностика landing.php</h2>";

echo "<div class='box'><b>1. functions.php:</b><br>";
try {
    require_once __DIR__ . '/functions.php';
    echo "<span class='ok'>✅ OK</span>";
} catch (Throwable $e) {
    echo "<span class='err'>❌ " . htmlspecialchars($e->getMessage()) . "</span>";
    echo "<pre>Файл: " . $e->getFile() . "\nРядок: " . $e->getLine() . "</pre>";
    echo "</div></body></html>";
    exit;
}
echo "</div>";

echo "<div class='box'><b>2. Функції, які потрібні landing.php:</b><br>";
$funcs = ['getSetting', 'policeSVG', 'gangsterSVG', 'socialIcon', 'icon', 'e'];
foreach ($funcs as $f) {
    echo function_exists($f)
        ? "<span class='ok'>✅ $f()</span><br>"
        : "<span class='err'>❌ $f() НЕ ІСНУЄ</span><br>";
}
echo "</div>";

echo "<div class='box'><b>3. Перевірка на дублікати в landing.php:</b><br>";
$content = file_get_contents(__DIR__ . '/landing.php');
$hasPolice = strpos($content, 'function policeSVG') !== false;
$hasGang   = strpos($content, 'function gangsterSVG') !== false;

echo $hasPolice
    ? "<span class='err'>❌ landing.php містить 'function policeSVG' — ЦЕ СПРИЧИНЯЄ 500!</span><br>"
    : "<span class='ok'>✅ Немає 'function policeSVG'</span><br>";

echo $hasGang
    ? "<span class='err'>❌ landing.php містить 'function gangsterSVG' — ЦЕ СПРИЧИНЯЄ 500!</span><br>"
    : "<span class='ok'>✅ Немає 'function gangsterSVG'</span><br>";

echo "</div>";

echo "<div class='box'><b>4. Спроба виконати landing.php:</b><br>";
try {
    ob_start();
    require __DIR__ . '/landing.php';
    $output = ob_get_clean();
    echo "<span class='ok'>✅ landing.php виконано (довжина: " . strlen($output) . ")</span>";
} catch (Throwable $e) {
    echo "<span class='err'>❌ " . htmlspecialchars($e->getMessage()) . "</span>";
    echo "<pre>Файл: " . $e->getFile() . "\nРядок: " . $e->getLine() . "\n\n";
    echo $e->getTraceAsString() . "</pre>";
}
echo "</div>";

echo "<p class='err'><b>Видали цей файл після перевірки!</b></p>";
echo "</body></html>";
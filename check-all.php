<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

echo "<!DOCTYPE html><html><head><meta charset='utf-8'>";
echo "<style>body{font-family:monospace;background:#0a0a0b;color:#ebedef;padding:20px;line-height:1.7}";
echo ".ok{color:#4ade80}.err{color:#f87171}.warn{color:#fbbf24}";
echo ".box{background:#151617;border:1px solid #262729;padding:14px;border-radius:8px;margin:10px 0}";
echo "pre{background:#1c1d1f;padding:10px;border-radius:6px;color:#fca5a5;overflow:auto;font-size:12px}</style></head><body>";

echo "<h2>🔍 Повна діагностика</h2>";

/* ─── 1. config.php ─── */
echo "<div class='box'><b>1. config.php:</b><br>";
try {
    require_once __DIR__ . '/config.php';
    echo "<span class='ok'>✅ OK</span>";
} catch (Throwable $e) {
    echo "<span class='err'>❌ " . htmlspecialchars($e->getMessage()) . "</span>";
    echo "<pre>Файл: " . $e->getFile() . "\nРядок: " . $e->getLine() . "</pre>";
    echo "</div></body></html>";
    exit;
}
echo "</div>";

/* ─── 2. functions.php ─── */
echo "<div class='box'><b>2. functions.php:</b><br>";
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

/* ─── 3. Функції ─── */
echo "<div class='box'><b>3. Обов'язкові функції:</b><br>";
$funcs = [
    'e','icon','socialIcon','siteLogo','csrf','checkCsrf',
    'currentUser','isLoggedIn','isAdmin','isModerator',
    'avatarUrl','timeAgo','flash','redirect',
    'isUserBanned','isCurrentUserBanned',
    'realDateTime','realDateTimeShort','realDate',
    'getUserRole','getRolePermissions','hasPermission',
    'canInCategory','canInSubforum','canAccessAdmin',
    'getSetting','setSetting','getAllSettings','getActiveSocials',
    'cleanEditorHtml','canEditPost','canViewHistory','canRollbackHistory',
    'savePostHistory','getPostHistory',
    'issuePunishment','revokePunishment','getUserActivePunishments',
    'getUserPunishmentsAll','isUserMuted','canPunish','canReviewAppeals',
    'createAppeal','getAppeal','getAppealMessages',
    'isSubscribed','getSubscribersCount','getFollowingCount','notifySubscribers',
    'getOrCreateDialog','getDialogMessages','sendDm','getUnreadDmCount','getDialogsList',
];
foreach ($funcs as $f) {
    echo function_exists($f)
        ? "<span class='ok'>✅ $f()</span><br>"
        : "<span class='err'>❌ $f() НЕ ІСНУЄ</span><br>";
}
echo "</div>";

/* ─── 4. БД ─── */
echo "<div class='box'><b>4. Таблиці БД:</b><br>";
$tables = ['users','categories','topics','posts','likes','notifications','roles','role_permissions','settings','subforums','subcategories','category_permissions','subforum_permissions','user_punishments','appeals','appeal_messages','subscriptions','dm_dialogs','dm_messages','post_history','faq','server_stats','server_players'];
foreach ($tables as $t) {
    try {
        $pdo->query("SELECT 1 FROM $t LIMIT 1");
        echo "<span class='ok'>✅ $t</span><br>";
    } catch (Throwable $e) {
        echo "<span class='err'>❌ $t — " . htmlspecialchars($e->getMessage()) . "</span><br>";
    }
}
echo "</div>";

/* ─── 5. header.php ─── */
echo "<div class='box'><b>5. Спроба require header.php:</b><br>";
try {
    ob_start();
    $pageTitle = 'Test';
    require __DIR__ . '/header.php';
    $out = ob_get_clean();
    echo "<span class='ok'>✅ header.php виконано (довжина: " . strlen($out) . ")</span>";
} catch (Throwable $e) {
    if (ob_get_level()) ob_end_clean();
    echo "<span class='err'>❌ " . htmlspecialchars($e->getMessage()) . "</span>";
    echo "<pre>Файл: " . $e->getFile() . "\nРядок: " . $e->getLine() . "\n\n";
    echo $e->getTraceAsString() . "</pre>";
}
echo "</div>";

/* ─── 6. login.php синтаксис ─── */
echo "<div class='box'><b>6. Перевірка синтаксису ключових файлів:</b><br>";
$files = ['login.php','register.php','index.php','landing.php','banned.php','appeal.php','profile.php','topic.php','create-topic.php'];
foreach ($files as $f) {
    $path = __DIR__ . '/' . $f;
    if (!file_exists($path)) {
        echo "<span class='warn'>⚠️ $f — не існує</span><br>";
        continue;
    }
    $content = file_get_contents($path);
    $b1 = substr_count($content, '{');
    $b2 = substr_count($content, '}');
    if ($b1 !== $b2) {
        echo "<span class='err'>❌ $f — дужки { } не збалансовані ($b1 vs $b2)</span><br>";
    } else {
        echo "<span class='ok'>✅ $f</span><br>";
    }
}
echo "</div>";

echo "<p class='err'><b>Видали цей файл після перевірки!</b></p>";
echo "</body></html>";
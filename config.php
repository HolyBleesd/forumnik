<?php
define('DB_HOST', 'sql306.infinityfree.com');   // ← ЗАМІНИ на свій!
define('DB_NAME', 'if0_42960914_forumnik');
define('DB_USER', 'if0_42960914');
define('DB_PASS', 'Yc76RYsaSMJxo');

define('SITE_NAME', 'NeverDM');
define('SITE_LOGO', 'https://cdn.creativefabrica.com/2019/03/Monogram-RP-Logo-Design-by-Greenlines-Studios.jpg');
/* ═══ ЖОРСТКО ВКАЖИ СВІЙ ДОМЕН (без слеша в кінці) ═══ */
define('SITE_URL', 'https://forumnik.gt.tc');
/* ═══════════════════════════════════════════════════ */

define('UPLOAD_DIR', __DIR__ . '/uploads/avatars/');
define('UPLOAD_URL', SITE_URL . '/uploads/avatars/');
define('POSTS_PER_PAGE', 15);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('Europe/Kyiv');
mb_internal_encoding('UTF-8');

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER, DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    die('❌ Помилка підключення до БД. Перевір дані в config.php.');
}
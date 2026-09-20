<?php
require_once __DIR__ . '/config.php';

/* ═══════════════════════════════════════════════════════════
   БАЗОВІ УТИЛІТИ
   ═══════════════════════════════════════════════════════════ */

function e($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function isLoggedIn(): bool {
    return !empty($_SESSION['user_id']);
}

function currentUser(): ?array {
    global $pdo;
    static $u = null;
    if ($u !== null) return $u;
    if (!isLoggedIn()) return null;
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $u = $stmt->fetch() ?: null;

        // ❗️ Тепер НЕ вбиваємо скрипт — тільки запамʼятовуємо
        // redirect на banned.php робимо у header.php
        return $u;
    } catch (Exception $ex) {
        return null;
    }
}

/**
 * Чи забанений поточний користувач
 */
function isCurrentUserBanned(): bool {
    $u = currentUser();
    if (!$u) return false;
    return isUserBanned((int)$u['id']) || !empty($u['is_banned']);
}

function isAdmin(): bool {
    $u = currentUser();
    if (!$u) return false;
    return ($u['role'] ?? '') === 'admin';
}

function isModerator(): bool {
    $u = currentUser();
    if (!$u) return false;
    return in_array($u['role'] ?? '', ['admin','moderator'], true);
}

function redirect(string $url) {
    header("Location: $url");
    exit;
}

function timeAgo(string $dt): string {
    $t = strtotime($dt);
    $d = time() - $t;
    if ($d < 60)      return 'щойно';
    if ($d < 3600)    return floor($d/60)   . ' хв тому';
    if ($d < 86400)   return floor($d/3600) . ' год тому';
    if ($d < 604800)  return floor($d/86400). ' дн тому';
    if ($d < 2592000) return floor($d/604800).' тиж тому';
    return date('d.m.Y H:i', $t);
}

function csrf(): string {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function checkCsrf(?string $t): bool {
    return !empty($_SESSION['csrf']) && is_string($t) && hash_equals($_SESSION['csrf'], $t);
}

function flash(?string $msg = null, string $type = 'info') {
    if ($msg !== null) {
        $_SESSION['flash'] = ['msg' => $msg, 'type' => $type];
        return null;
    }
    if (!empty($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }
    return null;
}

function notify(int $userId, string $type, string $msg, string $link = '') {
    global $pdo;
    if ($userId === (int)($_SESSION['user_id'] ?? 0)) return;
    try {
        $pdo->prepare("INSERT INTO notifications (user_id,type,message,link) VALUES (?,?,?,?)")
            ->execute([$userId, $type, $msg, $link]);
    } catch (Exception $e) {}
}

function paginate(int $total, int $perPage, int $current): string {
    $pages = max(1, (int)ceil($total / $perPage));
    if ($pages <= 1) return '';
    $html = '<div class="pagination">';
    $q = $_GET;
    for ($i = 1; $i <= $pages; $i++) {
        $q['page'] = $i;
        $url = '?' . http_build_query($q);
        $cls = $i === $current ? 'active' : '';
        $html .= "<a class=\"$cls\" href=\"" . e($url) . "\">$i</a>";
    }
    return $html . '</div>';
}

function avatarUrl(?array $user): string {
    if (!$user) $user = ['username' => '?', 'avatar' => null];
    if (!empty($user['avatar']) && file_exists(UPLOAD_DIR . $user['avatar'])) {
        return UPLOAD_URL . rawurlencode($user['avatar']);
    }
    $palette = ['#5b6ee1','#8b5cf6','#06b6d4','#10b981','#f59e0b','#ec4899','#6366f1','#14b8a6'];
    $color = $palette[ord($user['username'][0] ?? 'A') % count($palette)];
    $letter = htmlspecialchars(mb_strtoupper(mb_substr($user['username'] ?? '?', 0, 1)));
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="80" height="80">'
         . '<rect width="80" height="80" fill="' . $color . '" rx="40"/>'
         . '<text x="50%" y="50%" fill="#fff" font-family="Inter,Arial" font-size="34" font-weight="600" '
         . 'text-anchor="middle" dy=".35em">' . $letter . '</text></svg>';
    return 'data:image/svg+xml;base64,' . base64_encode($svg);
}

/* ═══════════════════════════════════════════════════════════
   ЛОГОТИП
   ═══════════════════════════════════════════════════════════ */

function siteLogo(int $size = 32): string {
    $url = defined('SITE_LOGO') ? SITE_LOGO : '';
    if (!$url) return icon('zap', $size);

    return '<img src="' . e($url) . '" alt="' . e(SITE_NAME) . '" '
         . 'class="site-logo-img" '
         . 'style="width:' . $size . 'px;height:' . $size . 'px;object-fit:cover;border-radius:8px;display:block;">';
}

/* ═══════════════════════════════════════════════════════════
   SVG ІКОНКИ
   ═══════════════════════════════════════════════════════════ */

function icon(string $name, int $size = 18, string $variant = 'stroke'): string {
    $icons = [
        /* ─── Навігація ─── */
        'home'        => '<path d="M3 9.5 12 3l9 6.5V20a1 1 0 0 1-1 1h-5v-6h-6v6H4a1 1 0 0 1-1-1V9.5Z"/>',
        'arrow-right' => '<path d="M5 12h14M13 5l7 7-7 7"/>',
        'arrow-left'  => '<path d="M19 12H5M11 5l-7 7 7 7"/>',
        'chevron-down'=> '<path d="m6 9 6 6 6-6"/>',

        /* ─── Дії ─── */
        'search'      => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
        'search-x'    => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5M8.5 8.5l5 5M13.5 8.5l-5 5"/>',
        'plus'        => '<path d="M12 5v14M5 12h14"/>',
        'check'       => '<path d="m5 12 5 5L20 7"/>',
        'x'           => '<path d="M6 6l12 12M18 6 6 18"/>',
        'menu'        => '<path d="M3 6h18M3 12h18M3 18h18"/>',
        'edit'        => '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 1 1 3 3L7 19l-4 1 1-4 12.5-12.5Z"/>',
        'send'        => '<path d="M22 2 11 13"/><path d="M22 2 15 22l-4-9-9-4 20-7Z"/>',
        'trash'       => '<path d="M3 6h18M8 6V4a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2M6 6l1 14a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-14"/>',
        'settings'    => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3 1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8 1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1Z"/>',

        /* ─── Користувачі ─── */
        'user'        => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-6 8-6s8 2 8 6"/>',
        'users'       => '<circle cx="9" cy="8" r="4"/><path d="M2 21c0-4 3.5-6 7-6s7 2 7 6"/><path d="M17 4a4 4 0 0 1 0 8M22 21c0-3-2-5-4.5-5.5"/>',
        'logout'      => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/>',
        'shield'      => '<path d="M12 3 4 6v6c0 5 3.5 8 8 9 4.5-1 8-4 8-9V6l-8-3Z"/>',

        /* ─── Контент ─── */
        'message'     => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v10Z"/>',
        'file-text'   => '<path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9Z"/><path d="M14 3v6h6M8 13h8M8 17h5"/>',
        'folder'      => '<path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7Z"/>',
        'bookmark'    => '<path d="M19 21 12 16l-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2Z"/>',
        'bell'        => '<path d="M6 8a6 6 0 0 1 12 0c0 7 3 8 3 8H3s3-1 3-8"/><path d="M10 21a2 2 0 0 0 4 0"/>',

        /* ─── Категорії ─── */
        'monitor'     => '<rect x="3" y="4" width="18" height="13" rx="2"/><path d="M8 21h8M12 17v4"/>',
        'gamepad'     => '<path d="M6 12h4M8 10v4M15 13h.01M18 11h.01"/><rect x="2" y="6" width="20" height="12" rx="6"/>',
        'music'       => '<path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/>',
        'dice'        => '<rect x="3" y="3" width="18" height="18" rx="3"/><path d="M8 8h.01M16 8h.01M12 12h.01M8 16h.01M16 16h.01"/>',
        'bulb'        => '<path d="M9 18h6M10 22h4M12 2a7 7 0 0 0-4 12.7c.6.5 1 1.3 1 2.1V18h6v-1.2c0-.8.4-1.6 1-2.1A7 7 0 0 0 12 2Z"/>',
        'flame'       => '<path d="M12 2c1 4 5 6 5 11a5 5 0 0 1-10 0c0-2 1-3 2-4 0 2 1 3 2 3s1-1 1-2c0-3-3-5 0-8Z"/>',
        'star'        => '<path d="m12 2 3 6.5 7 1-5 4.9 1.2 7L12 18l-6.2 3.4L7 14.4 2 9.5l7-1L12 2Z"/>',
        'heart'       => '<path d="M20.8 5.6a5 5 0 0 0-8.8 1.4A5 5 0 0 0 3.2 5.6c-1.8 1.9-1.6 5 .4 7L12 21l8.4-8.4c2-2 2.2-5.1.4-7Z"/>',
        'palette'     => '<circle cx="12" cy="12" r="10"/><circle cx="8" cy="10" r="1.2"/><circle cx="12" cy="7" r="1.2"/><circle cx="16" cy="10" r="1.2"/><circle cx="15" cy="15" r="1.2"/>',
        'camera'      => '<path d="M3 8a2 2 0 0 1 2-2h2l2-3h6l2 3h2a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8Z"/><circle cx="12" cy="13" r="4"/>',
        'film'        => '<rect x="3" y="3" width="18" height="18" rx="2"/><path d="M7 3v18M17 3v18M3 8h4M3 16h4M17 8h4M17 16h4"/>',
        'book'        => '<path d="M4 5a2 2 0 0 1 2-2h13v16H6a2 2 0 0 0-2 2V5Z"/><path d="M4 21a2 2 0 0 1 2-2h13"/>',
        'award'       => '<circle cx="12" cy="9" r="6"/><path d="m9 14-2 8 5-3 5 3-2-8"/>',
        'globe'       => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a15 15 0 0 1 0 18 15 15 0 0 1 0-18Z"/>',
        'bug'         => '<rect x="8" y="6" width="8" height="12" rx="4"/><path d="M8 12H4M20 12h-4M8 8 5 5M16 8l3-3M8 16l-3 3M16 16l3 3"/>',
        'hash'        => '<path d="M4 9h16M4 15h16M10 3 8 21M16 3l-2 18"/>',
        'grid'        => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',

        /* ─── Статуси ─── */
        'pin'         => '<path d="M12 17v5"/><path d="M9 3h6l-1 8 4 3H6l4-3-1-8Z"/>',
        'lock'        => '<rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 1 1 8 0v4"/>',
        'unlock'      => '<rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 7.5-2"/>',
        'eye'         => '<path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>',
        'clock'       => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'trend'       => '<path d="m3 17 6-6 4 4 8-8"/><path d="M14 7h7v7"/>',
        'chart'       => '<path d="M3 3v18h18"/><path d="M7 15v-4M12 15V7M17 15v-7"/>',
        'zap'         => '<path d="m13 2-9 12h7l-1 8 9-12h-7l1-8Z"/>',
        'sparkle'     => '<path d="m12 3 1.9 5.6L19.5 10l-5.6 1.9L12 17.5l-1.9-5.6L4.5 10l5.6-1.4L12 3Z"/>',

        /* ─── Редактор ─── */
        'align-left'   => '<line x1="17" y1="10" x2="3" y2="10"/><line x1="21" y1="6" x2="3" y2="6"/><line x1="21" y1="14" x2="3" y2="14"/><line x1="17" y1="18" x2="3" y2="18"/>',
        'align-center' => '<line x1="18" y1="10" x2="6" y2="10"/><line x1="21" y1="6" x2="3" y2="6"/><line x1="21" y1="14" x2="3" y2="14"/><line x1="18" y1="18" x2="6" y2="18"/>',
        'align-right'  => '<line x1="21" y1="10" x2="7" y2="10"/><line x1="21" y1="6" x2="3" y2="6"/><line x1="21" y1="14" x2="3" y2="14"/><line x1="21" y1="18" x2="7" y2="18"/>',
        'list'         => '<line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><circle cx="3.5" cy="6" r="1"/><circle cx="3.5" cy="12" r="1"/><circle cx="3.5" cy="18" r="1"/>',
        'list-ordered' => '<line x1="10" y1="6" x2="21" y2="6"/><line x1="10" y1="12" x2="21" y2="12"/><line x1="10" y1="18" x2="21" y2="18"/><path d="M4 6h1v4"/><path d="M4 10h2"/><path d="M6 18H4c0-1 2-2 2-3s-1-1.5-2-1"/>',
        'quote'        => '<path d="M3 21c3 0 7-1 7-8V5c0-1.25-.756-2-2-2H4c-1.25 0-2 .75-2 2v6c0 1.25.75 2 2 2h2c0 4-1 6-3 6v2z"/><path d="M15 21c3 0 7-1 7-8V5c0-1.25-.757-2-2-2h-4c-1.25 0-2 .75-2 2v6c0 1.25.75 2 2 2h2c0 4-1 6-3 6v2z"/>',
    ];

    $path = $icons[$name] ?? $icons['hash'];
    $fill = $variant === 'filled' ? 'currentColor' : 'none';
    $stroke = $variant === 'filled' ? 'none' : 'currentColor';

    return '<svg xmlns="http://www.w3.org/2000/svg" width="' . $size . '" height="' . $size . '" '
         . 'viewBox="0 0 24 24" fill="' . $fill . '" stroke="' . $stroke . '" '
         . 'stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" '
         . 'class="ico ico-' . $name . '">' . $path . '</svg>';
}

/* ═══════════════════════════════════════════════════════════
   ФІРМОВІ ІКОНКИ СОЦМЕРЕЖ
   ═══════════════════════════════════════════════════════════ */

function socialIcon(string $name, int $size = 20): string {
    $icons = [
        'discord' => '<path d="M20.317 4.3698a19.7913 19.7913 0 00-4.8851-1.5152.0741.0741 0 00-.0785.0371c-.211.3753-.4447.8648-.6083 1.2495-1.8447-.2762-3.68-.2762-5.4868 0-.1636-.3933-.4058-.8742-.6177-1.2495a.077.077 0 00-.0785-.037 19.7363 19.7363 0 00-4.8852 1.515.0699.0699 0 00-.0321.0277C.5334 9.0458-.319 13.5799.0992 18.0578a.0824.0824 0 00.0312.0561c2.0528 1.5076 4.0413 2.4228 5.9929 3.0294a.0777.0777 0 00.0842-.0276c.4616-.6304.8731-1.2952 1.226-1.9942a.076.076 0 00-.0416-.1057c-.6528-.2476-1.2743-.5495-1.8722-.8923a.077.077 0 01-.0076-.1277c.1258-.0943.2517-.1923.3718-.2914a.0743.0743 0 01.0776-.0105c3.9278 1.7933 8.18 1.7933 12.0614 0a.0739.0739 0 01.0785.0095c.1202.099.246.1981.3728.2924a.077.077 0 01-.0066.1276 12.2986 12.2986 0 01-1.873.8914.0766.0766 0 00-.0407.1067c.3604.698.7719 1.3628 1.225 1.9932a.076.076 0 00.0842.0286c1.961-.6067 3.9495-1.5219 6.0023-3.0294a.077.077 0 00.0313-.0552c.5004-5.177-.8382-9.6739-3.5485-13.6604a.061.061 0 00-.0312-.0286zM8.02 15.3312c-1.1825 0-2.1569-1.0857-2.1569-2.419 0-1.3332.9555-2.4189 2.157-2.4189 1.2108 0 2.1757 1.0952 2.1568 2.419 0 1.3332-.9555 2.4189-2.1569 2.4189zm7.9748 0c-1.1825 0-2.1569-1.0857-2.1569-2.419 0-1.3332.9554-2.4189 2.1569-2.4189 1.2108 0 2.1757 1.0952 2.1568 2.419 0 1.3332-.946 2.4189-2.1568 2.4189Z"/>',
        'telegram' => '<path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/>',
        'youtube' => '<path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/>',
        'tiktok' => '<path d="M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-.99-.32-2.15-.23-3.02.37-.63.41-1.11 1.04-1.36 1.75-.21.51-.15 1.07-.14 1.61.24 1.64 1.82 3.02 3.5 2.87 1.12-.01 2.19-.66 2.77-1.61.19-.33.4-.67.41-1.06.1-1.79.06-3.57.07-5.36.01-4.03-.01-8.05.02-12.07z"/>',
        'vk'      => '<path d="M13.162 18.994c.609 0 .858-.406.851-.915-.031-1.917.714-2.949 2.059-1.604 1.488 1.488 1.796 2.519 3.603 2.519h3.2c.808 0 1.126-.26 1.126-.668 0-.863-1.421-2.386-2.625-3.504-1.686-1.565-1.765-1.602-.313-3.486 1.801-2.339 4.157-5.336 2.073-5.336h-3.981c-.772 0-.828.435-1.103 1.083-.995 2.347-2.886 5.387-3.604 4.922-.751-.485-.407-2.406-.35-5.261.015-.754.011-1.271-1.141-1.539-.629-.145-1.241-.205-1.809-.205-2.273 0-3.841.953-2.95 1.119 1.571.293 1.42 3.692 1.054 5.16-.638 2.556-3.036-2.024-4.035-4.305-.241-.548-.315-.974-1.175-.974H1.21c-.868 0-1.016.403-1.016.814 0 .926 2.483 5.6 5.163 8.523 2.646 2.877 4.875 2.494 6.805 2.494v-.006z"/>',
    ];

    $path = $icons[$name] ?? '';
    if ($path === '') return icon('globe', $size);

    return '<svg xmlns="http://www.w3.org/2000/svg" width="' . $size . '" height="' . $size . '" '
         . 'viewBox="0 0 24 24" fill="currentColor" class="social-ico social-ico-' . $name . '">'
         . $path . '</svg>';
}

/* ═══════════════════════════════════════════════════════════
   НАЛАШТУВАННЯ
   ═══════════════════════════════════════════════════════════ */

function getSetting(string $key, $default = ''): string {
    global $pdo;
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try {
            $rows = $pdo->query("SELECT `key`, `value` FROM settings")->fetchAll();
            foreach ($rows as $r) {
                $cache[$r['key']] = $r['value'];
            }
        } catch (Exception $e) {
            $cache = [];
        }
    }
    return $cache[$key] ?? $default;
}

function setSetting(string $key, string $value): void {
    global $pdo;
    try {
        $pdo->prepare("INSERT INTO settings (`key`, `value`) VALUES (?, ?) 
                       ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)")
            ->execute([$key, $value]);
    } catch (Exception $e) {}
}

function getAllSettings(): array {
    global $pdo;
    try {
        $rows = $pdo->query("SELECT `key`, `value` FROM settings")->fetchAll();
        $out = [];
        foreach ($rows as $r) $out[$r['key']] = $r['value'];
        return $out;
    } catch (Exception $e) {
        return [];
    }
}

function getActiveSocials(): array {
    $networks = [
        'discord'  => ['name' => 'Discord',  'icon' => 'discord'],
        'tiktok'   => ['name' => 'TikTok',   'icon' => 'tiktok'],
        'youtube'  => ['name' => 'YouTube',  'icon' => 'youtube'],
        'telegram' => ['name' => 'Telegram', 'icon' => 'telegram'],
        'vk'       => ['name' => 'VK',       'icon' => 'vk'],
        'website'  => ['name' => 'Сайт',     'icon' => 'globe'],
    ];

    $out = [];
    foreach ($networks as $key => $info) {
        $url = trim(getSetting('social_' . $key, ''));
        if ($url === '') continue;
        $info['url'] = $url;
        $info['key'] = $key;
        $out[] = $info;
    }
    return $out;
}

/* ═══════════════════════════════════════════════════════════
   РОЛІ ТА ДОЗВОЛИ
   ═══════════════════════════════════════════════════════════ */

function getUserRole(?array $user = null): ?array {
    global $pdo;
    if ($user === null) $user = currentUser();
    if (!$user) return null;
    try {
        if (!empty($user['role_id'])) {
            $stmt = $pdo->prepare("SELECT * FROM roles WHERE id = ?");
            $stmt->execute([$user['role_id']]);
            $role = $stmt->fetch();
            if ($role) return $role;
        }
        $stmt = $pdo->prepare("SELECT * FROM roles WHERE slug = ?");
        $stmt->execute([$user['role'] ?? 'user']);
        return $stmt->fetch() ?: null;
    } catch (Exception $e) {
        return null;
    }
}

function getRolePermissions(int $roleId): array {
    global $pdo;
    static $cache = [];
    if (isset($cache[$roleId])) return $cache[$roleId];
    try {
        $stmt = $pdo->prepare("SELECT permission FROM role_permissions WHERE role_id = ?");
        $stmt->execute([$roleId]);
        return $cache[$roleId] = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        return $cache[$roleId] = [];
    }
}

function hasPermission(string $permission, ?array $user = null): bool {
    if ($user === null) $user = currentUser();
    if (!$user) return false;
    $role = getUserRole($user);
    if (!$role) return false;
    if ($role['slug'] === 'admin') return true;
    return in_array($permission, getRolePermissions((int)$role['id']), true);
}

function canInCategory(string $permission, int $categoryId, ?int $subcategoryId = null, ?array $user = null): bool {
    global $pdo;
    if ($user === null) $user = currentUser();
    if (!$user) return false;
    $role = getUserRole($user);
    if (!$role) return false;
    if ($role['slug'] === 'admin') return true;

    try {
        if ($subcategoryId) {
            $stmt = $pdo->prepare("SELECT permission FROM category_permissions WHERE subcategory_id = ? AND role_id = ? AND permission = ?");
            $stmt->execute([$subcategoryId, $role['id'], $permission]);
            if ($stmt->fetch()) return true;

            $stmt = $pdo->prepare("SELECT COUNT(*) FROM category_permissions WHERE subcategory_id = ?");
            $stmt->execute([$subcategoryId]);
            if ((int)$stmt->fetchColumn() > 0) return false;
        }

        $stmt = $pdo->prepare("SELECT permission FROM category_permissions WHERE category_id = ? AND subcategory_id IS NULL AND role_id = ? AND permission = ?");
        $stmt->execute([$categoryId, $role['id'], $permission]);
        if ($stmt->fetch()) return true;

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM category_permissions WHERE category_id = ? AND subcategory_id IS NULL");
        $stmt->execute([$categoryId]);
        if ((int)$stmt->fetchColumn() === 0) {
            return hasPermission($permission, $user);
        }
    } catch (Exception $e) {
        return hasPermission($permission, $user);
    }

    return false;
}

function canInSubforum(string $permission, int $subforumId, ?array $user = null): bool {
    global $pdo;
    if ($user === null) $user = currentUser();
    if (!$user) return false;

    $role = getUserRole($user);
    if (!$role) return false;
    if ($role['slug'] === 'admin') return true;

    try {
        $stmt = $pdo->prepare("SELECT permission FROM subforum_permissions WHERE subforum_id = ? AND role_id = ? AND permission = ?");
        $stmt->execute([$subforumId, $role['id'], $permission]);
        if ($stmt->fetch()) return true;

        $stmt = $pdo->prepare("SELECT * FROM subforums WHERE id = ?");
        $stmt->execute([$subforumId]);
        $sub = $stmt->fetch();

        if ($sub && !empty($sub['parent_id'])) {
            return canInSubforum($permission, (int)$sub['parent_id'], $user);
        }
        if ($sub && !empty($sub['category_id'])) {
            return canInCategory($permission, (int)$sub['category_id']);
        }
    } catch (Exception $e) {
        return hasPermission($permission, $user);
    }

    return hasPermission($permission, $user);
}

function getCategoryPermissions(?int $categoryId, ?int $subcategoryId = null): array {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT cp.*, r.slug AS role_slug, r.name AS role_name, r.color AS role_color
            FROM category_permissions cp
            JOIN roles r ON cp.role_id = r.id
            WHERE " . ($subcategoryId
                ? "cp.subcategory_id = ?"
                : "cp.category_id = ? AND cp.subcategory_id IS NULL") . "
        ");
        $stmt->execute([$subcategoryId ?: $categoryId]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

function getSubcategories(int $categoryId, bool $visibleOnly = true): array {
    global $pdo;
    try {
        $sql = "SELECT s.*,
                  (SELECT COUNT(*) FROM topics WHERE subcategory_id = s.id AND is_deleted = 0) AS topic_count
                FROM subcategories s
                WHERE s.category_id = ?";
        if ($visibleOnly) $sql .= " AND s.is_hidden = 0";
        $sql .= " ORDER BY s.sort_order, s.id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$categoryId]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

function canAccessAdmin(): bool {
    $u = currentUser();
    return $u && in_array($u['role'] ?? '', ['admin','moderator'], true);
}

function canAccessAdminPage(string $page): bool {
    return canAccessAdmin();
}

function isMuted(?array $user = null): bool {
    if ($user === null) $user = currentUser();
    if (!$user) return false;
    if (empty($user['muted_until'])) return false;
    return strtotime($user['muted_until']) > time();
}

function getUserPunishments(int $userId, bool $activeOnly = true): array {
    global $pdo;
    try {
        $sql = "SELECT * FROM user_punishments WHERE user_id = ?";
        if ($activeOnly) $sql .= " AND is_active = 1 AND (expires_at IS NULL OR expires_at > NOW())";
        $sql .= " ORDER BY issued_at DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

function allPermissions(): array {
    return [
        'topic.create'      => ['name' => 'Створювати теми',              'group' => 'Теми'],
        'topic.edit_own'    => ['name' => 'Редагувати свої теми',         'group' => 'Теми'],
        'topic.edit_any'    => ['name' => 'Редагувати чужі теми',         'group' => 'Теми'],
        'topic.delete_own'  => ['name' => 'Видаляти свої теми',           'group' => 'Теми'],
        'topic.delete_any'  => ['name' => 'Видаляти чужі теми',           'group' => 'Теми'],
        'topic.close'       => ['name' => 'Відкривати/закривати теми',    'group' => 'Теми'],
        'topic.pin'         => ['name' => 'Прикріпляти/відкріпляти теми','group' => 'Теми'],
        'topic.move'        => ['name' => 'Переміщувати теми',            'group' => 'Теми'],
        'admin.access'      => ['name' => 'Доступ до адмін-панелі',       'group' => 'Адмін'],
        'admin.dashboard'   => ['name' => '↳ Дашборд',                    'group' => 'Адмін'],
        'admin.users'       => ['name' => '↳ Користувачі',                'group' => 'Адмін'],
        'admin.categories'  => ['name' => '↳ Категорії',                  'group' => 'Адмін'],
        'admin.topics'      => ['name' => '↳ Теми',                       'group' => 'Адмін'],
        'admin.roles'       => ['name' => '↳ Ролі',                       'group' => 'Адмін'],
        'users.edit'        => ['name' => 'Редагувати користувачів',      'group' => 'Користувачі'],
        'users.punish'      => ['name' => 'Видавати покарання',           'group' => 'Користувачі'],
    ];
}

/* ═══════════════════════════════════════════════════════════
   ПІДФОРУМИ
   ═══════════════════════════════════════════════════════════ */

function getSubforum(int $id): ?array {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT * FROM subforums WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    } catch (Exception $e) {
        return null;
    }
}

function getSubforums(int $categoryId, ?int $parentId = null, bool $visibleOnly = true, int $depth = 0, int $maxDepth = 10): array {
    global $pdo;
    if ($depth > $maxDepth) return [];

    try {
        $sql = "SELECT * FROM subforums WHERE category_id = ?";
        $params = [$categoryId];

        if ($parentId === null) {
            $sql .= " AND parent_id IS NULL";
        } else {
            $sql .= " AND parent_id = ?";
            $params[] = $parentId;
        }

        if ($visibleOnly) $sql .= " AND is_hidden = 0";
        $sql .= " ORDER BY sort_order, id";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $items = $stmt->fetchAll();

        foreach ($items as &$item) {
            $item['topic_count'] = getSubforumTopicCount((int)$item['id']);
            $item['children'] = getSubforums($categoryId, (int)$item['id'], $visibleOnly, $depth + 1, $maxDepth);
            $item['has_children'] = count($item['children']) > 0;
        }
        unset($item);

        return $items;
    } catch (Exception $e) {
        return [];
    }
}

function getSubforumTopicCount(int $subforumId, bool $recursive = true): int {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM topics WHERE subforum_id = ? AND is_deleted = 0");
        $stmt->execute([$subforumId]);
        $count = (int)$stmt->fetchColumn();

        if ($recursive) {
            $stmt = $pdo->prepare("SELECT id FROM subforums WHERE parent_id = ?");
            $stmt->execute([$subforumId]);
            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $childId) {
                $count += getSubforumTopicCount((int)$childId, true);
            }
        }
        return $count;
    } catch (Exception $e) {
        return 0;
    }
}

function getSubforumBreadcrumbs(int $subforumId): array {
    $crumbs = [];
    $current = getSubforum($subforumId);
    $safety = 0;

    while ($current && $safety < 20) {
        array_unshift($crumbs, $current);
        if (empty($current['parent_id'])) break;
        $current = getSubforum((int)$current['parent_id']);
        $safety++;
    }

    return $crumbs;
}

function getSubforumPermissions(int $subforumId): array {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT sp.*, r.slug AS role_slug, r.name AS role_name, r.color AS role_color
            FROM subforum_permissions sp
            JOIN roles r ON sp.role_id = r.id
            WHERE sp.subforum_id = ?
        ");
        $stmt->execute([$subforumId]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

function getSubforumTopics(int $subforumId, int $limit = 20, int $offset = 0, bool $includeChildren = false): array {
    global $pdo;
    try {
        $ids = [$subforumId];
        if ($includeChildren) {
            $stmt = $pdo->prepare("SELECT id FROM subforums WHERE parent_id = ?");
            $stmt->execute([$subforumId]);
            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $id) {
                $ids[] = (int)$id;
            }
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("
            SELECT t.*, u.username, u.avatar,
              (SELECT COUNT(*) FROM posts WHERE topic_id = t.id AND is_deleted = 0) AS replies
            FROM topics t
            JOIN users u ON t.user_id = u.id
            WHERE t.subforum_id IN ($placeholders) AND t.is_deleted = 0
            ORDER BY t.is_pinned DESC, t.updated_at DESC
            LIMIT $limit OFFSET $offset
        ");
        $stmt->execute($ids);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

function countSubforums(int $categoryId, bool $visibleOnly = true): int {
    global $pdo;
    try {
        $sql = "SELECT COUNT(*) FROM subforums WHERE category_id = ?";
        if ($visibleOnly) $sql .= " AND is_hidden = 0";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$categoryId]);
        return (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        return 0;
    }
}

function buildSubforumTree(array $items, ?int $parentId = null): array {
    $tree = [];
    foreach ($items as $item) {
        if ((int)($item['parent_id'] ?? 0) === (int)($parentId ?? 0)) {
            $item['children'] = buildSubforumTree($items, (int)$item['id']);
            $tree[] = $item;
        }
    }
    return $tree;
}

/* ═══════════════════════════════════════════════════════════
   СЕРВЕРНА СТАТИСТИКА
   ═══════════════════════════════════════════════════════════ */

function getServerStats(): array {
    global $pdo;
    try {
        $stmt = $pdo->query("SELECT * FROM server_stats WHERE id = 1");
        $row = $stmt->fetch();
        if ($row) return $row;
    } catch (Exception $e) {}
    return [
        'online_count' => 0,
        'max_online' => 0,
        'total_players' => 0,
        'uptime_percent' => 99.90,
        'updated_at' => date('Y-m-d H:i:s'),
    ];
}

function getTopPlayers(int $limit = 10): array {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT * FROM server_players ORDER BY level DESC, kills DESC LIMIT ?");
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

function getFaq(): array {
    global $pdo;
    try {
        return $pdo->query("SELECT * FROM faq ORDER BY sort_order, id")->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

function getServerRules(): array {
    global $pdo;
    try {
        return $pdo->query("SELECT * FROM server_rules ORDER BY category, sort_order, id")->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

function getForumActivity(int $limit = 5): array {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT t.id, t.title, t.created_at, u.username, c.icon AS cat_icon, c.name AS cat_name
            FROM topics t
            JOIN users u ON t.user_id = u.id
            JOIN categories c ON t.category_id = c.id
            WHERE t.is_deleted = 0
            ORDER BY t.created_at DESC
            LIMIT ?
        ");
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

function getForumStats(): array {
    global $pdo;
    try {
        $row = $pdo->query("
            SELECT
              (SELECT COUNT(*) FROM users WHERE is_banned=0) AS users,
              (SELECT COUNT(*) FROM topics WHERE is_deleted=0) AS topics,
              (SELECT COUNT(*) FROM posts WHERE is_deleted=0) AS posts,
              (SELECT COUNT(*) FROM users WHERE last_seen > DATE_SUB(NOW(), INTERVAL 15 MINUTE)) AS online
        ")->fetch();
        return $row ?: ['users'=>0,'topics'=>0,'posts'=>0,'online'=>0];
    } catch (Exception $e) {
        return ['users'=>0,'topics'=>0,'posts'=>0,'online'=>0];
    }
}

/* ═══════════════════════════════════════════════════════════
   ОЧИЩЕННЯ HTML З РЕДАКТОРА
   ═══════════════════════════════════════════════════════════ */

function cleanEditorHtml(string $html): string {
    // Видаляємо скрипти
    $html = preg_replace('#<script\b[^>]*>(.*?)</script>#is', '', $html);
    // Видаляємо on* атрибути
    $html = preg_replace('#\son\w+\s*=\s*"[^"]*"#i', '', $html);
    $html = preg_replace("#\son\w+\s*=\s*'[^']*'#i", '', $html);
    // Видаляємо javascript: у href
    $html = preg_replace('#(href|src)\s*=\s*["\']?\s*javascript:[^"\'>\s]*#i', '$1="#"', $html);
    // Видаляємо iframe крім YouTube
    $html = preg_replace_callback('#<iframe\b[^>]*>.*?</iframe>#is', function($m){
        if (stripos($m[0], 'youtube.com/embed/') !== false || stripos($m[0], 'youtu.be') !== false) {
            return $m[0];
        }
        return '';
    }, $html);
    // Видаляємо зайві атрибути style (залишаємо тільки дозволені)
    return trim($html);
}

/* ═══════════════════════════════════════════════════════════
   ІСТОРІЯ РЕДАГУВАНЬ
   ═══════════════════════════════════════════════════════════ */

function savePostHistory(int $postId, string $before, string $after, int $userId, bool $isRollback = false): void {
    global $pdo;
    if ($before === $after) return;
    try {
        $pdo->prepare("INSERT INTO post_history (post_id, content_before, content_after, edited_by, is_rollback) VALUES (?,?,?,?,?)")
            ->execute([$postId, $before, $after, $userId, $isRollback ? 1 : 0]);

        $pdo->prepare("UPDATE posts SET edited_at = NOW(), edited_count = edited_count + 1 WHERE id = ?")
            ->execute([$postId]);
    } catch (Exception $e) {}
}

function getPostHistory(int $postId): array {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT h.*, u.username, u.avatar
            FROM post_history h
            JOIN users u ON h.edited_by = u.id
            WHERE h.post_id = ?
            ORDER BY h.edited_at DESC
        ");
        $stmt->execute([$postId]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Реальна дата + час (з локальним часовим поясом користувача)
 * Формат: Пн, 20 верес 2025 · 14:32:45
 */
function realDateTime(string $dt): string {
    $t = strtotime($dt);
    $days = ['Нд', 'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб'];
    $months = ['січ', 'лют', 'бер', 'квіт', 'трав', 'черв', 'лип', 'серп', 'вер', 'жовт', 'лист', 'груд'];
    $d = getdate($t);
    return sprintf(
        '%s, %d %s %d · %02d:%02d:%02d',
        $days[$d['wday']],
        $d['mday'],
        $months[$d['mon'] - 1],
        $d['year'],
        $d['hours'],
        $d['minutes'],
        $d['seconds']
    );
}

/**
 * Коротка дата + час
 */
function realDateTimeShort(string $dt): string {
    $t = strtotime($dt);
    return date('d.m.Y H:i', $t);
}

/**
 * Повна дата (без часу)
 */
function realDate(string $dt): string {
    $t = strtotime($dt);
    $days = ['Нд', 'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб'];
    $months = ['січ', 'лют', 'бер', 'квіт', 'трав', 'черв', 'лип', 'серп', 'вер', 'жовт', 'лист', 'груд'];
    $d = getdate($t);
    return sprintf('%s, %d %s %d', $days[$d['wday']], $d['mday'], $months[$d['mon'] - 1], $d['year']);
}

/**
 * Чи може користувач бачити історію
 */
function canViewHistory(?array $user = null): bool {
    if ($user === null) $user = currentUser();
    if (!$user) return false;

    $role = getUserRole($user);
    if (!$role) return false;
    if ($role['slug'] === 'admin') return true;

    return in_array('topic.view_history', getRolePermissions((int)$role['id']), true);
}

/**
 * Чи може користувач відкотити історію (тільки адмін)
 */
function canRollbackHistory(?array $user = null): bool {
    if ($user === null) $user = currentUser();
    if (!$user) return false;

    $role = getUserRole($user);
    return $role && $role['slug'] === 'admin';
}

/**
 * Чи може користувач редагувати цей пост
 */
function canEditPost(int $postAuthorId, ?array $user = null): bool {
    if ($user === null) $user = currentUser();
    if (!$user) return false;

    // Власник — так
    if ((int)$user['id'] === $postAuthorId) return true;

    // Модератор з правом edit_any
    return hasPermission('topic.edit_any', $user);
}

/* ═══════════════════════════════════════════════════════════
   СИСТЕМА ПОКАРАНЬ
   ═══════════════════════════════════════════════════════════ */

function issuePunishment(int $userId, string $type, string $reason, int $issuedBy, ?string $expiresAt = null): int {
    global $pdo;
    if (!in_array($type, ['warn','mute','ban'], true)) throw new Exception('Невірний тип');

    $stmt = $pdo->prepare("INSERT INTO user_punishments (user_id, type, reason, issued_by, expires_at, is_active) VALUES (?,?,?,?,?,1)");
    $stmt->execute([$userId, $type, $reason, $issuedBy, $expiresAt]);
    $id = (int)$pdo->lastInsertId();

    // Синхронізуємо user.is_banned
    if ($type === 'ban') {
        $pdo->prepare("UPDATE users SET is_banned = 1 WHERE id = ?")->execute([$userId]);
    }

    // Сповіщення
    $label = ['warn' => 'попередження', 'mute' => 'мут', 'ban' => 'бан'][$type];
    notify($userId, 'punish', 'Вам видано ' . $label . ': ' . $reason, SITE_URL . '/profile.php?id=' . $userId);

    return $id;
}

function revokePunishment(int $punishmentId, int $byUserId): void {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM user_punishments WHERE id = ?");
    $stmt->execute([$punishmentId]);
    $p = $stmt->fetch();
    if (!$p) return;

    $pdo->prepare("UPDATE user_punishments SET is_active = 0, revoked_by = ?, revoked_at = NOW() WHERE id = ?")
        ->execute([$byUserId, $punishmentId]);

    // Якщо бан і більше немає активних банів — знімаємо
    if ($p['type'] === 'ban') {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_punishments WHERE user_id = ? AND type = 'ban' AND is_active = 1 AND (expires_at IS NULL OR expires_at > NOW())");
        $stmt->execute([(int)$p['user_id']]);
        if ((int)$stmt->fetchColumn() === 0) {
            $pdo->prepare("UPDATE users SET is_banned = 0 WHERE id = ?")->execute([(int)$p['user_id']]);
        }
    }

    notify((int)$p['user_id'], 'punish_revoke', 'З вас знято покарання', SITE_URL . '/profile.php?id=' . (int)$p['user_id']);
}

function getUserActivePunishments(int $userId): array {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT p.*, u.username AS issued_by_name
            FROM user_punishments p
            LEFT JOIN users u ON p.issued_by = u.id
            WHERE p.user_id = ? AND p.is_active = 1
              AND (p.expires_at IS NULL OR p.expires_at > NOW())
            ORDER BY p.issued_at DESC
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    } catch (Exception $e) { return []; }
}

function getUserPunishmentsAll(int $userId): array {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT p.*, u.username AS issued_by_name,
                   r.username AS revoked_by_name
            FROM user_punishments p
            LEFT JOIN users u ON p.issued_by = u.id
            LEFT JOIN users r ON p.revoked_by = r.id
            WHERE p.user_id = ?
            ORDER BY p.issued_at DESC
            LIMIT 100
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    } catch (Exception $e) { return []; }
}

function isUserMuted(int $userId): bool {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_punishments WHERE user_id = ? AND type = 'mute' AND is_active = 1 AND (expires_at IS NULL OR expires_at > NOW())");
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn() > 0;
    } catch (Exception $e) { return false; }
}

function isUserBanned(int $userId): bool {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_punishments WHERE user_id = ? AND type = 'ban' AND is_active = 1 AND (expires_at IS NULL OR expires_at > NOW())");
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn() > 0;
    } catch (Exception $e) { return false; }
}

function canPunish(string $type, ?array $user = null): bool {
    if ($user === null) $user = currentUser();
    if (!$user) return false;
    $role = getUserRole($user);
    if (!$role) return false;
    if ($role['slug'] === 'admin') return true;

    $perm = ['warn' => 'users.warn', 'mute' => 'users.mute', 'ban' => 'users.ban'][$type] ?? null;
    if (!$perm) return false;
    return in_array($perm, getRolePermissions((int)$role['id']), true);
}

function canReviewAppeals(?array $user = null): bool {
    if ($user === null) $user = currentUser();
    if (!$user) return false;
    $role = getUserRole($user);
    if (!$role) return false;
    if ($role['slug'] === 'admin') return true;
    return in_array('appeal.review', getRolePermissions((int)$role['id']), true);
}

/* ═══════════════════════════════════════════════════════════
   АПЕЛЯЦІЇ
   ═══════════════════════════════════════════════════════════ */

function createAppeal(int $punishmentId, int $userId, string $firstMessage): int {
    global $pdo;
    // Перевірка, чи немає відкритої
    $stmt = $pdo->prepare("SELECT id FROM appeals WHERE punishment_id = ? AND status = 'open'");
    $stmt->execute([$punishmentId]);
    $existing = $stmt->fetchColumn();
    if ($existing) return (int)$existing;

    $pdo->prepare("INSERT INTO appeals (punishment_id, user_id) VALUES (?,?)")
        ->execute([$punishmentId, $userId]);
    $aid = (int)$pdo->lastInsertId();

    $pdo->prepare("INSERT INTO appeal_messages (appeal_id, user_id, message) VALUES (?,?,?)")
        ->execute([$aid, $userId, $firstMessage]);

    return $aid;
}

function getAppeal(int $appealId): ?array {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT a.*, p.type AS punish_type, p.reason AS punish_reason,
                   p.expires_at, u.username, u.avatar
            FROM appeals a
            JOIN user_punishments p ON a.punishment_id = p.id
            JOIN users u ON a.user_id = u.id
            WHERE a.id = ?
        ");
        $stmt->execute([$appealId]);
        return $stmt->fetch() ?: null;
    } catch (Exception $e) { return null; }
}

function getAppealMessages(int $appealId, int $since = 0): array {
    global $pdo;
    try {
        if ($since > 0) {
            $stmt = $pdo->prepare("
                SELECT m.*, u.username, u.avatar, u.role
                FROM appeal_messages m
                JOIN users u ON m.user_id = u.id
                WHERE m.appeal_id = ? AND m.id > ?
                ORDER BY m.created_at ASC
            ");
            $stmt->execute([$appealId, $since]);
        } else {
            $stmt = $pdo->prepare("
                SELECT m.*, u.username, u.avatar, u.role
                FROM appeal_messages m
                JOIN users u ON m.user_id = u.id
                WHERE m.appeal_id = ?
                ORDER BY m.created_at ASC
            ");
            $stmt->execute([$appealId]);
        }
        return $stmt->fetchAll();
    } catch (Exception $e) { return []; }
}

/* ═══════════════════════════════════════════════════════════
   ПІДПИСКИ
   ═══════════════════════════════════════════════════════════ */

function isSubscribed(int $followerId, int $followingId): bool {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT id FROM subscriptions WHERE follower_id = ? AND following_id = ?");
        $stmt->execute([$followerId, $followingId]);
        return (bool)$stmt->fetch();
    } catch (Exception $e) { return false; }
}

function getSubscribersCount(int $userId): int {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM subscriptions WHERE following_id = ?");
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    } catch (Exception $e) { return 0; }
}

function getFollowingCount(int $userId): int {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM subscriptions WHERE follower_id = ?");
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    } catch (Exception $e) { return 0; }
}

function notifySubscribers(int $authorId, string $type, string $message, string $link = ''): void {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT follower_id FROM subscriptions WHERE following_id = ?");
        $stmt->execute([$authorId]);
        $subs = $stmt->fetchAll(PDO::FETCH_COLUMN);
        foreach ($subs as $subId) {
            notify((int)$subId, $type, $message, $link);
        }
    } catch (Exception $e) {}
}

/* ═══════════════════════════════════════════════════════════
   ПРИВАТНІ ПОВІДОМЛЕННЯ
   ═══════════════════════════════════════════════════════════ */

function getOrCreateDialog(int $userId, int $otherId): int {
    global $pdo;
    if ($userId === $otherId) throw new Exception('Не можна з собою');
    $u1 = min($userId, $otherId);
    $u2 = max($userId, $otherId);

    try {
        $stmt = $pdo->prepare("SELECT id FROM dm_dialogs WHERE user1_id = ? AND user2_id = ?");
        $stmt->execute([$u1, $u2]);
        $id = $stmt->fetchColumn();
        if ($id) return (int)$id;

        $pdo->prepare("INSERT INTO dm_dialogs (user1_id, user2_id) VALUES (?,?)")
            ->execute([$u1, $u2]);
        return (int)$pdo->lastInsertId();
    } catch (Exception $e) {
        throw new Exception('Помилка діалогу: ' . $e->getMessage());
    }
}

function getDialogMessages(int $dialogId, int $since = 0): array {
    global $pdo;
    try {
        if ($since > 0) {
            $stmt = $pdo->prepare("
                SELECT m.*, u.username, u.avatar
                FROM dm_messages m
                JOIN users u ON m.sender_id = u.id
                WHERE m.dialog_id = ? AND m.id > ?
                ORDER BY m.created_at ASC
            ");
            $stmt->execute([$dialogId, $since]);
        } else {
            $stmt = $pdo->prepare("
                SELECT m.*, u.username, u.avatar
                FROM dm_messages m
                JOIN users u ON m.sender_id = u.id
                WHERE m.dialog_id = ?
                ORDER BY m.created_at ASC
                LIMIT 200
            ");
            $stmt->execute([$dialogId]);
        }
        return $stmt->fetchAll();
    } catch (Exception $e) { return []; }
}

function sendDm(int $dialogId, int $senderId, string $message): int {
    global $pdo;
    $safe = cleanEditorHtml($message);
    if (mb_strlen(strip_tags($safe)) < 1) throw new Exception('Порожнє повідомлення');

    $pdo->prepare("INSERT INTO dm_messages (dialog_id, sender_id, message) VALUES (?,?,?)")
        ->execute([$dialogId, $senderId, $safe]);
    $mid = (int)$pdo->lastInsertId();

    $pdo->prepare("UPDATE dm_dialogs SET last_message_at = NOW() WHERE id = ?")->execute([$dialogId]);

    // Сповіщення отримувачу
    $stmt = $pdo->prepare("SELECT user1_id, user2_id FROM dm_dialogs WHERE id = ?");
    $stmt->execute([$dialogId]);
    $d = $stmt->fetch();
    if ($d) {
        $other = ((int)$d['user1_id'] === $senderId) ? (int)$d['user2_id'] : (int)$d['user1_id'];
        notify($other, 'dm', 'Нове повідомлення', SITE_URL . '/dm.php?with=' . $senderId);
    }
    return $mid;
}

function getUnreadDmCount(int $userId): int {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM dm_messages m
            JOIN dm_dialogs d ON m.dialog_id = d.id
            WHERE (d.user1_id = ? OR d.user2_id = ?)
              AND m.sender_id != ?
              AND m.is_read = 0
        ");
        $stmt->execute([$userId, $userId, $userId]);
        return (int)$stmt->fetchColumn();
    } catch (Exception $e) { return 0; }
}

function getDialogsList(int $userId): array {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT d.*,
                CASE WHEN d.user1_id = ? THEN d.user2_id ELSE d.user1_id END AS other_id,
                u.username AS other_name, u.avatar AS other_avatar,
                (SELECT message FROM dm_messages WHERE dialog_id = d.id ORDER BY id DESC LIMIT 1) AS last_message,
                (SELECT COUNT(*) FROM dm_messages WHERE dialog_id = d.id AND sender_id != ? AND is_read = 0) AS unread
            FROM dm_dialogs d
            JOIN users u ON u.id = CASE WHEN d.user1_id = ? THEN d.user2_id ELSE d.user1_id END
            WHERE d.user1_id = ? OR d.user2_id = ?
            ORDER BY d.last_message_at DESC
            LIMIT 50
        ");
        $stmt->execute([$userId, $userId, $userId, $userId, $userId]);
        return $stmt->fetchAll();
    } catch (Exception $e) { return []; }
}
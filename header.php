<?php
require_once __DIR__ . '/functions.php';

/* ─── Перевірка бану для поточного користувача ─── */
$__currentUri = $_SERVER['REQUEST_URI'] ?? '';
$__isBannedPage = (
    strpos($__currentUri, '/banned.php') !== false ||
    strpos($__currentUri, '/appeal.php') !== false ||
    strpos($__currentUri, '/api/') !== false ||
    strpos($__currentUri, '/logout.php') !== false ||
    strpos($__currentUri, '/login.php') !== false
);

if (isLoggedIn() && function_exists('isCurrentUserBanned') && isCurrentUserBanned() && !$__isBannedPage) {
    $_SESSION['banned_user_id'] = (int)$_SESSION['user_id'];
    unset($_SESSION['user_id']);
    redirect(SITE_URL . '/banned.php');
}

/* ─── Дані користувача ─── */
$__user = currentUser();
$__notifCount = 0;
if ($__user) {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0");
        $stmt->execute([$__user['id']]);
        $__notifCount = (int)$stmt->fetchColumn();
    } catch (Exception $e) {}
}

$__isLanding = (defined('IS_LANDING') && IS_LANDING);
$__isAdminPage = (strpos($__currentUri, '/admin/') !== false);
?>
<!DOCTYPE html>
<html lang="uk">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($pageTitle ?? SITE_NAME) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/animations.css">
<?php if ($__isAdminPage): ?>
  <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/admin.css">
<?php endif; ?>
</head>
<body>
<div class="scroll-progress"><div class="scroll-progress-fill"></div></div>
<div class="bg-grid" aria-hidden="true"></div>
<div class="bg-glow" aria-hidden="true"></div>

<header class="site-header">
  <div class="container header-inner">

    <a href="<?= SITE_URL ?><?= $__isLanding ? '/index.php' : '/landing.php' ?>" class="logo">
      <?= siteLogo(34) ?>
      <span class="logo-text"><?= SITE_NAME ?></span>
    </a>

    <?php if (!$__isLanding): ?>
      <form class="search-form" action="<?= SITE_URL ?>/search.php" method="get">
        <span class="search-icon"><?= icon('search', 15) ?></span>
        <input type="text" name="q" placeholder="Пошук тем..." value="<?= e($_GET['q'] ?? '') ?>">
      </form>
    <?php endif; ?>

    <div class="user-area">

      <div class="header-clock" title="Ваш локальний час">
        <?= icon('clock', 14) ?>
        <span data-local-time="full">--:--:--</span>
      </div>

      <?php if ($__isLanding): ?>
        <a href="<?= SITE_URL ?>/index.php" class="btn btn-primary btn-sm">
          <?= icon('message', 15) ?><span>Форум</span>
        </a>
      <?php else: ?>
        <a href="<?= SITE_URL ?>/landing.php" class="btn btn-ghost btn-sm header-home">
          <?= icon('home', 15) ?><span>Головна</span>
        </a>
      <?php endif; ?>

      <?php if ($__user): ?>

        <?php if (!$__isLanding): ?>
  <a href="<?= SITE_URL ?>/create-topic.php" class="btn btn-primary btn-sm">
    <?= icon('plus', 15) ?><span>Тема</span>
  </a>
<?php endif; ?>

<?php
  $__openAppeals = 0;
  if ($__user && function_exists('canReviewAppeals') && canReviewAppeals()) {
      try {
          $stmtA = $pdo->query("SELECT COUNT(*) FROM appeals WHERE status = 'open'");
          $__openAppeals = (int)$stmtA->fetchColumn();
      } catch (Exception $e) {}
  }
?>

<?php if ($__user && function_exists('canReviewAppeals') && canReviewAppeals()): ?>
  <a href="<?= SITE_URL ?>/appeals.php" class="btn btn-ghost btn-sm appeals-btn" title="Апеляції">
    <?= icon('message', 15) ?>
    <span>Апеляції</span>
    <?php if ($__openAppeals > 0): ?>
      <span class="notif-count"><?= $__openAppeals ?></span>
    <?php endif; ?>
  </a>
<?php endif; ?>

<?php if (isModerator()): ?>
  <a href="<?= SITE_URL ?>/admin/index.php" class="btn btn-ghost btn-sm" title="Адмінка">
    <?= icon('shield', 15) ?>
  </a>
<?php endif; ?>

        <div class="user-menu" id="userMenu">
          <button class="user-btn" type="button">
            <img src="<?= avatarUrl($__user) ?>" class="avatar-xs" alt="">
            <span class="user-name"><?= e($__user['username']) ?></span>
            <?php if ($__notifCount): ?><span class="notif-dot"><?= $__notifCount ?></span><?php endif; ?>
            <span class="chevron"><?= icon('chevron-down', 12) ?></span>
          </button>
          <div class="dropdown">
            <a href="<?= SITE_URL ?>/profile.php?id=<?= $__user['id'] ?>">
              <?= icon('user', 15) ?> Профіль
            </a>
            <a href="<?= SITE_URL ?>/dm.php">
              <?= icon('message', 15) ?> Повідомлення
            </a>
            <a href="<?= SITE_URL ?>/profile.php?tab=notifications">
              <?= icon('bell', 15) ?> Сповіщення
              <?php if ($__notifCount): ?><span class="badge-count"><?= $__notifCount ?></span><?php endif; ?>
            </a>
            <div class="dropdown-divider"></div>
            <a href="<?= SITE_URL ?>/logout.php" class="danger">
              <?= icon('logout', 15) ?> Вийти
            </a>
          </div>
        </div>

      <?php else: ?>

        <a href="<?= SITE_URL ?>/login.php" class="btn btn-ghost btn-sm">Увійти</a>
        <a href="<?= SITE_URL ?>/register.php" class="btn btn-primary btn-sm">Реєстрація</a>

      <?php endif; ?>

    </div>
  </div>
</header>

<main class="container main">
<?php if ($f = flash()): ?>
  <div class="alert <?= e($f['type']) ?>">
    <span class="alert-ico"><?= $f['type'] === 'error' ? icon('x', 15) : icon('check', 15) ?></span>
    <span><?= e($f['msg']) ?></span>
  </div>
<?php endif; ?>
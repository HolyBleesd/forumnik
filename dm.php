<?php
require_once __DIR__ . '/functions.php';
if (!isLoggedIn()) redirect(SITE_URL . '/login.php');

$dialogs = getDialogsList((int)$_SESSION['user_id']);

$withId = (int)($_GET['with'] ?? 0);
if ($withId) {
    try {
        $dialogId = getOrCreateDialog((int)$_SESSION['user_id'], $withId);
        redirect(SITE_URL . '/dm.php?id=' . $dialogId);
    } catch (Exception $e) {}
}

$dialogId = (int)($_GET['id'] ?? 0);
$dialog = null;
$other = null;

if ($dialogId) {
    $stmt = $pdo->prepare("SELECT * FROM dm_dialogs WHERE id = ?");
    $stmt->execute([$dialogId]);
    $dialog = $stmt->fetch();
    $uid = (int)$_SESSION['user_id'];

    if (!$dialog || ((int)$dialog['user1_id'] !== $uid && (int)$dialog['user2_id'] !== $uid)) {
        redirect(SITE_URL . '/dm.php');
    }

    $otherId = ((int)$dialog['user1_id'] === $uid) ? (int)$dialog['user2_id'] : (int)$dialog['user1_id'];
    $stmt = $pdo->prepare("SELECT id, username, avatar, role FROM users WHERE id = ?");
    $stmt->execute([$otherId]);
    $other = $stmt->fetch();
}

$pageTitle = 'Повідомлення';
require __DIR__ . '/header.php';
?>

<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/dm.css">

<div class="dm-page">
  <aside class="dm-sidebar">
    <div class="dm-sidebar-head">
      <?= icon('message', 16) ?>
      <h2>Діалоги</h2>
    </div>
    <div class="dm-dialogs" id="dmDialogs">
      <?php if (!$dialogs): ?>
        <div class="dm-empty">Ще немає діалогів</div>
      <?php else: ?>
        <?php foreach ($dialogs as $d): ?>
          <a href="<?= SITE_URL ?>/dm.php?id=<?= $d['id'] ?>" class="dm-dialog <?= $d['id'] == $dialogId ? 'active' : '' ?>">
            <img src="<?= avatarUrl(['username' => $d['other_name'], 'avatar' => $d['other_avatar']]) ?>" class="dm-dialog-avatar" alt="">
            <div class="dm-dialog-body">
              <div class="dm-dialog-name">
                <?= e($d['other_name']) ?>
                <?php if ((int)$d['unread'] > 0): ?>
                  <span class="dm-dialog-badge"><?= (int)$d['unread'] ?></span>
                <?php endif; ?>
              </div>
              <div class="dm-dialog-preview"><?= e($d['last_message']) ?></div>
            </div>
            <div class="dm-dialog-time"><?= e($d['time']) ?></div>
          </a>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </aside>

  <main class="dm-main">
    <?php if ($dialog && $other): ?>
      <div class="dm-head">
        <img src="<?= avatarUrl($other) ?>" class="dm-head-avatar" alt="">
        <div class="dm-head-info">
          <div class="dm-head-name">
            <a href="<?= SITE_URL ?>/profile.php?id=<?= $other['id'] ?>"><?= e($other['username']) ?></a>
            <?php if ($other['role'] === 'admin'): ?><span class="badge role admin">ADMIN</span><?php endif; ?>
            <?php if ($other['role'] === 'moderator'): ?><span class="badge role mod">MOD</span><?php endif; ?>
          </div>
          <div class="dm-head-status"><?= icon('message', 12) ?> Діалог</div>
        </div>
      </div>

      <div class="dm-messages" id="dmMessages" data-dialog="<?= $dialogId ?>" data-other="<?= $other['id'] ?>">
        <div class="dm-loading"><?= icon('clock', 16) ?> Завантаження...</div>
      </div>

      <form class="dm-form" id="dmForm">
        <input type="hidden" name="csrf" value="<?= csrf() ?>">
        <div class="dm-input-wrap">
          <textarea name="message" rows="1" placeholder="Напишіть повідомлення..." maxlength="2000" required></textarea>
          <button type="submit" class="dm-send">
            <?= icon('send', 16) ?>
          </button>
        </div>
      </form>
    <?php else: ?>
      <div class="dm-placeholder">
        <?= icon('message', 42) ?>
        <p>Оберіть діалог зліва, щоб почати спілкування</p>
      </div>
    <?php endif; ?>
  </main>
</div>

<script src="<?= SITE_URL ?>/assets/js/dm.js"></script>
<?php require __DIR__ . '/footer.php'; ?>
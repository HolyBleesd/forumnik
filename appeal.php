<?php
require_once __DIR__ . '/functions.php';

if (!isLoggedIn()) redirect(SITE_URL . '/login.php');
if (!canReviewAppeals()) {
    flash('⛔ Немає доступу до апеляцій', 'error');
    redirect(SITE_URL);
}

$status = $_GET['status'] ?? 'open';
$status = in_array($status, ['open','closed','all'], true) ? $status : 'open';

$sql = "
    SELECT a.*, p.type AS punish_type, p.reason AS punish_reason,
           p.expires_at, p.issued_at,
           u.username, u.avatar,
           (SELECT COUNT(*) FROM appeal_messages WHERE appeal_id = a.id) AS msg_count,
           (SELECT message FROM appeal_messages WHERE appeal_id = a.id ORDER BY id DESC LIMIT 1) AS last_msg,
           (SELECT username FROM users WHERE id = (
              SELECT user_id FROM appeal_messages WHERE appeal_id = a.id ORDER BY id DESC LIMIT 1
           )) AS last_msg_user,
           (SELECT created_at FROM appeal_messages WHERE appeal_id = a.id ORDER BY id DESC LIMIT 1) AS last_msg_at
    FROM appeals a
    JOIN user_punishments p ON a.punishment_id = p.id
    JOIN users u ON a.user_id = u.id
";
if ($status !== 'all') {
    $sql .= " WHERE a.status = " . $pdo->quote($status);
}
$sql .= " ORDER BY a.created_at DESC LIMIT 100";

$appeals = $pdo->query($sql)->fetchAll();

$counts = $pdo->query("
    SELECT 
      (SELECT COUNT(*) FROM appeals WHERE status='open') AS open_count,
      (SELECT COUNT(*) FROM appeals WHERE status='closed') AS closed_count,
      (SELECT COUNT(*) FROM appeals) AS total_count
")->fetch();

$pageTitle = 'Апеляції';
require __DIR__ . '/header.php';
?>

<div class="breadcrumbs">
  <a href="<?= SITE_URL ?>"><?= icon('home', 12) ?> Головна</a>
  <span class="sep">/</span>
  <span class="current">Апеляції</span>
</div>

<div class="section-head">
  <h1 class="section-title"><?= icon('message', 18) ?> Апеляції</h1>
  <span class="muted small">
    <?= icon('clock', 12) ?> Відкрито: <b><?= (int)$counts['open_count'] ?></b>
  </span>
</div>

<div class="admin-nav">
  <a href="<?= SITE_URL ?>/appeals.php?status=open" class="<?= $status==='open'?'active':'' ?>">
    <?= icon('message', 14) ?> Відкриті
    <span class="nav-count"><?= (int)$counts['open_count'] ?></span>
  </a>
  <a href="<?= SITE_URL ?>/appeals.php?status=closed" class="<?= $status==='closed'?'active':'' ?>">
    <?= icon('check', 14) ?> Закриті
    <span class="nav-count"><?= (int)$counts['closed_count'] ?></span>
  </a>
  <a href="<?= SITE_URL ?>/appeals.php?status=all" class="<?= $status==='all'?'active':'' ?>">
    <?= icon('list', 14) ?> Всі
    <span class="nav-count"><?= (int)$counts['total_count'] ?></span>
  </a>
</div>

<?php if (!$appeals): ?>
  <div class="empty">
    <?= icon('message', 32) ?>
    <p>Немає апеляцій</p>
  </div>
<?php else: ?>
  <div class="appeals-list">
    <?php foreach ($appeals as $i => $a): ?>
      <a href="<?= SITE_URL ?>/appeal.php?id=<?= (int)$a['id'] ?>" class="appeal-row" style="animation-delay: <?= $i*0.03 ?>s">
        <div class="appeal-row-avatar-wrap">
          <img src="<?= avatarUrl(['username'=>$a['username'],'avatar'=>$a['avatar']]) ?>" alt="" class="appeal-row-avatar">
          <span class="appeal-row-status <?= $a['status'] === 'open' ? 'open' : 'closed' ?>"></span>
        </div>

        <div class="appeal-row-body">
          <div class="appeal-row-head">
            <div class="appeal-row-name">
              <?= e($a['username']) ?>
              <span class="appeal-row-type type-<?= e($a['punish_type']) ?>">
                <?= e(['warn'=>'WARN','mute'=>'MUTE','ban'=>'BAN'][$a['punish_type']] ?? $a['punish_type']) ?>
              </span>
            </div>
            <div class="appeal-row-time">
              <?= icon('clock', 11) ?> <?= timeAgo($a['created_at']) ?>
            </div>
          </div>

          <div class="appeal-row-reason">
            <?= icon('x', 11) ?> <?= e(mb_substr($a['punish_reason'], 0, 80)) ?><?= mb_strlen($a['punish_reason']) > 80 ? '…' : '' ?>
          </div>

          <?php if ($a['last_msg']): ?>
            <div class="appeal-row-preview">
              <b><?= e($a['last_msg_user'] ?? '') ?>:</b>
              <?= e(mb_substr(strip_tags($a['last_msg']), 0, 90)) ?>…
            </div>
          <?php endif; ?>
        </div>

        <div class="appeal-row-side">
          <div class="appeal-row-msgs">
            <?= icon('message', 12) ?> <?= (int)$a['msg_count'] ?>
          </div>
          <span class="appeal-row-arrow"><?= icon('arrow-right', 16) ?></span>
        </div>
      </a>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/footer.php'; ?>
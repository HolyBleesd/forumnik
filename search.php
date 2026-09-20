<?php
require_once __DIR__ . '/functions.php';

$q = trim($_GET['q'] ?? '');
$results = [];
if ($q !== '') {
    $stmt = $pdo->prepare("
      SELECT t.*, u.username, c.name AS cat_name, c.icon AS cat_icon
      FROM topics t JOIN users u ON t.user_id=u.id
      JOIN categories c ON t.category_id=c.id
      WHERE t.is_deleted=0 AND (t.title LIKE ? OR t.content LIKE ?)
      ORDER BY t.updated_at DESC LIMIT 50
    ");
    $like = '%' . $q . '%';
    $stmt->execute([$like, $like]);
    $results = $stmt->fetchAll();
}
$pageTitle = 'Пошук: ' . $q;
require __DIR__ . '/header.php';
?>
<div class="breadcrumbs">
  <a href="<?= SITE_URL ?>"><?= icon('home', 12) ?> Головна</a>
  <span class="sep">/</span>
  <span class="current">Пошук</span>
</div>

<section class="section reveal">
  <div class="section-head">
    <h2 class="section-title"><?= icon('search', 17) ?> Результати: «<?= e($q) ?>»</h2>
    <span class="muted small">Знайдено: <?= count($results) ?></span>
  </div>

  <div class="topics-list">
    <?php if (!$results): ?>
      <div class="empty">
        <?= icon('search-x', 28) ?>
        <p>Нічого не знайдено</p>
      </div>
    <?php else: foreach ($results as $t): ?>
      <a href="<?= SITE_URL ?>/topic.php?id=<?= $t['id'] ?>" class="topic-row">
        <div class="topic-ico">
          <?= icon($t['cat_icon'] ?: 'hash', 16) ?>
        </div>
        <div class="topic-info">
          <div class="topic-title"><?= e($t['title']) ?></div>
          <div class="topic-meta">
            <span><?= icon('user', 11) ?> <?= e($t['username']) ?></span>
            <span><?= icon('folder', 11) ?> <?= e($t['cat_name']) ?></span>
            <span><?= icon('clock', 11) ?> <?= timeAgo($t['updated_at']) ?></span>
          </div>
        </div>
      </a>
    <?php endforeach; endif; ?>
  </div>
</section>
<?php require __DIR__ . '/footer.php'; ?>
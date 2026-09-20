<?php
require_once __DIR__ . '/functions.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) redirect(SITE_URL);

/* Отримуємо підфорум */
try {
    $stmt = $pdo->prepare("SELECT * FROM subforums WHERE id = ?");
    $stmt->execute([$id]);
    $subforum = $stmt->fetch();
} catch (Exception $e) {
    $subforum = null;
}

if (!$subforum) {
    flash('Підфорум не знайдено', 'error');
    redirect(SITE_URL);
}

if (!empty($subforum['is_hidden'])) {
    flash('Підфорум приховано', 'error');
    redirect(SITE_URL);
}

$categoryId = (int)$subforum['category_id'];

/* Категорія */
try {
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
    $stmt->execute([$categoryId]);
    $cat = $stmt->fetch();
} catch (Exception $e) {
    $cat = null;
}

if (!$cat) redirect(SITE_URL);

/* Дочірні підфоруми */
$children = [];
try {
    $stmt = $pdo->prepare("
        SELECT s.*,
          (SELECT COUNT(*) FROM topics WHERE subforum_id = s.id AND is_deleted = 0) AS topic_count
        FROM subforums s
        WHERE s.parent_id = ? AND s.is_hidden = 0
        ORDER BY s.sort_order, s.id
    ");
    $stmt->execute([$id]);
    $children = $stmt->fetchAll();
    
    foreach ($children as &$ch) {
        $cnt = $pdo->prepare("SELECT COUNT(*) FROM subforums WHERE parent_id = ? AND is_hidden = 0");
        $cnt->execute([(int)$ch['id']]);
        $ch['has_children'] = (int)$cnt->fetchColumn() > 0;
    }
    unset($ch);
} catch (Exception $e) {
    $children = [];
}

/* Хлібні крихти */
$breadcrumbs = [];
try {
    $current = $subforum;
    $safety = 0;
    while ($current && $safety < 20) {
        array_unshift($breadcrumbs, $current);
        if (empty($current['parent_id'])) break;
        $stmt = $pdo->prepare("SELECT * FROM subforums WHERE id = ?");
        $stmt->execute([(int)$current['parent_id']]);
        $current = $stmt->fetch();
        $safety++;
    }
} catch (Exception $e) {
    $breadcrumbs = [$subforum];
}

/* Права */
$canCreate = false;
if (isLoggedIn()) {
    try {
        $canCreate = canInSubforum('topic.create', $id);
    } catch (Exception $e) {
        $canCreate = false;
    }
}

/* Теми */
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = POSTS_PER_PAGE;
$offset  = ($page - 1) * $perPage;

$topics = [];
$total = 0;
try {
    $stmt = $pdo->prepare("
        SELECT t.*, u.username,
          (SELECT COUNT(*) FROM posts WHERE topic_id = t.id AND is_deleted = 0) AS replies
        FROM topics t
        JOIN users u ON t.user_id = u.id
        WHERE t.subforum_id = ? AND t.is_deleted = 0
        ORDER BY t.is_pinned DESC, t.updated_at DESC
        LIMIT $perPage OFFSET $offset
    ");
    $stmt->execute([$id]);
    $topics = $stmt->fetchAll();
    
    $cnt = $pdo->prepare("SELECT COUNT(*) FROM topics WHERE subforum_id = ? AND is_deleted = 0");
    $cnt->execute([$id]);
    $total = (int)$cnt->fetchColumn();
} catch (Exception $e) {
    $topics = [];
    $total = 0;
}

$pageTitle = $subforum['name'];
require __DIR__ . '/header.php';
?>

<div class="breadcrumbs">
  <a href="<?= SITE_URL ?>"><?= icon('home', 12) ?> Головна</a>
  <span class="sep">/</span>
  <a href="<?= SITE_URL ?>/category.php?id=<?= $categoryId ?>"><?= e($cat['name']) ?></a>
  <?php foreach ($breadcrumbs as $crumb): ?>
    <span class="sep">/</span>
    <?php if ((int)$crumb['id'] === $id): ?>
      <span class="current"><?= e($crumb['name']) ?></span>
    <?php else: ?>
      <a href="<?= SITE_URL ?>/subforum.php?id=<?= $crumb['id'] ?>"><?= e($crumb['name']) ?></a>
    <?php endif; ?>
  <?php endforeach; ?>
</div>

<section class="section reveal">
  <div class="section-head">
    <h2 class="section-title">
      <span class="cat-ico small" style="--tint: <?= e($subforum['color']) ?>">
        <?= icon($subforum['icon'] ?: 'hash', 15) ?>
      </span>
      <?= e($subforum['name']) ?>
    </h2>
    <?php if ($canCreate): ?>
      <a href="<?= SITE_URL ?>/create-topic.php?cat=<?= $categoryId ?>&subforum=<?= $id ?>" class="btn btn-primary btn-sm">
        <?= icon('plus', 15) ?> Нова тема
      </a>
    <?php elseif (isLoggedIn()): ?>
      <span class="muted small"><?= icon('lock', 13) ?> Немає прав створювати теми</span>
    <?php endif; ?>
  </div>

  <?php if (!empty($subforum['description'])): ?>
    <p class="muted" style="margin-bottom:16px"><?= e($subforum['description']) ?></p>
  <?php endif; ?>

  <?php if ($children): ?>
    <div class="subcat-grid">
      <?php foreach ($children as $child): ?>
        <a href="<?= SITE_URL ?>/subforum.php?id=<?= $child['id'] ?>" class="subcat-card" style="--tint: <?= e($child['color']) ?>">
          <div class="subcat-ico"><?= icon($child['icon'] ?: 'hash', 18) ?></div>
          <div class="subcat-body">
            <div class="subcat-name"><?= e($child['name']) ?></div>
            <?php if (!empty($child['description'])): ?>
              <div class="subcat-desc"><?= e($child['description']) ?></div>
            <?php endif; ?>
            <div class="subcat-meta">
              <span><?= icon('file-text', 11) ?> <?= (int)$child['topic_count'] ?> тем</span>
              <?php if (!empty($child['has_children'])): ?>
                <span><?= icon('folder', 11) ?> вкладені</span>
              <?php endif; ?>
            </div>
          </div>
          <span class="subcat-arrow"><?= icon('arrow-right', 15) ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <div class="topics-list" style="margin-top: <?= $children ? '20px' : '0' ?>">
    <?php if (!$topics): ?>
      <div class="empty">
        <?= icon('message', 28) ?>
        <p>Тут ще немає тем</p>
      </div>
    <?php else: foreach ($topics as $i => $t): ?>
      <a href="<?= SITE_URL ?>/topic.php?id=<?= $t['id'] ?>" class="topic-row"
         style="animation-delay: <?= $i*0.04 ?>s">
        <div class="topic-ico"><?= icon($subforum['icon'] ?: 'hash', 16) ?></div>
        <div class="topic-info">
          <div class="topic-title">
            <?php if ($t['is_pinned']): ?>
              <span class="badge pinned"><?= icon('pin', 11) ?></span>
            <?php endif; ?>
            <?php if ($t['is_locked']): ?>
              <span class="badge locked"><?= icon('lock', 11) ?></span>
            <?php endif; ?>
            <?= e($t['title']) ?>
          </div>
          <div class="topic-meta">
            <span><?= icon('user', 11) ?> <?= e($t['username']) ?></span>
            <span><?= icon('clock', 11) ?> <?= timeAgo($t['created_at']) ?></span>
          </div>
        </div>
        <div class="topic-stats">
          <div><b><?= $t['replies'] ?></b><small>відп.</small></div>
          <div><b><?= $t['views'] ?></b><small>перегл.</small></div>
        </div>
      </a>
    <?php endforeach; endif; ?>
  </div>

  <?php if ($total > $perPage): ?>
    <?= paginate($total, $perPage, $page) ?>
  <?php endif; ?>
</section>
<?php require __DIR__ . '/footer.php'; ?>
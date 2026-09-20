<?php
require_once __DIR__ . '/functions.php';

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM categories WHERE id=? AND is_hidden=0");
$stmt->execute([$id]);
$cat = $stmt->fetch();
if (!$cat) redirect(SITE_URL . '/index.php');

$subId = (int)($_GET['sub'] ?? 0);
$sub = null;
if ($subId) {
    $stmt = $pdo->prepare("SELECT * FROM subcategories WHERE id=? AND category_id=? AND is_hidden=0");
    $stmt->execute([$subId, $id]);
    $sub = $stmt->fetch();
    if (!$sub) redirect(SITE_URL . "/category.php?id=$id");
}

/* Підфоруми (верхній рівень) */
$subforums = getSubforums($id, null, false);

/* Підкатегорії (старі) */
$subs = getSubcategories($id, false);

/* Права */
$canCreate = isLoggedIn() && canInCategory('topic.create', $id, $subId ?: null);

$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = POSTS_PER_PAGE;
$offset  = ($page - 1) * $perPage;

$whereSub = $subId ? "AND t.subcategory_id = $subId" : "";
$stmt = $pdo->prepare("
  SELECT t.*, u.username,
    (SELECT COUNT(*) FROM posts WHERE topic_id=t.id AND is_deleted=0) AS replies
  FROM topics t JOIN users u ON t.user_id=u.id
  WHERE t.category_id=? AND t.is_deleted=0 $whereSub
  ORDER BY t.is_pinned DESC, t.updated_at DESC
  LIMIT $perPage OFFSET $offset
");
$stmt->execute([$id]);
$topics = $stmt->fetchAll();

$cntStmt = $pdo->prepare("SELECT COUNT(*) FROM topics WHERE category_id=? AND is_deleted=0 $whereSub");
$cntStmt->execute([$id]);
$total = (int)$cntStmt->fetchColumn();

$pageTitle = $cat['name'];
require __DIR__ . '/header.php';
?>
<div class="breadcrumbs">
  <a href="<?= SITE_URL ?>"><?= icon('home', 12) ?> Головна</a>
  <span class="sep">/</span>
  <?php if ($sub): ?>
    <a href="<?= SITE_URL ?>/category.php?id=<?= $id ?>"><?= e($cat['name']) ?></a>
    <span class="sep">/</span>
    <span class="current"><?= e($sub['name']) ?></span>
  <?php else: ?>
    <span class="current"><?= e($cat['name']) ?></span>
  <?php endif; ?>
</div>

<section class="section reveal">
  <div class="section-head">
    <h2 class="section-title">
      <span class="cat-ico small" style="--tint: <?= e($sub ? $sub['color'] : $cat['color']) ?>">
        <?= icon($sub ? ($sub['icon'] ?: 'hash') : ($cat['icon'] ?: 'hash'), 15) ?>
      </span>
      <?= e($sub ? $sub['name'] : $cat['name']) ?>
    </h2>
    <?php if ($canCreate): ?>
      <a href="<?= SITE_URL ?>/create-topic.php?cat=<?= $id ?><?= $subId ? '&sub=' . $subId : '' ?>" class="btn btn-primary btn-sm">
        <?= icon('plus', 15) ?> Нова тема
      </a>
    <?php elseif (isLoggedIn()): ?>
      <span class="muted small"><?= icon('lock', 13) ?> У вас немає прав створювати теми тут</span>
    <?php endif; ?>
  </div>

  <?php if ($sub && $sub['description']): ?>
    <p class="muted" style="margin-bottom:16px"><?= e($sub['description']) ?></p>
  <?php elseif (!$sub && $cat['description']): ?>
    <p class="muted" style="margin-bottom:16px"><?= e($cat['description']) ?></p>
  <?php endif; ?>

  <?php /* ═══════════ ПІДФОРУМИ ═══════════ */ ?>
  <?php if (!$sub && $subforums): ?>
    <div class="subcat-grid">
      <?php foreach ($subforums as $sf):
        $sfCanCreate = isLoggedIn() && canInSubforum('topic.create', (int)$sf['id']);
      ?>
        <a href="<?= SITE_URL ?>/subforum.php?id=<?= $sf['id'] ?>" class="subcat-card" style="--tint: <?= e($sf['color']) ?>">
          <div class="subcat-ico"><?= icon($sf['icon'] ?: 'hash', 18) ?></div>
          <div class="subcat-body">
            <div class="subcat-name">
              <?= e($sf['name']) ?>
              <?php if ($sf['is_hidden']): ?>
                <span class="subcat-badge hidden" title="Прихований"><?= icon('lock', 10) ?></span>
              <?php endif; ?>
            </div>
            <?php if ($sf['description']): ?>
              <div class="subcat-desc"><?= e($sf['description']) ?></div>
            <?php endif; ?>
            <div class="subcat-meta">
              <span><?= icon('file-text', 11) ?> <?= (int)$sf['topic_count'] ?> тем</span>
              <?php if ($sf['has_children']): ?>
                <span><?= icon('folder', 11) ?> <?= count($sf['children']) ?> підфорумів</span>
              <?php endif; ?>
              <?php if (!$sfCanCreate): ?>
                <span class="subcat-meta-lock" title="Немає прав створювати">
                  <?= icon('lock', 11) ?> Тільки читання
                </span>
              <?php endif; ?>
            </div>
          </div>
          <span class="subcat-arrow"><?= icon('arrow-right', 15) ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php /* ═══════════ ПІДКАТЕГОРІЇ (старі) ═══════════ */ ?>
  <?php if (!$sub && $subs): ?>
    <div class="subcat-grid" style="margin-top: <?= $subforums ? '12px' : '0' ?>">
      <?php foreach ($subs as $s):
        $subCanCreate = isLoggedIn() && canInCategory('topic.create', $id, (int)$s['id']);
      ?>
        <a href="?id=<?= $id ?>&sub=<?= $s['id'] ?>" class="subcat-card" style="--tint: <?= e($s['color']) ?>">
          <div class="subcat-ico"><?= icon($s['icon'] ?: 'hash', 18) ?></div>
          <div class="subcat-body">
            <div class="subcat-name">
              <?= e($s['name']) ?>
              <?php if ($s['is_hidden']): ?>
                <span class="subcat-badge hidden" title="Прихована"><?= icon('lock', 10) ?></span>
              <?php endif; ?>
            </div>
            <?php if ($s['description']): ?>
              <div class="subcat-desc"><?= e($s['description']) ?></div>
            <?php endif; ?>
            <div class="subcat-meta">
              <span><?= icon('file-text', 11) ?> <?= (int)$s['topic_count'] ?> тем</span>
              <?php if (!$subCanCreate): ?>
                <span class="subcat-meta-lock" title="Немає прав створювати">
                  <?= icon('lock', 11) ?> Тільки читання
                </span>
              <?php endif; ?>
            </div>
          </div>
          <span class="subcat-arrow"><?= icon('arrow-right', 15) ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php /* ═══════════ ТЕМИ ═══════════ */ ?>
  <div class="topics-list" style="margin-top: <?= (!$sub && ($subforums || $subs)) ? '20px' : '0' ?>">
    <?php if (!$topics): ?>
      <div class="empty">
        <?= icon('message', 28) ?>
        <p>Тут ще немає тем</p>
      </div>
    <?php else: foreach ($topics as $i => $t): ?>
      <a href="<?= SITE_URL ?>/topic.php?id=<?= $t['id'] ?>" class="topic-row"
         style="animation-delay: <?= $i*0.04 ?>s">
        <div class="topic-ico">
          <?= icon($cat['icon'] ?: 'hash', 16) ?>
        </div>
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

  <?= paginate($total, $perPage, $page) ?>
</section>
<?php require __DIR__ . '/footer.php'; ?>
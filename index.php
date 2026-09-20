<?php
require_once __DIR__ . '/functions.php';

/* ═══════════ КАТЕГОРІЇ ═══════════ */
$cats = $pdo->query("
  SELECT c.*,
    (SELECT COUNT(*) FROM topics WHERE category_id=c.id AND is_deleted=0) AS topic_count,
    (SELECT COUNT(*) FROM posts p JOIN topics t ON p.topic_id=t.id
      WHERE t.category_id=c.id AND p.is_deleted=0) AS post_count,
    (SELECT COUNT(*) FROM subforums WHERE category_id=c.id AND is_hidden=0) AS subforum_count
  FROM categories c WHERE c.is_hidden=0 ORDER BY sort_order
")->fetchAll();

/* ═══════════ ОНЛАЙН ═══════════ */
$online = (int)$pdo->query("
  SELECT COUNT(*) FROM users
  WHERE last_seen > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
")->fetchColumn();

$onlineList = $pdo->query("
  SELECT id, username, avatar, role
  FROM users
  WHERE last_seen > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
  ORDER BY last_seen DESC
  LIMIT 12
")->fetchAll();

/* ═══════════ ОСТАННІ ТЕМИ ═══════════ */
$sideLatest = $pdo->query("
  SELECT t.id, t.title, t.created_at,
    u.username, c.icon AS cat_icon, c.color AS cat_color
  FROM topics t JOIN users u ON t.user_id=u.id
  JOIN categories c ON t.category_id=c.id
  WHERE t.is_deleted=0
  ORDER BY t.created_at DESC LIMIT 8
")->fetchAll();

$pageTitle = SITE_NAME;
require __DIR__ . '/header.php';
?>

<!-- ДВІ КОЛОНКИ -->
<div class="layout">

  <!-- ЛІВА: КАТЕГОРІЇ -->
  <div class="layout-main">
    <section class="section reveal">
      <div class="section-head">
        <h2 class="section-title"><?= icon('grid', 17) ?> Категорії</h2>
        <span class="muted small"><?= count($cats) ?> розділів</span>
      </div>

      <?php if (!$cats): ?>
        <div class="empty">
          <?= icon('folder', 28) ?>
          <p>Ще немає жодної категорії</p>
        </div>
      <?php else: ?>
        <div class="cat-list">
          <?php foreach ($cats as $i => $c): ?>
            <a href="<?= SITE_URL ?>/category.php?id=<?= $c['id'] ?>"
               class="cat-row"
               style="--tint: <?= e($c['color']) ?>; animation-delay: <?= $i * 0.04 ?>s">

              <div class="cat-row-ico">
                <?= icon($c['icon'] ?: 'hash', 24) ?>
              </div>

              <div class="cat-row-body">
                <div class="cat-row-name">
                  <?= e($c['name']) ?>
                  <?php if ($c['is_hidden']): ?>
                    <span class="badge locked" title="Прихована"><?= icon('lock', 11) ?></span>
                  <?php endif; ?>
                </div>
                <?php if ($c['description']): ?>
                  <div class="cat-row-desc"><?= e($c['description']) ?></div>
                <?php endif; ?>
                <div class="cat-row-meta">
                  <span><?= icon('message', 13) ?> <?= (int)$c['topic_count'] ?> тем</span>
                  <span><?= icon('file-text', 13) ?> <?= (int)$c['post_count'] ?> постів</span>
                  <?php if ((int)$c['subforum_count'] > 0): ?>
                    <span><?= icon('folder', 13) ?> <?= (int)$c['subforum_count'] ?> підфорумів</span>
                  <?php endif; ?>
                </div>
              </div>

              <span class="cat-row-arrow"><?= icon('arrow-right', 16) ?></span>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>
  </div>

  <!-- ПРАВА: СТАТИСТИКА -->
  <aside class="layout-side">

    <!-- ОНЛАЙН -->
    <div class="side-card side-slot-top reveal">
      <div class="side-head">
        <span class="side-head-ico online">
          <span class="pulse-dot"></span>
        </span>
        <h3>Зараз онлайн</h3>
        <span class="side-head-count"><?= $online ?></span>
      </div>

      <?php if (!$onlineList): ?>
        <div class="side-empty">Нікого немає онлайн</div>
      <?php else: ?>
        <div class="side-users">
          <?php foreach ($onlineList as $u): ?>
            <a href="<?= SITE_URL ?>/profile.php?id=<?= $u['id'] ?>"
               class="side-user" title="<?= e($u['username']) ?>">
              <img src="<?= avatarUrl($u) ?>" alt="">
              <span class="online-indicator"></span>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <!-- ОСТАННІ ТЕМИ -->
    <div class="side-card side-slot-bottom reveal">
      <div class="side-head">
        <span class="side-head-ico"><?= icon('trend', 14) ?></span>
        <h3>Останні теми</h3>
      </div>

      <?php if (!$sideLatest): ?>
        <div class="side-empty">Ще немає тем</div>
      <?php else: ?>
        <div class="side-latest">
          <?php foreach ($sideLatest as $t): ?>
            <a href="<?= SITE_URL ?>/topic.php?id=<?= $t['id'] ?>" class="side-latest-topic">
              <span class="side-latest-ico" style="--tint: <?= e($t['cat_color']) ?>">
                <?= icon($t['cat_icon'] ?: 'hash', 13) ?>
              </span>
              <div class="side-latest-body">
                <div class="side-latest-title"><?= e($t['title']) ?></div>
                <div class="side-latest-meta">
                  <span><?= icon('user', 10) ?> <?= e($t['username']) ?></span>
                  <span class="dot">·</span>
                  <span><?= timeAgo($t['created_at']) ?></span>
                </div>
              </div>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

  </aside>
</div>

<?php require __DIR__ . '/footer.php'; ?>
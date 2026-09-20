<?php
require_once __DIR__ . '/functions.php';

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("
  SELECT t.*, u.username, u.id AS author_id, u.role AS author_role, u.avatar AS author_avatar,
         c.name AS cat_name, c.id AS cat_id,
         sf.name AS subforum_name, sf.id AS subforum_id
  FROM topics t
  JOIN users u ON t.user_id=u.id
  JOIN categories c ON t.category_id=c.id
  LEFT JOIN subforums sf ON t.subforum_id = sf.id
  WHERE t.id=? AND t.is_deleted=0
");
$stmt->execute([$id]);
$topic = $stmt->fetch();
if (!$topic) { http_response_code(404); die('Тему не знайдено'); }

$pdo->prepare("UPDATE topics SET views=views+1 WHERE id=?")->execute([$id]);

/* ═══════════ НОВА ВІДПОВІДЬ ═══════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reply'
    && isLoggedIn() && !$topic['is_locked']) {

    // Перевірка муту
    if (isUserMuted((int)$_SESSION['user_id'])) {
        flash('⛔ Ви не можете відповідати — у вас мут', 'error');
        redirect(SITE_URL . "/topic.php?id=$id");
    }

    if (checkCsrf($_POST['csrf'] ?? '')) {
        $content = $_POST['content'] ?? '';
        $safe = cleanEditorHtml($content);
        if (mb_strlen(strip_tags($safe)) >= 2) {
            $pdo->prepare("INSERT INTO posts (topic_id,user_id,content) VALUES (?,?,?)")
                ->execute([$id, $_SESSION['user_id'], $safe]);
            $postId = $pdo->lastInsertId();
            $pdo->prepare("UPDATE topics SET updated_at=NOW() WHERE id=?")->execute([$id]);

            // Сповіщення автору теми
            notify($topic['user_id'], 'reply', 'Нова відповідь: ' . $topic['title'],
                   SITE_URL . "/topic.php?id=$id#post-$postId");

            // Сповіщення підписникам
            $me = currentUser();
            notifySubscribers((int)$_SESSION['user_id'], 'sub_topic_reply',
                ($_SESSION['user_id'] == $topic['user_id']
                    ? 'Оновлення у вашій темі: '
                    : 'Нова відповідь від ' . ($me['username'] ?? '') . ' у темі: ')
                . $topic['title'],
                SITE_URL . "/topic.php?id=$id#post-$postId");

            redirect(SITE_URL . "/topic.php?id=$id#post-$postId");
        }
    }
}

/* ═══════════ РЕДАГУВАННЯ ПОСТА ═══════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit-post') {
    if (!isLoggedIn() || !checkCsrf($_POST['csrf'] ?? '')) {
        redirect(SITE_URL . "/topic.php?id=$id");
    }

    $postId = (int)($_POST['post_id'] ?? 0);
    $newContent = $_POST['content'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM posts WHERE id = ? AND topic_id = ? AND is_deleted = 0");
    $stmt->execute([$postId, $id]);
    $post = $stmt->fetch();

    if ($post && canEditPost((int)$post['user_id'])) {
        $safe = cleanEditorHtml($newContent);
        if (mb_strlen(strip_tags($safe)) >= 2 && $safe !== $post['content']) {
            savePostHistory($postId, $post['content'], $safe, (int)$_SESSION['user_id']);
            $pdo->prepare("UPDATE posts SET content = ? WHERE id = ?")->execute([$safe, $postId]);
            flash('Пост оновлено', 'success');
        }
    }
    redirect(SITE_URL . "/topic.php?id=$id#post-$postId");
}

/* ═══════════ ВІДКАТ ІСТОРІЇ ═══════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'rollback') {
    if (!isLoggedIn() || !checkCsrf($_POST['csrf'] ?? '') || !canRollbackHistory()) {
        redirect(SITE_URL . "/topic.php?id=$id");
    }

    $historyId = (int)($_POST['history_id'] ?? 0);

    $stmt = $pdo->prepare("
        SELECT h.*, p.topic_id, p.content AS current_content
        FROM post_history h
        JOIN posts p ON h.post_id = p.id
        WHERE h.id = ? AND p.topic_id = ?
    ");
    $stmt->execute([$historyId, $id]);
    $h = $stmt->fetch();

    if ($h) {
        savePostHistory((int)$h['post_id'], $h['current_content'], $h['content_before'], (int)$_SESSION['user_id'], true);
        $pdo->prepare("UPDATE posts SET content = ? WHERE id = ?")->execute([$h['content_before'], $h['post_id']]);
        flash('Відкат виконано', 'success');
        redirect(SITE_URL . "/topic.php?id=$id#post-" . (int)$h['post_id']);
    }
    redirect(SITE_URL . "/topic.php?id=$id");
}

/* ═══════════ ОТРИМАННЯ ПОСТІВ ═══════════ */
$uid = (int)($_SESSION['user_id'] ?? 0);
$stmt = $pdo->prepare("
  SELECT p.*, u.username, u.id AS author_id, u.role, u.avatar,
    (SELECT COUNT(*) FROM likes WHERE post_id=p.id) AS likes,
    (SELECT COUNT(*) FROM likes WHERE post_id=p.id AND user_id=?) AS user_liked
  FROM posts p JOIN users u ON p.user_id=u.id
  WHERE p.topic_id=? AND p.is_deleted=0
  ORDER BY p.created_at ASC
");
$stmt->execute([$uid, $id]);
$posts = $stmt->fetchAll();

$me = currentUser();
$isMuted = isLoggedIn() && isUserMuted((int)$_SESSION['user_id']);

$pageTitle = $topic['title'];
require __DIR__ . '/header.php';
?>

<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/editor.css">

<div class="breadcrumbs">
  <a href="<?= SITE_URL ?>"><?= icon('home', 12) ?> Головна</a>
  <span class="sep">/</span>
  <a href="<?= SITE_URL ?>/category.php?id=<?= $topic['cat_id'] ?>"><?= e($topic['cat_name']) ?></a>
  <?php if ($topic['subforum_id']): ?>
    <span class="sep">/</span>
    <a href="<?= SITE_URL ?>/subforum.php?id=<?= $topic['subforum_id'] ?>"><?= e($topic['subforum_name']) ?></a>
  <?php endif; ?>
  <span class="sep">/</span>
  <span class="current"><?= e(mb_substr($topic['title'], 0, 40)) ?></span>
</div>

<article class="topic-main reveal">
  <div class="post-header">
    <img class="avatar-md" src="<?= avatarUrl(['username'=>$topic['username'],'avatar'=>$topic['author_avatar']]) ?>" alt="">
    <div class="post-author-info">
      <div class="post-author-line">
        <a href="<?= SITE_URL ?>/profile.php?id=<?= $topic['author_id'] ?>" class="post-author">
          <?= e($topic['username']) ?>
        </a>
        <?php if ($topic['author_role']==='admin'): ?><span class="badge role admin">ADMIN</span><?php endif; ?>
        <?php if ($topic['author_role']==='moderator'): ?><span class="badge role mod">MOD</span><?php endif; ?>
      </div>
      <div class="post-time">
        <?= icon('clock', 12) ?> <?= realDateTime($topic['created_at']) ?>
        <span class="dot">·</span>
        <?= icon('eye', 12) ?> <?= $topic['views'] ?>
      </div>
    </div>
    <?php if (isModerator()): ?>
      <div class="mod-actions">
        <button class="icon-btn" data-action="pin" data-id="<?= $id ?>" title="Закріпити">
          <?= $topic['is_pinned'] ? icon('pin', 15) : icon('bookmark', 15) ?>
        </button>
        <button class="icon-btn" data-action="lock" data-id="<?= $id ?>" title="Закрити">
          <?= $topic['is_locked'] ? icon('lock', 15) : icon('unlock', 15) ?>
        </button>
        <button class="icon-btn danger" data-action="delete-topic" data-id="<?= $id ?>" title="Видалити">
          <?= icon('trash', 15) ?>
        </button>
      </div>
    <?php endif; ?>
  </div>
  <h1 class="topic-h1"><?= e($topic['title']) ?></h1>
  <div class="post-content"><?= $topic['content'] ?></div>
</article>

<h2 class="replies-title">
  <?= icon('message', 17) ?> Відповіді <span class="count">(<?= count($posts) ?>)</span>
</h2>

<div class="posts-list">
  <?php foreach ($posts as $i => $p):
    $canEdit = isLoggedIn() && canEditPost((int)$p['author_id']);
    $canHistory = canViewHistory();
    $canRollback = canRollbackHistory();
    $hasEdits = (int)($p['edited_count'] ?? 0) > 0;
  ?>
    <article class="post-card" id="post-<?= $p['id'] ?>" style="animation-delay: <?= $i*0.04 ?>s">
      <div class="post-side">
        <img class="avatar-md" src="<?= avatarUrl(['username'=>$p['username'],'avatar'=>$p['avatar']]) ?>" alt="">
        <a href="<?= SITE_URL ?>/profile.php?id=<?= $p['author_id'] ?>" class="post-author-sm">
          <?= e($p['username']) ?>
        </a>
        <?php if ($p['role']==='admin'): ?><span class="badge role admin">ADMIN</span><?php endif; ?>
        <?php if ($p['role']==='moderator'): ?><span class="badge role mod">MOD</span><?php endif; ?>
      </div>
      <div class="post-body">
        <div class="post-time">
          <?= icon('clock', 12) ?> <?= realDateTime($p['created_at']) ?>
          <?php if ($hasEdits && !empty($p['edited_at'])): ?>
            <span class="post-edited-badge" title="Відредаговано">
              <?= icon('edit', 11) ?>
              Відредаговано <?= realDateTimeShort($p['edited_at']) ?>
              <?php if ((int)$p['edited_count'] > 1): ?>
                · <?= (int)$p['edited_count'] ?> разів
              <?php endif; ?>
            </span>
          <?php endif; ?>
        </div>

        <div class="post-content" data-content><?= $p['content'] ?></div>

        <!-- Форма редагування (схована) -->
        <?php if ($canEdit): ?>
          <div class="post-edit-form" style="display:none" data-edit-form>
            <div class="rich-editor rich-editor-compact" data-editor>
              <div class="rich-editor-toolbar" data-editor-toolbar>
                <div class="re-group">
                  <button type="button" data-block="p"><span class="re-icon-text">A</span></button>
                  <button type="button" data-block="h2"><span class="re-icon-text re-h2">H2</span></button>
                </div>
                <div class="re-sep"></div>
                <button type="button" data-cmd="bold"><b>B</b></button>
                <button type="button" data-cmd="italic"><i>I</i></button>
                <button type="button" data-cmd="underline"><u>U</u></button>
                <div class="re-sep"></div>
                <button type="button" data-cmd="justifyLeft"><?= icon('align-left', 15) ?></button>
                <button type="button" data-cmd="justifyCenter"><?= icon('align-center', 15) ?></button>
                <button type="button" data-cmd="justifyRight"><?= icon('align-right', 15) ?></button>
                <div class="re-sep"></div>
                <button type="button" data-cmd="insertUnorderedList"><?= icon('list', 15) ?></button>
                <button type="button" data-cmd="formatBlock" data-value="blockquote"><?= icon('quote', 15) ?></button>
                <div class="re-sep"></div>
                <div class="re-colors">
                  <button type="button" class="re-color-current"><span class="re-color-icon">A</span></button>
                  <div class="re-colors-menu">
                    <div class="re-colors-row">
                      <?php foreach (['#ef4444','#f59e0b','#22c55e','#3b82f6','#8b5cf6','#ec4899','#ffffff','#9ba1a6'] as $c): ?>
                        <button type="button" data-color="<?= $c ?>" style="background:<?= $c ?>"></button>
                      <?php endforeach; ?>
                    </div>
                  </div>
                </div>
                <div class="re-sep"></div>
                <button type="button" data-open-media><?= icon('film', 15) ?></button>
                <button type="button" data-cmd="removeFormat"><?= icon('x', 15) ?></button>
              </div>
              <div class="rich-editor-area"
                   contenteditable="true"
                   data-editor-area
                   data-placeholder="Редагувати повідомлення..."><?= $p['content'] ?></div>
              <textarea data-editor-input style="display:none"></textarea>
            </div>
            <div class="post-edit-actions">
              <button type="button" class="btn btn-ghost btn-sm" data-cancel-edit>
                <?= icon('x', 14) ?> Скасувати
              </button>
              <button type="button" class="btn btn-primary btn-sm" data-save-edit="<?= $p['id'] ?>">
                <?= icon('check', 14) ?> Зберегти
              </button>
            </div>
          </div>
        <?php endif; ?>

        <div class="post-actions">
          <button class="btn-like <?= $p['user_liked'] ? 'liked' : '' ?>"
                  data-post="<?= $p['id'] ?>" <?= !isLoggedIn() ? 'disabled' : '' ?>>
            <?= icon('heart', 14, $p['user_liked'] ? 'filled' : 'stroke') ?>
            <span class="like-count"><?= $p['likes'] ?></span>
          </button>

          <?php if ($canEdit): ?>
            <button class="btn-action" data-start-edit>
              <?= icon('edit', 14) ?> Редагувати
            </button>
          <?php endif; ?>

          <?php if ($canHistory && $hasEdits): ?>
            <button class="btn-action" data-history="<?= $p['id'] ?>" data-can-rollback="<?= $canRollback ? '1' : '0' ?>">
              <?= icon('clock', 14) ?> Перегляд історії
            </button>
          <?php endif; ?>

          <?php if (isModerator() || (isLoggedIn() && $_SESSION['user_id'] == $p['author_id'])): ?>
            <button class="icon-btn danger" data-action="delete-post" data-id="<?= $p['id'] ?>" title="Видалити">
              <?= icon('trash', 14) ?>
            </button>
          <?php endif; ?>
        </div>
      </div>
    </article>
  <?php endforeach; ?>
</div>

<?php if (isLoggedIn() && !$topic['is_locked'] && !$isMuted): ?>
  <div class="reply-form reveal">
    <h3><?= icon('edit', 16) ?> Відповісти</h3>

    <form method="post">
      <input type="hidden" name="csrf" value="<?= csrf() ?>">
      <input type="hidden" name="action" value="reply">

      <div class="rich-editor rich-editor-compact" data-editor>
        <div class="rich-editor-toolbar" data-editor-toolbar>
          <div class="re-group">
            <button type="button" data-block="p"><span class="re-icon-text">A</span></button>
            <button type="button" data-block="h2"><span class="re-icon-text re-h2">H2</span></button>
          </div>
          <div class="re-sep"></div>
          <button type="button" data-cmd="bold"><b>B</b></button>
          <button type="button" data-cmd="italic"><i>I</i></button>
          <button type="button" data-cmd="underline"><u>U</u></button>
          <div class="re-sep"></div>
          <button type="button" data-cmd="justifyLeft"><?= icon('align-left', 15) ?></button>
          <button type="button" data-cmd="justifyCenter"><?= icon('align-center', 15) ?></button>
          <button type="button" data-cmd="justifyRight"><?= icon('align-right', 15) ?></button>
          <div class="re-sep"></div>
          <button type="button" data-cmd="insertUnorderedList"><?= icon('list', 15) ?></button>
          <button type="button" data-cmd="insertOrderedList"><?= icon('list-ordered', 15) ?></button>
          <button type="button" data-cmd="formatBlock" data-value="blockquote"><?= icon('quote', 15) ?></button>
          <div class="re-sep"></div>
          <div class="re-colors">
            <button type="button" class="re-color-current"><span class="re-color-icon">A</span></button>
            <div class="re-colors-menu">
              <div class="re-colors-row">
                <?php foreach (['#ef4444','#f59e0b','#22c55e','#3b82f6','#8b5cf6','#ec4899','#ffffff','#9ba1a6'] as $c): ?>
                  <button type="button" data-color="<?= $c ?>" style="background:<?= $c ?>"></button>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
          <div class="re-sep"></div>
          <button type="button" data-open-media><?= icon('film', 15) ?></button>
          <button type="button" data-cmd="removeFormat"><?= icon('x', 15) ?></button>
        </div>
        <div class="rich-editor-area"
             contenteditable="true"
             data-editor-area
             data-placeholder="Напишіть вашу відповідь..."></div>
        <textarea name="content" data-editor-input style="display:none"></textarea>
      </div>

      <button class="btn btn-primary" style="margin-top:14px">
        <?= icon('send', 15) ?> Надіслати
      </button>
    </form>
  </div>
<?php elseif ($topic['is_locked']): ?>
  <div class="alert info">
    <?= icon('lock', 15) ?>
    <span>Тема закрита. Нові відповіді неможливі.</span>
  </div>
<?php elseif ($isMuted): ?>
  <div class="alert error">
    <?= icon('lock', 15) ?>
    <span>У вас мут — ви не можете залишати відповіді</span>
  </div>
<?php else: ?>
  <div class="alert info">
    <?= icon('user', 15) ?>
    <span><a href="<?= SITE_URL ?>/login.php">Увійдіть</a>, щоб залишити відповідь</span>
  </div>
<?php endif; ?>

<!-- ═══════════ МОДАЛКА ІСТОРІЇ ═══════════ -->
<div class="history-modal" id="historyModal">
  <div class="history-modal-box">
    <div class="history-modal-head">
      <div class="history-modal-title">
        <?= icon('clock', 16) ?>
        <span>Історія редагувань</span>
      </div>
      <button type="button" class="history-modal-close" data-close-history>
        <?= icon('x', 16) ?>
      </button>
    </div>
    <div class="history-modal-body" id="historyBody">
      <div class="history-empty">Завантаження...</div>
    </div>
  </div>
</div>

<script>
window.CSRF_TOKEN = <?= json_encode(csrf()) ?>;
window.TOPIC_ID = <?= (int)$id ?>;
window.HISTORY_DATA = {};
</script>
<script src="<?= SITE_URL ?>/assets/js/editor.js"></script>
<script src="<?= SITE_URL ?>/assets/js/topic.js"></script>

<?php require __DIR__ . '/footer.php'; ?>
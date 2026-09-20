<?php
require_once __DIR__ . '/functions.php';

$id = (int)($_GET['id'] ?? $_SESSION['user_id'] ?? 0);
if (!$id) redirect(SITE_URL);

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$id]);
$u = $stmt->fetch();
if (!$u) { flash('Користувача не знайдено', 'error'); redirect(SITE_URL); }

$me      = currentUser();
$isOwn   = isLoggedIn() && (int)$_SESSION['user_id'] === $id;
$canEdit = $isOwn || isAdmin();
$tab     = $_GET['tab'] ?? 'overview';
$err     = '';

/* ═══════════ ОБРОБКА ФОРМ ═══════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canEdit) {
    if (!checkCsrf($_POST['csrf'] ?? '')) {
        $err = 'Помилка безпеки';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'save') {
            try {
                if (isAdmin()) {
                    $newUsername = trim($_POST['username'] ?? $u['username']);
                    $newEmail    = trim($_POST['email'] ?? $u['email']);
                    $newRoleId   = (int)($_POST['role_id'] ?? $u['role_id']);
                    $newBanned   = isset($_POST['is_banned']) ? 1 : 0;

                    if (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $newUsername))
                        throw new Exception('Логін: 3-30 символів (a-z, 0-9, _)');
                    if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL))
                        throw new Exception('Невірний email');

                    $roleCheck = $pdo->prepare("SELECT * FROM roles WHERE id = ?");
                    $roleCheck->execute([$newRoleId]);
                    $newRoleObj = $roleCheck->fetch();
                    if (!$newRoleObj) throw new Exception('Роль не знайдена');

                    $roleSlug = in_array($newRoleObj['slug'], ['user','moderator','admin'], true)
                        ? $newRoleObj['slug'] : 'user';

                    if ($isOwn && $newRoleObj['slug'] !== 'admin')
                        throw new Exception('Не можна зняти роль admin з себе');

                    $uniq = $pdo->prepare("SELECT id FROM users WHERE (username=? OR email=?) AND id != ?");
                    $uniq->execute([$newUsername, $newEmail, $id]);
                    if ($uniq->fetch()) throw new Exception('Логін або email вже зайняті');

                    $pdo->prepare("UPDATE users SET username=?, email=?, role_id=?, role=?, is_banned=? WHERE id=?")
                        ->execute([$newUsername, $newEmail, $newRoleId, $roleSlug, $newBanned, $id]);

                    $u['username']  = $newUsername;
                    $u['email']     = $newEmail;
                    $u['role_id']   = $newRoleId;
                    $u['role']      = $roleSlug;
                    $u['is_banned'] = $newBanned;
                }

                $bio        = mb_substr(trim($_POST['bio'] ?? ''), 0, 200);
                $signature  = mb_substr(trim($_POST['signature'] ?? ''), 0, 200);
                $location   = mb_substr(trim($_POST['location'] ?? ''), 0, 100);
                $website    = mb_substr(trim($_POST['website'] ?? ''), 0, 255);
                $tg         = mb_substr(trim($_POST['social_telegram'] ?? ''), 0, 100);
                $discord    = mb_substr(trim($_POST['social_discord'] ?? ''), 0, 100);
                $showEmail  = isset($_POST['show_email']) ? 1 : 0;
                $birthday   = trim($_POST['birthday'] ?? '') ?: null;

                if ($website && !filter_var($website, FILTER_VALIDATE_URL))
                    throw new Exception('Невірний URL сайту');

                $pdo->prepare("UPDATE users SET bio=?, signature=?, location=?, website=?, social_telegram=?, social_discord=?, show_email=?, birthday=? WHERE id=?")
                    ->execute([$bio, $signature, $location, $website, $tg, $discord, $showEmail, $birthday, $id]);

                if (!empty($_FILES['avatar']['tmp_name']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
                    $f = $_FILES['avatar'];
                    if ($f['size'] > 2 * 1024 * 1024) throw new Exception('Аватар макс 2 МБ');
                    $info = @getimagesize($f['tmp_name']);
                    $allowed = [IMAGETYPE_JPEG=>'jpg', IMAGETYPE_PNG=>'png', IMAGETYPE_GIF=>'gif', IMAGETYPE_WEBP=>'webp'];
                    if (!$info || !isset($allowed[$info[2]])) throw new Exception('Тільки JPG/PNG/GIF/WEBP');

                    if (!is_dir(UPLOAD_DIR)) @mkdir(UPLOAD_DIR, 0755, true);
                    $name = 'u' . $id . '_' . bin2hex(random_bytes(6)) . '.' . $allowed[$info[2]];
                    if (move_uploaded_file($f['tmp_name'], UPLOAD_DIR . $name)) {
                        if ($u['avatar'] && file_exists(UPLOAD_DIR . $u['avatar'])) @unlink(UPLOAD_DIR . $u['avatar']);
                        $pdo->prepare("UPDATE users SET avatar=? WHERE id=?")->execute([$name, $id]);
                        $u['avatar'] = $name;
                    }
                }

                $newPass = $_POST['password'] ?? '';
                if ($newPass !== '') {
                    if (mb_strlen($newPass) < 6) throw new Exception('Пароль мінімум 6 символів');
                    $hash = password_hash($newPass, PASSWORD_DEFAULT);
                    $pdo->prepare("UPDATE users SET password=? WHERE id=?")->execute([$hash, $id]);
                }

                flash('Профіль збережено', 'success');
                redirect(SITE_URL . "/profile.php?id=$id");
            } catch (Exception $e) {
                $err = $e->getMessage();
            }
        }

        elseif ($action === 'delete_avatar') {
            if ($u['avatar'] && file_exists(UPLOAD_DIR . $u['avatar'])) @unlink(UPLOAD_DIR . $u['avatar']);
            $pdo->prepare("UPDATE users SET avatar=NULL WHERE id=?")->execute([$id]);
            flash('Аватар видалено', 'success');
            redirect(SITE_URL . "/profile.php?id=$id");
        }
    }
}

/* ═══════════ СТАТИСТИКА ═══════════ */
$s1 = $pdo->prepare("SELECT COUNT(*) FROM topics WHERE user_id=? AND is_deleted=0");
$s1->execute([$id]);
$topicsCount = (int)$s1->fetchColumn();

$s2 = $pdo->prepare("SELECT COUNT(*) FROM posts WHERE user_id=? AND is_deleted=0");
$s2->execute([$id]);
$postsCount = (int)$s2->fetchColumn();

$s3 = $pdo->prepare("SELECT COUNT(*) FROM likes l JOIN posts p ON l.post_id = p.id WHERE p.user_id = ?");
$s3->execute([$id]);
$likesReceived = (int)$s3->fetchColumn();

$s4 = $pdo->prepare("SELECT COUNT(*) FROM likes WHERE user_id=?");
$s4->execute([$id]);
$likesGiven = (int)$s4->fetchColumn();

/* Роль */
$userRole = getUserRole($u);
$roleColor = $userRole['color'] ?? '#5b6ee1';

/* Теми */
$lastTopics = $pdo->prepare("
    SELECT t.*, c.name AS cat_name, c.icon AS cat_icon, c.color AS cat_color,
           (SELECT COUNT(*) FROM posts WHERE topic_id=t.id AND is_deleted=0) AS replies
    FROM topics t JOIN categories c ON t.category_id = c.id
    WHERE t.user_id = ? AND t.is_deleted = 0
    ORDER BY t.created_at DESC LIMIT 10
");
$lastTopics->execute([$id]);
$userTopics = $lastTopics->fetchAll();

/* Пости */
$lastPosts = $pdo->prepare("
    SELECT p.*, t.title AS topic_title, t.id AS topic_id
    FROM posts p JOIN topics t ON p.topic_id = t.id
    WHERE p.user_id = ? AND p.is_deleted = 0
    ORDER BY p.created_at DESC LIMIT 10
");
$lastPosts->execute([$id]);
$userPosts = $lastPosts->fetchAll();

/* Сповіщення */
$notifs = [];
$unreadCount = 0;
if ($isOwn) {
    $n = $pdo->prepare("SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 50");
    $n->execute([$id]);
    $notifs = $n->fetchAll();
    foreach ($notifs as $n) if (!$n['is_read']) $unreadCount++;
    $pdo->prepare("UPDATE notifications SET is_read=1 WHERE user_id=?")->execute([$id]);
}

/* Ролі для адмін-модалки */
$allRoles = [];
if (isAdmin()) {
    $allRoles = $pdo->query("SELECT * FROM roles ORDER BY sort_order")->fetchAll();
}

$isOnline = ($u['last_seen'] > date('Y-m-d H:i:s', time() - 900));

$pageTitle = $u['username'];
require __DIR__ . '/header.php';
?>

<div class="profile-layout">

  <!-- ═══════════ ЛІВИЙ САЙДБАР ═══════════ -->
  <aside class="profile-sidebar">

    <div class="pcard pcard-user">
      <div class="pcard-user-cover"></div>
      <div class="pcard-user-body">
        <div class="pcard-avatar-wrap">
          <img class="pcard-avatar" src="<?= avatarUrl($u) ?>" alt="">
          <?php if ($isOnline && !$u['is_banned']): ?>
            <span class="pcard-online" title="Онлайн"></span>
          <?php endif; ?>
        </div>

        <h1 class="pcard-name">
          <?= e($u['username']) ?>
          <?php if ($userRole): ?>
            <span class="pcard-role" style="--tint: <?= e($roleColor) ?>">
              <?= icon('shield', 11) ?> <?= e($userRole['name']) ?>
            </span>
          <?php endif; ?>
        </h1>

        <?php if ($u['is_banned']): ?>
          <div class="pcard-banned"><?= icon('lock', 12) ?> Акаунт заблоковано</div>
        <?php endif; ?>

        <?php if (!empty($u['bio'])): ?>
          <p class="pcard-bio"><?= e($u['bio']) ?></p>
        <?php endif; ?>

        <?php if ($canEdit): ?>
          <button type="button" class="pcard-edit-btn" id="openEditProfile">
            <?= icon('edit', 14) ?>
            <?= $isOwn ? 'Редагувати профіль' : 'Адмін-редагування' ?>
          </button>
        <?php endif; ?>
      </div>
    </div>

    <div class="pcard">
      <div class="pcard-head"><?= icon('chart', 13) ?> Статистика</div>
      <div class="pcard-stats">
        <div class="pcard-stat">
          <div class="pcard-stat-num"><?= $topicsCount ?></div>
          <div class="pcard-stat-lbl">Тем</div>
        </div>
        <div class="pcard-stat">
          <div class="pcard-stat-num"><?= $postsCount ?></div>
          <div class="pcard-stat-lbl">Постів</div>
        </div>
        <div class="pcard-stat">
          <div class="pcard-stat-num"><?= $likesReceived ?></div>
          <div class="pcard-stat-lbl">Лайків отр.</div>
        </div>
        <div class="pcard-stat">
          <div class="pcard-stat-num"><?= $likesGiven ?></div>
          <div class="pcard-stat-lbl">Лайків від.</div>
        </div>
      </div>
    </div>

    <div class="pcard">
      <div class="pcard-head"><?= icon('user', 13) ?> Інформація</div>
      <div class="pcard-info">
        <div class="pcard-info-row">
          <span class="pcard-info-lbl"><?= icon('clock', 12) ?> Реєстрація</span>
          <span><?= date('d.m.Y', strtotime($u['created_at'])) ?></span>
        </div>
        <div class="pcard-info-row">
          <span class="pcard-info-lbl"><?= icon('eye', 12) ?> Візит</span>
          <span><?= timeAgo($u['last_seen']) ?></span>
        </div>
        <?php if (!empty($u['location'])): ?>
          <div class="pcard-info-row">
            <span class="pcard-info-lbl"><?= icon('globe', 12) ?> Місто</span>
            <span><?= e($u['location']) ?></span>
          </div>
        <?php endif; ?>
        <?php if (!empty($u['birthday'])): ?>
          <div class="pcard-info-row">
            <span class="pcard-info-lbl"><?= icon('star', 12) ?> ДН</span>
            <span><?= date('d.m.Y', strtotime($u['birthday'])) ?></span>
          </div>
        <?php endif; ?>
        <?php if (!empty($u['show_email']) || isAdmin()): ?>
          <div class="pcard-info-row">
            <span class="pcard-info-lbl"><?= icon('send', 12) ?> Email</span>
            <span class="pcard-info-value"><?= e($u['email']) ?></span>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <?php if (!empty($u['website']) || !empty($u['social_telegram']) || !empty($u['social_discord'])): ?>
      <div class="pcard">
        <div class="pcard-head"><?= icon('globe', 13) ?> Контакти</div>
        <div class="pcard-links">
          <?php if (!empty($u['website'])): ?>
            <a href="<?= e($u['website']) ?>" target="_blank" rel="noopener" class="pcard-link">
              <span class="pcard-link-ico"><?= icon('globe', 14) ?></span>
              <span><?= e(preg_replace('#^https?://#', '', $u['website'])) ?></span>
            </a>
          <?php endif; ?>
          <?php if (!empty($u['social_telegram'])): ?>
            <a href="https://t.me/<?= e(ltrim($u['social_telegram'], '@')) ?>" target="_blank" rel="noopener" class="pcard-link">
              <span class="pcard-link-ico"><?= icon('send', 14) ?></span>
              <span><?= e($u['social_telegram']) ?></span>
            </a>
          <?php endif; ?>
          <?php if (!empty($u['social_discord'])): ?>
            <div class="pcard-link">
              <span class="pcard-link-ico"><?= icon('message', 14) ?></span>
              <span><?= e($u['social_discord']) ?></span>
            </div>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>

    <?php if (!empty($u['signature'])): ?>
      <div class="pcard">
        <div class="pcard-head"><?= icon('edit', 13) ?> Підпис</div>
        <p class="pcard-signature"><?= nl2br(e($u['signature'])) ?></p>
      </div>
    <?php endif; ?>

  </aside>

  <!-- ═══════════ ПРАВА ЧАСТИНА ═══════════ -->
  <main class="profile-main">

    <?php if ($err): ?>
      <div class="alert error">
        <span class="alert-ico"><?= icon('x', 15) ?></span>
        <span><?= e($err) ?></span>
      </div>
    <?php endif; ?>

    <nav class="profile-nav">
      <a href="?id=<?= $id ?>&tab=overview" class="<?= $tab==='overview'?'active':'' ?>">
        <?= icon('user', 14) ?> Огляд
      </a>
      <a href="?id=<?= $id ?>&tab=posts" class="<?= $tab==='posts'?'active':'' ?>">
        <?= icon('message', 14) ?> Пости
        <span class="pnav-count"><?= $postsCount ?></span>
      </a>
      <a href="?id=<?= $id ?>&tab=topics" class="<?= $tab==='topics'?'active':'' ?>">
        <?= icon('file-text', 14) ?> Теми
        <span class="pnav-count"><?= $topicsCount ?></span>
      </a>
      <?php if ($isOwn): ?>
        <a href="?id=<?= $id ?>&tab=notifications" class="<?= $tab==='notifications'?'active':'' ?>">
          <?= icon('bell', 14) ?> Сповіщення
          <?php if ($unreadCount > 0): ?>
            <span class="pnav-count accent"><?= $unreadCount ?></span>
          <?php endif; ?>
        </a>
      <?php endif; ?>
    </nav>

    <?php if ($tab === 'overview'): ?>
      <div class="profile-section reveal">
        <div class="psection-head"><?= icon('trend', 15) ?> Остання активність</div>

        <?php if (!$userPosts && !$userTopics): ?>
          <div class="empty compact"><?= icon('message', 24) ?><p>Ще немає активності</p></div>
        <?php else: ?>
          <?php if (!empty($userPosts)): ?>
            <div class="pactivity-title">Останні пости</div>
            <div class="pactivity">
              <?php foreach (array_slice($userPosts, 0, 3) as $p): ?>
                <div class="pactivity-item">
                  <span class="pactivity-ico"><?= icon('message', 14) ?></span>
                  <div class="pactivity-body">
                    <div class="pactivity-head">
                      У темі <a href="<?= SITE_URL ?>/topic.php?id=<?= $p['topic_id'] ?>">
                        <?= e(mb_substr($p['topic_title'], 0, 50)) ?>
                      </a>
                    </div>
                    <div class="pactivity-text"><?= e(mb_substr($p['content'], 0, 120)) ?>…</div>
                  </div>
                  <span class="pactivity-time"><?= timeAgo($p['created_at']) ?></span>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>

          <?php if (!empty($userTopics)): ?>
            <div class="pactivity-title" style="margin-top:16px">Останні теми</div>
            <div class="pactivity">
              <?php foreach (array_slice($userTopics, 0, 3) as $t): ?>
                <a href="<?= SITE_URL ?>/topic.php?id=<?= $t['id'] ?>" class="pactivity-item link">
                  <span class="pactivity-ico" style="--tint: <?= e($t['cat_color'] ?? '#5b6ee1') ?>">
                    <?= icon($t['cat_icon'] ?: 'hash', 14) ?>
                  </span>
                  <div class="pactivity-body">
                    <div class="pactivity-head"><?= e($t['title']) ?></div>
                    <div class="pactivity-text">
                      <?= icon('folder', 11) ?> <?= e($t['cat_name']) ?>
                      · <?= icon('message', 11) ?> <?= (int)$t['replies'] ?> відп.
                    </div>
                  </div>
                  <span class="pactivity-time"><?= timeAgo($t['created_at']) ?></span>
                </a>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        <?php endif; ?>
      </div>

    <?php elseif ($tab === 'posts'): ?>
      <div class="profile-section reveal">
        <div class="psection-head">
          <?= icon('message', 15) ?> Пости
          <span class="psection-count"><?= count($userPosts) ?></span>
        </div>
        <?php if (!$userPosts): ?>
          <div class="empty compact"><?= icon('message', 24) ?><p>Ще немає постів</p></div>
        <?php else: ?>
          <div class="pposts">
            <?php foreach ($userPosts as $p): ?>
              <div class="ppost">
                <div class="ppost-head">
                  <a href="<?= SITE_URL ?>/topic.php?id=<?= $p['topic_id'] ?>" class="ppost-topic">
                    <?= icon('message', 12) ?> <?= e($p['topic_title']) ?>
                  </a>
                  <span class="ppost-time"><?= timeAgo($p['created_at']) ?></span>
                </div>
                <div class="ppost-body"><?= nl2br(e(mb_substr($p['content'], 0, 300))) ?></div>
                <a href="<?= SITE_URL ?>/topic.php?id=<?= $p['topic_id'] ?>#post-<?= $p['id'] ?>" class="ppost-link">
                  Перейти <?= icon('arrow-right', 12) ?>
                </a>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

    <?php elseif ($tab === 'topics'): ?>
      <div class="profile-section reveal">
        <div class="psection-head">
          <?= icon('file-text', 15) ?> Теми
          <span class="psection-count"><?= count($userTopics) ?></span>
        </div>
        <?php if (!$userTopics): ?>
          <div class="empty compact"><?= icon('file-text', 24) ?><p>Ще немає тем</p></div>
        <?php else: ?>
          <div class="ptopics">
            <?php foreach ($userTopics as $t): ?>
              <a href="<?= SITE_URL ?>/topic.php?id=<?= $t['id'] ?>" class="ptopic">
                <span class="ptopic-ico" style="--tint: <?= e($t['cat_color'] ?? '#5b6ee1') ?>">
                  <?= icon($t['cat_icon'] ?: 'hash', 16) ?>
                </span>
                <div class="ptopic-body">
                  <div class="ptopic-title"><?= e($t['title']) ?></div>
                  <div class="ptopic-meta">
                    <span><?= icon('folder', 11) ?> <?= e($t['cat_name']) ?></span>
                    <span><?= icon('clock', 11) ?> <?= timeAgo($t['created_at']) ?></span>
                  </div>
                </div>
                <div class="ptopic-stats">
                  <div><b><?= (int)$t['replies'] ?></b><small>відп.</small></div>
                  <div><b><?= (int)$t['views'] ?></b><small>перегл.</small></div>
                </div>
              </a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

    <?php elseif ($tab === 'notifications' && $isOwn): ?>
      <div class="profile-section reveal">
        <div class="psection-head"><?= icon('bell', 15) ?> Сповіщення</div>
        <?php if (!$notifs): ?>
          <div class="empty compact"><?= icon('bell', 24) ?><p>Немає сповіщень</p></div>
        <?php else: ?>
          <div class="pnotifs">
            <?php foreach ($notifs as $n): ?>
              <a href="<?= e($n['link'] ?: '#') ?>" class="pnotif <?= $n['is_read'] ? '' : 'unread' ?>">
                <span class="pnotif-ico">
                  <?= $n['type']==='reply' ? icon('message', 15) : ($n['type']==='like' ? icon('heart', 15) : icon('bell', 15)) ?>
                </span>
                <div class="pnotif-body">
                  <div class="pnotif-msg"><?= e($n['message']) ?></div>
                  <div class="pnotif-time"><?= timeAgo($n['created_at']) ?></div>
                </div>
                <?php if (!$n['is_read']): ?>
                  <span class="pnotif-dot"></span>
                <?php endif; ?>
              </a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>

  </main>
</div>

<!-- ═══════════ МОДАЛКА РЕДАГУВАННЯ ═══════════ -->
<?php if ($canEdit): ?>
<div class="user-modal user-modal-premium" id="editProfileModal">
  <div class="user-modal-box user-modal-box-xl">

    <div class="user-modal-head">
      <div class="user-modal-head-left">
        <div class="user-modal-head-ico"><?= icon('edit', 18) ?></div>
        <div class="user-modal-head-text">
          <h3><?= isAdmin() && !$isOwn ? 'Адмін-редагування' : 'Редагування профілю' ?></h3>
          <p>Оновіть інформацію, яку бачать інші користувачі</p>
        </div>
      </div>
      <button type="button" class="user-modal-close" data-close-modal><?= icon('x', 16) ?></button>
    </div>

    <div class="user-modal-nav">
      <button type="button" class="um-nav-item active" data-tab="main">
        <?= icon('user', 14) ?> Основне
      </button>
      <button type="button" class="um-nav-item" data-tab="about">
        <?= icon('edit', 14) ?> Про себе
      </button>
      <button type="button" class="um-nav-item" data-tab="contacts">
        <?= icon('globe', 14) ?> Контакти
      </button>
      <?php if (isAdmin()): ?>
        <button type="button" class="um-nav-item" data-tab="admin">
          <?= icon('shield', 14) ?> Адмін
        </button>
      <?php endif; ?>
    </div>

    <form method="post" enctype="multipart/form-data" class="user-modal-form" id="profileForm">
      <input type="hidden" name="csrf" value="<?= csrf() ?>">
      <input type="hidden" name="action" value="save">

      <div class="user-modal-body">

        <div class="um-tab active" data-tab-content="main">
          <div class="um-avatar-section">
            <div class="um-avatar-preview">
              <img id="avatarPreview" src="<?= avatarUrl($u) ?>" alt="">
              <label class="um-avatar-overlay">
                <input type="file" name="avatar" accept="image/*" id="avatarInput">
                <span><?= icon('camera', 20) ?></span>
                <span class="um-avatar-overlay-text">Змінити</span>
              </label>
            </div>
            <div class="um-avatar-info">
              <div class="um-avatar-name"><?= e($u['username']) ?></div>
              <div class="um-avatar-hint">
                <?= icon('sparkle', 12) ?>
                JPG, PNG, GIF або WEBP. Максимум 2 МБ.
              </div>
              <div class="um-avatar-actions">
                <label class="um-btn-upload">
                  <input type="file" name="avatar" accept="image/*" id="avatarInput2">
                  <?= icon('camera', 14) ?> Завантажити
                </label>
                <?php if ($u['avatar']): ?>
                  <button type="submit" name="action" value="delete_avatar" class="um-btn-remove"
                          onclick="return confirm('Видалити аватар?')">
                    <?= icon('trash', 14) ?> Видалити
                  </button>
                <?php endif; ?>
              </div>
            </div>
          </div>

          <div class="um-section">
            <div class="um-section-head"><?= icon('user', 14) ?> Основна інформація</div>
            <div class="um-fields">
              <div class="um-field">
                <label><?= icon('clock', 12) ?> Дата народження</label>
                <input type="date" name="birthday" value="<?= e($u['birthday'] ?? '') ?>">
              </div>
              <div class="um-field">
                <label><?= icon('globe', 12) ?> Місто</label>
                <input type="text" name="location" maxlength="100" placeholder="Київ" value="<?= e($u['location'] ?? '') ?>">
              </div>
            </div>
          </div>

          <div class="um-section">
            <div class="um-section-head"><?= icon('shield', 14) ?> Приватність</div>
            <div class="um-switches">
              <label class="um-switch">
                <div class="um-switch-body">
                  <div class="um-switch-title"><?= icon('send', 13) ?> Показувати email</div>
                  <div class="um-switch-desc">Інші користувачі зможуть бачити вашу пошту</div>
                </div>
                <input type="checkbox" name="show_email" <?= !empty($u['show_email']) ? 'checked' : '' ?>>
                <span class="um-switch-toggle"></span>
              </label>
            </div>
          </div>
        </div>

        <div class="um-tab" data-tab-content="about">
          <div class="um-section">
            <div class="um-section-head">
              <?= icon('user', 14) ?> Про себе
              <span class="um-section-hint">макс 200 символів</span>
            </div>
            <textarea name="bio" rows="4" maxlength="200"
                      placeholder="Розкажіть про себе, свої інтереси, досвід..."
                      class="um-textarea" data-counter="bio"><?= e($u['bio'] ?? '') ?></textarea>
            <div class="um-counter"><span data-counter-view="bio"><?= mb_strlen($u['bio'] ?? '') ?></span>/200</div>
          </div>

          <div class="um-section">
            <div class="um-section-head">
              <?= icon('edit', 14) ?> Підпис
              <span class="um-section-hint">макс 200 символів</span>
            </div>
            <textarea name="signature" rows="3" maxlength="200"
                      placeholder="Підпис, який з'являється під вашими постами..."
                      class="um-textarea" data-counter="signature"><?= e($u['signature'] ?? '') ?></textarea>
            <div class="um-counter"><span data-counter-view="signature"><?= mb_strlen($u['signature'] ?? '') ?></span>/200</div>
          </div>
        </div>

        <div class="um-tab" data-tab-content="contacts">
          <div class="um-section">
            <div class="um-section-head"><?= icon('globe', 14) ?> Соціальні мережі</div>
            <div class="um-fields">
              <div class="um-field">
                <label><?= icon('globe', 12) ?> Веб-сайт</label>
                <div class="um-input-wrap">
                  <span class="um-input-ico"><?= icon('globe', 14) ?></span>
                  <input type="url" name="website" maxlength="255" placeholder="https://example.com" value="<?= e($u['website'] ?? '') ?>">
                </div>
              </div>
              <div class="um-field">
                <label><?= icon('send', 12) ?> Telegram</label>
                <div class="um-input-wrap">
                  <span class="um-input-ico"><?= icon('send', 14) ?></span>
                  <input type="text" name="social_telegram" maxlength="100" placeholder="@username" value="<?= e($u['social_telegram'] ?? '') ?>">
                </div>
              </div>
              <div class="um-field um-field-full">
                <label><?= icon('message', 12) ?> Discord</label>
                <div class="um-input-wrap">
                  <span class="um-input-ico"><?= icon('message', 14) ?></span>
                  <input type="text" name="social_discord" maxlength="100" placeholder="username#0000" value="<?= e($u['social_discord'] ?? '') ?>">
                </div>
              </div>
            </div>
          </div>

          <div class="um-section">
            <div class="um-section-head"><?= icon('lock', 14) ?> Безпека</div>
            <div class="um-field um-field-full">
              <label><?= icon('lock', 12) ?> Новий пароль</label>
              <div class="um-input-wrap">
                <span class="um-input-ico"><?= icon('lock', 14) ?></span>
                <input type="password" name="password" id="newPassword" placeholder="Залишіть порожнім, щоб не змінювати" minlength="6">
                <button type="button" class="um-input-eye" data-toggle="newPassword"><?= icon('eye', 14) ?></button>
              </div>
              <div class="um-field-hint">Мінімум 6 символів</div>
            </div>
          </div>
        </div>

        <?php if (isAdmin()): ?>
        <div class="um-tab" data-tab-content="admin">
          <div class="um-section">
            <div class="um-section-head danger"><?= icon('shield', 14) ?> Адміністративні налаштування</div>

            <div class="um-fields">
              <div class="um-field">
                <label><?= icon('user', 12) ?> Логін</label>
                <input type="text" name="username" required value="<?= e($u['username']) ?>">
              </div>
              <div class="um-field">
                <label><?= icon('send', 12) ?> Email</label>
                <input type="email" name="email" required value="<?= e($u['email']) ?>">
              </div>
            </div>

            <div class="um-field um-field-full">
              <label><?= icon('shield', 12) ?> Роль</label>
              <select name="role_id">
                <?php foreach ($allRoles as $r): ?>
                  <option value="<?= (int)$r['id'] ?>" <?= (int)($u['role_id'] ?? 0) === (int)$r['id'] ? 'selected' : '' ?>>
                    <?= e($r['name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <?php if (!$isOwn): ?>
              <div class="um-switches" style="margin-top:12px">
                <label class="um-switch danger">
                  <div class="um-switch-body">
                    <div class="um-switch-title"><?= icon('lock', 13) ?> Забанити користувача</div>
                    <div class="um-switch-desc">Користувач не зможе зайти на сайт</div>
                  </div>
                  <input type="checkbox" name="is_banned" <?= !empty($u['is_banned']) ? 'checked' : '' ?>>
                  <span class="um-switch-toggle"></span>
                </label>
              </div>
            <?php endif; ?>
          </div>
        </div>
        <?php endif; ?>

      </div>

      <div class="user-modal-foot">
        <div class="um-foot-info"><?= icon('shield', 12) ?> Ваші дані захищені</div>
        <div class="um-foot-actions">
          <button type="button" class="um-btn um-btn-ghost" data-close-modal>Скасувати</button>
          <button type="submit" class="um-btn um-btn-primary">
            <?= icon('check', 15) ?> Зберегти зміни
          </button>
        </div>
      </div>

    </form>
  </div>
</div>
<?php endif; ?>

<script>
(function(){
  const $ = id => document.getElementById(id);
  const modal = $('editProfileModal');

  const openBtn = $('openEditProfile');
  if (openBtn && modal) {
    openBtn.addEventListener('click', () => {
      modal.classList.add('open');
      document.body.style.overflow = 'hidden';
    });
  }

  document.querySelectorAll('[data-close-modal]').forEach(el => {
    el.addEventListener('click', () => {
      const m = el.closest('.user-modal');
      if (m) m.classList.remove('open');
      document.body.style.overflow = '';
    });
  });

  if (modal) {
    modal.addEventListener('click', e => {
      if (e.target === modal) {
        modal.classList.remove('open');
        document.body.style.overflow = '';
      }
    });
  }

  document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
      document.querySelectorAll('.user-modal.open').forEach(m => {
        m.classList.remove('open');
        document.body.style.overflow = '';
      });
    }
  });

  /* Таби */
  document.querySelectorAll('.um-nav-item').forEach(item => {
    item.addEventListener('click', () => {
      const tab = item.dataset.tab;
      document.querySelectorAll('.um-nav-item').forEach(n => n.classList.remove('active'));
      document.querySelectorAll('.um-tab').forEach(c => c.classList.remove('active'));
      item.classList.add('active');
      const content = document.querySelector(`[data-tab-content="${tab}"]`);
      if (content) content.classList.add('active');
    });
  });

  /* Прев'ю аватара */
  [$('avatarInput'), $('avatarInput2')].filter(Boolean).forEach(inp => {
    inp.addEventListener('change', e => {
      const file = e.target.files[0];
      const preview = $('avatarPreview');
      if (file && preview) {
        const reader = new FileReader();
        reader.onload = ev => { preview.src = ev.target.result; };
        reader.readAsDataURL(file);
      }
    });
  });

  /* Показ пароля */
  document.querySelectorAll('[data-toggle]').forEach(btn => {
    btn.addEventListener('click', () => {
      const inp = $(btn.dataset.toggle);
      if (!inp) return;
      const isPass = inp.type === 'password';
      inp.type = isPass ? 'text' : 'password';
      btn.innerHTML = isPass
        ? '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9.9 5.1A10 10 0 0 1 12 5c6 0 10 7 10 7a18 18 0 0 1-3 4M6.6 6.6A18 18 0 0 0 2 12s4 7 10 7c2 0 3.8-.9 5.3-2M3 3l18 18"/></svg>'
        : '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>';
    });
  });

  /* Лічильники */
  document.querySelectorAll('[data-counter]').forEach(el => {
    const key = el.dataset.counter;
    const view = document.querySelector(`[data-counter-view="${key}"]`);
    if (!view) return;
    const update = () => {
      const len = el.value.length;
      view.textContent = len;
      const counter = view.closest('.um-counter');
      if (counter) {
        counter.classList.toggle('warn', len > 180);
        counter.classList.toggle('danger', len > 195);
      }
    };
    el.addEventListener('input', update);
    update();
  });
})();
</script>

<?php require __DIR__ . '/footer.php'; ?>
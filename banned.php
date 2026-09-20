<?php
require_once __DIR__ . '/functions.php';

$banUserId = (int)($_SESSION['banned_user_id'] ?? 0);
if (!$banUserId) {
    redirect(SITE_URL . '/login.php');
}

$stmt = $pdo->prepare("SELECT id, username, avatar FROM users WHERE id = ?");
$stmt->execute([$banUserId]);
$u = $stmt->fetch();

if (!$u) {
    unset($_SESSION['banned_user_id']);
    redirect(SITE_URL . '/login.php');
}

$stmt = $pdo->prepare("
    SELECT p.*, m.username AS issued_by_name
    FROM user_punishments p
    LEFT JOIN users m ON p.issued_by = m.id
    WHERE p.user_id = ? AND p.type = 'ban' AND p.is_active = 1
      AND (p.expires_at IS NULL OR p.expires_at > NOW())
    ORDER BY p.issued_at DESC LIMIT 1
");
$stmt->execute([$banUserId]);
$ban = $stmt->fetch();

if (!$ban) {
    unset($_SESSION['banned_user_id']);
    redirect(SITE_URL . '/login.php');
}

/* Апеляція */
$appeal = null;
try {
    $stmt = $pdo->prepare("SELECT * FROM appeals WHERE punishment_id = ? AND user_id = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$ban['id'], $banUserId]);
    $appeal = $stmt->fetch();
} catch (Exception $e) {}

/* Час */
$durationText = 'Назавжди';
if (!empty($ban['expires_at'])) {
    $left = strtotime($ban['expires_at']) - time();
    if ($left <= 0) {
        $durationText = 'Термін вийшов';
    } else {
        $days = floor($left / 86400);
        $hours = floor(($left % 86400) / 3600);
        $mins = floor(($left % 3600) / 60);
        if ($days > 0) $durationText = $days . ' дн ' . $hours . ' год';
        elseif ($hours > 0) $durationText = $hours . ' год ' . $mins . ' хв';
        else $durationText = $mins . ' хв';
    }
}

function bd($dt) {
    if (!$dt) return '—';
    return date('d.m.Y H:i', strtotime($dt));
}
?>
<!DOCTYPE html>
<html lang="uk">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Вас забанено — <?= e(SITE_NAME) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/animations.css">
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/appeal.css">
</head>
<body class="banned-body">

<div class="bg-grid"></div>
<div class="bg-glow"></div>

<div class="banned-scene">

  <div class="banned-card">

    <div class="banned-badge">
      <?= icon('shield', 12) ?>
      <?= e(SITE_NAME) ?> · Доступ заборонено
    </div>

    <div class="banned-shield">
      <div class="banned-shield-ring"></div>
      <div class="banned-shield-ring r2"></div>
      <div class="banned-shield-ico"><?= icon('lock', 44) ?></div>
    </div>

    <h1 class="banned-title">Вас забанено</h1>
    <p class="banned-subtitle">
      На жаль, доступ до форуму обмежено.<br>
      Нижче вказані деталі вашого бану.
    </p>

    <div class="banned-info">

      <div class="banned-info-row">
        <div class="banned-info-ico"><?= icon('user', 16) ?></div>
        <div class="banned-info-body">
          <div class="banned-info-lbl">Користувач</div>
          <div class="banned-info-val"><?= e($u['username']) ?></div>
        </div>
      </div>

      <div class="banned-info-row">
        <div class="banned-info-ico danger"><?= icon('x', 16) ?></div>
        <div class="banned-info-body">
          <div class="banned-info-lbl">Причина</div>
          <div class="banned-info-val"><?= e($ban['reason']) ?></div>
        </div>
      </div>

      <div class="banned-info-row">
        <div class="banned-info-ico warn"><?= icon('shield', 16) ?></div>
        <div class="banned-info-body">
          <div class="banned-info-lbl">Хто видав</div>
          <div class="banned-info-val"><?= e($ban['issued_by_name'] ?: 'Система') ?></div>
        </div>
      </div>

      <div class="banned-info-row">
        <div class="banned-info-ico"><?= icon('clock', 16) ?></div>
        <div class="banned-info-body">
          <div class="banned-info-lbl">Коли видано</div>
          <div class="banned-info-val"><?= bd($ban['issued_at']) ?></div>
        </div>
      </div>

      <div class="banned-info-row">
        <div class="banned-info-ico danger"><?= icon('lock', 16) ?></div>
        <div class="banned-info-body">
          <div class="banned-info-lbl">Термін</div>
          <div class="banned-info-val">
            <?= e($durationText) ?>
            <?php if (!empty($ban['expires_at'])): ?>
              <span class="banned-hint">(до <?= bd($ban['expires_at']) ?>)</span>
            <?php endif; ?>
          </div>
        </div>
      </div>

    </div>

    <?php if ($appeal): ?>
      <div class="banned-appeal-active">
        <div class="banned-appeal-active-head">
          <?= icon('message', 16) ?>
          <span>
            <?= $appeal['status'] === 'open' ? 'Ваша апеляція на розгляді' : 'Апеляція закрита' ?>
          </span>
        </div>
        <a href="<?= SITE_URL ?>/appeal.php?id=<?= (int)$appeal['id'] ?>" class="banned-btn banned-btn-primary">
          <?= icon('message', 16) ?>
          <?= $appeal['status'] === 'open' ? 'Відкрити чат' : 'Переглянути' ?>
        </a>
      </div>
    <?php else: ?>
      <button type="button" class="banned-btn banned-btn-primary" id="openAppealModal">
        <?= icon('edit', 16) ?> Подати апеляцію
      </button>
    <?php endif; ?>

    <a href="<?= SITE_URL ?>/logout.php" class="banned-logout">
      <?= icon('logout', 13) ?> Вийти з акаунту
    </a>

  </div>

  <div class="banned-footer">
    © <?= date('Y') ?> <?= e(SITE_NAME) ?>
  </div>

</div>

<!-- Модалка апеляції -->
<div class="appeal-modal" id="appealModal">
  <div class="appeal-modal-box">
    <div class="appeal-modal-head">
      <div class="appeal-modal-head-left">
        <div class="appeal-modal-ico"><?= icon('message', 18) ?></div>
        <div>
          <h3>Подати апеляцію</h3>
          <p>Розкажіть адміністрації свою ситуацію</p>
        </div>
      </div>
      <button type="button" class="appeal-modal-close" data-close-modal><?= icon('x', 16) ?></button>
    </div>

    <form id="appealForm" class="appeal-modal-form">
      <input type="hidden" name="csrf" value="<?= csrf() ?>">
      <input type="hidden" name="punishment_id" value="<?= (int)$ban['id'] ?>">

      <label>
        <span class="appeal-modal-lbl">Ваше повідомлення</span>
        <textarea name="message" rows="7" required minlength="10" maxlength="2000"
                  placeholder="Опишіть чому ваш бан треба зняти..."></textarea>
      </label>

      <div class="appeal-modal-hint">
        <?= icon('sparkle', 12) ?> Мінімум 10 символів
      </div>

      <div class="appeal-modal-actions">
        <button type="button" class="appeal-modal-btn appeal-modal-btn-ghost" data-close-modal>
          Скасувати
        </button>
        <button type="submit" class="appeal-modal-btn appeal-modal-btn-primary">
          <?= icon('send', 15) ?> Надіслати
        </button>
      </div>
    </form>
  </div>
</div>

<script>
window.SITE_URL = <?= json_encode(SITE_URL) ?>;
window.CSRF     = <?= json_encode(csrf()) ?>;

(function(){
  const modal = document.getElementById('appealModal');
  const openBtn = document.getElementById('openAppealModal');
  const form = document.getElementById('appealForm');

  if (openBtn && modal) {
    openBtn.addEventListener('click', () => {
      modal.classList.add('show');
      document.body.style.overflow = 'hidden';
      setTimeout(() => {
        const ta = modal.querySelector('textarea');
        if (ta) ta.focus();
      }, 200);
    });
  }

  document.querySelectorAll('[data-close-modal]').forEach(el => {
    el.addEventListener('click', () => {
      modal.classList.remove('show');
      document.body.style.overflow = '';
    });
  });

  if (modal) {
    modal.addEventListener('click', e => {
      if (e.target === modal) {
        modal.classList.remove('show');
        document.body.style.overflow = '';
      }
    });
  }

  document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && modal) {
      modal.classList.remove('show');
      document.body.style.overflow = '';
    }
  });

  if (form) {
    form.addEventListener('submit', async e => {
      e.preventDefault();
      const data = new FormData(form);
      const btn = form.querySelector('button[type="submit"]');
      const origHtml = btn.innerHTML;
      btn.disabled = true;
      btn.innerHTML = '⏳ Надсилаємо...';

      try {
        const res = await fetch(window.SITE_URL + '/api/appeal.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            action: 'create',
            punishment_id: parseInt(data.get('punishment_id')),
            message: data.get('message'),
            csrf: data.get('csrf')
          })
        });
        const r = await res.json();
        if (r.ok) {
          location.href = window.SITE_URL + '/appeal.php?id=' + r.appeal_id;
        } else {
          alert(r.error || 'Помилка');
          btn.disabled = false;
          btn.innerHTML = origHtml;
        }
      } catch(err){
        alert('Помилка мережі');
        btn.disabled = false;
        btn.innerHTML = origHtml;
      }
    });
  }
})();
</script>

</body>
</html>
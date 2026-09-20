<?php
require_once __DIR__ . '/functions.php';
if (isLoggedIn()) redirect(SITE_URL);

$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!checkCsrf($_POST['csrf'] ?? '')) {
        $err = 'Помилка безпеки';
    } else {
        $login = trim($_POST['login'] ?? '');
        $pass  = $_POST['password'] ?? '';
        $stmt  = $pdo->prepare("SELECT * FROM users WHERE username=? OR email=?");
        $stmt->execute([$login, $login]);
        $u = $stmt->fetch();

        if ($u && password_verify($pass, $u['password'])) {

            /* ─── Перевірка активного бану ─── */
            $banStmt = $pdo->prepare("
                SELECT * FROM user_punishments
                WHERE user_id = ? AND type = 'ban' AND is_active = 1
                  AND (expires_at IS NULL OR expires_at > NOW())
                ORDER BY issued_at DESC LIMIT 1
            ");
            $banStmt->execute([$u['id']]);
            $activeBan = $banStmt->fetch();

            if ($activeBan) {
                $_SESSION['banned_user_id'] = (int)$u['id'];
                redirect(SITE_URL . '/banned.php');
            } else {
                // Все гаразд — пускаємо
                session_regenerate_id(true);
                $_SESSION['user_id'] = $u['id'];
                unset($_SESSION['banned_user_id']);
                flash('Вітаємо, ' . $u['username'] . '!', 'success');
                redirect(SITE_URL);
            }

        } else {
            $err = 'Невірний логін або пароль';
        }
    }
}

$pageTitle = 'Вхід';
require __DIR__ . '/header.php';
?>

<div class="auth-page">
  <div class="auth-split">

    <div class="auth-hero">
      <div class="auth-hero-bg"></div>
      <div class="auth-hero-grid"></div>

      <div class="auth-hero-content">
        <a href="<?= SITE_URL ?>" class="auth-hero-logo">
          <?= siteLogo(34) ?>
          <span><?= SITE_NAME ?></span>
        </a>

        <div class="auth-hero-body">
          <div class="auth-hero-badge">
            <?= icon('sparkle', 12) ?> Вхід у систему
          </div>

          <h2>З поверненням до <span class="gradient-text">спільноти</span></h2>
          <p>Увійдіть, щоб продовжити спілкування та відкрити всі можливості форуму.</p>

          <div class="auth-hero-features">
            <div class="auth-hero-feature">
              <span class="auth-hero-feature-ico"><?= icon('message', 17) ?></span>
              <div>
                <b>Живі обговорення</b>
                <span>Спілкуйтесь у темах та коментарях</span>
              </div>
            </div>
            <div class="auth-hero-feature">
              <span class="auth-hero-feature-ico"><?= icon('users', 17) ?></span>
              <div>
                <b>Спільнота</b>
                <span>Знаходьте однодумців та нових друзів</span>
              </div>
            </div>
            <div class="auth-hero-feature">
              <span class="auth-hero-feature-ico"><?= icon('zap', 17) ?></span>
              <div>
                <b>Швидко та зручно</b>
                <span>Мінімум кліків — максимум результату</span>
              </div>
            </div>
          </div>
        </div>

        <div class="auth-hero-footer">
          <span><?= icon('clock', 11) ?> <?= date('Y') ?> <?= SITE_NAME ?></span>
          <span class="auth-hero-stats">
            <i></i> Онлайн
          </span>
        </div>
      </div>
    </div>

    <div class="auth-form-side">
      <div class="auth-box">

        <div class="auth-box-head">
          <div class="auth-box-ico"><?= icon('shield', 24) ?></div>
          <h1>Вхід в акаунт</h1>
          <p class="muted">Введіть свої дані для продовження</p>
        </div>

        <?php if ($err): ?>
          <div class="alert error">
            <span class="alert-ico"><?= icon('x', 15) ?></span>
            <span><?= e($err) ?></span>
          </div>
        <?php endif; ?>

        <form method="post" class="auth-form-body" autocomplete="on">
          <input type="hidden" name="csrf" value="<?= csrf() ?>">

          <label class="auth-field">
            <span class="auth-field-lbl">
              <?= icon('user', 13) ?>
              <span>Логін або Email</span>
            </span>
            <div class="auth-input-wrap">
              <span class="auth-input-ico"><?= icon('user', 15) ?></span>
              <input type="text" name="login" required autofocus
                     placeholder="admin123 або email@example.com"
                     value="<?= e($_POST['login'] ?? '') ?>">
            </div>
          </label>

          <label class="auth-field">
            <span class="auth-field-lbl">
              <?= icon('lock', 13) ?>
              <span>Пароль</span>
            </span>
            <div class="auth-input-wrap">
              <span class="auth-input-ico"><?= icon('lock', 15) ?></span>
              <input type="password" name="password" id="passInput" required
                     placeholder="••••••••">
              <button type="button" class="auth-eye" data-toggle-pass>
                <?= icon('eye', 15) ?>
              </button>
            </div>
          </label>

          <button type="submit" class="auth-submit">
            <span>Увійти</span>
            <?= icon('arrow-right', 16) ?>
          </button>
        </form>

        <div class="auth-divider">
          <span>Немає акаунту?</span>
        </div>

        <a href="<?= SITE_URL ?>/register.php" class="auth-alt-btn">
          <?= icon('sparkle', 15) ?>
          <span>Створити акаунт</span>
          <?= icon('arrow-right', 14) ?>
        </a>

      </div>
    </div>

  </div>
</div>

<script>
  const btn = document.querySelector('[data-toggle-pass]');
  const inp = document.getElementById('passInput');
  if (btn && inp) {
    btn.addEventListener('click', () => {
      const isPass = inp.type === 'password';
      inp.type = isPass ? 'text' : 'password';
      btn.innerHTML = isPass
        ? '<svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9.9 5.1A10 10 0 0 1 12 5c6 0 10 7 10 7a18 18 0 0 1-3 4M6.6 6.6A18 18 0 0 0 2 12s4 7 10 7c2 0 3.8-.9 5.3-2M3 3l18 18"/></svg>'
        : '<svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>';
    });
  }
</script>

<?php require __DIR__ . '/footer.php'; ?>
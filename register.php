<?php
require_once __DIR__ . '/functions.php';
if (isLoggedIn()) redirect(SITE_URL);

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!checkCsrf($_POST['csrf'] ?? '')) {
        $err = 'Помилка безпеки';
    } else {
        $u  = trim($_POST['username'] ?? '');
        $e  = trim($_POST['email'] ?? '');
        $p  = $_POST['password'] ?? '';
        $p2 = $_POST['password2'] ?? '';

        if (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $u)) $err = 'Логін: 3-30 символів (a-z, 0-9, _)';
        elseif (!filter_var($e, FILTER_VALIDATE_EMAIL))  $err = 'Невірний email';
        elseif (mb_strlen($p) < 6)                       $err = 'Пароль мінімум 6 символів';
        elseif ($p !== $p2)                              $err = 'Паролі не співпадають';
        else {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username=? OR email=?");
            $stmt->execute([$u, $e]);
            if ($stmt->fetch()) {
                $err = 'Користувач з таким логіном або email вже існує';
            } else {
                $hash = password_hash($p, PASSWORD_DEFAULT);
                $pdo->prepare("INSERT INTO users (username, email, password) VALUES (?,?,?)")
                    ->execute([$u, $e, $hash]);
                session_regenerate_id(true);
                $_SESSION['user_id'] = $pdo->lastInsertId();
                flash('Акаунт створено! Вітаємо у спільноті', 'success');
                redirect(SITE_URL);
            }
        }
    }
}
$pageTitle = 'Реєстрація';
require __DIR__ . '/header.php';
?>

<div class="auth-page">
  <div class="auth-split">

    <!-- ЛІВА: ГРАФІКА -->
    <div class="auth-hero">
      <div class="auth-hero-bg"></div>
      <div class="auth-hero-grid"></div>
      <div class="auth-hero-orbs">
        <span></span><span></span><span></span>
      </div>

      <div class="auth-hero-content">
        <a href="<?= SITE_URL ?>" class="auth-hero-logo">
          <span class="logo-mark"><?= icon('zap', 18) ?></span>
          <span><?= SITE_NAME ?></span>
        </a>

        <div class="auth-hero-body">
          <div class="auth-hero-badge">
            <?= icon('sparkle', 12) ?> Приєднайся сьогодні
          </div>

          <h2>Створи свій <span class="gradient-text">акаунт</span></h2>
          <p>Приєднуйся до спільноти за хвилину та почни спілкування прямо зараз.</p>

          <div class="auth-hero-features">
            <div class="auth-hero-feature">
              <span class="auth-hero-feature-ico">
                <?= icon('zap', 17) ?>
              </span>
              <div>
                <b>Швидка реєстрація</b>
                <span>Тільки логін, email і пароль</span>
              </div>
            </div>
            <div class="auth-hero-feature">
              <span class="auth-hero-feature-ico">
                <?= icon('shield', 17) ?>
              </span>
              <div>
                <b>Безпека даних</b>
                <span>Пароль хешується за сучасними стандартами</span>
              </div>
            </div>
            <div class="auth-hero-feature">
              <span class="auth-hero-feature-ico">
                <?= icon('heart', 17) ?>
              </span>
              <div>
                <b>Спілкуйся вільно</b>
                <span>Створюй теми, відповідай, лайкай</span>
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

    <!-- ПРАВА: ФОРМА -->
    <div class="auth-form-side">
      <div class="auth-box">

        <div class="auth-box-head">
          <div class="auth-box-ico"><?= icon('user', 24) ?></div>
          <h1>Створити акаунт</h1>
          <p class="muted">Заповніть поля нижче, щоб почати</p>
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
              <span>Логін</span>
            </span>
            <div class="auth-input-wrap">
              <span class="auth-input-ico"><?= icon('user', 15) ?></span>
              <input type="text" name="username" required minlength="3" maxlength="30"
                     pattern="[a-zA-Z0-9_]+"
                     placeholder="my_username"
                     value="<?= e($_POST['username'] ?? '') ?>">
            </div>
            <span class="auth-field-hint">
              <?= icon('check', 10) ?> 3-30 символів (a-z, 0-9, _)
            </span>
          </label>

          <label class="auth-field">
            <span class="auth-field-lbl">
              <?= icon('send', 13) ?>
              <span>Email</span>
            </span>
            <div class="auth-input-wrap">
              <span class="auth-input-ico"><?= icon('send', 15) ?></span>
              <input type="email" name="email" required maxlength="120"
                     placeholder="you@example.com"
                     value="<?= e($_POST['email'] ?? '') ?>">
            </div>
          </label>

          <label class="auth-field">
            <span class="auth-field-lbl">
              <?= icon('lock', 13) ?>
              <span>Пароль</span>
            </span>
            <div class="auth-input-wrap">
              <span class="auth-input-ico"><?= icon('lock', 15) ?></span>
              <input type="password" name="password" id="passInput1" required minlength="6"
                     placeholder="••••••••">
              <button type="button" class="auth-eye" data-toggle-pass="1">
                <?= icon('eye', 15) ?>
              </button>
            </div>

            <div class="auth-pass-strength" id="passStrength">
              <div class="auth-strength-bars">
                <span></span><span></span><span></span><span></span><span></span>
              </div>
              <div class="auth-strength-text">Надійність пароля</div>
            </div>
          </label>

          <label class="auth-field">
            <span class="auth-field-lbl">
              <?= icon('check', 13) ?>
              <span>Повторіть пароль</span>
            </span>
            <div class="auth-input-wrap">
              <span class="auth-input-ico"><?= icon('shield', 15) ?></span>
              <input type="password" name="password2" id="passInput2" required minlength="6"
                     placeholder="••••••••">
              <button type="button" class="auth-eye" data-toggle-pass="2">
                <?= icon('eye', 15) ?>
              </button>
            </div>
          </label>

          <button type="submit" class="auth-submit">
            <span>Зареєструватись</span>
            <?= icon('sparkle', 16) ?>
          </button>

          <p class="auth-terms">
            <?= icon('shield', 11) ?>
            Реєструючись, ви погоджуєтесь з правилами форуму
          </p>
        </form>

        <div class="auth-divider">
          <span>Вже маєте акаунт?</span>
        </div>

        <a href="<?= SITE_URL ?>/login.php" class="auth-alt-btn">
          <?= icon('logout', 15) ?>
          <span>Увійти в акаунт</span>
          <?= icon('arrow-right', 14) ?>
        </a>

      </div>
    </div>

  </div>
</div>

<script>
  const EYE_OPEN = '<svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>';
  const EYE_CLOSED = '<svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9.9 5.1A10 10 0 0 1 12 5c6 0 10 7 10 7a18 18 0 0 1-3 4M6.6 6.6A18 18 0 0 0 2 12s4 7 10 7c2 0 3.8-.9 5.3-2M3 3l18 18"/></svg>';

  document.querySelectorAll('[data-toggle-pass]').forEach(btn => {
    btn.addEventListener('click', () => {
      const n = btn.dataset.togglePass;
      const inp = document.getElementById('passInput' + n);
      if (!inp) return;
      const isPass = inp.type === 'password';
      inp.type = isPass ? 'text' : 'password';
      btn.innerHTML = isPass ? EYE_CLOSED : EYE_OPEN;
    });
  });

  const pass = document.getElementById('passInput1');
  const strength = document.getElementById('passStrength');
  if (pass && strength) {
    pass.addEventListener('input', () => {
      const v = pass.value;
      let score = 0;
      if (v.length >= 6) score++;
      if (v.length >= 10) score++;
      if (/[A-Z]/.test(v)) score++;
      if (/[0-9]/.test(v)) score++;
      if (/[^A-Za-z0-9]/.test(v)) score++;

      const bars = strength.querySelectorAll('.auth-strength-bars span');
      const text = strength.querySelector('.auth-strength-text');

      const colors = ['#ef4444', '#f59e0b', '#eab308', '#4ade80', '#4ade80'];
      const labels = ['Дуже слабкий', 'Слабкий', 'Середній', 'Хороший', 'Надійний'];

      bars.forEach((b, i) => {
        if (i < score) {
          b.style.background = colors[score - 1];
          b.style.opacity = '1';
        } else {
          b.style.background = 'var(--bg-4)';
          b.style.opacity = '1';
        }
      });

      if (score > 0) {
        text.textContent = labels[score - 1];
        text.style.color = colors[score - 1];
      } else {
        text.textContent = 'Надійність пароля';
        text.style.color = '';
      }
    });
  }
</script>

<?php require __DIR__ . '/footer.php'; ?>
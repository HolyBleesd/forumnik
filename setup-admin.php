<?php
require_once __DIR__ . '/config.php';

/* ═══════════════════════════════════════════
   ДАНІ АДМІНА — зміни під себе
   ═══════════════════════════════════════════ */
$ADMIN_USERNAME = 'admin123';
$ADMIN_PASSWORD = '123321';
$ADMIN_EMAIL    = 'admin123@forum.local';
/* ═══════════════════════════════════════════ */

header('Content-Type: text/html; charset=utf-8');

$status = 'ok';
$msg    = '';

try {
    // Перевіряємо, чи вже є такий користувач
    $stmt = $pdo->prepare("SELECT id, username FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$ADMIN_USERNAME, $ADMIN_EMAIL]);
    $existing = $stmt->fetch();

    $hash = password_hash($ADMIN_PASSWORD, PASSWORD_DEFAULT);

    if ($existing) {
        $pdo->prepare("UPDATE users SET password=?, role='admin', is_banned=0 WHERE id=?")
            ->execute([$hash, $existing['id']]);
        $msg = 'Користувача <b>' . htmlspecialchars($ADMIN_USERNAME) . '</b> оновлено — пароль змінено, роль <b>admin</b> надано.';
    } else {
        $pdo->prepare("INSERT INTO users (username, email, password, role) VALUES (?,?,?,'admin')")
            ->execute([$ADMIN_USERNAME, $ADMIN_EMAIL, $hash]);
        $msg = 'Адмін-акаунт успішно створено!';
    }
} catch (Exception $e) {
    $status = 'err';
    $msg = 'Помилка: ' . htmlspecialchars($e->getMessage());
}
?><!DOCTYPE html>
<html lang="uk">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Створення адміна</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Inter', sans-serif;
    background: #0a0a0b; color: #ebedef;
    min-height: 100vh; padding: 40px 20px;
    display: flex; align-items: flex-start; justify-content: center;
    line-height: 1.6;
  }
  .box {
    max-width: 520px; width: 100%;
    background: #151617; border: 1px solid #262729;
    border-radius: 14px; padding: 32px;
    box-shadow: 0 12px 40px rgba(0,0,0,.5);
  }
  h1 { font-size: 20px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
  .ok { color: #4ade80; }
  .err { color: #f87171; }
  .msg {
    padding: 14px 16px; border-radius: 10px; margin-bottom: 20px;
    background: #1c1d1f; border: 1px solid #262729; font-size: 14px;
  }
  .msg.ok { border-color: rgba(74,222,128,.3); background: rgba(74,222,128,.06); color: #86efac; }
  .msg.err { border-color: rgba(239,68,68,.3); background: rgba(239,68,68,.06); color: #fca5a5; }
  .creds {
    background: #1c1d1f; border: 1px solid #262729;
    border-radius: 10px; padding: 16px 20px; margin: 16px 0;
    font-family: 'JetBrains Mono', Consolas, monospace; font-size: 13px;
  }
  .creds div { display: flex; justify-content: space-between; padding: 4px 0; }
  .creds b { color: #6e7ef0; }
  .actions { display: flex; gap: 10px; margin-top: 20px; flex-wrap: wrap; }
  .btn {
    display: inline-flex; align-items: center; gap: 7px;
    padding: 10px 18px; border-radius: 9px; font-size: 14px; font-weight: 500;
    text-decoration: none; transition: .18s;
    border: 1px solid transparent;
  }
  .btn-primary { background: #5b6ee1; color: #fff; }
  .btn-primary:hover { background: #6e7ef0; }
  .warn {
    margin-top: 24px; padding: 14px 16px;
    background: rgba(239,68,68,.08); border: 1px solid rgba(239,68,68,.3);
    border-radius: 10px; color: #fca5a5; font-size: 13.5px;
  }
  code {
    background: #1c1d1f; padding: 2px 7px; border-radius: 5px;
    font-family: 'JetBrains Mono', Consolas, monospace; font-size: 12.5px;
    color: #ebedef;
  }
  .muted { color: #9ba1a6; font-size: 12.5px; margin-top: 6px; }
</style>
</head>
<body>
<div class="box">
  <h1>🔐 Створення адміна</h1>

  <div class="msg <?= $status === 'ok' ? 'ok' : 'err' ?>"><?= $msg ?></div>

  <?php if ($status === 'ok'): ?>
    <div class="creds">
      <div><span>Логін:</span>  <b><?= htmlspecialchars($ADMIN_USERNAME) ?></b></div>
      <div><span>Пароль:</span> <b><?= htmlspecialchars($ADMIN_PASSWORD) ?></b></div>
      <div><span>Роль:</span>   <b>admin</b></div>
    </div>

    <div class="actions">
      <a href="<?= htmlspecialchars(SITE_URL) ?>/login.php" class="btn btn-primary">
        → Увійти
      </a>
    </div>

    <div class="warn">
      ⚠️ <b>Терміново видали цей файл після входу!</b>
      <p class="muted">Якщо залишиш — будь-хто зможе скинути пароль твого адміна.</p>
    </div>
  <?php endif; ?>
</div>
</body>
</html>
</main>

<footer class="site-footer">
  <div class="container">
    <div class="footer-top">
      <?php
        $__socials = function_exists('getActiveSocials') ? getActiveSocials() : [];
      ?>
      <?php if ($__socials): ?>
        <div class="footer-socials">
          <?php foreach ($__socials as $s): ?>
            <a href="<?= e($s['url']) ?>" target="_blank" rel="noopener"
               class="footer-social footer-social-<?= e($s['key']) ?>"
               title="<?= e($s['name']) ?>">
              <?= socialIcon($s['key'], 20) ?>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <div class="footer-links">
        <a href="<?= SITE_URL ?>/page.php?slug=policy">
          <?= icon('lock', 12) ?> Політика конфіденційності
        </a>
        <a href="<?= SITE_URL ?>/page.php?slug=terms">
          <?= icon('file-text', 12) ?> Умови і правила
        </a>
      </div>
    </div>

    <div class="footer-bottom">
      <p class="footer-brand">
        <?= siteLogo(22) ?>
        <span><?= SITE_NAME ?></span>
        <span class="muted">· © <?= date('Y') ?></span>
      </p>
      <p class="muted small footer-time">
        <?= icon('clock', 12) ?>
        <span data-local-time="full">--:--:--</span>
      </p>
    </div>
  </div>
</footer>

<div id="toast" class="toast"></div>

<script>
  // Ховаємо футер на auth-сторінках
  if (document.querySelector('.auth-page')) {
    document.body.classList.add('auth-body');
    var footer = document.querySelector('.site-footer');
    if (footer) footer.style.display = 'none';
  }
</script>

<script>
  window.SITE_URL  = <?= json_encode(SITE_URL) ?>;
  window.CSRF      = <?= json_encode(csrf()) ?>;
  window.IS_LOGGED = <?= isLoggedIn() ? 'true' : 'false' ?>;
</script>
<script src="<?= SITE_URL ?>/assets/js/modals.js"></script>
<script src="<?= SITE_URL ?>/assets/js/animations.js"></script>
<script src="<?= SITE_URL ?>/assets/js/main.js"></script>
</body>
</html>
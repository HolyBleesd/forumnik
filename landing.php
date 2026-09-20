<?php
require_once __DIR__ . '/functions.php';
define('IS_LANDING', true);

$serverName  = getSetting('landing_server_name', 'NeverDM');
$tagline     = getSetting('landing_tagline',     'Найкращий дм-сервер');
$lore        = getSetting('landing_lore',        'Вітаємо на NeverDM — де кожен гравець пише власну історію.');
$serverIp    = getSetting('landing_ip',          '');
$discordUrl  = getSetting('landing_discord_url', '');
$launcherUrl = getSetting('landing_launcher_url','#launcher');

$laBg = 'https://images7.alphacoders.com/853/853456.jpg';

$featuresRaw = getSetting('landing_features', '');
$features = array_filter(array_map('trim', explode("\n", $featuresRaw)));
if (!$features) {
    $features = ['Фракції та банди', 'Поліція та спецназ', 'Роботи та бізнес', 'Економіка сервера'];
}

$stats       = function_exists('getServerStats')   ? getServerStats()   : ['online_count'=>0,'max_online'=>0,'total_players'=>0,'uptime_percent'=>99.9];
$topPlayers  = function_exists('getTopPlayers')    ? getTopPlayers(5)   : [];
$faq         = function_exists('getFaq')           ? getFaq()           : [];
$forumStats  = function_exists('getForumStats')    ? getForumStats()    : ['users'=>0,'topics'=>0,'posts'=>0,'online'=>0];
$recentForum = function_exists('getForumActivity') ? getForumActivity(5) : [];

$pageTitle = $serverName . ' — Головна';
require __DIR__ . '/header.php';
?>

<style>
  :root{ --la-bg: url('<?= e($laBg) ?>'); }
</style>
<script>document.body.classList.add('landing-active');</script>

<!-- ═══════════════════════════════════════════════════════
     HERO
     ═══════════════════════════════════════════════════════ -->
<section class="lp-hero" id="hero">

  <div class="lp-layer lp-sky" data-layer="0.05">
    <div class="lp-stars">
      <?php for ($i = 0; $i < 40; $i++): ?>
        <span style="left:<?= rand(0,100) ?>%;top:<?= rand(0,65) ?>%;animation-delay:<?= rand(0,500)/100 ?>s"></span>
      <?php endfor; ?>
    </div>
  </div>

  <div class="lp-fog"></div>

  <div class="lp-particles">
    <?php for ($i = 0; $i < 24; $i++): ?>
      <span style="left:<?= rand(0,100) ?>%;animation-delay:<?= rand(0,900)/100 ?>s"></span>
    <?php endfor; ?>
  </div>

  <div class="lp-hero-content">
    <div class="lp-hero-badge">
      <span class="lp-dot"></span>
      <?= e($serverName) ?> · Онлайн
    </div>

    <h1 class="lp-hero-title">
      <span class="lp-title-1"><?= e($serverName) ?></span>
      <span class="lp-title-2"><?= e($tagline) ?></span>
    </h1>

    <p class="lp-hero-desc"><?= nl2br(e(mb_substr($lore, 0, 220))) ?><?= mb_strlen($lore) > 220 ? '…' : '' ?></p>

    <div class="lp-hero-actions">
      <button type="button" class="lp-btn lp-btn-primary" id="scrollToLauncher">
        <?= icon('arrow-right', 16) ?> Почати грати
      </button>
      <a href="<?= SITE_URL ?>/index.php" class="lp-btn lp-btn-ghost">
        <?= icon('message', 16) ?> Форум
      </a>
    </div>

    <div class="lp-hero-meta-row">
      <?php if ($serverIp): ?>
        <button type="button" class="lp-ip" data-copy="<?= e($serverIp) ?>">
          <span class="lp-ip-lbl">IP</span>
          <span class="lp-ip-val"><?= e($serverIp) ?></span>
          <span class="lp-ip-copy"><?= icon('file-text', 13) ?></span>
        </button>
      <?php endif; ?>

      <div class="lp-time">
        <?= icon('clock', 14) ?>
        <span data-local-time="full">--:--:--</span>
      </div>
    </div>
  </div>

  <div class="lp-live-bar">
    <div class="lp-live-item">
      <span class="lp-live-dot"></span>
      <span class="lp-live-lbl">Онлайн</span>
      <span class="lp-live-val" data-count="<?= (int)$stats['online_count'] ?>">0</span>
    </div>
    <div class="lp-live-item">
      <span class="lp-live-lbl">Макс. онлайн</span>
      <span class="lp-live-val" data-count="<?= (int)$stats['max_online'] ?>">0</span>
    </div>
    <div class="lp-live-item">
      <span class="lp-live-lbl">Всього гравців</span>
      <span class="lp-live-val" data-count="<?= (int)$stats['total_players'] ?>">0</span>
    </div>
    <div class="lp-live-item">
      <span class="lp-live-lbl">Аптайм</span>
      <span class="lp-live-val" data-count="<?= (int)$stats['uptime_percent'] ?>">0</span>
      <span class="lp-live-suf">%</span>
    </div>
  </div>

  <div class="lp-scroll">
    <span>ГОРТАЙТЕ</span>
    <div class="lp-scroll-line"></div>
  </div>
</section>

<!-- ═══════════════════════════════════════════════════════
     ПРО СЕРВЕР
     ═══════════════════════════════════════════════════════ -->
<section class="lp-section lp-about" id="about">
  <div class="lp-inner">
    <div class="lp-grid-2">

      <div class="lp-about-content">
        <div class="lp-tag"><?= icon('book', 13) ?> Про нас</div>
        <h2 class="lp-h2">Історія <span class="lp-accent"><?= e($serverName) ?></span></h2>
        <div class="lp-lore"><?= nl2br(e($lore)) ?></div>

        <div class="lp-info-grid">
          <div class="lp-info-row">
            <div class="lp-info-ico"><?= icon('users', 18) ?></div>
            <div>
              <div class="lp-info-num" data-count="<?= (int)$forumStats['users'] ?>">0</div>
              <div class="lp-info-lbl">Гравців на форумі</div>
            </div>
          </div>
          <div class="lp-info-row">
            <div class="lp-info-ico"><?= icon('message', 18) ?></div>
            <div>
              <div class="lp-info-num" data-count="<?= (int)$forumStats['topics'] ?>">0</div>
              <div class="lp-info-lbl">Обговорень</div>
            </div>
          </div>
          <div class="lp-info-row">
            <div class="lp-info-ico"><?= icon('file-text', 18) ?></div>
            <div>
              <div class="lp-info-num" data-count="<?= (int)$forumStats['posts'] ?>">0</div>
              <div class="lp-info-lbl">Повідомлень</div>
            </div>
          </div>
          <div class="lp-info-row">
            <div class="lp-info-ico"><?= icon('zap', 18) ?></div>
            <div>
              <div class="lp-info-num" data-count="<?= (int)$stats['uptime_percent'] ?>">0</div>
              <div class="lp-info-lbl">% Аптайм</div>
            </div>
          </div>
        </div>
      </div>

      <div class="lp-about-visual" data-tilt>
        <div class="lp-card">
          <div class="lp-card-head">
            <span class="lp-card-dot"></span>
            <span class="lp-card-title"><?= e($serverName) ?> · Live</span>
          </div>
          <div class="lp-card-body">
            <div class="lp-card-logo"><?= siteLogo(64) ?></div>
            <div class="lp-card-name"><?= e($serverName) ?></div>
            <div class="lp-card-tag"><?= e($tagline) ?></div>

            <?php if ($serverIp): ?>
              <button class="lp-card-ip" data-copy="<?= e($serverIp) ?>">
                <?= icon('globe', 13) ?> <?= e($serverIp) ?>
              </button>
            <?php endif; ?>

            <div class="lp-card-stats">
              <div>
                <div class="lp-card-num" data-count="<?= (int)$stats['online_count'] ?>">0</div>
                <div class="lp-card-lbl">Онлайн</div>
              </div>
              <div>
                <div class="lp-card-num" data-count="<?= (int)$stats['max_online'] ?>">0</div>
                <div class="lp-card-lbl">Макс.</div>
              </div>
              <div>
                <div class="lp-card-num" data-count="<?= (int)$stats['total_players'] ?>">0</div>
                <div class="lp-card-lbl">Гравців</div>
              </div>
            </div>
          </div>
        </div>
      </div>

    </div>
  </div>
</section>

<!-- ═══════════════════════════════════════════════════════
     ПЕРЕВАГИ
     ═══════════════════════════════════════════════════════ -->
<?php if ($features): ?>
<section class="lp-section lp-features-section">
  <div class="lp-inner">
    <div class="lp-center-head">
      <div class="lp-tag"><?= icon('sparkle', 13) ?> Особливості</div>
      <h2 class="lp-h2">Що вас <span class="lp-accent">чекає</span></h2>
    </div>

    <div class="lp-features">
      <?php foreach ($features as $i => $f): ?>
        <div class="lp-feature" data-tilt style="animation-delay: <?= $i * 0.06 ?>s">
          <div class="lp-feature-num"><?= str_pad($i + 1, 2, '0', STR_PAD_LEFT) ?></div>
          <div class="lp-feature-ico">
            <?= icon(['zap','shield','users','trend','sparkle','star','award','flame'][$i % 8], 22) ?>
          </div>
          <div class="lp-feature-text"><?= e($f) ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ═══════════════════════════════════════════════════════
     ТОП ГРАВЦІВ
     ═══════════════════════════════════════════════════════ -->
<?php if ($topPlayers): ?>
<section class="lp-section">
  <div class="lp-inner">
    <div class="lp-center-head">
      <div class="lp-tag"><?= icon('award', 13) ?> Лідери</div>
      <h2 class="lp-h2">Топ <span class="lp-accent">гравців</span></h2>
    </div>

    <div class="lp-players">
      <?php foreach ($topPlayers as $i => $p): ?>
        <div class="lp-player" data-tilt>
          <div class="lp-player-rank rank-<?= $i + 1 ?>">#<?= $i + 1 ?></div>
          <div class="lp-player-avatar"><?= icon('user', 22) ?></div>
          <div class="lp-player-body">
            <div class="lp-player-name"><?= e($p['nickname']) ?></div>
            <div class="lp-player-meta">
              <span><?= icon('shield', 11) ?> <?= e($p['faction']) ?></span>
              <span><?= icon('star', 11) ?> LVL <?= (int)$p['level'] ?></span>
            </div>
          </div>
          <div class="lp-player-stats">
            <div class="lp-player-stat">
              <div class="lp-player-stat-num"><?= number_format((int)$p['playtime_hours']) ?></div>
              <div class="lp-player-stat-lbl">годин</div>
            </div>
            <div class="lp-player-stat">
              <div class="lp-player-stat-num"><?= number_format((int)$p['kills']) ?></div>
              <div class="lp-player-stat-lbl">кілів</div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ═══════════════════════════════════════════════════════
     ЛАУНЧЕР
     ═══════════════════════════════════════════════════════ -->
<section class="lp-section lp-launcher-section" id="launcher">
  <div class="lp-inner">
    <div class="lp-launcher">
      <div class="lp-launcher-content">
        <div class="lp-tag"><?= icon('send', 13) ?> Почати гру</div>
        <h2 class="lp-h2">Скачати <span class="lp-accent">лаунчер</span></h2>

        <p class="lp-launcher-desc">
          Завантажте офіційний лаунчер <?= e($serverName) ?>, встановіть його та розпочніть свою історію. Один клік — і ви в грі.
        </p>

        <div class="lp-launcher-actions">
          <a href="<?= e($launcherUrl) ?>" class="lp-btn lp-btn-primary lp-btn-lg">
            <?= icon('arrow-right', 18) ?> Завантажити лаунчер
          </a>
          <?php if ($serverIp): ?>
            <button type="button" class="lp-btn lp-btn-ghost lp-btn-lg" data-copy="<?= e($serverIp) ?>">
              <?= icon('file-text', 16) ?> <?= e($serverIp) ?>
            </button>
          <?php endif; ?>
        </div>

        <div class="lp-launcher-info">
          <span><?= icon('check', 13) ?> Windows 10/11</span>
          <span><?= icon('check', 13) ?> Безкоштовно</span>
          <span><?= icon('check', 13) ?> ~200 МБ</span>
          <span><?= icon('check', 13) ?> Автооновлення</span>
        </div>
      </div>

      <div class="lp-launcher-visual">
        <div class="lp-launcher-3d" data-tilt>
          <div class="lp-window">
            <div class="lp-window-bar">
              <span></span><span></span><span></span>
              <span class="lp-window-title"><?= e($serverName) ?> Launcher</span>
            </div>
            <div class="lp-window-body">
              <div class="lp-window-logo"><?= siteLogo(64) ?></div>
              <div class="lp-window-btn">ГРАТИ</div>
              <div class="lp-window-progress">
                <div class="lp-window-progress-fill"></div>
              </div>
              <div class="lp-window-status">
                <span class="lp-window-dot"></span>
                Завантаження ресурсів
              </div>
            </div>
          </div>
          <div class="lp-window-shadow"></div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ═══════════════════════════════════════════════════════
     FAQ
     ═══════════════════════════════════════════════════════ -->
<?php if ($faq): ?>
<section class="lp-section">
  <div class="lp-inner">
    <div class="lp-center-head">
      <div class="lp-tag"><?= icon('bulb', 13) ?> FAQ</div>
      <h2 class="lp-h2">Часті <span class="lp-accent">питання</span></h2>
    </div>

    <div class="lp-faq">
      <?php foreach ($faq as $i => $f): ?>
        <details class="lp-faq-item" <?= $i === 0 ? 'open' : '' ?>>
          <summary class="lp-faq-q">
            <span><?= e($f['question']) ?></span>
            <span class="lp-faq-icon"><?= icon('chevron-down', 16) ?></span>
          </summary>
          <div class="lp-faq-a"><?= nl2br(e($f['answer'])) ?></div>
        </details>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ═══════════════════════════════════════════════════════
     АКТИВНІСТЬ ФОРУМУ
     ═══════════════════════════════════════════════════════ -->
<?php if ($recentForum): ?>
<section class="lp-section lp-forum-section">
  <div class="lp-inner">
    <div class="lp-center-head">
      <div class="lp-tag"><?= icon('message', 13) ?> Форум</div>
      <h2 class="lp-h2">Останнє на <span class="lp-accent">форумі</span></h2>
    </div>

    <div class="lp-forum-list">
      <?php foreach ($recentForum as $t): ?>
        <a href="<?= SITE_URL ?>/topic.php?id=<?= $t['id'] ?>" class="lp-forum-item" data-tilt>
          <div class="lp-forum-ico"><?= icon($t['cat_icon'] ?: 'message', 18) ?></div>
          <div class="lp-forum-body">
            <div class="lp-forum-title"><?= e($t['title']) ?></div>
            <div class="lp-forum-meta">
              <span><?= icon('user', 11) ?> <?= e($t['username']) ?></span>
              <span><?= icon('folder', 11) ?> <?= e($t['cat_name']) ?></span>
              <span><?= icon('clock', 11) ?> <?= timeAgo($t['created_at']) ?></span>
            </div>
          </div>
          <div class="lp-forum-arrow"><?= icon('arrow-right', 16) ?></div>
        </a>
      <?php endforeach; ?>
    </div>

    <div class="lp-forum-cta">
      <a href="<?= SITE_URL ?>/index.php" class="lp-btn lp-btn-ghost">
        <?= icon('message', 15) ?> Перейти на форум
      </a>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ═══════════════════════════════════════════════════════
     CTA
     ═══════════════════════════════════════════════════════ -->
<section class="lp-section lp-cta-section">
  <div class="lp-inner">
    <div class="lp-cta" data-tilt>
      <div class="lp-cta-content">
        <h2 class="lp-cta-title">Приєднуйся до <span class="lp-accent"><?= e($serverName) ?></span></h2>
        <p class="lp-cta-desc">Стань частиною спільноти — реєструйся та заходь у гру прямо зараз</p>

        <div class="lp-cta-actions">
          <a href="<?= SITE_URL ?>/register.php" class="lp-btn lp-btn-primary lp-btn-lg">
            <?= icon('user', 17) ?> Зареєструватися
          </a>
          <?php if ($discordUrl): ?>
            <a href="<?= e($discordUrl) ?>" target="_blank" rel="noopener" class="lp-btn lp-btn-ghost lp-btn-lg">
              <?= socialIcon('discord', 17) ?> Discord
            </a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</section>

<script>
(function(){
  /* ─── Копіювання ─── */
  document.querySelectorAll('[data-copy]').forEach(el => {
    el.addEventListener('click', () => {
      const text = el.dataset.copy;
      if (!text) return;
      const success = () => window.toast ? window.toast('IP: ' + text, 'success', 2000) : alert('IP: ' + text);
      if (navigator.clipboard) navigator.clipboard.writeText(text).then(success);
      else {
        const ta = document.createElement('textarea');
        ta.value = text; document.body.appendChild(ta); ta.select();
        document.execCommand('copy'); document.body.removeChild(ta);
        success();
      }
    });
  });

  /* ─── Скрол до лаунчера ─── */
  const sBtn = document.getElementById('scrollToLauncher');
  if (sBtn) sBtn.addEventListener('click', () => {
    const t = document.getElementById('launcher');
    if (!t) return;
    const top = t.getBoundingClientRect().top + window.scrollY - 80;
    window.scrollTo({ top, behavior: 'smooth' });
  });

  /* ─── Паралакс ─── */
  const layers = document.querySelectorAll('.lp-hero [data-layer]');
  const hero = document.querySelector('.lp-hero');
  let ticking = false;
  function updateParallax() {
    if (!hero) { ticking = false; return; }
    const y = window.scrollY;
    const hh = hero.offsetHeight;
    if (y < hh + 300) {
      layers.forEach(el => {
        const speed = parseFloat(el.dataset.layer) || 0.3;
        el.style.transform = `translate3d(0, ${y * speed}px, 0)`;
      });
    }
    ticking = false;
  }
  window.addEventListener('scroll', () => {
    if (!ticking) { requestAnimationFrame(updateParallax); ticking = true; }
  }, { passive: true });

  /* ─── Лічильники ─── */
  const io2 = new IntersectionObserver(entries => {
    entries.forEach(en => {
      if (en.isIntersecting) {
        const el = en.target;
        const target = parseFloat(el.dataset.count) || 0;
        const dur = 1200;
        const start = performance.now();
        const easeOut = p => 1 - Math.pow(1 - p, 3);
        const isDecimal = target % 1 !== 0;
        const step = now => {
          const p = Math.min((now - start) / dur, 1);
          const v = target * easeOut(p);
          el.textContent = isDecimal ? v.toFixed(1) : Math.floor(v).toLocaleString('uk-UA');
          if (p < 1) requestAnimationFrame(step);
        };
        requestAnimationFrame(step);
        io2.unobserve(el);
      }
    });
  }, { threshold: 0.3 });
  document.querySelectorAll('[data-count]').forEach(el => io2.observe(el));

  /* ─── Reveal секцій ─── */
  const io = new IntersectionObserver(entries => {
    entries.forEach(en => {
      if (en.isIntersecting) {
        en.target.classList.add('is-visible');
        io.unobserve(en.target);
      }
    });
  }, { threshold: 0.08, rootMargin: '0px 0px -60px 0px' });
  document.querySelectorAll('.lp-section, .lp-feature, .lp-player, .lp-forum-item').forEach(el => io.observe(el));
})();
</script>

<?php require __DIR__ . '/footer.php'; ?>
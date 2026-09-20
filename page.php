<?php
require_once __DIR__ . '/functions.php';

$slug = $_GET['slug'] ?? '';
$map = [
    'policy' => [
        'title_key'     => 'policy_title',
        'content_key'   => 'policy_content',
        'default_title' => 'Політика конфіденційності',
        'icon'          => 'lock',
    ],
    'terms'  => [
        'title_key'     => 'terms_title',
        'content_key'   => 'terms_content',
        'default_title' => 'Умови і правила',
        'icon'          => 'file-text',
    ],
];

if (!isset($map[$slug])) redirect(SITE_URL);

$title   = getSetting($map[$slug]['title_key'],   $map[$slug]['default_title']);
$content = getSetting($map[$slug]['content_key'], '');

$pageTitle = $title;
require __DIR__ . '/header.php';
?>

<div class="breadcrumbs">
  <a href="<?= SITE_URL ?>"><?= icon('home', 12) ?> Головна</a>
  <span class="sep">/</span>
  <span class="current"><?= e($title) ?></span>
</div>

<article class="page-content reveal">
  <header class="page-content-head">
    <h1><?= icon($map[$slug]['icon'], 22) ?> <?= e($title) ?></h1>
  </header>

  <div class="page-content-body">
    <?php if (trim($content) === ''): ?>
      <div class="empty compact">
        <?= icon('file-text', 28) ?>
        <p>Сторінка ще не заповнена</p>
      </div>
    <?php else: ?>
      <?= nl2br($content) ?>
    <?php endif; ?>
  </div>
</article>

<?php require __DIR__ . '/footer.php'; ?>
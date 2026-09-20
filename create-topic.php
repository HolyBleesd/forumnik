<?php
require_once __DIR__ . '/functions.php';
if (!isLoggedIn()) redirect(SITE_URL . '/login.php');

// Перевірка муту
if (isUserMuted((int)$_SESSION['user_id'])) {
    flash('⛔ Ви не можете створювати теми — у вас мут', 'error');
    redirect(SITE_URL);
}

$cats = $pdo->query("SELECT * FROM categories WHERE is_hidden=0 ORDER BY sort_order")->fetchAll();

/* ─── Підкатегорії та підфоруми ─── */
$subsByCat = [];
$subforumsByCat = [];

try {
    $allSubs = $pdo->query("SELECT * FROM subcategories WHERE is_hidden=0 ORDER BY sort_order")->fetchAll();
    foreach ($allSubs as $s) $subsByCat[$s['category_id']][] = $s;
} catch (Exception $e) {}

try {
    $allSubforums = $pdo->query("SELECT * FROM subforums WHERE is_hidden=0 ORDER BY sort_order")->fetchAll();
    foreach ($allSubforums as $sf) $subforumsByCat[$sf['category_id']][] = $sf;
} catch (Exception $e) {}

/* ─── Дерево для JS ─── */
$catTree = [];
foreach ($cats as $c) {
    $items = [];
    foreach ($subsByCat[$c['id']] ?? [] as $s) {
        $items[] = [
            'type'  => 'sub',
            'id'    => (int)$s['id'],
            'name'  => $s['name'],
            'icon'  => $s['icon'] ?? 'hash',
            'color' => $s['color'] ?? '#5b6ee1',
        ];
    }
    foreach ($subforumsByCat[$c['id']] ?? [] as $sf) {
        $items[] = [
            'type'  => 'subforum',
            'id'    => (int)$sf['id'],
            'name'  => $sf['name'],
            'icon'  => $sf['icon'] ?? 'hash',
            'color' => $sf['color'] ?? '#5b6ee1',
        ];
    }
    $catTree[] = [
        'id'    => (int)$c['id'],
        'name'  => $c['name'],
        'icon'  => $c['icon'] ?? 'hash',
        'color' => $c['color'] ?? '#5b6ee1',
        'desc'  => $c['description'] ?? '',
        'items' => $items,
    ];
}

$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!checkCsrf($_POST['csrf'] ?? '')) {
        $err = 'Помилка безпеки';
    } else {
        $catId   = (int)($_POST['category_id'] ?? 0);
        $subId   = (int)($_POST['subcategory_id'] ?? 0) ?: null;
        $subfId  = (int)($_POST['subforum_id'] ?? 0) ?: null;
        $title   = trim($_POST['title'] ?? '');
        $content = $_POST['content'] ?? '';

        if (!$catId) {
            $err = 'Оберіть категорію';
        } elseif (mb_strlen($title) < 5) {
            $err = 'Заголовок мінімум 5 символів';
        } elseif (mb_strlen($title) > 255) {
            $err = 'Заголовок занадто довгий';
        } elseif (mb_strlen(strip_tags($content)) < 10) {
            $err = 'Опис мінімум 10 символів';
        } else {
            /* Перевірка прав */
            $canPost = true;

            if ($subfId) {
                try {
                    if (!canInSubforum('topic.create', $subfId)) {
                        $canPost = false;
                        $err = 'Немає прав створювати теми в цьому підфорумі';
                    }
                } catch (Exception $e) {}
            } elseif ($subId) {
                try {
                    if (!canInCategory('topic.create', $catId, $subId)) {
                        $canPost = false;
                        $err = 'Немає прав створювати теми в цій підкатегорії';
                    }
                } catch (Exception $e) {}
            } else {
                try {
                    if (!canInCategory('topic.create', $catId)) {
                        $canPost = false;
                        $err = 'Немає прав створювати теми в цій категорії';
                    }
                } catch (Exception $e) {}
            }

            if ($canPost) {
                try {
                    $cols = $pdo->query("SHOW COLUMNS FROM topics")->fetchAll(PDO::FETCH_COLUMN);
                    $hasSubId  = in_array('subcategory_id', $cols);
                    $hasSubfId = in_array('subforum_id', $cols);

                    $safe = cleanEditorHtml($content);

                    if ($hasSubfId && $hasSubId) {
                        $stmt = $pdo->prepare("INSERT INTO topics (category_id, subcategory_id, subforum_id, user_id, title, content) VALUES (?,?,?,?,?,?)");
                        $stmt->execute([$catId, $subId, $subfId, $_SESSION['user_id'], $title, $safe]);
                    } elseif ($hasSubId) {
                        $stmt = $pdo->prepare("INSERT INTO topics (category_id, subcategory_id, user_id, title, content) VALUES (?,?,?,?,?)");
                        $stmt->execute([$catId, $subId, $_SESSION['user_id'], $title, $safe]);
                    } else {
                        $stmt = $pdo->prepare("INSERT INTO topics (category_id, user_id, title, content) VALUES (?,?,?,?)");
                        $stmt->execute([$catId, $_SESSION['user_id'], $title, $safe]);
                    }

                    $tid = $pdo->lastInsertId();

                    // Сповіщення підписникам
                    $me = currentUser();
                    notifySubscribers((int)$_SESSION['user_id'], 'sub_new_topic',
                        ($me['username'] ?? 'Користувач') . ' створив нову тему: ' . $title,
                        SITE_URL . "/topic.php?id=$tid");

                    flash('Тему створено!', 'success');
                    redirect(SITE_URL . "/topic.php?id=$tid");
                } catch (Exception $e) {
                    $err = 'Помилка: ' . $e->getMessage();
                }
            }
        }
    }
}

$pageTitle = 'Нова тема';
require __DIR__ . '/header.php';
?>

<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/editor.css">

<div class="breadcrumbs">
  <a href="<?= SITE_URL ?>"><?= icon('home', 12) ?> Головна</a>
  <span class="sep">/</span>
  <span class="current">Нова тема</span>
</div>

<div class="editor-page reveal">
  <div class="editor-page-head">
    <h1><?= icon('edit', 20) ?> Створити нову тему</h1>
    <p>Оберіть категорію, введіть заголовок і напишіть повідомлення</p>
  </div>

  <?php if ($err): ?>
    <div class="alert error">
      <span class="alert-ico"><?= icon('x', 15) ?></span>
      <span><?= e($err) ?></span>
    </div>
  <?php endif; ?>

  <form method="post" class="editor-form">
    <input type="hidden" name="csrf" value="<?= csrf() ?>">

    <!-- ═══ КРОК 1: КАТЕГОРІЯ ═══ -->
    <div class="editor-block">
      <div class="editor-block-head">
        <span class="editor-block-num">1</span>
        <div>
          <div class="editor-block-title">Куди публікуємо?</div>
          <div class="editor-block-desc">Оберіть категорію та підрозділ</div>
        </div>
      </div>

      <div class="cat-picker" id="catPicker">
        <input type="hidden" name="category_id" id="catIdInput" value="<?= (int)($_POST['category_id'] ?? $_GET['cat'] ?? 0) ?>">
        <input type="hidden" name="subcategory_id" id="subIdInput" value="<?= (int)($_POST['subcategory_id'] ?? 0) ?>">
        <input type="hidden" name="subforum_id" id="subfIdInput" value="<?= (int)($_POST['subforum_id'] ?? 0) ?>">

        <button type="button" class="cat-picker-trigger" id="catPickerTrigger">
          <span class="cat-picker-trigger-icon" id="catPickerIcon">
            <?= icon('folder', 16) ?>
          </span>
          <span class="cat-picker-trigger-text" id="catPickerText">Оберіть категорію...</span>
          <span class="cat-picker-trigger-chevron"><?= icon('chevron-down', 14) ?></span>
        </button>

        <div class="cat-picker-menu" id="catPickerMenu"></div>
      </div>
    </div>

    <!-- ═══ КРОК 2: ЗАГОЛОВОК ═══ -->
    <div class="editor-block">
      <div class="editor-block-head">
        <span class="editor-block-num">2</span>
        <div>
          <div class="editor-block-title">Заголовок теми</div>
          <div class="editor-block-desc">Коротко опишіть суть</div>
        </div>
      </div>

      <input type="text" name="title" class="editor-title-input"
             required minlength="5" maxlength="255"
             placeholder="Наприклад: Пропозиції щодо покращення сервера"
             value="<?= e($_POST['title'] ?? '') ?>">
    </div>

    <!-- ═══ КРОК 3: ПОВІДОМЛЕННЯ ═══ -->
    <div class="editor-block">
      <div class="editor-block-head">
        <span class="editor-block-num">3</span>
        <div>
          <div class="editor-block-title">Повідомлення</div>
          <div class="editor-block-desc">Опишіть детально</div>
        </div>
      </div>

      <div class="rich-editor" data-editor>
        <div class="rich-editor-toolbar" data-editor-toolbar>

          <div class="re-group">
            <button type="button" data-block="p" title="Звичайний текст">
              <span class="re-icon-text">A</span>
            </button>
            <button type="button" data-block="h1" title="Заголовок 1">
              <span class="re-icon-text re-h1">H1</span>
            </button>
            <button type="button" data-block="h2" title="Заголовок 2">
              <span class="re-icon-text re-h2">H2</span>
            </button>
          </div>

          <div class="re-sep"></div>

          <div class="re-dropdown">
            <button type="button" class="re-dropdown-trigger" data-dd-trigger="font">
              Шрифт <?= icon('chevron-down', 12) ?>
            </button>
            <div class="re-dropdown-menu" data-dd-menu="font">
              <button type="button" data-font="Inter">Inter</button>
              <button type="button" data-font="Georgia, serif">Georgia</button>
              <button type="button" data-font="'Times New Roman', serif">Times New Roman</button>
              <button type="button" data-font="'JetBrains Mono', monospace">JetBrains Mono</button>
              <button type="button" data-font="Arial, sans-serif">Arial</button>
              <button type="button" data-font="'Courier New', monospace">Courier</button>
            </div>
          </div>

          <div class="re-dropdown">
            <button type="button" class="re-dropdown-trigger" data-dd-trigger="size">
              Розмір <?= icon('chevron-down', 12) ?>
            </button>
            <div class="re-dropdown-menu" data-dd-menu="size">
              <button type="button" data-size="12">12px</button>
              <button type="button" data-size="14">14px</button>
              <button type="button" data-size="16">16px</button>
              <button type="button" data-size="18">18px</button>
              <button type="button" data-size="22">22px</button>
              <button type="button" data-size="28">28px</button>
            </div>
          </div>

          <div class="re-sep"></div>

          <button type="button" data-cmd="bold" title="Жирний"><b>B</b></button>
          <button type="button" data-cmd="italic" title="Курсив"><i>I</i></button>
          <button type="button" data-cmd="underline" title="Підкреслений"><u>U</u></button>
          <button type="button" data-cmd="strikeThrough" title="Закреслений"><s>S</s></button>

          <div class="re-sep"></div>

          <button type="button" data-cmd="justifyLeft" title="По лівому краю">
            <?= icon('align-left', 15) ?>
          </button>
          <button type="button" data-cmd="justifyCenter" title="По центру">
            <?= icon('align-center', 15) ?>
          </button>
          <button type="button" data-cmd="justifyRight" title="По правому краю">
            <?= icon('align-right', 15) ?>
          </button>

          <div class="re-sep"></div>

          <button type="button" data-cmd="insertUnorderedList" title="Маркований список">
            <?= icon('list', 15) ?>
          </button>
          <button type="button" data-cmd="insertOrderedList" title="Нумерований список">
            <?= icon('list-ordered', 15) ?>
          </button>
          <button type="button" data-cmd="formatBlock" data-value="blockquote" title="Цитата">
            <?= icon('quote', 15) ?>
          </button>

          <div class="re-sep"></div>

          <div class="re-colors">
            <button type="button" class="re-color-current" title="Колір тексту">
              <span class="re-color-icon">A</span>
            </button>
            <div class="re-colors-menu">
              <div class="re-colors-row">
                <?php foreach ([
                  '#ef4444','#f59e0b','#eab308','#22c55e','#14b8a6',
                  '#06b6d4','#3b82f6','#8b5cf6','#ec4899','#ffffff',
                  '#9ba1a6','#6b7278','#000000'
                ] as $c): ?>
                  <button type="button" data-color="<?= $c ?>" style="background: <?= $c ?>"></button>
                <?php endforeach; ?>
              </div>
            </div>
          </div>

          <div class="re-colors">
            <button type="button" class="re-color-current" title="Підсвітка">
              <span class="re-color-icon" style="background:#fef08a;color:#000">A</span>
            </button>
            <div class="re-colors-menu">
              <div class="re-colors-row">
                <?php foreach ([
                  '#fef08a','#fed7aa','#fecaca','#bbf7d0','#bfdbfe',
                  '#e9d5ff','#fbcfe8','#e5e7eb'
                ] as $c): ?>
                  <button type="button" data-highlight="<?= $c ?>" style="background: <?= $c ?>"></button>
                <?php endforeach; ?>
              </div>
            </div>
          </div>

          <div class="re-sep"></div>

          <button type="button" data-open-media title="Вставити медіа">
            <?= icon('film', 15) ?>
          </button>
          <button type="button" data-cmd="removeFormat" title="Очистити форматування">
            <?= icon('x', 15) ?>
          </button>
        </div>

        <div class="rich-editor-area"
             contenteditable="true"
             data-editor-area
             data-placeholder="Напишіть ваше повідомлення тут..."><?= $_POST['content'] ?? '' ?></div>

        <textarea name="content" data-editor-input style="display:none"><?= e($_POST['content'] ?? '') ?></textarea>
      </div>
    </div>

    <!-- ═══ ДІЇ ═══ -->
    <div class="editor-actions">
      <a href="<?= SITE_URL ?>" class="btn btn-ghost">
        <?= icon('arrow-left', 15) ?> Скасувати
      </a>
      <button type="submit" class="btn btn-primary btn-lg">
        <?= icon('send', 16) ?> Створити тему
      </button>
    </div>
  </form>
</div>

<script>
window.CAT_TREE = <?= json_encode($catTree, JSON_UNESCAPED_UNICODE) ?>;
window.SELECTED_CAT = <?= (int)($_POST['category_id'] ?? $_GET['cat'] ?? 0) ?>;
window.SELECTED_SUB = <?= (int)($_POST['subcategory_id'] ?? 0) ?>;
window.SELECTED_SUBF = <?= (int)($_POST['subforum_id'] ?? 0) ?>;
</script>
<script src="<?= SITE_URL ?>/assets/js/cat-picker.js"></script>
<script src="<?= SITE_URL ?>/assets/js/editor.js"></script>

<?php require __DIR__ . '/footer.php'; ?>
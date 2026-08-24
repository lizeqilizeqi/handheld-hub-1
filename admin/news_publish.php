<?php

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/lib/feed_repo.php';
require_once dirname(__DIR__) . '/lib/news_editor.php';
require_once dirname(__DIR__) . '/lib/site_context.php';

hh_admin_require_login();
$pdo = hh_pdo();

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$msg = '';
$err = '';
$row = null;

if ($id > 0) {
    $row = hh_feed_item_by_id($pdo, $id);
    if (!$row) {
        $err = hh_admin_t('not_found');
        $id = 0;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? (string) $_POST['action'] : 'publish';
    $status = $action === 'draft' ? 'pending' : 'published';
    $payload = array(
        'title_zh' => $_POST['title_zh'] ?? '',
        'title_en' => $_POST['title_en'] ?? '',
        'summary_zh' => $_POST['summary_zh'] ?? '',
        'summary_en' => $_POST['summary_en'] ?? '',
        'body_zh' => $_POST['body_html'] ?? '',
        'image_url' => $_POST['image_url'] ?? '',
        'status' => $status,
    );
    try {
        if ($id > 0 && $row) {
            hh_feed_item_save_manual($pdo, $id, $payload);
            $msg = $status === 'published' ? hh_admin_t('news_publish_done') : hh_admin_t('news_draft_saved');
        } else {
            $id = hh_feed_item_create_manual($pdo, $payload);
            $msg = $status === 'published' ? hh_admin_t('news_publish_done') : hh_admin_t('news_draft_saved');
        }
        $row = hh_feed_item_by_id($pdo, $id);
        header('Location: news_publish.php?id=' . (int) $id . '&saved=' . ($status === 'published' ? 'pub' : 'draft'), true, 302);
        exit;
    } catch (Throwable $e) {
        $err = $e->getMessage();
    }
}

if (isset($_GET['saved'])) {
    $msg = ($_GET['saved'] === 'draft') ? hh_admin_t('news_draft_saved') : hh_admin_t('news_publish_done');
}

require_once __DIR__ . '/layout.php';
hh_admin_layout_start('news_publish');

$titleZh = $row ? (string) ($row['title_zh'] ?: $row['title']) : '';
$titleEn = $row ? (string) ($row['title_en'] ?? '') : '';
$summaryZh = $row ? (string) ($row['summary_zh'] ?: $row['summary'] ?? '') : '';
$summaryEn = $row ? (string) ($row['summary_en'] ?? '') : '';
$bodyHtml = $row ? (string) ($row['body_zh'] ?? '') : '';
$coverUrl = $row ? (string) ($row['image_url'] ?? '') : '';
$statusVal = $row ? (string) $row['status'] : 'pending';
?>
<h1><?php echo hh_h(hh_admin_t('news_publish')); ?></h1>
<p class="muted"><?php echo hh_h(hh_admin_t('news_publish_hint')); ?>
  <?php if ($id > 0): ?> · <a href="feeds.php"><?php echo hh_h(hh_admin_t('feeds')); ?></a>
  · <?php hh_admin_news_action_links((int) $id, (string) ($row['slug'] ?? '')); ?><?php endif; ?>
</p>

<?php if ($msg): ?><p class="flash ok"><?php echo hh_h($msg); ?></p><?php endif; ?>
<?php if ($err): ?><p class="flash err"><?php echo hh_h($err); ?></p><?php endif; ?>

<form method="post" class="news-publish-form" id="news-publish-form">
  <div class="card">
    <div class="form-grid games-form-grid">
      <label class="full"><?php echo hh_h(hh_admin_t('news_title')); ?>
        <input name="title_zh" id="news-title-zh" required value="<?php echo hh_h($titleZh); ?>" placeholder="中文标题">
      </label>
      <label class="full"><?php echo hh_h(hh_admin_t('games_title_en')); ?>
        <input name="title_en" value="<?php echo hh_h($titleEn); ?>" placeholder="Optional English title">
      </label>
      <label class="full"><?php echo hh_h(hh_admin_t('news_cover')); ?>
        <input type="hidden" name="image_url" id="news-cover-url" value="<?php echo hh_h($coverUrl); ?>">
        <div class="news-cover-row">
          <div class="news-cover-preview" id="news-cover-preview">
            <?php if ($coverUrl !== ''): ?>
            <img src="<?php echo hh_h($coverUrl); ?>" alt="">
            <?php else: ?>
            <span class="muted"><?php echo hh_h(hh_admin_t('news_cover_empty')); ?></span>
            <?php endif; ?>
          </div>
          <div class="news-cover-actions">
            <button type="button" class="btn-secondary" id="news-cover-pick"><?php echo hh_h(hh_admin_t('news_cover_pick')); ?></button>
            <button type="button" class="btn-secondary" id="news-cover-clear"><?php echo hh_h(hh_admin_t('news_cover_clear')); ?></button>
            <input type="file" accept="image/*" id="news-cover-file" hidden>
          </div>
        </div>
      </label>
      <label class="full"><?php echo hh_h(hh_admin_t('news_summary_optional')); ?>
        <textarea name="summary_zh" rows="2" placeholder="留空则自动从正文截取"><?php echo hh_h($summaryZh); ?></textarea>
      </label>
      <label class="full"><?php echo hh_h(hh_admin_t('games_summary_en')); ?>
        <textarea name="summary_en" rows="2" placeholder="Optional"><?php echo hh_h($summaryEn); ?></textarea>
      </label>
    </div>
  </div>

  <div class="card news-editor-card">
    <label><?php echo hh_h(hh_admin_t('news_body')); ?></label>
    <div id="news-quill-toolbar" class="news-quill-toolbar">
      <span class="ql-formats">
        <select class="ql-header">
          <option selected></option>
          <option value="2"></option>
          <option value="3"></option>
        </select>
      </span>
      <span class="ql-formats">
        <button class="ql-bold"></button>
        <button class="ql-italic"></button>
        <button class="ql-underline"></button>
        <button class="ql-blockquote"></button>
      </span>
      <span class="ql-formats">
        <button class="ql-list" value="ordered"></button>
        <button class="ql-list" value="bullet"></button>
      </span>
      <span class="ql-formats">
        <button class="ql-link"></button>
        <button type="button" class="ql-image" id="news-body-image"></button>
      </span>
      <span class="ql-formats">
        <button type="button" class="btn-secondary news-paste-tip" id="news-paste-tip" title="<?php echo hh_h(hh_admin_t('news_paste_hint')); ?>">📋</button>
      </span>
    </div>
    <div id="news-quill-editor" class="news-quill-editor"></div>
    <textarea name="body_html" id="news-body-html" hidden><?php echo hh_h($bodyHtml); ?></textarea>
    <p class="muted news-editor-hint"><?php echo hh_h(hh_admin_t('news_paste_hint')); ?></p>
    <input type="file" accept="image/*" id="news-body-image-file" hidden>
  </div>

  <p class="news-publish-actions">
    <?php if ($id > 0 && $statusVal === 'published'): ?>
    <span class="badge badge-published"><?php echo hh_h(hh_admin_t('published_count')); ?></span>
    <?php endif; ?>
    <button type="submit" name="action" value="publish" class="btn-primary"><?php echo hh_h(hh_admin_t('news_publish_btn')); ?></button>
    <button type="submit" name="action" value="draft" class="btn-secondary"><?php echo hh_h(hh_admin_t('news_save_draft')); ?></button>
    <a class="btn-secondary" href="feeds.php"><?php echo hh_h(hh_admin_t('feeds')); ?></a>
  </p>
</form>

<link href="https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.snow.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.min.js"></script>
<script src="assets/news-editor.js?v=<?php echo (int) @filemtime(__DIR__ . '/assets/news-editor.js'); ?>"></script>
<?php hh_admin_layout_end(); ?>

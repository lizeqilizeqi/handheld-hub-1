<?php

require_once __DIR__ . '/bootstrap.php';

hh_admin_require_login();

require_once dirname(__DIR__) . '/lib/handheld_repo.php';



$pdo = hh_pdo();

$msg = '';

$err = '';



if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;

    $h = $id > 0 ? hh_handheld_by_id($pdo, $id) : null;

    if (!$h) {

        $err = hh_admin_t('not_found');

    } else {

        $pdo->prepare('UPDATE hh_handhelds SET status=?, name_en=? WHERE id=?')->execute(array(

            isset($_POST['status']) ? (string) $_POST['status'] : 'draft',

            isset($_POST['name_en']) ? trim((string) $_POST['name_en']) : '',

            $id,

        ));

        foreach (array('zh', 'en') as $loc) {

            hh_handheld_save_content($pdo, $id, $loc, array(

                'title' => isset($_POST['title_' . $loc]) ? (string) $_POST['title_' . $loc] : '',

                'summary' => isset($_POST['summary_' . $loc]) ? (string) $_POST['summary_' . $loc] : '',

                'body_html' => isset($_POST['body_' . $loc]) ? (string) $_POST['body_' . $loc] : '',

                'meta_description' => isset($_POST['meta_' . $loc]) ? (string) $_POST['meta_' . $loc] : '',

                'review_status' => isset($_POST['review_' . $loc]) ? (string) $_POST['review_' . $loc] : 'pending',

                'verified_urls' => array_filter(array_map('trim', explode("\n", isset($_POST['verified_urls']) ? (string) $_POST['verified_urls'] : ''))),

            ));

        }

        $msg = hh_admin_t('saved');

    }

}



$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

$brand = isset($_GET['brand']) ? (string) $_GET['brand'] : '';

$q = isset($_GET['q']) ? (string) $_GET['q'] : '';



require_once __DIR__ . '/layout.php';

hh_admin_layout_start('handhelds');



if ($id > 0) {

    $h = hh_handheld_by_id($pdo, $id);

    if (!$h) {

        echo '<div class="alert alert-error">' . hh_h(hh_admin_t('not_found')) . '</div>';

    } else {

        $specs = hh_handheld_specs($pdo, $id);

        $zh = hh_handheld_content($pdo, $id, 'zh');

        $en = hh_handheld_content($pdo, $id, 'en');

        $images = hh_handheld_images($pdo, $id);
        $bpZh = hh_blogger_post_row($pdo, $id, 'zh');
        $bpEn = hh_blogger_post_row($pdo, $id, 'en');

        if ($msg) echo '<div class="alert alert-ok">' . hh_h($msg) . '</div>';

        if ($err) echo '<div class="alert alert-error">' . hh_h($err) . '</div>';

        ?>

        <div class="card">

          <div class="preview-header">

            <div>

              <h3><?php echo hh_h($h['name_zh'] ?: $h['slug']); ?></h3>

              <p class="muted"><?php echo hh_h(hh_admin_t('slug')); ?>：<?php echo hh_h($h['slug']); ?> · <a href="preview.php?id=<?php echo (int) $id; ?>"><?php echo hh_h(hh_admin_t('preview')); ?></a></p>

            </div>

          </div>

          <div class="grid-2" style="margin-bottom:1rem;">
            <div>
              <p><strong>独立站</strong></p>
              <p><a href="/zh/handheld/<?php echo hh_h($h['slug']); ?>" target="_blank" rel="noopener">中文 /zh/handheld/<?php echo hh_h($h['slug']); ?></a></p>
              <p><a href="/en/handheld/<?php echo hh_h($h['slug']); ?>" target="_blank" rel="noopener">English /en/handheld/<?php echo hh_h($h['slug']); ?></a></p>
              <p><a href="publish.php?id=<?php echo (int) $id; ?>">独立站发布 / 预览</a></p>
            </div>
            <div>
              <p><strong>Blogger</strong></p>
              <p>中文：<?php if ($bpZh && !empty($bpZh['blogger_url'])): ?><a href="<?php echo hh_h($bpZh['blogger_url']); ?>" target="_blank" rel="noopener"><?php echo hh_h($bpZh['sync_status'] ?? '已发布'); ?></a><?php else: ?><span class="muted">未发布</span><?php endif; ?></p>
              <p>英文：<?php if ($bpEn && !empty($bpEn['blogger_url'])): ?><a href="<?php echo hh_h($bpEn['blogger_url']); ?>" target="_blank" rel="noopener"><?php echo hh_h($bpEn['sync_status'] ?? '已发布'); ?></a><?php else: ?><span class="muted">未发布</span><?php endif; ?></p>
            </div>
          </div>

          <form method="post">

            <input type="hidden" name="id" value="<?php echo (int) $id; ?>">

            <div class="grid-2">

              <div>

                <label>英文名称</label>

                <input name="name_en" value="<?php echo hh_h($h['name_en']); ?>">

                <label><?php echo hh_h(hh_admin_t('status')); ?></label>

                <select name="status">

                  <?php foreach (array('draft','review','published') as $st): ?>

                  <option value="<?php echo $st; ?>"<?php echo $h['status'] === $st ? ' selected' : ''; ?>><?php echo hh_h(hh_admin_status_label($st)); ?></option>

                  <?php endforeach; ?>

                </select>

                <label><?php echo hh_h(hh_admin_t('verified_urls')); ?></label>

                <textarea name="verified_urls" rows="3"><?php

                  $urls = $en && !empty($en['verified_urls']) ? json_decode($en['verified_urls'], true) : array();

                  echo hh_h(is_array($urls) ? implode("\n", $urls) : '');

                ?></textarea>

              </div>

              <div>

                <label><?php echo hh_h(hh_admin_t('specs')); ?></label>

                <textarea readonly rows="10"><?php echo hh_h(json_encode($specs, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)); ?></textarea>

                <?php if ($images): ?>

                <p><?php echo hh_h(hh_admin_t('cover')); ?>：</p>

                <img src="<?php echo hh_h(hh_image_public_url($images[0]['path'])); ?>" alt="" style="max-width:180px;border-radius:8px;">

                <?php endif; ?>

              </div>

            </div>

            <h4>中文内容</h4>

            <label>标题</label><input name="title_zh" value="<?php echo hh_h($zh['title'] ?? ''); ?>">

            <label>摘要</label><textarea name="summary_zh" rows="2"><?php echo hh_h($zh['summary'] ?? ''); ?></textarea>

            <label>正文 HTML</label><textarea name="body_zh" rows="8"><?php echo hh_h($zh['body_html'] ?? ''); ?></textarea>

            <label>审核状态</label>

            <select name="review_zh">

              <?php foreach (array('pending','ai_draft','human_approved') as $rs): ?>

              <option value="<?php echo $rs; ?>"<?php echo (($zh['review_status'] ?? '') === $rs) ? ' selected' : ''; ?>><?php echo hh_h(hh_admin_review_label($rs)); ?></option>

              <?php endforeach; ?>

            </select>

            <h4>英文内容</h4>

            <label>标题 Title</label><input name="title_en" value="<?php echo hh_h($en['title'] ?? ''); ?>">

            <label>SEO 描述 Meta</label><input name="meta_en" value="<?php echo hh_h($en['meta_description'] ?? ''); ?>">

            <label>摘要 Summary</label><textarea name="summary_en" rows="2"><?php echo hh_h($en['summary'] ?? ''); ?></textarea>

            <label>正文 HTML</label><textarea name="body_en" rows="10"><?php echo hh_h($en['body_html'] ?? ''); ?></textarea>

            <label>审核状态</label>

            <select name="review_en">

              <?php foreach (array('pending','ai_draft','human_approved') as $rs): ?>

              <option value="<?php echo $rs; ?>"<?php echo (($en['review_status'] ?? '') === $rs) ? ' selected' : ''; ?>><?php echo hh_h(hh_admin_review_label($rs)); ?></option>

              <?php endforeach; ?>

            </select>

            <button type="submit"><?php echo hh_h(hh_admin_t('save')); ?></button>

            <a class="btn btn-secondary" href="preview.php?id=<?php echo (int) $id; ?>"><?php echo hh_h(hh_admin_t('preview')); ?></a>

            <a class="btn btn-secondary" href="translate.php?id=<?php echo (int) $id; ?>"><?php echo hh_h(hh_admin_t('generate_en')); ?></a>

            <a class="btn btn-secondary" href="blogger.php?id=<?php echo (int) $id; ?>">发布到 Blogger（中/英）</a>

          </form>

        </div>

        <?php

    }

} else {

    $list = hh_handheld_list($pdo, array('brand' => $brand, 'q' => $q, 'limit' => 100));

    $brands = hh_brands($pdo);

    ?>

    <div class="card">

      <form method="get" class="grid-2">

        <div><label><?php echo hh_h(hh_admin_t('search')); ?></label><input name="q" value="<?php echo hh_h($q); ?>" placeholder="名称、品牌、标识"></div>

        <div><label><?php echo hh_h(hh_admin_t('brand')); ?></label>

          <select name="brand" onchange="this.form.submit()">

            <option value=""><?php echo hh_h(hh_admin_t('all_brands')); ?></option>

            <?php foreach ($brands as $b): ?>

            <option value="<?php echo hh_h($b['brand']); ?>"<?php echo $brand === $b['brand'] ? ' selected' : ''; ?>><?php echo hh_h($b['brand']); ?> (<?php echo (int) $b['c']; ?>)</option>

            <?php endforeach; ?>

          </select>

        </div>

      </form>

    </div>

    <div class="card">

      <table>

        <tr><th><?php echo hh_h(hh_admin_t('name')); ?></th><th><?php echo hh_h(hh_admin_t('brand')); ?></th><th><?php echo hh_h(hh_admin_t('release')); ?></th><th><?php echo hh_h(hh_admin_t('screen')); ?></th><th><?php echo hh_h(hh_admin_t('status')); ?></th><th><?php echo hh_h(hh_admin_t('actions')); ?></th></tr>

        <?php foreach ($list as $h): ?>

        <tr>

          <td><?php echo hh_h($h['name_zh'] ?: $h['slug']); ?></td>

          <td><?php echo hh_h($h['brand']); ?></td>

          <td><?php echo hh_h($h['release_date']); ?></td>

          <td><?php echo hh_h(trim($h['screen_size'] . ' ' . $h['screen_ratio'])); ?></td>

          <td><?php echo hh_h(hh_admin_status_label($h['status'])); ?></td>

          <td><?php hh_admin_action_links((int) $h['id']); ?></td>

        </tr>

        <?php endforeach; ?>

      </table>

    </div>

    <?php

}

hh_admin_layout_end();


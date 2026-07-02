<?php

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/handheld_repo.php';
require_once __DIR__ . '/detail_scraper.php';

class HhScraperService
{
    /** @var PDO */
    private $pdo;

    /** @var int|null */
    private $jobId;

    public function __construct(PDO $pdo, $jobId = null)
    {
        $this->pdo = $pdo;
        $this->jobId = $jobId;
    }

    public function run($mode = 'incremental', $singleSlug = null)
    {
        $stats = array(
            'items_found' => 0,
            'items_new' => 0,
            'items_updated' => 0,
            'items_failed' => 0,
            'total_pages' => 0,
            'current_page' => 0,
        );

        try {
            if ($this->jobId) {
                hh_scrape_log($this->pdo, $this->jobId, 'info', '', '任务开始');
            }

            require_once dirname(__DIR__) . '/brand_repo.php';
            try {
                if ($this->jobId) {
                    hh_scrape_log($this->pdo, $this->jobId, 'info', '', '同步品牌 Logo…');
                }
                $brandCount = hh_brand_sync_catalog($this->pdo);
                if ($this->jobId) {
                    hh_scrape_log($this->pdo, $this->jobId, 'info', '', '已同步 ' . (int) $brandCount . ' 个品牌 Logo');
                }
            } catch (Throwable $e) {
                if ($this->jobId) {
                    hh_scrape_log($this->pdo, $this->jobId, 'error', '', '品牌 Logo 同步失败：' . $e->getMessage());
                }
            }

            if ($singleSlug !== null && $singleSlug !== '') {
                if ($this->jobId) {
                    hh_scrape_log($this->pdo, $this->jobId, 'info', $singleSlug, '单台抓取模式');
                }
                $coverUrl = hh_scraper_lookup_list_cover($singleSlug);
                $slugs = array(array(
                    'slug' => $singleSlug,
                    'url' => hh_scraper_base_url() . '/handheld/' . $singleSlug,
                    'cover_url' => $coverUrl,
                ));
            } else {
                if ($this->jobId) {
                    hh_scrape_log($this->pdo, $this->jobId, 'info', '', '正在获取掌机圈列表…');
                }
                $slugs = hh_scraper_fetch_all_slugs();
                $stats['total_pages'] = 1;
            }
            $stats['items_found'] = count($slugs);
            if ($this->jobId) {
                hh_scrape_log($this->pdo, $this->jobId, 'info', '', '已发现 ' . $stats['items_found'] . ' 台掌机，开始逐台抓取');
                hh_scrape_job_update_progress($this->pdo, $this->jobId, array_merge($stats, array(
                    'message' => '已发现 ' . $stats['items_found'] . ' 台，开始抓取详情…',
                )));
            }

            $done = 0;
            foreach ($slugs as $item) {
                $slug = $item['slug'];
                $done++;
                if ($this->jobId) {
                    hh_scrape_log($this->pdo, $this->jobId, 'fetch', $slug, '正在抓取 [' . $done . '/' . $stats['items_found'] . ']');
                }
                try {
                    $detail = hh_scraper_fetch_detail($slug);
                    $coverUrl = isset($item['cover_url']) ? (string) $item['cover_url'] : '';
                    $detailUrl = isset($detail['detail_image_url']) ? (string) $detail['detail_image_url'] : '';
                    $hash = hh_content_hash(
                        $detail['specs'],
                        $detail['body_html_zh'],
                        $detail['name_zh'],
                        $coverUrl . '|' . $detailUrl
                    );
                    $existing = hh_handheld_by_slug($this->pdo, $slug);

                    if ($mode !== 'full' && $existing && $existing['content_hash'] === $hash) {
                        if ($this->jobId) {
                            hh_scrape_log($this->pdo, $this->jobId, 'skip', $slug, '跳过（内容无变化）' . ($detail['name_zh'] ? '：' . $detail['name_zh'] : ''));
                        }
                        if ($this->jobId) {
                            hh_scrape_job_update_progress($this->pdo, $this->jobId, array_merge($stats, array(
                                'current_page' => $done,
                                'message' => sprintf('进度 %d/%d（新增 %d，更新 %d，失败 %d）', $done, $stats['items_found'], $stats['items_new'], $stats['items_updated'], $stats['items_failed']),
                            )));
                        }
                        continue;
                    }

                    $result = hh_handheld_upsert($this->pdo, array(
                        'slug' => $slug,
                        'brand' => $detail['brand'],
                        'name_zh' => $detail['name_zh'],
                        'release_text' => $detail['release_text'],
                        'screen_size' => $detail['screen_size'],
                        'screen_ratio' => $detail['screen_ratio'],
                        'source_url' => $detail['source_url'],
                        'content_hash' => $hash,
                        'status' => 'draft',
                    ));
                    $id = $result['id'];

                    hh_handheld_save_specs($this->pdo, $id, $detail['specs']);

                    if ($coverUrl === '' && $detailUrl !== '') {
                        $coverUrl = $detailUrl;
                    }
                    $images = hh_scraper_download_images($slug, $coverUrl, $detailUrl);
                    if (count($images) > 0) {
                        hh_handheld_replace_images($this->pdo, $id, $images);
                    }

                    hh_handheld_save_content($this->pdo, $id, 'zh', array(
                        'title' => $detail['name_zh'],
                        'body_html' => $detail['body_html_zh'] !== '' ? $detail['body_html_zh'] : hh_specs_table_html($detail['specs'], 'zh'),
                        'summary' => isset($detail['specs']['品牌']) ? $detail['specs']['品牌'] . ' ' . $detail['name_zh'] : $detail['name_zh'],
                        'meta_description' => mb_substr(strip_tags($detail['name_zh']), 0, 160, 'UTF-8'),
                        'review_status' => 'pending',
                    ));

                    if ($result['is_new']) {
                        $stats['items_new']++;
                        hh_scrape_log($this->pdo, $this->jobId, 'ok', $slug, '新增：' . ($detail['name_zh'] ?: $slug));
                    } elseif ($result['changed']) {
                        $stats['items_updated']++;
                        hh_scrape_log($this->pdo, $this->jobId, 'ok', $slug, '更新：' . ($detail['name_zh'] ?: $slug));
                    }
                } catch (Throwable $e) {
                    $stats['items_failed']++;
                    hh_scrape_log($this->pdo, $this->jobId, 'error', $slug, '失败：' . $e->getMessage());
                }
                if ($this->jobId) {
                    hh_scrape_job_update_progress($this->pdo, $this->jobId, array_merge($stats, array(
                        'current_page' => $done,
                        'message' => sprintf('进度 %d/%d（新增 %d，更新 %d，失败 %d）', $done, $stats['items_found'], $stats['items_new'], $stats['items_updated'], $stats['items_failed']),
                    )));
                }
            }

            if ($this->jobId) {
                hh_scrape_log($this->pdo, $this->jobId, 'info', '', sprintf('任务完成：新增 %d，更新 %d，失败 %d', $stats['items_new'], $stats['items_updated'], $stats['items_failed']));
                hh_scrape_job_finish($this->pdo, $this->jobId, array_merge($stats, array(
                    'status' => 'done',
                    'message' => sprintf('new=%d updated=%d failed=%d', $stats['items_new'], $stats['items_updated'], $stats['items_failed']),
                )));
            }
            return $stats;
        } catch (Throwable $e) {
            if ($this->jobId) {
                hh_scrape_log($this->pdo, $this->jobId, 'error', '', '任务失败：' . $e->getMessage());
                hh_scrape_job_finish($this->pdo, $this->jobId, array_merge($stats, array(
                    'status' => 'failed',
                    'message' => $e->getMessage(),
                )));
            }
            throw $e;
        }
    }
}

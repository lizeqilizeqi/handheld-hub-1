<?php



require_once __DIR__ . '/../handheld_repo.php';

require_once __DIR__ . '/mydrivers_news.php';



class HhNewsScraperService

{

    private $pdo;

    private $jobId;



    public function __construct(PDO $pdo, $jobId)

    {

        $this->pdo = $pdo;

        $this->jobId = (int) $jobId;

    }



    public function run()

    {

        $stats = array(

            'items_found' => 0,

            'items_new' => 0,

            'items_updated' => 0,

            'items_failed' => 0,

            'current_page' => 0,

            'total_pages' => 1,

        );



        $opts = hh_scrape_job_options($this->pdo, $this->jobId);

        $since = isset($opts['since']) ? (string) $opts['since'] : hh_mydrivers_default_since_date();

        if (!preg_match('#^\d{4}-\d{2}-\d{2}$#', $since)) {

            $since = hh_mydrivers_default_since_date();

        }



        try {

            hh_scrape_log($this->pdo, $this->jobId, 'info', '', '资讯抓取：快科技掌机游戏 · 不早于 ' . $since);

            $sourceId = hh_mydrivers_source_id($this->pdo);



            hh_scrape_log($this->pdo, $this->jobId, 'fetch', '', '拉取列表（含滚动分页）…');

            $list = hh_mydrivers_collect_list_items($since);

            $stats['items_found'] = count($list);

            hh_scrape_job_update_progress($this->pdo, $this->jobId, array_merge($stats, array(

                'message' => '列表 ' . $stats['items_found'] . ' 条，开始抓取正文…',

            )));



            $delayMs = (int) hh_config_get('scraper.request_delay_ms', 1200);

            $i = 0;

            foreach ($list as $item) {

                $i++;

                $stats['current_page'] = $i;

                $stats['total_pages'] = max($stats['items_found'], 1);

                try {

                    $hash = hash('sha256', (int) $sourceId . '|' . $item['link']);

                    $st = $this->pdo->prepare('SELECT id FROM hh_feed_items WHERE guid_hash = ? LIMIT 1');

                    $st->execute(array($hash));

                    if ($st->fetchColumn()) {

                        hh_scrape_log($this->pdo, $this->jobId, 'skip', '', '已存在：' . ($item['title'] ?? ''));

                        continue;

                    }



                    hh_scrape_log($this->pdo, $this->jobId, 'fetch', '', '正文：' . ($item['title'] ?? $item['link']));

                    $article = hh_mydrivers_fetch_article($item['link']);

                    if ($article['title'] !== '') {

                        $item['title'] = $article['title'];

                    }

                    if (!empty($article['published_at'])) {

                        $item['published_at'] = $article['published_at'];

                    }

                    if ($article['body_html'] !== '') {

                        $item['body_zh'] = $article['body_html'];

                    }

                    if ($item['image'] === '' && $article['lead_image'] !== '') {

                        $item['image'] = $article['lead_image'];

                    }



                    $r = hh_feed_item_upsert_scraped($this->pdo, $sourceId, $item);

                    if ($r === 'new') {

                        $stats['items_new']++;

                        hh_scrape_log($this->pdo, $this->jobId, 'ok', '', '新增：' . ($item['title'] ?? ''));

                    }

                } catch (Throwable $e) {

                    $stats['items_failed']++;

                    hh_scrape_log($this->pdo, $this->jobId, 'error', '', '失败：' . $e->getMessage());

                }



                if ($delayMs > 0) {

                    usleep($delayMs * 1000);

                }

                if ($i % 5 === 0) {

                    hh_scrape_job_update_progress($this->pdo, $this->jobId, array_merge($stats, array(

                        'message' => '正文进度 ' . $i . '/' . $stats['items_found'],

                    )));

                }

            }



            hh_scrape_job_finish($this->pdo, $this->jobId, array_merge($stats, array(

                'status' => 'done',

                'message' => sprintf('完成：列表 %d，新增 %d，失败 %d', $stats['items_found'], $stats['items_new'], $stats['items_failed']),

            )));

        } catch (Throwable $e) {

            hh_scrape_log($this->pdo, $this->jobId, 'error', '', '任务失败：' . $e->getMessage());

            hh_scrape_job_finish($this->pdo, $this->jobId, array_merge($stats, array(

                'status' => 'failed',

                'message' => $e->getMessage(),

            )));

            throw $e;

        }



        return $stats;

    }

}



class HhGameScraperService

{

    private $pdo;

    private $jobId;



    public function __construct(PDO $pdo, $jobId)

    {

        $this->pdo = $pdo;

        $this->jobId = (int) $jobId;

    }



    public function run()

    {

        hh_scrape_log($this->pdo, $this->jobId, 'info', '', '复古游戏抓取尚未配置数据源');

        hh_scrape_job_finish($this->pdo, $this->jobId, array(

            'status' => 'failed',

            'items_found' => 0,

            'items_new' => 0,

            'items_updated' => 0,

            'items_failed' => 0,

            'current_page' => 0,

            'total_pages' => 0,

            'message' => '复古游戏抓取源未配置，请在后续版本添加',

        ));

        return array('items_found' => 0, 'items_new' => 0, 'items_updated' => 0, 'items_failed' => 0);

    }

}



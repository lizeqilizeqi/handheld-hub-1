<?php
$html = file_get_contents('https://zhangjiquan.com/handheld/rg-rotate');
preg_match_all('/<img[^>]+>/i', $html, $m);
echo 'detail img count=' . count($m[0]) . PHP_EOL;
foreach ($m[0] as $i => $tag) {
    echo $i . ' ' . $tag . PHP_EOL;
}
echo "---LIST---\n";
$list = file_get_contents('https://zhangjiquan.com/handhelds?page=1');
$pos = strpos($list, '/handheld/rg-rotate');
echo substr($list, max(0, $pos - 300), 1200) . PHP_EOL;

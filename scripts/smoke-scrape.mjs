/**
 * Smoke test: fetch zhangjiquan list + one detail page (no DB required).
 * Run: node scripts/smoke-scrape.mjs
 */
const BASE = 'https://zhangjiquan.com';
const UA = 'HandheldHubBot/1.0 (+smoke-test)';

async function fetchText(url) {
  const res = await fetch(url, {
    headers: { 'User-Agent': UA, 'Accept-Language': 'zh-CN,zh;q=0.9,en;q=0.8' },
  });
  if (!res.ok) throw new Error(`HTTP ${res.status} for ${url}`);
  return res.text();
}

function parseListSlugs(html) {
  const re = /href="(\/handheld\/([a-zA-Z0-9_-]+))"/g;
  const slugs = [];
  const seen = new Set();
  let m;
  while ((m = re.exec(html)) !== null) {
    if (!seen.has(m[2])) {
      seen.add(m[2]);
      slugs.push({ slug: m[2], url: BASE + m[1] });
    }
  }
  return slugs;
}

function parseDetail(html, slug) {
  const h1 = html.match(/<h1[^>]*>([^<]+)<\/h1>/i);
  const name = h1 ? h1[1].trim() : slug;
  const specs = {};
  const rowRe = /<tr[^>]*>\s*<t[dh][^>]*>([^<]*)<\/t[dh]>\s*<td[^>]*>([^<]*)<\/td>/gi;
  let rm;
  while ((rm = rowRe.exec(html)) !== null) {
    const k = rm[1].replace(/\s+/g, ' ').trim();
    const v = rm[2].replace(/\s+/g, ' ').trim();
    if (k && v && k !== v) specs[k] = v;
  }
  const imgs = [...html.matchAll(/<img[^>]+src="([^"]+)"/gi)]
    .map((x) => x[1])
    .filter((s) => /\.(jpg|jpeg|png|webp|gif)/i.test(s) && !/logo|qrcode/i.test(s));
  return { name, specs, imageCount: new Set(imgs).size };
}

async function main() {
  console.log('=== Handheld Hub smoke test ===\n');

  console.log('1) Fetch list page...');
  const listHtml = await fetchText(`${BASE}/handhelds?page=1`);
  const slugs = parseListSlugs(listHtml);
  console.log(`   OK — found ${slugs.length} handhelds on page 1`);
  if (slugs.length === 0) throw new Error('No slugs parsed — site HTML may have changed');

  const sample = slugs[0];
  console.log(`\n2) Fetch detail: ${sample.slug} ...`);
  await new Promise((r) => setTimeout(r, 1200));
  const detailHtml = await fetchText(sample.url);
  const detail = parseDetail(detailHtml, sample.slug);
  console.log(`   Name: ${detail.name}`);
  console.log(`   Brand: ${detail.specs['品牌'] || detail.specs['Brand'] || '(n/a)'}`);
  console.log(`   Release: ${detail.specs['发布时间'] || '(n/a)'}`);
  console.log(`   Spec rows: ${Object.keys(detail.specs).length}`);
  console.log(`   Images found: ${detail.imageCount}`);

  console.log('\n=== Smoke test PASSED ===');
  console.log('Next: install Docker Desktop, then run:');
  console.log('  cd "D:\\cursor\\google blog\\1\\handheld-hub"');
  console.log('  docker compose up -d');
  console.log('  docker compose exec web php bin/scrape.php --slug=' + sample.slug);
}

main().catch((e) => {
  console.error('\n=== Smoke test FAILED ===');
  console.error(e.message || e);
  process.exit(1);
});

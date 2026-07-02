const UA = 'HandheldHubBot/1.0';
async function go(url) {
  const r = await fetch(url, { headers: { 'User-Agent': UA } });
  return r.text();
}

(async () => {
  const list = await go('https://zhangjiquan.com/handhelds?page=1');
  const slug = 'rg-rotate';
  const idx = list.indexOf('/handheld/rg-rotate');
  console.log('LIST snippet around link:\n', list.slice(Math.max(0, idx - 200), idx + 800));

  const detail = await go('https://zhangjiquan.com/handheld/rg-rotate');
  // find h1 and nearby img
  const h1 = detail.match(/<h1[^>]*>([^<]+)/i);
  console.log('\nH1:', h1 && h1[1]);

  const imgs = [...detail.matchAll(/<img[^>]+>/gi)].map((m) => m[0]);
  console.log('\nDETAIL img tags count:', imgs.length);
  imgs.forEach((tag, i) => console.log(i, tag.slice(0, 200)));
})();

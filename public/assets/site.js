(function () {
  var banner = document.getElementById('cookie-banner');
  var btn = document.getElementById('cookie-accept');
  if (!banner || !btn) return;
  try {
    if (localStorage.getItem('hh_cookie_consent') === '1') return;
  } catch (e) {}
  banner.hidden = false;
  btn.addEventListener('click', function () {
    try { localStorage.setItem('hh_cookie_consent', '1'); } catch (e) {}
    banner.hidden = true;
  });
})();

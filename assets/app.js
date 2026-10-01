// Toggle terang / gelap — mandiri (tidak tergantung CDN Tailwind)
(function () {
  var root = document.documentElement;
  var btn = document.getElementById('themeToggle');
  var label = document.getElementById('themeLabel');

  function apply(dark) {
    root.classList.toggle('dark', dark);
    root.setAttribute('data-theme', dark ? 'dark' : 'light');
    root.style.colorScheme = dark ? 'dark' : 'light';
    try { localStorage.setItem('pi-theme', dark ? 'dark' : 'light'); } catch (e) {}
    if (label) label.textContent = dark ? 'Gelap' : 'Terang';
    if (window.Chart) {
      Chart.defaults.color = dark ? '#94a3b8' : '#64748b';
      Chart.defaults.borderColor = dark ? 'rgba(255,255,255,0.08)' : 'rgba(15,23,42,0.08)';
    }
  }

  // samakan label dengan kondisi awal (dari init di <head>)
  if (label) label.textContent = root.classList.contains('dark') ? 'Gelap' : 'Terang';

  if (btn) btn.addEventListener('click', function () {
    apply(!root.classList.contains('dark'));
  });

  console.log('Pojok Indah ready, theme=' + root.getAttribute('data-theme'));
})();

// Searchable dropdown / autocomplete generik.
// txt: input ketik, hid: hidden kode, drop: div daftar, items: [{kode,nama,...}],
// fmt: fn(item)->label, onPick: fn(item|null).
window.piCombo = function (txt, hid, drop, items, fmt, onPick) {
  var list = [], hl = -1;
  function close() { drop.classList.add('hidden'); hl = -1; }
  function render() {
    var q = (txt.value || '').trim().toLowerCase();
    list = [];
    for (var i = 0; i < items.length && list.length < 60; i++) {
      var b = items[i];
      if (!q || ((b.kode || '') + ' ' + (b.nama || '')).toLowerCase().indexOf(q) !== -1) list.push(b);
    }
    drop.innerHTML = '';
    if (!list.length) {
      var em = document.createElement('div');
      em.className = 'pi-empty';
      em.textContent = 'Tidak ketemu — coba kata lain';
      drop.appendChild(em);
    } else {
      list.forEach(function (b, i) {
        var d = document.createElement('div');
        d.className = 'pi-opt';
        d.textContent = fmt(b);
        d.addEventListener('mousedown', function (e) { e.preventDefault(); pick(b); });
        d.addEventListener('mousemove', function () { setHl(i); });
        drop.appendChild(d);
      });
    }
    hl = -1;
    drop.classList.remove('hidden');
  }
  function setHl(i) {
    hl = i;
    var kids = drop.children;
    for (var k = 0; k < kids.length; k++) kids[k].classList.toggle('hl', k === i);
    if (kids[i] && kids[i].scrollIntoView) kids[i].scrollIntoView({ block: 'nearest' });
  }
  function pick(b) {
    hid.value = b.kode;
    txt.value = fmt(b);
    close();
    if (onPick) onPick(b);
  }
  txt.addEventListener('focus', render);
  txt.addEventListener('input', function () { hid.value = ''; if (onPick) onPick(null); render(); });
  txt.addEventListener('keydown', function (e) {
    if (drop.classList.contains('hidden')) {
      if (e.key === 'ArrowDown' || e.key === 'Enter') { render(); e.preventDefault(); }
      return;
    }
    if (e.key === 'ArrowDown') { e.preventDefault(); setHl(Math.min(hl + 1, list.length - 1)); }
    else if (e.key === 'ArrowUp') { e.preventDefault(); setHl(Math.max(hl - 1, 0)); }
    else if (e.key === 'Enter') { if (hl >= 0 && list[hl]) { e.preventDefault(); pick(list[hl]); } }
    else if (e.key === 'Escape') { close(); }
  });
  txt.addEventListener('blur', function () { setTimeout(close, 150); });
  return { pick: pick, close: close, render: render };
};

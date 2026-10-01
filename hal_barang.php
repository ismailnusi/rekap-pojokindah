<?php
// Data Barang — fungsi tetap, tampilan mengikuti template
$owner = !empty($owner); // true = owner, false = karyawan (baca saja)
$q = trim($_GET['q'] ?? '');
if ($q !== '') {
    $st = $pdo->prepare("SELECT * FROM barang WHERE kode LIKE ? OR nama LIKE ? ORDER BY kode");
    $st->execute(["%$q%","%$q%"]);
} else {
    $st = $pdo->query("SELECT * FROM barang ORDER BY kode");
}
$rows = $st->fetchAll(PDO::FETCH_ASSOC);
$maxN = 0;
foreach ($pdo->query("SELECT kode FROM barang")->fetchAll(PDO::FETCH_COLUMN) as $k) { if (preg_match('/F(\d+)/',$k,$m)) $maxN = max($maxN,(int)$m[1]); }
$nextKode = 'F' . str_pad($maxN+1, 3, '0', STR_PAD_LEFT);
$edit = null;
if (isset($_GET['edit'])) {
    $s = $pdo->prepare("SELECT * FROM barang WHERE kode=?"); $s->execute([$_GET['edit']]);
    $edit = $s->fetch(PDO::FETCH_ASSOC);
}
// total terjual per barang (semua tanggal) → sisa = stok_awal − terjual
$terjualMap = $pdo->query("SELECT kode_barang, COALESCE(SUM(qty),0) FROM transaksi GROUP BY kode_barang")->fetchAll(PDO::FETCH_KEY_PAIR);
$sisaOf = function ($r) use ($terjualMap) {
    $awal = (int)($r['stok_awal'] ?? 0);
    if ($awal <= 0) return null; // tanpa batas (jasa)
    return $awal - (int)($terjualMap[$r['kode']] ?? 0);
};
$restock = stok_menipis($pdo);
?>
<div class="space-y-6">
  <?php if ($restock): ?>
  <div class="glass-card p-5 rounded-3xl border-l-4 border-l-rose-500">
    <h3 class="font-bold text-sm flex items-center"><i class="fa-solid fa-triangle-exclamation text-rose-500 mr-2.5"></i> Perlu Restock (<?=count($restock)?>)</h3>
    <div class="mt-3 flex flex-wrap gap-2">
      <?php foreach ($restock as $r): ?>
        <?php if ($owner): ?>
        <a href="index.php?page=barang&edit=<?=e($r['kode'])?>" title="Klik untuk tambah stok" class="flex items-center gap-2 text-xs px-3 py-2 rounded-xl border <?= $r['status'] === 'habis' ? 'bg-rose-500/10 border-rose-500/40 text-rose-600 dark:text-rose-400' : 'bg-amber-500/10 border-amber-500/40 text-amber-600 dark:text-amber-400' ?> hover:scale-105 transition">
        <?php else: ?>
        <span class="flex items-center gap-2 text-xs px-3 py-2 rounded-xl border <?= $r['status'] === 'habis' ? 'bg-rose-500/10 border-rose-500/40 text-rose-600 dark:text-rose-400' : 'bg-amber-500/10 border-amber-500/40 text-amber-600 dark:text-amber-400' ?>">
        <?php endif; ?>
          <b class="font-mono"><?=e($r['kode'])?></b> <?=e($r['nama'])?>
          <span class="font-bold">sisa <?=e($r['sisa'])?></span>
          <span class="opacity-70"><?= $r['status'] === 'habis' ? 'HABIS' : 'min ' . $r['stok_min'] ?></span>
        <?= $owner ? '</a>' : '</span>' ?>
      <?php endforeach; ?>
    </div>
    <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-2">Klik item untuk tambah stoknya.</p>
  </div>
  <?php endif; ?>
<div class="space-y-6">
  <div class="glass-card p-5 rounded-3xl flex flex-col md:flex-row md:items-center justify-between gap-4">
    <div>
      <h2 class="text-xl font-bold flex items-center"><i class="fa-solid fa-boxes-stacked text-emerald-500 mr-3"></i> Catalog &amp; Inventory</h2>
      <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Kelola data harga jual dan modal produk/jasa</p>
    </div>
    <div class="flex items-center space-x-3">
      <form method="get" action="index.php" class="relative">
        <input type="hidden" name="page" value="barang">
        <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-3 text-xs text-slate-400"></i>
        <input type="text" name="q" value="<?=e($q)?>" placeholder="Cari kode atau nama..." class="pl-9 pr-4 py-2 text-xs glass-input rounded-xl focus:outline-none w-52 sm:w-64">
      </form>
      <span class="text-xs bg-slate-100 dark:bg-slate-800 text-emerald-600 dark:text-emerald-400 border border-slate-200 dark:border-slate-700 px-3.5 py-2 rounded-xl font-bold"><?=count($rows)?> Item</span>
    </div>
  </div>

  <div class="grid <?= $owner ? 'lg:grid-cols-12' : '' ?> gap-6">
    <?php if ($owner): ?>
    <div class="lg:col-span-4 glass-card p-6 rounded-3xl space-y-4 h-fit">
      <h3 class="font-bold text-sm border-b border-slate-200 dark:border-slate-800 pb-4 flex items-center">
        <i class="fa-solid fa-square-plus text-emerald-500 mr-2.5"></i> <?= $edit ? 'Edit Item '.e($edit['kode']) : 'Tambah Item Baru' ?>
      </h3>
      <form method="post" enctype="multipart/form-data" class="space-y-4">
        <input type="hidden" name="aksi" value="<?= $edit ? 'barang_edit' : 'barang_tambah' ?>">
        <?php if ($edit): ?><input type="hidden" name="kode_lama" value="<?=e($edit['kode'])?>"><?php endif; ?>
        <div><label class="block text-xs font-semibold mb-1.5">Kode Item</label>
          <input type="text" name="kode" required pattern="[A-Za-z0-9]+" value="<?=e($edit['kode'] ?? $nextKode)?>" class="w-full text-xs glass-input rounded-xl p-3 font-mono font-bold focus:outline-none"></div>
        <div><label class="block text-xs font-semibold mb-1.5">Nama Barang / Jasa</label>
          <input type="text" name="nama" required value="<?=e($edit['nama'] ?? '')?>" placeholder="cth: FOTO COPY WARNA" class="w-full text-xs glass-input rounded-xl p-3 focus:outline-none"></div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div><label class="block text-xs font-semibold mb-1.5">Harga Jual (Rp)</label>
            <input type="number" name="harga_jual" min="0" step="100" value="<?=e($edit['harga_jual'] ?? 0)?>" class="w-full text-xs glass-input rounded-xl p-3 focus:outline-none"></div>
          <div><label class="block text-xs font-semibold mb-1.5">Modal HPP (Rp)</label>
            <input type="number" name="modal" min="0" step="100" value="<?=e($edit['modal'] ?? 0)?>" class="w-full text-xs glass-input rounded-xl p-3 focus:outline-none"></div>
        </div>
        <div><label class="block text-xs font-semibold mb-1.5">Stok Awal (pcs) — 0 = tanpa batas/jasa</label>
          <input type="number" name="stok_awal" min="0" step="1" value="<?=e($edit['stok_awal'] ?? 0)?>" class="w-full text-xs glass-input rounded-xl p-3 focus:outline-none">
          <?php if ($edit && (int)($edit['stok_awal'] ?? 0) > 0): ?>
            <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">Terjual <?= (int)($terjualMap[$edit['kode']] ?? 0) ?> • Sisa <?= $sisaOf($edit) ?> (stok awal tidak berubah oleh penjualan)</p>
          <?php endif; ?>
        </div>
        <div><label class="block text-xs font-semibold mb-1.5">Batas Minimum (peringatan menipis)</label>
          <input type="number" name="stok_min" min="0" step="1" value="<?=e($edit['stok_min'] ?? 0)?>" class="w-full text-xs glass-input rounded-xl p-3 focus:outline-none">
          <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">Sisa ≤ batas ini = masuk daftar restock. 0 = hanya ingatkan saat habis.</p>
        </div>
        <div><label class="block text-xs font-semibold mb-1.5">Foto Produk (JPG/PNG)</label>
          <input type="file" name="gambar" accept="image/*" class="w-full text-xs glass-input rounded-xl p-2 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:bg-emerald-500/20 file:text-emerald-500 dark:file:text-emerald-400">
          <?php if ($edit && $edit['gambar'] && file_exists(UPLOAD_DIR.'/'.$edit['gambar'])): ?>
            <img src="uploads/<?=e($edit['gambar'])?>" class="w-24 h-24 object-cover rounded-xl mt-2 border border-slate-200 dark:border-slate-700" alt="">
          <?php endif; ?>
        </div>
        <button class="w-full bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-extrabold py-3 rounded-xl text-xs shadow-lg shadow-emerald-500/20 transition">
          <?= $edit ? 'Simpan Perubahan' : 'Simpan ke Catalog' ?>
        </button>
        <?php if ($edit): ?><a href="index.php?page=barang" class="block text-center text-xs text-slate-500 hover:underline">Batal</a><?php endif; ?>
      </form>
      <p class="text-[11px] text-slate-500 dark:text-slate-400">Modal dipakai di Rekapan: Laba = Total Penjualan − Total Modal. Jasa isi 0.</p>
    </div>
    <?php endif; ?>

    <div class="<?= $owner ? 'lg:col-span-8' : '' ?> glass-card p-6 rounded-3xl space-y-4">
      <h3 class="font-bold text-sm border-b border-slate-200 dark:border-slate-800 pb-4">Daftar Inventaris <?= $owner ? '' : '<span class="text-[11px] font-normal text-slate-500">(baca saja — perubahan oleh Owner)</span>' ?></h3>
      <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 overflow-x-auto custom-scrollbar">
        <table class="w-full text-left text-xs min-w-[760px]">
          <thead class="tbl-head font-semibold border-b border-slate-200 dark:border-slate-800">
            <tr><th class="p-3 text-center">Preview</th><th class="p-3">Kode</th><th class="p-3">Nama Produk</th><th class="p-3 text-right">Harga Jual</th><?= $owner ? '<th class="p-3 text-right">Modal</th>' : '' ?><th class="p-3 text-right">Stok Awal</th><th class="p-3 text-right">Min</th><th class="p-3 text-right">Terjual</th><th class="p-3 text-right">Sisa Stok</th><?= $owner ? '<th class="p-3 text-center">Aksi</th>' : '' ?></tr>
          </thead>
          <tbody class="tbl-body divide-y tbl-row">
            <?php foreach ($rows as $r):
              $awal = (int)($r['stok_awal'] ?? 0);
              $tj = (int)($terjualMap[$r['kode']] ?? 0);
              $sisa = $awal > 0 ? $awal - $tj : null;
            ?>
            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/30 transition">
              <td class="p-2 text-center">
                <?php if ($r['gambar'] && file_exists(UPLOAD_DIR.'/'.$r['gambar'])): ?>
                  <button type="button" class="img-zoom-btn group relative" data-full="uploads/<?=e($r['gambar'])?>" data-caption="<?=e($r['kode'])?> - <?=e($r['nama'])?>" title="Klik untuk zoom">
                    <img src="uploads/<?=e($r['gambar'])?>" class="w-9 h-9 rounded-xl object-cover border border-slate-200 dark:border-slate-700 mx-auto group-hover:scale-110 group-hover:border-emerald-500 transition" alt="<?=e($r['nama'])?>">
                    <span class="absolute -bottom-1 -right-1 w-4 h-4 rounded-full bg-emerald-500 text-slate-950 flex items-center justify-center" style="font-size:8px"><i class="fa-solid fa-magnifying-glass-plus"></i></span>
                  </button>
                <?php else: ?>
                  <div class="w-9 h-9 rounded-xl bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 flex items-center justify-center mx-auto text-slate-400"><i class="fa-solid fa-image"></i></div>
                <?php endif; ?>
              </td>
              <td class="p-3 font-mono font-bold text-emerald-600 dark:text-emerald-400"><?=e($r['kode'])?></td>
              <td class="p-3 font-semibold"><?=e($r['nama'])?></td>
              <td class="p-3 text-right font-medium"><?=rupiah($r['harga_jual'])?></td>
              <?php if ($owner): ?><td class="p-3 text-right text-slate-500"><?=rupiah($r['modal'])?></td><?php endif; ?>
              <td class="p-3 text-right"><?= $awal > 0 ? $awal : '<span class="text-slate-400">∞</span>' ?></td>
              <td class="p-3 text-right text-slate-500"><?= $awal > 0 ? (int)($r['stok_min'] ?? 0) : '<span class="text-slate-400">-</span>' ?></td>
              <td class="p-3 text-right"><?= $awal > 0 ? $tj : '<span class="text-slate-400">-</span>' ?></td>
              <td class="p-3 text-right font-bold <?= $sisa === null ? 'text-slate-400' : ($sisa <= 0 ? 'text-rose-500' : ($sisa <= 5 ? 'text-amber-500' : 'text-emerald-600 dark:text-emerald-400')) ?>"><?= $sisa === null ? '∞' : $sisa ?></td>
              <?php if ($owner): ?>
              <td class="p-3 text-center space-x-1 whitespace-nowrap">
                <a href="index.php?page=barang&edit=<?=e($r['kode'])?><?= $q!==''?'&q='.urlencode($q):'' ?>" class="bg-slate-200 dark:bg-slate-800 hover:bg-slate-300 dark:hover:bg-slate-700 px-2.5 py-1 rounded-lg text-[11px] font-semibold transition inline-block">Edit</a>
                <form method="post" style="display:inline" onsubmit="return confirm('Hapus <?=e($r['kode'])?> ?')">
                  <input type="hidden" name="aksi" value="barang_hapus"><input type="hidden" name="kode" value="<?=e($r['kode'])?>">
                  <button class="bg-rose-500/10 hover:bg-rose-500/20 text-rose-500 px-2.5 py-1 rounded-lg text-[11px] font-semibold transition">Hapus</button>
                </form>
              </td>
              <?php endif; ?>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Lightbox zoom gambar -->
<div id="imgLightbox" class="fixed inset-0 z-[100] hidden items-center justify-center p-4" style="background:rgba(2,6,12,.85);backdrop-filter:blur(6px)">
  <div class="max-w-3xl w-full">
    <div class="flex items-center justify-between mb-3 gap-2">
      <p id="lbCaption" class="text-sm font-bold text-white truncate"></p>
      <div class="flex items-center gap-2">
        <button type="button" id="lbZoomOut" title="Perkecil" class="w-9 h-9 rounded-xl bg-white/10 hover:bg-white/20 text-white text-sm transition"><i class="fa-solid fa-minus"></i></button>
        <span id="lbPct" class="text-xs text-slate-300 w-12 text-center">100%</span>
        <button type="button" id="lbZoomIn" title="Perbesar" class="w-9 h-9 rounded-xl bg-white/10 hover:bg-white/20 text-white text-sm transition"><i class="fa-solid fa-plus"></i></button>
        <button type="button" id="lbReset" title="Reset" class="w-9 h-9 rounded-xl bg-white/10 hover:bg-white/20 text-white text-sm transition"><i class="fa-solid fa-rotate-left"></i></button>
        <button type="button" id="lbClose" title="Tutup (Esc)" class="w-9 h-9 rounded-xl bg-rose-500 hover:bg-rose-400 text-white text-sm transition"><i class="fa-solid fa-xmark"></i></button>
      </div>
    </div>
    <div id="lbStage" class="overflow-auto rounded-2xl border border-white/15 bg-slate-950 flex items-center justify-center custom-scrollbar" style="max-height:75vh;min-height:200px">
      <img id="lbImg" src="" alt="" class="max-w-none select-none" draggable="false" style="transform-origin:center center">
    </div>
    <p class="text-[11px] text-slate-400 mt-2 text-center">Scroll / tombol +/− untuk zoom • seret untuk geser • klik area gelap / Esc untuk tutup</p>
  </div>
</div>

<script>
(function () {
  var box = document.getElementById('imgLightbox');
  var img = document.getElementById('lbImg');
  var stage = document.getElementById('lbStage');
  var cap = document.getElementById('lbCaption');
  var pct = document.getElementById('lbPct');
  var scale = 1, base = 1;

  function render() {
    img.style.transform = 'scale(' + scale + ')';
    pct.textContent = Math.round(scale * 100) + '%';
  }
  function open(src, caption) {
    img.src = src; cap.textContent = caption || '';
    scale = 1; stage.scrollLeft = 0; stage.scrollTop = 0;
    img.onload = function () {
      // sesuaikan awal agar muat di layar
      var r = stage.getBoundingClientRect();
      var s = Math.min(1, (r.width - 32) / img.naturalWidth, ((window.innerHeight * 0.75) - 32) / img.naturalHeight);
      base = s > 0 ? s : 1; scale = 1; render();
      img.style.width = (img.naturalWidth * base) + 'px';
    };
    box.classList.remove('hidden'); box.classList.add('flex');
    document.body.style.overflow = 'hidden';
  }
  function close() {
    box.classList.add('hidden'); box.classList.remove('flex');
    img.src = ''; document.body.style.overflow = '';
  }
  document.querySelectorAll('.img-zoom-btn').forEach(function (b) {
    b.addEventListener('click', function () { open(b.dataset.full, b.dataset.caption); });
  });
  document.getElementById('lbClose').addEventListener('click', close);
  document.getElementById('lbReset').addEventListener('click', function () { scale = 1; stage.scrollLeft = 0; stage.scrollTop = 0; render(); });
  document.getElementById('lbZoomIn').addEventListener('click', function () { scale = Math.min(5, scale + 0.25); render(); });
  document.getElementById('lbZoomOut').addEventListener('click', function () { scale = Math.max(0.25, scale - 0.25); render(); });
  box.addEventListener('click', function (e) { if (e.target === box) close(); });
  document.addEventListener('keydown', function (e) {
    if (box.classList.contains('hidden')) return;
    if (e.key === 'Escape') close();
    if (e.key === '+' || e.key === '=') { scale = Math.min(5, scale + 0.25); render(); }
    if (e.key === '-') { scale = Math.max(0.25, scale - 0.25); render(); }
    if (e.key === '0') { scale = 1; render(); }
  });
  stage.addEventListener('wheel', function (e) {
    e.preventDefault();
    scale = Math.max(0.25, Math.min(5, scale + (e.deltaY < 0 ? 0.15 : -0.15)));
    render();
  }, { passive: false });
  // seret untuk geser saat di-zoom
  var drag = null;
  stage.addEventListener('mousedown', function (e) { drag = { x: e.clientX, y: e.clientY, l: stage.scrollLeft, t: stage.scrollTop }; });
  window.addEventListener('mouseup', function () { drag = null; });
  stage.addEventListener('mousemove', function (e) {
    if (!drag) return;
    stage.scrollLeft = drag.l - (e.clientX - drag.x);
    stage.scrollTop = drag.t - (e.clientY - drag.y);
  });
})();
</script>

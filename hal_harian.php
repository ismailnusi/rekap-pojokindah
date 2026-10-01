<?php
// Halaman Harian — fungsi tetap, tampilan mengikuti template
$owner = !empty($owner); // karyawan: input saja, tanpa hapus
$bulan = bulan_aktif();
$tanggal = tanggal_aktif();
if (substr($tanggal,0,7) !== $bulan) { $tanggal = $bulan . '-01'; }

$allBarang = $pdo->query("SELECT kode,nama,harga_jual,COALESCE(stok_awal,0) AS stok_awal FROM barang ORDER BY nama")->fetchAll(PDO::FETCH_ASSOC);
$terjualAll = $pdo->query("SELECT kode_barang, COALESCE(SUM(qty),0) FROM transaksi GROUP BY kode_barang")->fetchAll(PDO::FETCH_KEY_PAIR);

$st = $pdo->prepare("SELECT t.*, b.nama FROM transaksi t LEFT JOIN barang b ON b.kode=t.kode_barang WHERE t.tanggal=? ORDER BY t.id");
$st->execute([$tanggal]);
$jual = $st->fetchAll(PDO::FETCH_ASSOC);

$st2 = $pdo->prepare("SELECT * FROM pengeluaran WHERE tanggal=? ORDER BY id");
$st2->execute([$tanggal]);
$keluar = $st2->fetchAll(PDO::FETCH_ASSOC);

$st3 = $pdo->prepare("SELECT jumlah FROM transfer_harian WHERE tanggal=?");
$st3->execute([$tanggal]);
$transfer = (int)($st3->fetchColumn() ?: 0);

$totalPendapatan = array_sum(array_column($jual,'jumlah'));
$totalKeluar = array_sum(array_column($keluar,'jumlah'));

// kasbon hari ini
$stK = $pdo->prepare("SELECT * FROM kasbon WHERE tanggal=? ORDER BY id");
$stK->execute([$tanggal]);
$pinjamHari = $stK->fetchAll(PDO::FETCH_ASSOC);
$stS = $pdo->prepare("SELECT * FROM kasbon_setoran WHERE tanggal=? ORDER BY id");
$stS->execute([$tanggal]);
$setorHari = $stS->fetchAll(PDO::FETCH_ASSOC);
$totPinjamHari = array_sum(array_column($pinjamHari, 'jumlah'));
$totSetorHari = array_sum(array_column($setorHari, 'jumlah'));

// kasbon mengurangi saldo kas, setoran mengembalikan; keuntungan TIDAK tersentuh
$saldoKas = $totalPendapatan - $totalKeluar - $transfer - $totPinjamHari + $totSetorHari;
// sisa per orang (global) untuk dropdown setoran
$sisaOrang = kasbon_sisa_map($pdo);
$adaSisa = array_filter($sisaOrang, function ($v) { return $v['sisa'] > 0; });

// semua tanggal yg ada pengimputan (transaksi / pengeluaran / transfer / kasbon / setoran) — untuk titik kalender
$filledDates = $pdo->query("SELECT tanggal FROM transaksi UNION SELECT tanggal FROM pengeluaran UNION SELECT tanggal FROM transfer_harian UNION SELECT tanggal FROM kasbon UNION SELECT tanggal FROM kasbon_setoran")->fetchAll(PDO::FETCH_COLUMN);
// libur nasional tahun berjalan (Sabtu/Minggu otomatis merah via JS)
$liburAwal = hari_libur(substr($tanggal, 0, 4));
$hariIndo = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
$blnIndo = [1=>'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
$ts = strtotime($tanggal);
$judulTanggal = $hariIndo[(int)date('w', $ts)] . ', ' . (int)date('j', $ts) . ' ' . $blnIndo[(int)date('n', $ts)] . ' ' . date('Y', $ts);
?>
<div class="space-y-6">
  <div class="glass-card p-5 rounded-3xl flex flex-col md:flex-row md:items-center justify-between gap-4 relative z-30">
    <div>
      <h2 class="text-xl font-bold flex items-center"><i class="fa-regular fa-calendar-check text-emerald-500 mr-3"></i> Penjualan Harian</h2>
      <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Closing otomatis dari catatan operasional harian • <b class="text-slate-700 dark:text-slate-200"><?=e($judulTanggal)?></b></p>
    </div>
    <div class="relative" id="calWrap">
      <button type="button" id="calBtn" class="glass-input pl-4 pr-4 py-2.5 rounded-xl text-xs font-bold flex items-center gap-2.5 hover:border-emerald-500 transition">
        <i class="fa-regular fa-calendar-days text-emerald-500"></i>
        <span id="calBtnText"><?=e($judulTanggal)?></span>
        <i class="fa-solid fa-chevron-down text-[10px] text-slate-400"></i>
      </button>
      <div id="calPop" class="hidden absolute right-0 mt-2 z-50 glass-card rounded-2xl p-4 shadow-2xl" style="width:320px;max-width:calc(100vw - 2rem)">
        <div class="flex items-center justify-between mb-3">
          <button type="button" id="calPrev" class="w-8 h-8 rounded-lg bg-slate-100 dark:bg-slate-800 hover:bg-emerald-500 hover:text-slate-950 text-xs transition">‹</button>
          <div class="text-sm font-extrabold"><span id="calMonth"></span> <span id="calYear" class="text-emerald-600 dark:text-emerald-400"></span></div>
          <button type="button" id="calNext" class="w-8 h-8 rounded-lg bg-slate-100 dark:bg-slate-800 hover:bg-emerald-500 hover:text-slate-950 text-xs transition">›</button>
        </div>
        <div class="grid grid-cols-7 gap-1 text-center text-[10px] font-bold mb-1">
          <span>Sen</span><span>Sel</span><span>Rab</span><span>Kam</span><span>Jum</span><span class="text-rose-500">Sab</span><span class="text-rose-500">Min</span>
        </div>
        <div id="calGrid" class="grid grid-cols-7 gap-1"></div>
        <div id="calLibur" class="mt-3 text-[11px] text-slate-500 dark:text-slate-400 space-y-1"></div>
        <div class="mt-3 pt-3 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between text-[11px]">
          <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-rose-500 inline-block"></span> Sabtu/Minggu &amp; libur nasional</span>
          <span class="flex items-center gap-1.5"><span class="w-1.5 h-1.5 rounded-full bg-emerald-500 inline-block"></span> Ada input</span>
        </div>
        <button type="button" id="calToday" class="mt-3 w-full text-[11px] font-bold py-2 rounded-xl bg-emerald-500/15 hover:bg-emerald-500/25 text-emerald-600 dark:text-emerald-400 transition">Kembali ke hari ini</button>
      </div>
    </div>
  </div>

<script>
(function () {
  var FILLED = new Set(<?=json_encode(array_values($filledDates))?>);
  var HOL = <?=json_encode($liburAwal)?>;
  var holLoaded = {};
  holLoaded[<?=substr($tanggal, 0, 4)?>] = true;
  var sel = '<?=e($tanggal)?>';
  var p = sel.split('-');
  var vy = +p[0], vm = +p[1] - 1;
  var today = new Date(); today.setHours(0, 0, 0, 0);
  var BLN = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
  var wrap = document.getElementById('calWrap');
  var pop = document.getElementById('calPop');
  var grid = document.getElementById('calGrid');

  function pad(n) { return (n < 10 ? '0' : '') + n; }
  function key(y, m, d) { return y + '-' + pad(m + 1) + '-' + pad(d); }

  function ensureHol(y, done) {
    if (holLoaded[y]) { done(); return; }
    fetch('index.php?ajax=libur&tahun=' + y)
      .then(function (r) { return r.json(); })
      .then(function (j) { for (var k in j) HOL[k] = j[k]; holLoaded[y] = true; done(); })
      .catch(function () { holLoaded[y] = true; done(); });
  }

  function render() {
    document.getElementById('calMonth').textContent = BLN[vm];
    document.getElementById('calYear').textContent = vy;
    grid.innerHTML = '';
    var first = new Date(vy, vm, 1);
    var off = (first.getDay() + 6) % 7; // Senin dulu
    var days = new Date(vy, vm + 1, 0).getDate();
    for (var i = 0; i < off; i++) grid.appendChild(document.createElement('span'));
    for (var d = 1; d <= days; d++) {
      (function (d) {
        var k = key(vy, vm, d);
        var dow = new Date(vy, vm, d).getDay();
        var red = (dow === 0 || dow === 6) || HOL[k];
        var b = document.createElement('button');
        b.type = 'button';
        b.className = 'relative h-9 rounded-lg text-xs flex flex-col items-center justify-center transition ' +
          (k === sel ? 'bg-gradient-to-br from-emerald-400 to-teal-600 text-slate-950 font-extrabold shadow-lg shadow-emerald-500/30'
            : red ? 'text-rose-500 font-bold hover:bg-rose-500/10'
            : 'hover:bg-slate-100 dark:hover:bg-slate-800 font-semibold');
        if (HOL[k]) b.title = HOL[k];
        var s = document.createElement('span'); s.textContent = d; b.appendChild(s);
        if (FILLED.has(k)) {
          var dot = document.createElement('span');
          dot.className = 'w-1 h-1 rounded-full ' + (k === sel ? 'bg-slate-950' : 'bg-emerald-500');
          dot.style.marginTop = '1px'; b.appendChild(dot);
        }
        b.addEventListener('click', function () {
          var m = pad(vm + 1);
          window.location = 'index.php?page=harian&bulan=' + vy + '-' + m + '&tanggal=' + k;
        });
        grid.appendChild(b);
      })(d);
    }
    // daftar libur bulan ini
    var lb = document.getElementById('calLibur');
    var pre = vy + '-' + pad(vm + 1);
    var list = Object.keys(HOL).filter(function (k) { return k.indexOf(pre) === 0; }).sort();
    lb.innerHTML = list.length
      ? '<b class="text-rose-500">Libur:</b> ' + list.map(function (k) { return +k.slice(8) + ' ' + HOL[k]; }).join(' • ')
      : '';
  }

  function show() { pop.classList.remove('hidden'); ensureHol(vy, render); }
  document.getElementById('calBtn').addEventListener('click', function (e) {
    e.stopPropagation();
    pop.classList.contains('hidden') ? show() : pop.classList.add('hidden');
  });
  document.addEventListener('click', function (e) { if (!wrap.contains(e.target)) pop.classList.add('hidden'); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') pop.classList.add('hidden'); });
  document.getElementById('calPrev').addEventListener('click', function () { vm--; if (vm < 0) { vm = 11; vy--; } ensureHol(vy, render); });
  document.getElementById('calNext').addEventListener('click', function () { vm++; if (vm > 11) { vm = 0; vy++; } ensureHol(vy, render); });
  document.getElementById('calToday').addEventListener('click', function () {
    var k = today.getFullYear() + '-' + pad(today.getMonth() + 1) + '-' + pad(today.getDate());
    window.location = 'index.php?page=harian&bulan=' + k.slice(0, 7) + '&tanggal=' + k;
  });
  render();
})();
</script>

<?php $restockHarian = stok_menipis($pdo); ?>
  <?php if ($restockHarian): ?>
  <a href="index.php?page=barang" class="glass-card p-4 rounded-2xl border-l-4 border-l-rose-500 flex flex-wrap items-center gap-x-3 gap-y-1.5 hover:border-rose-400 transition">
    <span class="text-xs font-extrabold text-rose-500 flex items-center gap-2"><i class="fa-solid fa-triangle-exclamation"></i> <?=count($restockHarian)?> barang perlu restock:</span>
    <?php foreach (array_slice($restockHarian, 0, 8) as $r): ?>
      <span class="text-[11px] font-semibold <?= $r['status'] === 'habis' ? 'text-rose-500' : 'text-amber-600 dark:text-amber-400' ?>"><?=e($r['kode'])?> (sisa <?=e($r['sisa'])?>)</span>
    <?php endforeach; ?>
    <?php if (count($restockHarian) > 8): ?><span class="text-[11px] text-slate-500">+<?=count($restockHarian) - 8?> lainnya →</span><?php endif; ?>
  </a>
  <?php endif; ?>

  <div class="grid lg:grid-cols-12 gap-6">
    <div class="lg:col-span-5 space-y-6">
      <div class="glass-card p-6 rounded-3xl space-y-5 relative z-10">
        <div class="border-b border-slate-200 dark:border-slate-800 pb-4">
          <h3 class="font-bold text-sm flex items-center"><i class="fa-solid fa-cart-plus text-emerald-500 mr-2.5"></i> Input Transaksi (<?=e($tanggal)?>)</h3>
          <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">Sistem akan menggabungkan otomatis barang berulang</p>
        </div>
        <form method="post" class="space-y-4" id="jualForm">
          <input type="hidden" name="aksi" value="jual_tambah">
          <input type="hidden" name="tanggal" value="<?=e($tanggal)?>">
          <div>
            <label class="block text-xs font-semibold mb-1.5">Pilih Barang / Jasa (ketik untuk cari)</label>
            <div class="relative">
              <input type="text" id="barangTxt" placeholder="Ketik: hitam, bufalo, F001..." autocomplete="off" class="w-full text-xs glass-input rounded-xl p-3 focus:outline-none">
              <input type="hidden" name="kode_barang" id="barangKode">
              <div id="barangDrop" class="pi-drop hidden absolute left-0 right-0 mt-1 rounded-xl overflow-auto custom-scrollbar shadow-2xl" style="max-height:220px"></div>
            </div>
            <div id="prevHarga" class="text-[11px] text-emerald-600 dark:text-emerald-400 mt-1"></div>
          </div>
          <div>
            <label class="block text-xs font-semibold mb-1.5">Jumlah (Qty)</label>
            <input type="number" name="qty" value="1" min="1" required class="w-full text-xs glass-input rounded-xl p-3 focus:outline-none">
          </div>
          <button class="w-full bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-extrabold py-3 rounded-xl text-xs shadow-lg shadow-emerald-500/20 transition flex items-center justify-center space-x-2">
            <i class="fa-solid fa-circle-plus"></i><span>Tambah Transaksi</span>
          </button>
        </form>
        <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 mt-4">
          <table class="w-full text-left text-xs">
            <thead class="tbl-head font-semibold border-b border-slate-200 dark:border-slate-800">
              <tr><th class="p-3">Kode</th><th class="p-3">Nama Item</th><th class="p-3 text-right">Harga</th><th class="p-3 text-center">Qty</th><th class="p-3 text-right">Total</th><th class="p-3"></th></tr>
            </thead>
            <tbody class="tbl-body divide-y tbl-row">
              <?php foreach ($jual as $j): ?>
              <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/30">
                <td class="p-3 font-semibold text-emerald-600 dark:text-emerald-400"><?=e($j['kode_barang'])?></td>
                <td class="p-3"><?=e($j['nama'] ?? '-')?></td>
                <td class="p-3 text-right text-slate-500 dark:text-slate-400"><?=rupiah($j['harga'])?></td>
                <td class="p-3 text-center font-bold"><?=$j['qty']?></td>
                <td class="p-3 text-right font-bold text-emerald-600 dark:text-emerald-400"><?=rupiah($j['jumlah'])?></td>
                <td class="p-3"><?php if ($owner): ?><form method="post" onsubmit="return confirm('Hapus baris ini?')">
                  <input type="hidden" name="aksi" value="jual_hapus"><input type="hidden" name="id" value="<?=$j['id']?>"><input type="hidden" name="tanggal" value="<?=e($tanggal)?>">
                  <button class="text-rose-500 hover:text-rose-400 font-bold">×</button>
                </form><?php endif; ?></td>
              </tr>
              <?php endforeach; ?>
              <?php if (!$jual): ?><tr><td colspan="6" class="p-5 text-center text-slate-500 italic">Belum ada penjualan hari ini — input dari buku manual saat closing.</td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="lg:col-span-7 space-y-6">
      <div class="glass-card p-6 rounded-3xl space-y-5">
        <div class="bg-gradient-to-r from-emerald-50 to-slate-100 dark:from-emerald-950 dark:to-slate-900 border border-emerald-500/30 p-5 rounded-2xl flex justify-between items-center shadow-inner">
          <div><span class="text-xs uppercase font-bold tracking-wider text-emerald-600 dark:text-emerald-400 block">Total Pendapatan Harian</span>
          <span class="text-xs text-slate-500 dark:text-slate-400">Sisa Kas + Non-Tunai/QRIS</span></div>
          <span class="text-3xl font-black tracking-tight"><?=rupiah($totalPendapatan)?></span>
        </div>
        <form method="post" class="bg-slate-50 dark:bg-slate-900/50 p-4 rounded-2xl border border-slate-200 dark:border-slate-800/80 space-y-3">
          <input type="hidden" name="aksi" value="transfer_simpan"><input type="hidden" name="tanggal" value="<?=e($tanggal)?>">
          <label class="block text-xs font-medium">Setoran QRIS / Transfer Non-Tunai Hari Ini</label>
          <div class="flex gap-2">
            <input type="number" name="jumlah" value="<?=$transfer?>" min="0" placeholder="Rp 0" class="flex-grow text-xs glass-input rounded-xl p-3 focus:outline-none">
            <button class="bg-slate-800 hover:bg-slate-700 text-white text-xs px-5 py-3 rounded-xl font-bold transition border border-slate-700">Simpan Non-Tunai</button>
          </div>
        </form>
      </div>

      <div class="glass-card p-6 rounded-3xl space-y-5">
        <h3 class="font-bold text-sm border-b border-slate-200 dark:border-slate-800 pb-4 flex items-center"><i class="fa-solid fa-receipt text-rose-400 mr-2.5"></i> Pengeluaran Operasional (<?=e($tanggal)?>)</h3>
        <form method="post" class="grid grid-cols-12 gap-2">
          <input type="hidden" name="aksi" value="keluar_tambah"><input type="hidden" name="tanggal" value="<?=e($tanggal)?>">
          <input type="text" name="nama" required placeholder="Deskripsi (cth: Kertas HVS)" class="col-span-5 text-xs glass-input rounded-xl p-3 focus:outline-none">
          <input type="number" name="harga" value="0" min="0" placeholder="Harga" class="col-span-3 text-xs glass-input rounded-xl p-3 focus:outline-none">
          <input type="number" name="qty" value="1" min="1" placeholder="Qty" class="col-span-2 text-xs glass-input rounded-xl p-3 focus:outline-none">
          <button class="col-span-2 bg-rose-500/20 hover:bg-rose-500/30 text-rose-500 border border-rose-500/30 rounded-xl text-xs font-bold transition flex items-center justify-center"><i class="fa-solid fa-plus"></i></button>
        </form>
        <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800">
          <table class="w-full text-left text-xs">
            <thead class="tbl-head font-semibold border-b border-slate-200 dark:border-slate-800">
              <tr><th class="p-3">Nama Pengeluaran</th><th class="p-3 text-right">Harga</th><th class="p-3 text-center">Banyak</th><th class="p-3 text-right">Total</th><th class="p-3"></th></tr>
            </thead>
            <tbody class="tbl-body">
              <?php foreach ($keluar as $k): ?>
              <tr class="border-b tbl-row"><td class="p-3"><?=e($k['nama'])?></td><td class="p-3 text-right"><?=rupiah($k['harga'])?></td><td class="p-3 text-center font-bold"><?=$k['qty']?></td><td class="p-3 text-right font-bold"><?=rupiah($k['jumlah'])?></td>
              <td class="p-3"><?php if ($owner): ?><form method="post"><input type="hidden" name="aksi" value="keluar_hapus"><input type="hidden" name="id" value="<?=$k['id']?>"><input type="hidden" name="tanggal" value="<?=e($tanggal)?>"><button class="text-rose-500 font-bold">×</button></form><?php endif; ?></td></tr>
              <?php endforeach; ?>
              <?php if (!$keluar): ?><tr><td colspan="5" class="p-5 text-center text-slate-500 italic">Belum ada catatan pengeluaran hari ini.</td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
        <div class="bg-slate-900 dark:bg-slate-900 p-5 rounded-2xl border border-slate-200 dark:border-slate-800 flex flex-col sm:flex-row justify-between sm:items-center gap-3">
          <div><p class="text-xs font-bold text-amber-600 dark:text-amber-400 uppercase tracking-wide">Sisa Kas Fisik (Tunai)</p>
          <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">Formula: Pendapatan – Pengeluaran – Transfer – Kasbon + Setoran</p>
          <p class="text-[11px] text-slate-500 dark:text-slate-400">Pengeluaran <?=rupiah($totalKeluar)?> • Transfer <?=rupiah($transfer)?> • Kasbon <?=rupiah($totPinjamHari)?> • Setoran <?=rupiah($totSetorHari)?></p></div>
          <span class="text-2xl font-black text-emerald-600 dark:text-emerald-400"><?=rupiah($saldoKas)?></span>
        </div>
      </div>

      <div class="glass-card p-6 rounded-3xl space-y-5">
        <h3 class="font-bold text-sm border-b border-slate-200 dark:border-slate-800 pb-4 flex items-center"><i class="fa-solid fa-hand-holding-dollar text-amber-500 mr-2.5"></i> Kasbon / Pinjaman Karyawan (<?=e($tanggal)?>)</h3>

        <form method="post" class="grid grid-cols-12 gap-2">
          <input type="hidden" name="aksi" value="kasbon_tambah"><input type="hidden" name="tanggal" value="<?=e($tanggal)?>">
          <input type="text" name="nama" required placeholder="Nama karyawan" class="col-span-3 text-xs glass-input rounded-xl p-3 focus:outline-none">
          <input type="number" name="jumlah" required min="1" placeholder="Jumlah pinjaman" class="col-span-3 text-xs glass-input rounded-xl p-3 focus:outline-none">
          <input type="text" name="keterangan" placeholder="Keterangan / untuk apa" class="col-span-4 text-xs glass-input rounded-xl p-3 focus:outline-none">
          <button title="Simpan pinjaman" class="col-span-2 bg-amber-500/20 hover:bg-amber-500/30 text-amber-600 dark:text-amber-400 border border-amber-500/30 rounded-xl text-xs font-bold transition flex items-center justify-center gap-1"><i class="fa-solid fa-plus"></i> Pinjam</button>
        </form>
        <form method="post" class="grid grid-cols-12 gap-2">
          <input type="hidden" name="aksi" value="setoran_tambah"><input type="hidden" name="tanggal" value="<?=e($tanggal)?>">
          <select name="nama" required class="col-span-3 text-xs glass-input rounded-xl p-3 focus:outline-none" <?= $adaSisa ? '' : 'disabled' ?>>
            <option value=""><?= $adaSisa ? 'Pilih yang menyetor...' : 'Belum ada kasbon berjalan' ?></option>
            <?php foreach ($adaSisa as $o): ?>
              <option value="<?=e($o['nama'])?>"><?=e($o['nama'])?> (sisa <?=rupiah($o['sisa'])?>)</option>
            <?php endforeach; ?>
          </select>
          <input type="number" name="jumlah" required min="1" placeholder="Jumlah setoran" class="col-span-3 text-xs glass-input rounded-xl p-3 focus:outline-none" <?= $adaSisa ? '' : 'disabled' ?>>
          <input type="text" name="keterangan" placeholder="Keterangan cicilan" class="col-span-4 text-xs glass-input rounded-xl p-3 focus:outline-none" <?= $adaSisa ? '' : 'disabled' ?>>
          <button title="Simpan setoran" <?= $adaSisa ? '' : 'disabled' ?> class="col-span-2 bg-emerald-500/20 hover:bg-emerald-500/30 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30 rounded-xl text-xs font-bold transition flex items-center justify-center gap-1 disabled:opacity-40"><i class="fa-solid fa-plus"></i> Setor</button>
        </form>

        <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800">
          <table class="w-full text-left text-xs">
            <thead class="tbl-head font-semibold border-b border-slate-200 dark:border-slate-800">
              <tr><th class="p-3">Jenis</th><th class="p-3">Nama</th><th class="p-3 text-right">Jumlah</th><th class="p-3">Keterangan</th><th class="p-3"></th></tr>
            </thead>
            <tbody class="tbl-body">
              <?php foreach ($pinjamHari as $p): ?>
              <tr class="border-b tbl-row"><td class="p-3"><span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-amber-500/15 text-amber-600 dark:text-amber-400">PINJAM</span></td><td class="p-3 font-semibold"><?=e($p['nama'])?></td><td class="p-3 text-right font-bold"><?=rupiah($p['jumlah'])?></td><td class="p-3 text-slate-500"><?=e($p['keterangan'])?></td>
              <td class="p-3"><?php if ($owner): ?><form method="post" onsubmit="return confirm('Hapus kasbon ini?')"><input type="hidden" name="aksi" value="kasbon_hapus"><input type="hidden" name="id" value="<?=$p['id']?>"><input type="hidden" name="tanggal" value="<?=e($tanggal)?>"><button class="text-rose-500 font-bold">×</button></form><?php endif; ?></td></tr>
              <?php endforeach; ?>
              <?php foreach ($setorHari as $s): ?>
              <tr class="border-b tbl-row"><td class="p-3"><span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-emerald-500/15 text-emerald-600 dark:text-emerald-400">SETOR</span></td><td class="p-3 font-semibold"><?=e($s['nama'])?></td><td class="p-3 text-right font-bold"><?=rupiah($s['jumlah'])?></td><td class="p-3 text-slate-500"><?=e($s['keterangan'])?></td>
              <td class="p-3"><?php if ($owner): ?><form method="post" onsubmit="return confirm('Hapus setoran ini?')"><input type="hidden" name="aksi" value="setoran_hapus"><input type="hidden" name="id" value="<?=$s['id']?>"><input type="hidden" name="tanggal" value="<?=e($tanggal)?>"><button class="text-rose-500 font-bold">×</button></form><?php endif; ?></td></tr>
              <?php endforeach; ?>
              <?php if (!$pinjamHari && !$setorHari): ?><tr><td colspan="5" class="p-5 text-center text-slate-500 italic">Belum ada kasbon/setoran hari ini.</td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
        <p class="text-[11px] text-slate-500 dark:text-slate-400">Pinjaman hari ini <?=rupiah($totPinjamHari)?> mengurangi kas • Setoran hari ini <?=rupiah($totSetorHari)?> menambah kas • Keuntungan tidak terpengaruh.</p>
      </div>
    </div>
  </div>
</div>

<script>
const BARANG = <?=json_encode(array_map(function ($b) use ($terjualAll) {
  $aw = (int)$b['stok_awal'];
  return ['kode' => $b['kode'], 'nama' => $b['nama'], 'harga' => (int)$b['harga_jual'],
    'sisa' => $aw > 0 ? $aw - (int)($terjualAll[$b['kode']] ?? 0) : null];
}, $allBarang))?>;
// tunggu app.js (piCombo) siap dulu
document.addEventListener('DOMContentLoaded', function () {
const prev = document.getElementById('prevHarga');
function fmtB(b) {
  let s = b.kode + ' - ' + b.nama + ' (Rp' + Number(b.harga).toLocaleString('id-ID') + ')';
  if (b.sisa !== null) s += b.sisa <= 0 ? ' — HABIS' : ' — sisa ' + b.sisa;
  return s;
}
if (window.piCombo) window.piCombo(
  document.getElementById('barangTxt'),
  document.getElementById('barangKode'),
  document.getElementById('barangDrop'),
  BARANG, fmtB,
  function (b) {
    if (!b) { prev.textContent = ''; return; }
    let t = b.nama + ' — Rp' + Number(b.harga).toLocaleString('id-ID') + ' (otomatis)';
    if (b.sisa !== null) t += b.sisa <= 0 ? ' • STOK HABIS' : ' • sisa stok: ' + b.sisa;
    prev.textContent = t;
  }
);
document.getElementById('jualForm').addEventListener('submit', function (e) {
  if (!document.getElementById('barangKode').value) {
    e.preventDefault();
    alert('Pilih dulu barang dari hasil pencarian.');
    document.getElementById('barangTxt').focus();
  }
});
});
</script>

<?php
// Diagram — AKUMULASI semua tanggal (fungsi tetap), tampilan template
$owner = !empty($owner); // karyawan: tanpa modal & keuntungan
$mode = $_GET['mode'] ?? 'semua';
if (!in_array($mode, ['semua','bulan'])) $mode = 'semua';
$bulan = bulan_aktif();

if ($mode === 'bulan') {
    $like = $bulan . '%';
    $wT = "tanggal LIKE ?"; $pT = [$like];
    $wB = "t.tanggal LIKE ?"; $pB = [$like];
    $labelPeriode = "Akumulasi 1 bulan: " . $bulan;
} else {
    $wT = "1=1"; $pT = [];
    $wB = "1=1"; $pB = [];
    $labelPeriode = "Akumulasi SEMUA tanggal (terus bertambah)";
}

$s = $pdo->prepare("SELECT COALESCE(SUM(jumlah),0) FROM transaksi WHERE $wT"); $s->execute($pT); $pendapatan=(int)$s->fetchColumn();
$s = $pdo->prepare("SELECT COALESCE(SUM(jumlah),0) FROM pengeluaran WHERE $wT"); $s->execute($pT); $pengeluaran=(int)$s->fetchColumn();
$s = $pdo->prepare("SELECT COALESCE(SUM(jumlah),0) FROM transfer_harian WHERE $wT"); $s->execute($pT); $transfer=(int)$s->fetchColumn();
$s = $pdo->prepare("SELECT COALESCE(SUM(t.qty * COALESCE(b.modal,0)),0) FROM transaksi t LEFT JOIN barang b ON b.kode=t.kode_barang WHERE $wB");
$s->execute($pB); $totalModal=(int)$s->fetchColumn();
$keuntungan = $pendapatan - $totalModal - $pengeluaran; // kasbon TIDAK memotong keuntungan
$s = $pdo->prepare("SELECT COALESCE(SUM(jumlah),0) FROM kasbon WHERE $wT"); $s->execute($pT); $totPinjamP=(int)$s->fetchColumn();
$s = $pdo->prepare("SELECT COALESCE(SUM(jumlah),0) FROM kasbon_setoran WHERE $wT"); $s->execute($pT); $totSetorP=(int)$s->fetchColumn();
$saldoKas = $pendapatan - $pengeluaran - $transfer - $totPinjamP + $totSetorP; // kasbon mengurangi kas, setoran mengembalikan

$ch = $pdo->prepare("SELECT COALESCE(b.nama,t.kode_barang) AS nama, SUM(t.jumlah) AS total, SUM(t.qty) AS qty
  FROM transaksi t LEFT JOIN barang b ON b.kode=t.kode_barang
  WHERE $wB GROUP BY t.kode_barang ORDER BY total DESC LIMIT 8");
$ch->execute($pB);
$chart = $ch->fetchAll(PDO::FETCH_ASSOC);

if ($mode === 'bulan') {
    $q = $pdo->prepare("SELECT tanggal, COALESCE(SUM(jumlah),0) AS omzet FROM transaksi WHERE tanggal LIKE ? GROUP BY tanggal ORDER BY tanggal");
    $q->execute([$like]);
    $perTgl = $q->fetchAll(PDO::FETCH_ASSOC);
    $days = (int)date('t', strtotime($bulan.'-01'));
    $map = []; foreach ($perTgl as $r) $map[$r['tanggal']] = (int)$r['omzet'];
    $lbl=[]; $kum=[]; $run=0;
    for ($d=1;$d<=$days;$d++) { $t = sprintf('%s-%02d',$bulan,$d); $run += $map[$t] ?? 0; $lbl[] = (string)$d; $kum[] = $run; }
} else {
    $q = $pdo->query("SELECT tanggal, COALESCE(SUM(jumlah),0) AS omzet FROM transaksi GROUP BY tanggal ORDER BY tanggal");
    $perTgl = $q->fetchAll(PDO::FETCH_ASSOC);
    $lbl=[]; $kum=[]; $run=0;
    foreach ($perTgl as $r) { $run += (int)$r['omzet']; $lbl[] = date('d M', strtotime($r['tanggal'])); $kum[] = $run; }
}
?>
<div class="space-y-6">
  <div class="glass-card p-5 rounded-3xl flex flex-col sm:flex-row sm:items-center justify-between gap-4">
    <div>
      <h2 class="text-xl font-bold flex items-center"><i class="fa-solid fa-chart-pie text-emerald-500 mr-3"></i> Executive Analytics</h2>
      <p class="text-xs text-slate-500 dark:text-slate-400 mt-1"><?=e($labelPeriode)?> — bukan per tanggal</p>
    </div>
    <div class="flex bg-slate-100 dark:bg-slate-900 p-1.5 rounded-2xl border border-slate-200 dark:border-slate-800 text-xs">
      <a href="index.php?page=diagram&mode=semua" class="<?= $mode==='semua' ? 'bg-emerald-500 text-slate-950 font-bold shadow-lg shadow-emerald-500/20' : 'text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white font-medium' ?> px-4 py-2 rounded-xl">Akumulasi Total</a>
      <a href="index.php?page=diagram&mode=bulan&bulan=<?=e($bulan)?>" class="<?= $mode==='bulan' ? 'bg-emerald-500 text-slate-950 font-bold shadow-lg shadow-emerald-500/20' : 'text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white font-medium' ?> px-4 py-2 rounded-xl">Per Bulan</a>
    </div>
  </div>
  <?php if ($mode==='bulan'): ?>
  <form class="glass-card p-4 rounded-2xl flex items-center gap-3 text-xs" method="get" action="index.php">
    <input type="hidden" name="page" value="diagram"><input type="hidden" name="mode" value="bulan">
    <span>Bulan:</span><input type="month" name="bulan" value="<?=e($bulan)?>" onchange="this.form.submit()" class="glass-input px-3 py-2 rounded-xl font-semibold focus:outline-none">
  </form>
  <?php endif; ?>

  <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-4">
    <div class="glass-card p-4 rounded-2xl"><p class="text-[10px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Total Pendapatan</p><p class="text-lg font-black mt-1"><?=rupiah($pendapatan)?></p></div>
    <button type="button" id="keluarBtn" title="Klik untuk rincian pengeluaran" class="glass-card p-4 rounded-2xl text-left hover:border-rose-400 hover:scale-[1.02] transition group">
      <p class="text-[10px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider flex items-center justify-between">Total Pengeluaran <i class="fa-solid fa-magnifying-glass-plus text-rose-400 opacity-0 group-hover:opacity-100 transition"></i></p>
      <p class="text-lg font-black text-rose-500 mt-1"><?=rupiah($pengeluaran)?></p>
      <p class="text-[10px] text-slate-400 mt-0.5">klik untuk rincian</p>
    </button>
    <?php if ($owner): ?>
    <div class="glass-card p-4 rounded-2xl"><p class="text-[10px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Total Modal</p><p class="text-lg font-black text-amber-500 mt-1"><?=rupiah($totalModal)?></p></div>
    <?php endif; ?>
    <button type="button" id="kasbonBtn" title="Klik untuk daftar kasbon" class="glass-card p-4 rounded-2xl text-left hover:border-amber-400 hover:scale-[1.02] transition group">
      <p class="text-[10px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider flex items-center justify-between">Total Kasbon <i class="fa-solid fa-magnifying-glass-plus text-amber-500 opacity-0 group-hover:opacity-100 transition"></i></p>
      <p class="text-lg font-black text-amber-600 dark:text-amber-400 mt-1"><?=rupiah($totPinjamP - $totSetorP)?></p>
      <p class="text-[10px] text-slate-400 mt-0.5">klik untuk rincian</p>
    </button>
    <?php if ($owner): ?>
    <div class="glass-card p-4 rounded-2xl bg-emerald-500/10 border-emerald-500/30"><p class="text-[10px] font-semibold text-emerald-600 dark:text-emerald-400 uppercase tracking-wider">Keuntungan Bersih</p><p class="text-lg font-black text-emerald-600 dark:text-emerald-400 mt-1"><?=rupiah($keuntungan)?></p></div>
    <?php endif; ?>
    <div class="glass-card p-4 rounded-2xl col-span-2 sm:col-span-1"><p class="text-[10px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Saldo Kas Tunai</p><p class="text-lg font-black mt-1"><?=rupiah($saldoKas)?></p>
      <p class="text-[10px] text-slate-500">TF <?=rupiah($transfer)?> • Kasbon <?=rupiah($totPinjamP - $totSetorP)?></p></div>
  </div>

  <div class="grid lg:grid-cols-2 gap-6">
    <div class="glass-card p-6 rounded-3xl space-y-4">
      <h3 class="font-bold text-sm flex items-center"><i class="fa-solid fa-chart-line text-emerald-500 mr-2.5"></i> Akumulasi Pertumbuhan Omzet</h3>
      <?php if (!$kum || max($kum)==0): ?><p class="text-center text-slate-500 italic text-xs py-8">Belum ada data.</p><?php else: ?>
      <div class="h-64 w-full"><canvas id="lineChart"></canvas></div>
      <?php endif; ?>
    </div>
    <div class="glass-card p-6 rounded-3xl space-y-4">
      <h3 class="font-bold text-sm flex items-center"><i class="fa-solid fa-chart-bar text-teal-500 mr-2.5"></i> Top Performing Products</h3>
      <?php if (!$chart): ?><p class="text-center text-slate-500 italic text-xs py-8">Belum ada data.</p><?php else: ?>
      <div class="h-64 w-full"><canvas id="barChart"></canvas></div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php
// rincian pengeluaran (sesuai periode: semua / bulan)
$rk = $pdo->prepare("SELECT tanggal, nama, harga, qty, jumlah FROM pengeluaran WHERE $wT ORDER BY tanggal DESC, id DESC LIMIT 300");
$rk->execute($pT);
$rincian = $rk->fetchAll(PDO::FETCH_ASSOC);
$hrP = ['Min','Sen','Sel','Rab','Kam','Jum','Sab'];
$blP = [1=>'Jan',2=>'Feb',3=>'Mar',4=>'Apr',5=>'Mei',6=>'Jun',7=>'Jul',8=>'Agu',9=>'Sep',10=>'Okt',11=>'Nov',12=>'Des'];
?>
<div id="keluarModal" class="fixed inset-0 z-[100] hidden items-center justify-center p-4" style="background:rgba(2,6,12,.8);backdrop-filter:blur(6px)">
  <div class="glass-card rounded-3xl w-full max-w-2xl max-h-[82vh] flex flex-col overflow-hidden">
    <div class="flex items-center justify-between gap-2 p-5 border-b border-slate-200 dark:border-slate-800">
      <div>
        <h3 class="font-bold text-sm flex items-center"><i class="fa-solid fa-receipt text-rose-400 mr-2.5"></i> Rincian Pengeluaran</h3>
        <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5"><?=e($mode === 'bulan' ? 'Bulan ' . $bulan : 'Akumulasi semua tanggal')?> • Total <b class="text-rose-500"><?=rupiah($pengeluaran)?></b></p>
      </div>
      <button type="button" id="keluarClose" title="Tutup (Esc)" class="w-9 h-9 rounded-xl bg-rose-500 hover:bg-rose-400 text-white text-sm transition shrink-0"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="overflow-auto custom-scrollbar p-5 pt-3">
      <?php if (!$rincian): ?>
        <p class="text-center text-slate-500 italic text-xs py-8">Belum ada pengeluaran pada periode ini.</p>
      <?php else: ?>
      <table class="w-full text-left text-xs">
        <thead class="tbl-head font-semibold border-b border-slate-200 dark:border-slate-800 sticky top-0">
          <tr><th class="p-3">Tanggal</th><th class="p-3">Pengeluaran</th><th class="p-3 text-right">Harga</th><th class="p-3 text-center">Qty</th><th class="p-3 text-right">Jumlah</th></tr>
        </thead>
        <tbody class="tbl-body divide-y tbl-row">
          <?php foreach ($rincian as $r):
            $t = strtotime($r['tanggal']);
            $ft = $hrP[(int)date('w', $t)] . ', ' . (int)date('j', $t) . ' ' . $blP[(int)date('n', $t)] . ' ' . date('Y', $t);
          ?>
          <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/30">
            <td class="p-3 whitespace-nowrap font-semibold"><?=e($ft)?></td>
            <td class="p-3"><?=e($r['nama'])?></td>
            <td class="p-3 text-right text-slate-500"><?=rupiah($r['harga'])?></td>
            <td class="p-3 text-center font-bold"><?=$r['qty']?></td>
            <td class="p-3 text-right font-bold text-rose-500"><?=rupiah($r['jumlah'])?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php if (count($rincian) === 300): ?><p class="text-[11px] text-slate-500 mt-2 text-center">Menampilkan 300 terbaru.</p><?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
(function () {
  var m = document.getElementById('keluarModal');
  var b = document.getElementById('keluarBtn');
  if (!m || !b) return;
  function open() { m.classList.remove('hidden'); m.classList.add('flex'); document.body.style.overflow = 'hidden'; }
  function close() { m.classList.add('hidden'); m.classList.remove('flex'); document.body.style.overflow = ''; }
  b.addEventListener('click', open);
  document.getElementById('keluarClose').addEventListener('click', close);
  m.addEventListener('click', function (e) { if (e.target === m) close(); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !m.classList.contains('hidden')) close(); });
})();
</script>

<?php
// data popup kasbon: ringkasan per orang = global (semua tanggal, terkini),
// daftar pinjam & setor mengikuti periode aktif
$rkP = $pdo->prepare("SELECT tanggal, nama, jumlah, keterangan FROM kasbon WHERE $wT ORDER BY tanggal DESC, id DESC LIMIT 300");
$rkP->execute($pT);
$kasbonList = $rkP->fetchAll(PDO::FETCH_ASSOC);
$rkS = $pdo->prepare("SELECT tanggal, nama, jumlah, keterangan FROM kasbon_setoran WHERE $wT ORDER BY tanggal DESC, id DESC LIMIT 300");
$rkS->execute($pT);
$setorList = $rkS->fetchAll(PDO::FETCH_ASSOC);
$ringkasOrang = kasbon_sisa_map($pdo);
uasort($ringkasOrang, function ($a, $b) { return $b['sisa'] <=> $a['sisa']; });
$hrP = ['Min','Sen','Sel','Rab','Kam','Jum','Sab'];
$blP = [1=>'Jan',2=>'Feb',3=>'Mar',4=>'Apr',5=>'Mei',6=>'Jun',7=>'Jul',8=>'Agu',9=>'Sep',10=>'Okt',11=>'Nov',12=>'Des'];
$ftgl = function ($d) use ($hrP, $blP) {
    $t = strtotime($d);
    return $hrP[(int)date('w', $t)] . ', ' . (int)date('j', $t) . ' ' . $blP[(int)date('n', $t)] . ' ' . date('Y', $t);
};
?>
<div id="kasbonModal" class="fixed inset-0 z-[100] hidden items-center justify-center p-4" style="background:rgba(2,6,12,.8);backdrop-filter:blur(6px)">
  <div class="glass-card rounded-3xl w-full max-w-2xl max-h-[82vh] flex flex-col overflow-hidden">
    <div class="flex items-center justify-between gap-2 p-5 border-b border-slate-200 dark:border-slate-800">
      <div>
        <h3 class="font-bold text-sm flex items-center"><i class="fa-solid fa-hand-holding-dollar text-amber-500 mr-2.5"></i> Daftar Kasbon</h3>
        <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5"><?=e($mode === 'bulan' ? 'Bulan ' . $bulan : 'Akumulasi semua tanggal')?> • Sisa belum kembali <b class="text-amber-600 dark:text-amber-400"><?=rupiah($totPinjamP - $totSetorP)?></b> (pinjam <?=rupiah($totPinjamP)?> − setor <?=rupiah($totSetorP)?>)</p>
      </div>
      <button type="button" id="kasbonClose" title="Tutup (Esc)" class="w-9 h-9 rounded-xl bg-rose-500 hover:bg-rose-400 text-white text-sm transition shrink-0"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="overflow-auto custom-scrollbar p-5 pt-3 space-y-5">
      <div>
        <p class="text-xs font-bold mb-2">Sisa per orang (saat ini, semua tanggal)</p>
        <?php if (!$ringkasOrang): ?><p class="text-slate-500 italic text-xs">Belum ada kasbon.</p><?php else: ?>
        <table class="w-full text-left text-xs">
          <thead class="tbl-head font-semibold border-b border-slate-200 dark:border-slate-800">
            <tr><th class="p-2.5">Nama</th><th class="p-2.5 text-right">Total Pinjam</th><th class="p-2.5 text-right">Total Setor</th><th class="p-2.5 text-right">Sisa</th></tr>
          </thead>
          <tbody class="tbl-body divide-y tbl-row">
            <?php foreach ($ringkasOrang as $o): ?>
            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/30">
              <td class="p-2.5 font-semibold"><?=e($o['nama'])?> <?= $o['sisa'] <= 0 ? '<span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-emerald-500/15 text-emerald-600 dark:text-emerald-400">LUNAS</span>' : '' ?></td>
              <td class="p-2.5 text-right"><?=rupiah($o['pinjam'])?></td>
              <td class="p-2.5 text-right text-emerald-600 dark:text-emerald-400"><?=rupiah($o['setor'])?></td>
              <td class="p-2.5 text-right font-bold <?= $o['sisa'] > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-slate-400' ?>"><?=rupiah($o['sisa'])?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <?php endif; ?>
      </div>
      <div>
        <p class="text-xs font-bold mb-2">Daftar pinjaman (<?=e($mode === 'bulan' ? $bulan : 'semua tanggal')?>)</p>
        <?php if (!$kasbonList): ?><p class="text-slate-500 italic text-xs">Tidak ada pinjaman pada periode ini.</p><?php else: ?>
        <table class="w-full text-left text-xs">
          <thead class="tbl-head font-semibold border-b border-slate-200 dark:border-slate-800">
            <tr><th class="p-2.5">Tanggal</th><th class="p-2.5">Nama</th><th class="p-2.5 text-right">Jumlah</th><th class="p-2.5">Untuk apa</th></tr>
          </thead>
          <tbody class="tbl-body divide-y tbl-row">
            <?php foreach ($kasbonList as $r): ?>
            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/30">
              <td class="p-2.5 whitespace-nowrap font-semibold"><?=e($ftgl($r['tanggal']))?></td>
              <td class="p-2.5"><?=e($r['nama'])?></td>
              <td class="p-2.5 text-right font-bold"><?=rupiah($r['jumlah'])?></td>
              <td class="p-2.5 text-slate-500"><?=e($r['keterangan'])?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <?php endif; ?>
      </div>
      <div>
        <p class="text-xs font-bold mb-2">Daftar setoran / cicilan (<?=e($mode === 'bulan' ? $bulan : 'semua tanggal')?>)</p>
        <?php if (!$setorList): ?><p class="text-slate-500 italic text-xs">Tidak ada setoran pada periode ini.</p><?php else: ?>
        <table class="w-full text-left text-xs">
          <thead class="tbl-head font-semibold border-b border-slate-200 dark:border-slate-800">
            <tr><th class="p-2.5">Tanggal</th><th class="p-2.5">Nama</th><th class="p-2.5 text-right">Jumlah</th><th class="p-2.5">Keterangan</th></tr>
          </thead>
          <tbody class="tbl-body divide-y tbl-row">
            <?php foreach ($setorList as $r): ?>
            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/30">
              <td class="p-2.5 whitespace-nowrap font-semibold"><?=e($ftgl($r['tanggal']))?></td>
              <td class="p-2.5"><?=e($r['nama'])?></td>
              <td class="p-2.5 text-right font-bold text-emerald-600 dark:text-emerald-400"><?=rupiah($r['jumlah'])?></td>
              <td class="p-2.5 text-slate-500"><?=e($r['keterangan'])?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<script>
(function () {
  var m = document.getElementById('kasbonModal');
  var b = document.getElementById('kasbonBtn');
  if (!m || !b) return;
  function open() { m.classList.remove('hidden'); m.classList.add('flex'); document.body.style.overflow = 'hidden'; }
  function close() { m.classList.add('hidden'); m.classList.remove('flex'); document.body.style.overflow = ''; }
  b.addEventListener('click', open);
  document.getElementById('kasbonClose').addEventListener('click', close);
  m.addEventListener('click', function (e) { if (e.target === m) close(); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !m.classList.contains('hidden')) close(); });
})();
</script>

<script>
(function(){
  const dark = document.documentElement.classList.contains('dark');
  Chart.defaults.color = dark ? '#94a3b8' : '#64748b';
  Chart.defaults.borderColor = dark ? 'rgba(255,255,255,0.08)' : 'rgba(15,23,42,0.08)';
  <?php if ($kum && max($kum)>0): ?>
  new Chart(document.getElementById('lineChart'), { type: 'line',
    data: { labels: <?=json_encode($lbl)?>, datasets: [{ label: 'Omzet Kumulatif', data: <?=json_encode($kum)?>,
      borderColor: '#10b981', backgroundColor: 'rgba(16,185,129,0.12)', fill: true, tension: 0.4, borderWidth: 3, pointBackgroundColor: '#10b981' }] },
    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } },
      scales: { y: { ticks: { callback: v => 'Rp' + Number(v).toLocaleString('id-ID') } } } } });
  <?php endif; ?>
  <?php if ($chart): ?>
  new Chart(document.getElementById('barChart'), { type: 'bar',
    data: { labels: <?=json_encode(array_column($chart,'nama'))?>, datasets: [{ data: <?=json_encode(array_map('intval',array_column($chart,'total')))?>,
      backgroundColor: '#2dd4bf', borderRadius: 8 }] },
    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } },
      scales: { x: { ticks: { maxRotation: 60, minRotation: 45, font: { size: 10 } } },
                y: { ticks: { callback: v => 'Rp' + Number(v).toLocaleString('id-ID') } } } } });
  <?php endif; ?>
})();
</script>

<?php
// Rekapan — fungsi tetap, tampilan template
$owner = !empty($owner); // karyawan: tanpa modal & laba
$bulan = bulan_aktif();
$like = $bulan . '%';
$rows = $pdo->prepare("
  SELECT b.kode, b.nama, b.harga_jual, b.modal,
         COALESCE(SUM(t.qty),0) AS qty,
         COALESCE(SUM(t.jumlah),0) AS penjualan
  FROM barang b
  LEFT JOIN transaksi t ON t.kode_barang = b.kode AND t.tanggal LIKE ?
  GROUP BY b.kode ORDER BY penjualan DESC, b.kode
");
$rows->execute([$like]);
$data = $rows->fetchAll(PDO::FETCH_ASSOC);
$tQty=0;$tJual=0;$tModal=0;$tLaba=0;
foreach ($data as &$r) {
    $r['total_modal'] = $r['qty'] * $r['modal'];
    $r['laba'] = $r['penjualan'] - $r['total_modal'];
    $tQty += $r['qty']; $tJual += $r['penjualan']; $tModal += $r['total_modal']; $tLaba += $r['laba'];
}
unset($r);
?>
<div class="space-y-6">
  <div class="glass-card p-5 rounded-3xl flex flex-col md:flex-row md:items-center justify-between gap-4">
    <div>
      <h2 class="text-xl font-bold flex items-center"><i class="fa-solid fa-file-invoice-dollar text-emerald-500 mr-3"></i> Rekapan Bulanan</h2>
      <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Laporan gabungan akumulasi per item otomatis</p>
    </div>
    <form class="flex items-center space-x-3" method="get" action="index.php">
      <input type="hidden" name="page" value="rekapan">
      <div class="flex items-center space-x-2 glass-input px-3.5 py-2 rounded-xl text-xs">
        <span class="text-slate-500 dark:text-slate-400 font-medium">Bulan:</span>
        <input type="month" name="bulan" value="<?=e($bulan)?>" onchange="this.form.submit()" class="bg-transparent font-semibold focus:outline-none">
      </div>
      <button type="button" onclick="window.print()" class="bg-slate-800 hover:bg-slate-700 border border-slate-700 text-white px-4 py-2 rounded-xl text-xs font-bold transition flex items-center"><i class="fa-solid fa-print mr-2"></i> Cetak PDF</button>
    </form>
  </div>

  <div class="grid grid-cols-2 <?= $owner ? 'lg:grid-cols-4' : '' ?> gap-4">
    <div class="glass-card p-5 rounded-3xl border-l-4 border-l-emerald-500">
      <span class="text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider block">Total Penjualan</span>
      <span class="text-2xl font-black mt-1 block"><?=rupiah($tJual)?></span>
    </div>
    <?php if ($owner): ?>
    <div class="glass-card p-5 rounded-3xl border-l-4 border-l-amber-500">
      <span class="text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider block">Total Modal (HPP)</span>
      <span class="text-2xl font-black mt-1 block"><?=rupiah($tModal)?></span>
    </div>
    <div class="glass-card p-5 rounded-3xl border-l-4 border-l-teal-400">
      <span class="text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider block">Laba Kotor</span>
      <span class="text-2xl font-black text-emerald-600 dark:text-emerald-400 mt-1 block"><?=rupiah($tLaba)?></span>
    </div>
    <?php endif; ?>
    <div class="glass-card p-5 rounded-3xl border-l-4 border-l-blue-500">
      <span class="text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider block">Volume Terjual</span>
      <span class="text-2xl font-black text-blue-500 mt-1 block"><?=number_format($tQty)?> pcs</span>
    </div>
  </div>

  <div class="glass-card p-6 rounded-3xl space-y-4">
    <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 overflow-x-auto custom-scrollbar">
      <table class="w-full text-left text-xs min-w-[760px]">
        <thead class="tbl-head font-semibold border-b border-slate-200 dark:border-slate-800">
          <tr><th class="p-3.5">Kode</th><th class="p-3.5">Nama Barang</th><th class="p-3.5 text-center">Terjual</th><th class="p-3.5 text-right">Penjualan</th><?= $owner ? '<th class="p-3.5 text-right">Modal/pcs</th><th class="p-3.5 text-right">Total Modal</th><th class="p-3.5 text-right">Laba</th>' : '' ?></tr>
        </thead>
        <tbody class="tbl-body divide-y tbl-row">
          <?php foreach ($data as $r): if ($r['qty']==0) continue; ?>
          <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/30">
            <td class="p-3.5 font-mono font-bold text-emerald-600 dark:text-emerald-400"><?=e($r['kode'])?></td>
            <td class="p-3.5 font-medium"><?=e($r['nama'])?></td>
            <td class="p-3.5 text-center font-bold"><?=$r['qty']?></td>
            <td class="p-3.5 text-right"><?=rupiah($r['penjualan'])?></td>
            <?php if ($owner): ?>
            <td class="p-3.5 text-right text-slate-500"><?=rupiah($r['modal'])?></td>
            <td class="p-3.5 text-right text-slate-500"><?=rupiah($r['total_modal'])?></td>
            <td class="p-3.5 text-right font-bold <?= $r['laba']<0?'text-rose-500':'text-emerald-600 dark:text-emerald-400' ?>"><?=rupiah($r['laba'])?></td>
            <?php endif; ?>
          </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot class="bg-slate-100 dark:bg-slate-900/80 font-bold border-t border-slate-200 dark:border-slate-800">
          <tr><td colspan="2" class="p-3.5 uppercase tracking-wider text-xs text-slate-500">Akumulasi Total</td>
          <td class="p-3.5 text-center text-blue-500"><?=$tQty?></td>
          <td class="p-3.5 text-right"><?=rupiah($tJual)?></td>
          <?php if ($owner): ?>
          <td class="p-3.5 text-right text-slate-500">-</td>
          <td class="p-3.5 text-right text-amber-500"><?=rupiah($tModal)?></td>
          <td class="p-3.5 text-right text-emerald-600 dark:text-emerald-400"><?=rupiah($tLaba)?></td>
          <?php endif; ?></tr>
        </tfoot>
      </table>
    </div>
    <p class="text-[11px] text-slate-500 dark:text-slate-400">Hanya item terjual bulan <?=e($bulan)?> yang tampil.<?= $owner ? ' Laba = Penjualan − Modal (belum dikurangi Pengeluaran harian).' : '' ?></p>
  </div>
</div>

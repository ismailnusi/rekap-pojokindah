<?php
// Pengaturan Hak Akses — khusus owner
$owner = !empty($owner);
$izinList = [
    'izin_edit_transaksi' => ['Edit Transaksi', 'Karyawan bisa mengubah Qty transaksi harian yang sudah tersimpan.'],
    'izin_hapus_transaksi' => ['Hapus Transaksi', 'Karyawan bisa menghapus baris penjualan, pengeluaran, kasbon, dan setoran.'],
    'izin_ubah_barang' => ['Tambah / Edit Data Barang', 'Karyawan bisa menambah barang baru serta mengubah harga, stok, dan foto.'],
    'izin_hapus_barang' => ['Hapus Data Barang', 'Karyawan bisa menghapus barang dari katalog.'],
];
$saatIni = [];
foreach (array_keys($izinList) as $k) $saatIni[$k] = setting_get($pdo, $k, '0') === '1';
$auditRows = $pdo->query("SELECT username, aksi, detail, ip, waktu FROM audit_log ORDER BY id DESC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);
function badgeAksi($a) {
    if ($a === 'hapus') return '<span class="text-[10px] font-extrabold px-2 py-0.5 rounded-full bg-rose-500/15 text-rose-500">HAPUS</span>';
    if ($a === 'edit') return '<span class="text-[10px] font-extrabold px-2 py-0.5 rounded-full bg-blue-500/15 text-blue-500">EDIT</span>';
    if ($a === 'tambah') return '<span class="text-[10px] font-extrabold px-2 py-0.5 rounded-full bg-emerald-500/15 text-emerald-600 dark:text-emerald-400">TAMBAH</span>';
    return '<span class="text-[10px] font-extrabold px-2 py-0.5 rounded-full bg-amber-500/15 text-amber-600 dark:text-amber-400">DITOLAK</span>';
}
?>
<div class="space-y-6">
  <div class="glass-card p-5 rounded-3xl flex flex-col md:flex-row md:items-center justify-between gap-4">
    <div>
      <h2 class="text-xl font-bold flex items-center"><i class="fa-solid fa-shield-halved text-emerald-500 mr-3"></i> Pengaturan Hak Akses</h2>
      <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Kontrol tombol Edit/Hapus di akun karyawan + audit keamanan</p>
    </div>
  </div>

  <div class="grid lg:grid-cols-12 gap-6">
    <div class="lg:col-span-5">
      <form method="post" class="glass-card p-6 rounded-3xl space-y-4">
        <input type="hidden" name="aksi" value="simpan_izin">
        <h3 class="font-bold text-sm border-b border-slate-200 dark:border-slate-800 pb-4">Izin Karyawan</h3>
        <?php foreach ($izinList as $k => $v): ?>
        <label class="flex items-start gap-3 bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-800 rounded-2xl p-4 cursor-pointer hover:border-emerald-500 transition">
          <input type="checkbox" name="<?=e($k)?>" value="1" <?= $saatIni[$k] ? 'checked' : '' ?> class="mt-1 w-4 h-4 accent-emerald-500">
          <span><b class="text-xs block"><?=e($v[0])?></b><span class="text-[11px] text-slate-500 dark:text-slate-400"><?=e($v[1])?></span></span>
        </label>
        <?php endforeach; ?>
        <button class="w-full bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-extrabold py-3 rounded-xl text-xs shadow-lg shadow-emerald-500/20 transition">Simpan Pengaturan</button>
        <p class="text-[11px] text-slate-500 dark:text-slate-400">Mati = tombol disembunyikan total dari karyawan dan ditolak di server.</p>
      </form>
    </div>

    <div class="lg:col-span-7">
      <div class="glass-card p-6 rounded-3xl space-y-4">
        <h3 class="font-bold text-sm border-b border-slate-200 dark:border-slate-800 pb-4 flex items-center"><i class="fa-solid fa-clipboard-list text-amber-500 mr-2.5"></i> Log Aktivitas Keamanan (100 terbaru)</h3>
        <div class="overflow-x-auto rounded-2xl border border-slate-200 dark:border-slate-800 custom-scrollbar">
          <table class="w-full text-left text-xs min-w-[620px]">
            <thead class="tbl-head font-semibold border-b border-slate-200 dark:border-slate-800">
              <tr><th class="p-3">Waktu</th><th class="p-3">Siapa</th><th class="p-3">Aksi</th><th class="p-3">Detail</th><th class="p-3">IP</th></tr>
            </thead>
            <tbody class="tbl-body divide-y tbl-row">
              <?php foreach ($auditRows as $a):
                $t = strtotime($a['waktu']);
              ?>
              <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/30">
                <td class="p-3 whitespace-nowrap"><?=date('d/m/Y H:i', $t)?></td>
                <td class="p-3 font-bold"><?=e($a['username'])?></td>
                <td class="p-3"><?=badgeAksi($a['aksi'])?></td>
                <td class="p-3"><?=e($a['detail'])?></td>
                <td class="p-3 text-slate-500"><?=e($a['ip'] ?: '-')?></td>
              </tr>
              <?php endforeach; ?>
              <?php if (!$auditRows): ?><tr><td colspan="5" class="p-5 text-center text-slate-500 italic">Belum ada aktivitas karyawan yang tercatat.</td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<?php
// Invoice / Nota — fungsi pembuatan + daftar
$owner = !empty($owner);
$kat = $pdo->query("SELECT kode, nama, harga_jual FROM barang ORDER BY nama")->fetchAll(PDO::FETCH_ASSOC);
$jmPerTgl = $pdo->query("SELECT tanggal, COUNT(*) FROM invoice GROUP BY tanggal")->fetchAll(PDO::FETCH_KEY_PAIR);

$list = $pdo->query("SELECT * FROM invoice ORDER BY tanggal DESC, id DESC LIMIT 200")->fetchAll(PDO::FETCH_ASSOC);
$statusLbl = ['lunas' => 'LUNAS', 'dp' => 'DP', 'belum' => 'BELUM LUNAS'];
$metodeLbl = ['tunai' => 'Tunai', 'qris' => 'QRIS', 'transfer' => 'Transfer'];
// data untuk modal edit (per invoice + itemnya)
$invItems = [];
$ids = array_map('intval', array_column($list, 'id'));
if ($ids) {
  foreach ($pdo->query("SELECT * FROM invoice_item WHERE invoice_id IN (" . implode(',', $ids) . ") ORDER BY id")->fetchAll(PDO::FETCH_ASSOC) as $it) {
    $invItems[$it['invoice_id']][] = $it;
  }
}
$invData = [];
foreach ($list as $v) {
  $invData[$v['id']] = ['nomor' => $v['nomor'], 'tanggal' => $v['tanggal'], 'pelanggan' => $v['pelanggan'],
    'wa' => $v['wa'], 'metode' => $v['metode'], 'status' => $v['status'], 'dp' => (int)$v['dp'],
    'items' => $invItems[$v['id']] ?? []];
}
?>
<div class="space-y-6">
  <div class="glass-card p-5 rounded-3xl flex flex-col md:flex-row md:items-center justify-between gap-4">
    <div>
      <h2 class="text-xl font-bold flex items-center"><i class="fa-solid fa-receipt text-emerald-500 mr-3"></i> Invoice / Nota</h2>
      <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Nota pesanan percetakan + kuitansi resmi Pojok Indah</p>
    </div>
    <span class="text-xs bg-slate-100 dark:bg-slate-800 text-emerald-600 dark:text-emerald-400 border border-slate-200 dark:border-slate-700 px-3.5 py-2 rounded-xl font-bold"><?=count($list)?> invoice</span>
  </div>

  <div class="grid lg:grid-cols-12 gap-6">
    <div class="lg:col-span-5">
      <form method="post" id="invForm" class="glass-card p-6 rounded-3xl space-y-4 relative z-10">
        <input type="hidden" name="aksi" value="invoice_buat">
        <input type="hidden" name="items_json" id="itemsJson">
        <h3 class="font-bold text-sm border-b border-slate-200 dark:border-slate-800 pb-4 flex items-center"><i class="fa-solid fa-file-circle-plus text-emerald-500 mr-2.5"></i> Buat Invoice Baru</h3>
        <div class="grid grid-cols-2 gap-3">
          <div><label class="block text-xs font-semibold mb-1.5">Tanggal Invoice</label>
            <input type="date" name="tanggal" id="invTgl" value="<?=date('Y-m-d')?>" class="w-full text-xs glass-input rounded-xl p-3 focus:outline-none"></div>
          <div><label class="block text-xs font-semibold mb-1.5">Nomor Invoice</label>
            <input type="text" id="invNomor" readonly value="otomatis" class="w-full text-xs glass-input rounded-xl p-3 font-mono font-bold text-emerald-600 dark:text-emerald-400 focus:outline-none"></div>
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div><label class="block text-xs font-semibold mb-1.5">Nama Pemesan</label>
            <input type="text" name="pelanggan" required placeholder="cth: Budi" class="w-full text-xs glass-input rounded-xl p-3 focus:outline-none"></div>
          <div><label class="block text-xs font-semibold mb-1.5">No. WhatsApp</label>
            <input type="text" name="wa" placeholder="cth: 0812..." class="w-full text-xs glass-input rounded-xl p-3 focus:outline-none"></div>
        </div>
        <div>
          <label class="block text-xs font-semibold mb-1.5">Detail Pesanan</label>
          <div id="invRows" class="space-y-2"></div>
          <button type="button" id="addRow" class="mt-2 text-xs font-bold px-3 py-2 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 transition"><i class="fa-solid fa-plus mr-1"></i> Tambah Baris</button>
        </div>
        <div class="bg-slate-50 dark:bg-slate-900/50 p-4 rounded-2xl border border-slate-200 dark:border-slate-800 flex justify-between items-center">
          <span class="text-xs font-bold uppercase tracking-wider">Total</span>
          <span id="invTotal" class="text-2xl font-black">Rp0</span>
        </div>
        <div>
          <label class="block text-xs font-semibold mb-1.5">Status Pembayaran</label>
          <div class="grid grid-cols-3 gap-2 text-xs font-bold">
            <label class="cursor-pointer"><input type="radio" name="status" value="lunas" class="peer hidden" checked>
              <span class="block text-center px-2 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 peer-checked:bg-emerald-500 peer-checked:text-slate-950 peer-checked:border-emerald-500 transition">Lunas</span></label>
            <label class="cursor-pointer"><input type="radio" name="status" value="dp" class="peer hidden">
              <span class="block text-center px-2 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 peer-checked:bg-amber-500 peer-checked:text-slate-950 peer-checked:border-amber-500 transition">DP</span></label>
            <label class="cursor-pointer"><input type="radio" name="status" value="belum" class="peer hidden">
              <span class="block text-center px-2 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 peer-checked:bg-slate-500 peer-checked:text-white peer-checked:border-slate-500 transition">Cek Total</span></label>
          </div>
          <div id="dpWrap" class="hidden mt-2 grid grid-cols-2 gap-3">
            <div><label class="block text-xs font-semibold mb-1.5">Nominal DP (Rp)</label>
              <input type="number" name="dp_nominal" id="dpNominal" min="0" value="0" class="w-full text-xs glass-input rounded-xl p-3 focus:outline-none"></div>
            <div class="bg-amber-500/10 border border-amber-500/30 rounded-xl p-3 text-xs"><span class="text-slate-500 dark:text-slate-400">Sisa tagihan:</span><br><b id="invSisa" class="text-base text-amber-600 dark:text-amber-400">Rp0</b></div>
          </div>
        </div>
        <div><label class="block text-xs font-semibold mb-1.5">Metode Pembayaran</label>
          <select name="metode" class="w-full text-xs glass-input rounded-xl p-3 focus:outline-none">
            <option value="tunai">Tunai</option><option value="qris">QRIS</option><option value="transfer">Transfer</option>
          </select></div>
        <button class="w-full bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-extrabold py-3 rounded-xl text-xs shadow-lg shadow-emerald-500/20 transition"><i class="fa-solid fa-floppy-disk mr-2"></i>Simpan Invoice</button>
      </form>
    </div>

    <div class="lg:col-span-7">
      <div class="glass-card p-6 rounded-3xl space-y-4">
        <h3 class="font-bold text-sm border-b border-slate-200 dark:border-slate-800 pb-4">Daftar Invoice</h3>
        <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 overflow-x-auto custom-scrollbar">
          <table class="w-full text-left text-xs min-w-[680px]">
            <thead class="tbl-head font-semibold border-b border-slate-200 dark:border-slate-800">
              <tr><th class="p-3">Nomor / Tgl</th><th class="p-3">Pelanggan</th><th class="p-3 text-right">Total</th><th class="p-3 text-center">Status</th><th class="p-3 text-center">Aksi</th></tr>
            </thead>
            <tbody class="tbl-body divide-y tbl-row">
              <?php foreach ($list as $v):
                $sisa = $v['status'] === 'lunas' ? 0 : ($v['total'] - $v['dp']);
              ?>
              <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/30">
                <td class="p-3"><b class="font-mono"><?=e($v['nomor'])?></b><br><span class="text-slate-500"><?=e($v['tanggal'])?> • <?=e($metodeLbl[$v['metode']] ?? $v['metode'])?></span></td>
                <td class="p-3 font-semibold"><?=e($v['pelanggan'])?><?= $v['wa'] !== '' ? '<br><span class="text-slate-500 font-normal">' . e($v['wa']) . '</span>' : '' ?></td>
                <td class="p-3 text-right font-bold"><?=rupiah($v['total'])?><?= $sisa > 0 ? '<br><span class="text-amber-600 dark:text-amber-400 font-semibold">sisa ' . rupiah($sisa) . '</span>' : '' ?></td>
                <td class="p-3 text-center"><span class="text-[10px] font-extrabold px-2.5 py-1 rounded-full <?= $v['status'] === 'lunas' ? 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400' : ($v['status'] === 'dp' ? 'bg-amber-500/15 text-amber-600 dark:text-amber-400' : 'bg-slate-500/15 text-slate-500') ?>"><?=e($statusLbl[$v['status']] ?? $v['status'])?></span></td>
                <td class="p-3 text-center whitespace-nowrap">
                  <a href="invoice_cetak.php?id=<?=$v['id']?>" target="_blank" title="Cetak / PDF" class="bg-slate-200 dark:bg-slate-800 hover:bg-slate-300 dark:hover:bg-slate-700 px-2.5 py-1 rounded-lg text-[11px] font-semibold transition inline-block"><i class="fa-solid fa-print mr-1"></i>Cetak</a>
                  <?php if ($v['status'] !== 'lunas'): ?>
                  <form method="post" style="display:inline" onsubmit="return confirm('Lunasi <?=e($v['nomor'])?>?')">
                    <input type="hidden" name="aksi" value="invoice_lunas"><input type="hidden" name="id" value="<?=$v['id']?>">
                    <button title="Pelunasan" class="bg-emerald-500/15 hover:bg-emerald-500/25 text-emerald-600 dark:text-emerald-400 px-2.5 py-1 rounded-lg text-[11px] font-bold transition">Lunasi</button>
                  </form>
                  <?php endif; ?>
                  <?php if ($owner): ?>
                  <button type="button" title="Edit" onclick="openEdit(<?=$v['id']?>)" class="bg-blue-500/15 hover:bg-blue-500/25 text-blue-500 px-2.5 py-1 rounded-lg text-[11px] font-bold transition"><i class="fa-solid fa-pen"></i></button>
                  <form method="post" style="display:inline" onsubmit="return confirm('Hapus <?=e($v['nomor'])?>?')">
                    <input type="hidden" name="aksi" value="invoice_hapus"><input type="hidden" name="id" value="<?=$v['id']?>">
                    <button title="Hapus" class="bg-rose-500/10 hover:bg-rose-500/20 text-rose-500 px-2.5 py-1 rounded-lg text-[11px] font-semibold transition">×</button>
                  </form>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
              <?php if (!$list): ?><tr><td colspan="5" class="p-5 text-center text-slate-500 italic">Belum ada invoice.</td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
// tunggu app.js (piCombo) siap dulu
document.addEventListener('DOMContentLoaded', function () {
  var KAT = <?=json_encode($kat)?>;
  var JM = <?=json_encode($jmPerTgl)?>;
  var rows = document.getElementById('invRows');
  var tgl = document.getElementById('invTgl');
  var nomor = document.getElementById('invNomor');

  function rp(n) { return 'Rp' + Number(n || 0).toLocaleString('id-ID'); }
  function updNomor() {
    var c = (tgl.value || '').replace(/-/g, '');
    var n = (JM[tgl.value] || 0) + 1;
    nomor.value = c ? 'INV-' + c + '-' + String(n).padStart(3, '0') : 'otomatis';
  }
  function addRow() {
    var d = document.createElement('div');
    d.className = 'grid grid-cols-12 gap-1.5 items-center bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-800 rounded-xl p-2';
    d.innerHTML =
      '<div class="col-span-12 sm:col-span-4 relative">' +
        '<input class="w-full text-[11px] glass-input rounded-lg p-2 focus:outline-none r-txt" placeholder="Ketik barang... (hitam, F001)" autocomplete="off">' +
        '<input type="hidden" class="r-kode">' +
        '<div class="r-drop pi-drop hidden absolute left-0 right-0 mt-1 rounded-xl overflow-auto custom-scrollbar shadow-2xl" style="max-height:200px"></div>' +
      '</div>' +
      '<input class="col-span-5 sm:col-span-3 text-[11px] glass-input rounded-lg p-2 focus:outline-none r-spek" placeholder="Ukuran/Spesifikasi">' +
      '<input type="number" class="col-span-2 text-[11px] glass-input rounded-lg p-2 focus:outline-none r-qty" value="1" min="1">' +
      '<input type="number" class="col-span-3 sm:col-span-2 text-[11px] glass-input rounded-lg p-2 focus:outline-none r-harga" min="0" value="0">' +
      '<div class="col-span-2 sm:col-span-1 text-right"><span class="r-jml text-[11px] font-bold text-emerald-600 dark:text-emerald-400">Rp0</span> <button type="button" class="r-del text-rose-500 font-bold px-1">×</button></div>';
    rows.appendChild(d);
    var hg = d.querySelector('.r-harga');
    function fmtR(b) { return b.kode + ' - ' + b.nama + ' (Rp' + Number(b.harga_jual).toLocaleString('id-ID') + ')'; }
    if (window.piCombo) window.piCombo(
      d.querySelector('.r-txt'), d.querySelector('.r-kode'), d.querySelector('.r-drop'),
      KAT, fmtR,
      function (b) { if (b) { hg.value = b.harga_jual; calc(); } }
    );
    d.querySelector('.r-qty').addEventListener('input', calc);
    hg.addEventListener('input', calc);
    d.querySelector('.r-del').addEventListener('click', function () { d.remove(); calc(); });
  }
  function total() {
    var t = 0;
    rows.childNodes.forEach(function (d) {
      if (!d.querySelector) return;
      var q = Math.max(1, +d.querySelector('.r-qty').value || 1);
      var h = Math.max(0, +d.querySelector('.r-harga').value || 0);
      d.querySelector('.r-jml').textContent = rp(q * h);
      t += q * h;
    });
    return t;
  }
  function calc() {
    var t = total();
    document.getElementById('invTotal').textContent = rp(t);
    var st = document.querySelector('#invForm input[name="status"]:checked').value;
    document.getElementById('dpWrap').classList.toggle('hidden', st !== 'dp');
    var dp = Math.max(0, +document.getElementById('dpNominal').value || 0);
    document.getElementById('invSisa').textContent = rp(Math.max(0, t - (st === 'dp' ? dp : (st === 'lunas' ? t : 0))));
  }
  document.getElementById('addRow').addEventListener('click', addRow);
  document.getElementById('dpNominal').addEventListener('input', calc);
  document.querySelectorAll('input[name="status"]').forEach(function (r) { r.addEventListener('change', calc); });
  tgl.addEventListener('change', updNomor);
  document.getElementById('invForm').addEventListener('submit', function (e) {
    var items = [];
    var kosong = false;
    rows.childNodes.forEach(function (d) {
      if (!d.querySelector) return;
      var kd = d.querySelector('.r-kode').value;
      if (!kd) { kosong = true; return; }
      items.push({ kode: kd, spek: d.querySelector('.r-spek').value, qty: d.querySelector('.r-qty').value, harga: d.querySelector('.r-harga').value });
    });
    if (!items.length || kosong) { e.preventDefault(); alert('Pilih dulu barang tiap baris dari hasil pencarian.'); return; }
    document.getElementById('itemsJson').value = JSON.stringify(items);
  });
  updNomor(); addRow(); calc();
});
</script>

<?php if ($owner): ?>
<div id="editModal" class="fixed inset-0 z-[100] hidden items-center justify-center p-4" style="background:rgba(2,6,12,.8);backdrop-filter:blur(6px)">
  <div class="glass-card rounded-3xl w-full max-w-2xl max-h-[88vh] flex flex-col overflow-hidden">
    <div class="flex items-center justify-between gap-2 p-5 border-b border-slate-200 dark:border-slate-800">
      <h3 class="font-bold text-sm flex items-center"><i class="fa-solid fa-pen text-blue-500 mr-2.5"></i> Edit Invoice <span id="eNomor" class="ml-2 font-mono text-emerald-600 dark:text-emerald-400"></span></h3>
      <button type="button" id="editClose" class="w-9 h-9 rounded-xl bg-slate-200 dark:bg-slate-800 hover:bg-slate-300 dark:hover:bg-slate-700 text-sm transition shrink-0">×</button>
    </div>
    <form method="post" id="eForm" class="overflow-auto custom-scrollbar p-5 space-y-4">
      <input type="hidden" name="aksi" value="invoice_edit">
      <input type="hidden" name="id" id="eId">
      <input type="hidden" name="items_json" id="eItems">
      <div class="grid grid-cols-3 gap-3">
        <div><label class="block text-xs font-semibold mb-1.5">Tanggal</label>
          <input type="date" name="tanggal" id="eTgl" class="w-full text-xs glass-input rounded-xl p-3 focus:outline-none"></div>
        <div><label class="block text-xs font-semibold mb-1.5">Nama Pemesan</label>
          <input type="text" name="pelanggan" id="ePel" required class="w-full text-xs glass-input rounded-xl p-3 focus:outline-none"></div>
        <div><label class="block text-xs font-semibold mb-1.5">No. WhatsApp</label>
          <input type="text" name="wa" id="eWa" class="w-full text-xs glass-input rounded-xl p-3 focus:outline-none"></div>
      </div>
      <div>
        <label class="block text-xs font-semibold mb-1.5">Detail Pesanan</label>
        <div id="eRows" class="space-y-2"></div>
        <button type="button" id="eAdd" class="mt-2 text-xs font-bold px-3 py-2 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 transition"><i class="fa-solid fa-plus mr-1"></i> Tambah Baris</button>
      </div>
      <div class="bg-slate-50 dark:bg-slate-900/50 p-4 rounded-2xl border border-slate-200 dark:border-slate-800 flex justify-between items-center">
        <span class="text-xs font-bold uppercase tracking-wider">Total</span>
        <span id="eTotal" class="text-2xl font-black">Rp0</span>
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div><label class="block text-xs font-semibold mb-1.5">Status</label>
          <div class="grid grid-cols-3 gap-1.5 text-[11px] font-bold">
            <label class="cursor-pointer"><input type="radio" name="estatus" value="lunas" class="peer hidden">
              <span class="block text-center px-1 py-2 rounded-lg border border-slate-200 dark:border-slate-700 peer-checked:bg-emerald-500 peer-checked:text-slate-950 peer-checked:border-emerald-500 transition">Lunas</span></label>
            <label class="cursor-pointer"><input type="radio" name="estatus" value="dp" class="peer hidden">
              <span class="block text-center px-1 py-2 rounded-lg border border-slate-200 dark:border-slate-700 peer-checked:bg-amber-500 peer-checked:text-slate-950 peer-checked:border-amber-500 transition">DP</span></label>
            <label class="cursor-pointer"><input type="radio" name="estatus" value="belum" class="peer hidden">
              <span class="block text-center px-1 py-2 rounded-lg border border-slate-200 dark:border-slate-700 peer-checked:bg-slate-500 peer-checked:text-white peer-checked:border-slate-500 transition">Cek Total</span></label>
          </div></div>
        <div><label class="block text-xs font-semibold mb-1.5">Metode</label>
          <select name="metode" id="eMet" class="w-full text-xs glass-input rounded-xl p-3 focus:outline-none">
            <option value="tunai">Tunai</option><option value="qris">QRIS</option><option value="transfer">Transfer</option>
          </select></div>
      </div>
      <div id="eDpWrap" class="hidden grid grid-cols-2 gap-3">
        <div><label class="block text-xs font-semibold mb-1.5">Nominal DP (Rp)</label>
          <input type="number" name="dp_nominal" id="eDp" min="0" value="0" class="w-full text-xs glass-input rounded-xl p-3 focus:outline-none"></div>
        <div class="bg-amber-500/10 border border-amber-500/30 rounded-xl p-3 text-xs"><span class="text-slate-500 dark:text-slate-400">Sisa tagihan:</span><br><b id="eSisa" class="text-base text-amber-600 dark:text-amber-400">Rp0</b></div>
      </div>
      <button class="w-full bg-blue-500 hover:bg-blue-400 text-white font-extrabold py-3 rounded-xl text-xs transition"><i class="fa-solid fa-floppy-disk mr-2"></i>Simpan Perubahan</button>
      <p class="text-[11px] text-slate-500 text-center">Nomor invoice tidak berubah.</p>
    </form>
  </div>
</div>

<script>
var INVDATA = <?=json_encode($invData)?>;
function openEdit(id) {
  var v = INVDATA[id];
  if (!v) return;
  document.getElementById('eId').value = id;
  document.getElementById('eNomor').textContent = v.nomor;
  document.getElementById('eTgl').value = v.tanggal;
  document.getElementById('ePel').value = v.pelanggan;
  document.getElementById('eWa').value = v.wa || '';
  document.getElementById('eMet').value = v.metode;
  document.getElementById('eDp').value = v.dp || 0;
  document.querySelectorAll('#eForm input[name="estatus"]').forEach(function (r) { r.checked = (r.value === v.status); });
  var box = document.getElementById('eRows');
  box.innerHTML = '';
  (v.items || []).forEach(function (it) { eAddRow(it); });
  if (!v.items || !v.items.length) eAddRow(null);
  eCalc();
  var m = document.getElementById('editModal');
  m.classList.remove('hidden'); m.classList.add('flex');
  document.body.style.overflow = 'hidden';
}
function closeEdit() {
  var m = document.getElementById('editModal');
  m.classList.add('hidden'); m.classList.remove('flex');
  document.body.style.overflow = '';
}
function eRp(n) { return 'Rp' + Number(n || 0).toLocaleString('id-ID'); }
function eFmt(b) { return b.kode + ' - ' + b.nama + ' (Rp' + Number(b.harga_jual).toLocaleString('id-ID') + ')'; }
function eAddRow(pre) {
  var box = document.getElementById('eRows');
  var EKAT = <?=json_encode($kat)?>;
  var d = document.createElement('div');
  d.className = 'grid grid-cols-12 gap-1.5 items-center bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-800 rounded-xl p-2';
  d.innerHTML =
    '<div class="col-span-12 sm:col-span-4 relative">' +
      '<input class="w-full text-[11px] glass-input rounded-lg p-2 focus:outline-none e-txt" placeholder="Ketik barang..." autocomplete="off">' +
      '<input type="hidden" class="e-kode">' +
      '<div class="e-drop pi-drop hidden absolute left-0 right-0 mt-1 rounded-xl overflow-auto custom-scrollbar shadow-2xl" style="max-height:200px"></div>' +
    '</div>' +
    '<input class="col-span-5 sm:col-span-3 text-[11px] glass-input rounded-lg p-2 focus:outline-none e-spek" placeholder="Ukuran/Spesifikasi">' +
    '<input type="number" class="col-span-2 text-[11px] glass-input rounded-lg p-2 focus:outline-none e-qty" value="1" min="1">' +
    '<input type="number" class="col-span-3 sm:col-span-2 text-[11px] glass-input rounded-lg p-2 focus:outline-none e-harga" min="0" value="0">' +
    '<div class="col-span-2 sm:col-span-1 text-right"><span class="e-jml text-[11px] font-bold text-emerald-600 dark:text-emerald-400">Rp0</span> <button type="button" class="e-del text-rose-500 font-bold px-1">×</button></div>';
  box.appendChild(d);
  var hg = d.querySelector('.e-harga');
  if (window.piCombo) window.piCombo(
    d.querySelector('.e-txt'), d.querySelector('.e-kode'), d.querySelector('.e-drop'),
    EKAT, eFmt,
    function (b) { if (b) { hg.value = b.harga_jual; eCalc(); } }
  );
  if (pre) {
    d.querySelector('.e-kode').value = pre.kode_barang || '';
    d.querySelector('.e-txt').value = (pre.kode_barang || '') + ' - ' + (pre.nama || '');
    d.querySelector('.e-spek').value = pre.spesifikasi || '';
    d.querySelector('.e-qty').value = pre.qty || 1;
    hg.value = pre.harga || 0;
  }
  d.querySelector('.e-qty').addEventListener('input', eCalc);
  hg.addEventListener('input', eCalc);
  d.querySelector('.e-del').addEventListener('click', function () { d.remove(); eCalc(); });
}
function eCalc() {
  var t = 0;
  document.getElementById('eRows').childNodes.forEach(function (d) {
    if (!d.querySelector) return;
    var q = Math.max(1, +d.querySelector('.e-qty').value || 1);
    var h = Math.max(0, +d.querySelector('.e-harga').value || 0);
    d.querySelector('.e-jml').textContent = eRp(q * h);
    t += q * h;
  });
  document.getElementById('eTotal').textContent = eRp(t);
  var st = document.querySelector('#eForm input[name="estatus"]:checked').value;
  document.getElementById('eDpWrap').classList.toggle('hidden', st !== 'dp');
  var dp = Math.max(0, +document.getElementById('eDp').value || 0);
  document.getElementById('eSisa').textContent = eRp(Math.max(0, t - (st === 'dp' ? dp : (st === 'lunas' ? t : 0))));
}
document.addEventListener('DOMContentLoaded', function () {
  document.getElementById('editClose').addEventListener('click', closeEdit);
  document.getElementById('editModal').addEventListener('click', function (e) { if (e.target.id === 'editModal') closeEdit(); });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && !document.getElementById('editModal').classList.contains('hidden')) closeEdit();
  });
  document.getElementById('eAdd').addEventListener('click', function () { eAddRow(null); });
  document.getElementById('eDp').addEventListener('input', eCalc);
  document.querySelectorAll('#eForm input[name="estatus"]').forEach(function (r) { r.addEventListener('change', eCalc); });
  document.getElementById('eForm').addEventListener('submit', function (e) {
    var items = [], kosong = false;
    document.getElementById('eRows').childNodes.forEach(function (d) {
      if (!d.querySelector) return;
      var kd = d.querySelector('.e-kode').value;
      if (!kd) { kosong = true; return; }
      items.push({ kode: kd, spek: d.querySelector('.e-spek').value, qty: d.querySelector('.e-qty').value, harga: d.querySelector('.e-harga').value });
    });
    if (!items.length || kosong) { e.preventDefault(); alert('Pilih dulu barang tiap baris dari hasil pencarian.'); return; }
    document.getElementById('eItems').value = JSON.stringify(items);
  });
});
</script>
<?php endif; ?>

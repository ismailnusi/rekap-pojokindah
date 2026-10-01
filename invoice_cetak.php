<?php
// Cetak / PDF Invoice — Struk Thermal 58/80mm atau kertas A5/A4
require_once __DIR__ . '/config.php';
$pdo = db();
require_login();

$id = (int)($_GET['id'] ?? 0);
$kertas = $_GET['kertas'] ?? 't80';
if (!in_array($kertas, ['t58', 't80', 'a5', 'a4'])) $kertas = 't80';

$st = $pdo->prepare("SELECT * FROM invoice WHERE id=?");
$st->execute([$id]);
$inv = $st->fetch(PDO::FETCH_ASSOC);
if (!$inv) die('Invoice tidak ditemukan.');

$si = $pdo->prepare("SELECT * FROM invoice_item WHERE invoice_id=? ORDER BY id");
$si->execute([$id]);
$items = $si->fetchAll(PDO::FETCH_ASSOC);

$sisa = $inv['status'] === 'lunas' ? 0 : ($inv['total'] - $inv['dp']);
$statusTxt = ['lunas' => 'LUNAS', 'dp' => 'BELUM LUNAS (DP)', 'belum' => 'BELUM LUNAS'];
$metodeTxt = ['tunai' => 'Tunai', 'qris' => 'QRIS', 'transfer' => 'Transfer'];
$t = strtotime($inv['tanggal']);
$ftgl = date('d/m/Y', $t);
$isStruk = ($kertas === 't58' || $kertas === 't80');
$nmKertas = ['t58' => 'Thermal 58mm', 't80' => 'Thermal 80mm', 'a5' => 'Kertas A5', 'a4' => 'Kertas A4'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?=e($inv['nomor'])?> — <?=e($nmKertas[$kertas])?></title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: 'Plus Jakarta Sans', Arial, sans-serif; background: #525659; color: #111; }
  .toolbar { max-width: 700px; margin: 16px auto; background: #fff; border-radius: 12px; padding: 12px 16px; display: flex; gap: 8px; align-items: center; flex-wrap: wrap; font-size: 13px; }
  .toolbar a, .toolbar button { padding: 8px 14px; border-radius: 8px; border: 1px solid #ddd; background: #f4f4f4; color: #111; text-decoration: none; font-weight: 700; cursor: pointer; font-size: 13px; font-family: inherit; }
  .toolbar a.on { background: #10b981; border-color: #10b981; color: #fff; }
  .toolbar .print { background: #111827; color: #fff; border-color: #111827; }
  .paper { background: #fff; margin: 0 auto 32px; }
  /* Struk */
  .struk { width: <?= $kertas === 't58' ? '58mm' : '80mm' ?>; padding: 3mm; font-size: 11px; line-height: 1.45; }
  .struk h1 { font-size: 15px; text-align: center; }
  .struk .c { text-align: center; }
  .struk .dash { border-top: 1px dashed #000; margin: 6px 0; }
  .struk table { width: 100%; border-collapse: collapse; }
  .struk .r { text-align: right; }
  .struk .stamp { border: 2px solid #000; border-radius: 6px; text-align: center; font-weight: 800; padding: 3px; margin: 8px 0; font-size: 13px; }
  /* A5 / A4 */
  .folio { width: <?= $kertas === 'a5' ? '148mm' : '190mm' ?>; max-width: 100%; padding: 10mm; font-size: 12px; line-height: 1.5; }
  .folio .kop { display: flex; gap: 12px; align-items: center; border-bottom: 3px double #000; padding-bottom: 10px; margin-bottom: 12px; }
  .folio .kop img { width: 56px; height: 56px; object-fit: cover; border-radius: 50%; background: #000; }
  .folio .kop h1 { font-size: 22px; letter-spacing: 1px; }
  .folio .kop p { font-size: 11px; color: #444; }
  .folio .kop-img img { width: 100%; display: block; }
  .folio table.items { width: 100%; border-collapse: collapse; margin: 10px 0; }
  .folio table.items th, .folio table.items td { border: 1px solid #000; padding: 6px 8px; }
  .folio table.items th { background: #f0f0f0; }
  .folio .r { text-align: right; }
  .folio .c { text-align: center; }
  .folio .tot { width: 220px; margin-left: auto; }
  .folio .tot td { padding: 3px 0; }
  .folio .ttd { display: flex; justify-content: space-between; margin-top: 28px; text-align: center; }
  .folio .stamp { display: inline-block; border: 2px solid #000; border-radius: 8px; font-weight: 800; padding: 4px 18px; margin-top: 10px; letter-spacing: 2px; }
  @media print {
    body { background: #fff; }
    .toolbar { display: none; }
    .paper { margin: 0; }
    <?php if ($kertas === 't58'): ?> @page { size: 58mm auto; margin: 0; } <?php endif; ?>
    <?php if ($kertas === 't80'): ?> @page { size: 80mm auto; margin: 0; } <?php endif; ?>
    <?php if ($kertas === 'a5'): ?> @page { size: A5; margin: 8mm; } <?php endif; ?>
    <?php if ($kertas === 'a4'): ?> @page { size: A4; margin: 10mm; } <?php endif; ?>
  }
</style>
</head>
<body>
<div class="toolbar">
  <span>Kertas:</span>
  <?php foreach ($nmKertas as $k => $n): ?>
    <a href="invoice_cetak.php?id=<?=$id?>&kertas=<?=$k?>" class="<?= $k === $kertas ? 'on' : '' ?>"><?=$n?></a>
  <?php endforeach; ?>
  <button class="print" onclick="window.print()">🖨️ Cetak / PDF</button>
  <a href="index.php?page=invoice">← Kembali</a>
</div>

<div class="paper <?= $isStruk ? 'struk' : 'folio' ?>">
<?php if ($isStruk): ?>
  <div class="c"><h1>POJOK INDAH</h1><div>Percetakan • Fotocopy • ATK • Jilid</div><div><b>NOTA PEMBAYARAN</b></div></div>
  <div class="dash"></div>
  <div>No: <?=e($inv['nomor'])?><br>Tgl: <?=e($ftgl)?><br>Pemesan: <?=e($inv['pelanggan'])?><?= $inv['wa'] !== '' ? '<br>WA: ' . e($inv['wa']) : '' ?><br>Kasir: <?=e($inv['kasir'])?></div>
  <div class="dash"></div>
  <table>
    <?php foreach ($items as $it): ?>
    <tr><td colspan="2"><?=e($it['nama'])?><?= $it['spesifikasi'] !== '' ? ' <i>(' . e($it['spesifikasi']) . ')</i>' : '' ?></td></tr>
    <tr><td><?=e($it['qty'])?> x <?=rupiah($it['harga'])?></td><td class="r"><?=rupiah($it['jumlah'])?></td></tr>
    <?php endforeach; ?>
  </table>
  <div class="dash"></div>
  <table>
    <tr><td><b>TOTAL</b></td><td class="r"><b><?=rupiah($inv['total'])?></b></td></tr>
    <tr><td>Bayar (<?=e($metodeTxt[$inv['metode']] ?? '')?>)</td><td class="r"><?=rupiah($inv['status'] === 'lunas' ? $inv['total'] : $inv['dp'])?></td></tr>
    <tr><td><b>SISA</b></td><td class="r"><b><?=rupiah($sisa)?></b></td></tr>
  </table>
  <div class="stamp"><?=e($statusTxt[$inv['status']] ?? '')?></div>
  <div class="c">Terima kasih atas kepercayaan Anda.<br>Barang yang sudah dibeli tidak dapat dikembalikan.</div>
<?php else: ?>
  <div class="kop">
    <img src="assets/kop-invoice.png" alt="PI" onerror="this.style.display='none'">
    <div><h1>POJOK INDAH</h1><p>Percetakan • Fotocopy • ATK • Jilid &amp; Penjualan Alat Tulis Kantor<br><b>NOTA PEMBAYARAN / INVOICE</b></p></div>
  </div>
  <table style="width:100%">
    <tr><td style="width:50%">No. Nota: <b><?=e($inv['nomor'])?></b><br>Tanggal: <?=e($ftgl)?><br>Kasir: <?=e($inv['kasir'])?></td>
    <td>Pemesan: <b><?=e($inv['pelanggan'])?></b><?= $inv['wa'] !== '' ? '<br>WA: ' . e($inv['wa']) : '' ?><br>Metode: <?=e($metodeTxt[$inv['metode']] ?? '')?></td></tr>
  </table>
  <table class="items">
    <tr><th style="width:28px">No</th><th>Nama Barang / Jasa</th><th>Ukuran / Spesifikasi</th><th class="r">Qty</th><th class="r">Harga</th><th class="r">Jumlah</th></tr>
    <?php $no = 1; foreach ($items as $it): ?>
    <tr><td class="c"><?=$no++?></td><td><?=e($it['nama'])?></td><td><?=e($it['spesifikasi'] ?: '-')?></td><td class="r"><?=$it['qty']?></td><td class="r"><?=rupiah($it['harga'])?></td><td class="r"><?=rupiah($it['jumlah'])?></td></tr>
    <?php endforeach; ?>
  </table>
  <table class="tot">
    <tr><td>Total</td><td class="r"><b><?=rupiah($inv['total'])?></b></td></tr>
    <tr><td>Uang Muka (DP)</td><td class="r"><?=rupiah($inv['status'] === 'lunas' ? $inv['total'] : $inv['dp'])?></td></tr>
    <tr><td><b>Sisa Tagihan</b></td><td class="r"><b><?=rupiah($sisa)?></b></td></tr>
  </table>
  <div><span class="stamp"><?=e($statusTxt[$inv['status']] ?? '')?></span></div>
  <div class="ttd">
    <div>Pelanggan<br><br><br><br>( <?=e($inv['pelanggan'])?> )</div>
    <div>Hormat kami<br><br><br><br>( <?=e($inv['kasir'] ?: 'Kasir')?> )</div>
  </div>
  <p style="margin-top:16px;font-size:11px;color:#444">Catatan: Barang yang sudah dibeli tidak dapat dikembalikan. Simpan nota ini sebagai bukti pembayaran yang sah.</p>
<?php endif; ?>
</div>
</body>
</html>

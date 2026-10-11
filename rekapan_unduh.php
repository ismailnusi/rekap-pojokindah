<?php
// Download Rekapan Bulanan (Excel .xls) — komprehensif per bulan
require_once __DIR__ . '/config.php';
$pdo = db();
require_login();

$bulan = $_GET['bulan'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $bulan)) $bulan = date('Y-m');
$like = $bulan . '%';

// role peminta (karyawan: tanpa kolom modal/laba)
$rk = $pdo->prepare("SELECT role FROM users WHERE id=?");
$rk->execute([$_SESSION['uid'] ?? 0]);
$owner = ($rk->fetchColumn() === 'owner');

$rp = function ($n) { return number_format((int)$n, 0, ',', '.'); };
$H = function ($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };

// --- ringkasan ---
$sum = function ($sql, $p = []) use ($pdo) {
    $s = $pdo->prepare($sql); $s->execute($p); return (int)$s->fetchColumn();
};
$pendapatan = $sum("SELECT COALESCE(SUM(jumlah),0) FROM transaksi WHERE tanggal LIKE ?", [$like]);
$pengeluaran = $sum("SELECT COALESCE(SUM(jumlah),0) FROM pengeluaran WHERE tanggal LIKE ?", [$like]);
$transfer = $sum("SELECT COALESCE(SUM(jumlah),0) FROM transfer_harian WHERE tanggal LIKE ?", [$like]);
$pinjam = $sum("SELECT COALESCE(SUM(jumlah),0) FROM kasbon WHERE tanggal LIKE ?", [$like]);
$setor = $sum("SELECT COALESCE(SUM(jumlah),0) FROM kasbon_setoran WHERE tanggal LIKE ?", [$like]);
$s = $pdo->prepare("SELECT COALESCE(SUM(t.qty * COALESCE(b.modal,0)),0) FROM transaksi t LEFT JOIN barang b ON b.kode=t.kode_barang WHERE t.tanggal LIKE ?");
$s->execute([$like]); $totalModal = (int)$s->fetchColumn();
$keuntungan = $pendapatan - $totalModal - $pengeluaran;
$saldoKas = $pendapatan - $pengeluaran - $transfer - $pinjam + $setor;

// --- rincian harian (tanggal, barang, qty, harga, jumlah, kasir) ---
$st = $pdo->prepare("SELECT t.tanggal, t.kode_barang, COALESCE(b.nama,'-') AS nama, t.qty, t.harga, t.jumlah,
        COALESCE(t.dibuat_oleh,'') AS kasir, COALESCE(b.modal,0) AS modal
    FROM transaksi t LEFT JOIN barang b ON b.kode=t.kode_barang
    WHERE t.tanggal LIKE ? ORDER BY t.tanggal, t.id");
$st->execute([$like]);
$detail = $st->fetchAll(PDO::FETCH_ASSOC);

// --- transfer / QRIS harian ---
$st = $pdo->prepare("SELECT tanggal, jumlah FROM transfer_harian WHERE tanggal LIKE ? ORDER BY tanggal");
$st->execute([$like]);
$trs = $st->fetchAll(PDO::FETCH_ASSOC);

// --- pengeluaran ---
$st = $pdo->prepare("SELECT tanggal, nama, harga, qty, jumlah, COALESCE(dibuat_oleh,'') AS kasir FROM pengeluaran WHERE tanggal LIKE ? ORDER BY tanggal, id");
$st->execute([$like]);
$keluar = $st->fetchAll(PDO::FETCH_ASSOC);

// --- kasbon ---
$st = $pdo->prepare("SELECT tanggal, nama, jumlah, keterangan FROM kasbon WHERE tanggal LIKE ? ORDER BY tanggal, id");
$st->execute([$like]);
$pinjamList = $st->fetchAll(PDO::FETCH_ASSOC);
$st = $pdo->prepare("SELECT tanggal, nama, jumlah, keterangan FROM kasbon_setoran WHERE tanggal LIKE ? ORDER BY tanggal, id");
$st->execute([$like]);
$setorList = $st->fetchAll(PDO::FETCH_ASSOC);

// --- rekap per item ---
$st = $pdo->prepare("SELECT b.kode, b.nama, COALESCE(SUM(t.qty),0) AS qty, COALESCE(SUM(t.jumlah),0) AS penjualan,
        b.modal, COALESCE(SUM(t.qty),0)*b.modal AS total_modal
    FROM barang b LEFT JOIN transaksi t ON t.kode_barang=b.kode AND t.tanggal LIKE ?
    GROUP BY b.kode ORDER BY penjualan DESC");
$st->execute([$like]);
$perItem = array_values(array_filter($st->fetchAll(PDO::FETCH_ASSOC), function ($r) { return (int)$r['qty'] > 0; }));

header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="Rekapan-' . $bulan . '.xls"');
header('Pragma: no-cache');
echo "\xEF\xBB\xBF";
?>
<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel">
<head><meta charset="UTF-8"></head>
<body>
<h2>REKAPAN BULANAN POJOK INDAH — <?=$H($bulan)?></h2>
<p>Diunduh: <?=date('d/m/Y H:i')?> oleh <?=$H($_SESSION['uname'] ?? '')?></p>

<h3>1. RINGKASAN</h3>
<table border="1">
<tr><th>Uraian</th><th>Nominal (Rp)</th></tr>
<tr><td>Total Pendapatan</td><td><?=$rp($pendapatan)?></td></tr>
<?php if ($owner): ?>
<tr><td>Total Modal</td><td><?=$rp($totalModal)?></td></tr>
<tr><td>Keuntungan Bersih (Pendapatan-Modal-Pengeluaran)</td><td><?=$rp($keuntungan)?></td></tr>
<?php endif; ?>
<tr><td>Total Pengeluaran</td><td><?=$rp($pengeluaran)?></td></tr>
<tr><td>Total Transfer / QRIS</td><td><?=$rp($transfer)?></td></tr>
<tr><td>Total Pinjaman Kasbon</td><td><?=$rp($pinjam)?></td></tr>
<tr><td>Total Setoran Kasbon</td><td><?=$rp($setor)?></td></tr>
<tr><td><b>Saldo Kas (Pendapatan-Pengeluaran-Transfer-Kasbon+Setoran)</b></td><td><b><?=$rp($saldoKas)?></b></td></tr>
</table>

<h3>2. RINCIAN TRANSAKSI HARIAN (tanggal, barang, qty, harga, kasir)</h3>
<table border="1">
<tr><th>Tanggal</th><th>Kode</th><th>Nama Barang / Jasa</th><th>Qty</th><th>Harga (Rp)</th><th>Jumlah (Rp)</th><?php if ($owner): ?><th>Modal/pcs (Rp)</th><?php endif; ?><th>Diinput Oleh</th></tr>
<?php foreach ($detail as $d): ?>
<tr><td><?=$H($d['tanggal'])?></td><td><?=$H($d['kode_barang'])?></td><td><?=$H($d['nama'])?></td><td><?=$d['qty']?></td><td><?=$rp($d['harga'])?></td><td><?=$rp($d['jumlah'])?></td><?php if ($owner): ?><td><?=$rp($d['modal'])?></td><?php endif; ?><td><?=$H($d['kasir'] !== '' ? $d['kasir'] : '-')?></td></tr>
<?php endforeach; ?>
<?php if (!$detail): ?><tr><td colspan="8">Tidak ada transaksi.</td></tr><?php endif; ?>
</table>

<h3>3. TRANSFER / QRIS HARIAN</h3>
<table border="1">
<tr><th>Tanggal</th><th>Jumlah (Rp)</th></tr>
<?php foreach ($trs as $t): ?><tr><td><?=$H($t['tanggal'])?></td><td><?=$rp($t['jumlah'])?></td></tr><?php endforeach; ?>
<?php if (!$trs): ?><tr><td colspan="2">Tidak ada.</td></tr><?php endif; ?>
</table>

<h3>4. PENGELUARAN</h3>
<table border="1">
<tr><th>Tanggal</th><th>Nama Pengeluaran</th><th>Harga (Rp)</th><th>Qty</th><th>Jumlah (Rp)</th><th>Diinput Oleh</th></tr>
<?php foreach ($keluar as $k): ?><tr><td><?=$H($k['tanggal'])?></td><td><?=$H($k['nama'])?></td><td><?=$rp($k['harga'])?></td><td><?=$k['qty']?></td><td><?=$rp($k['jumlah'])?></td><td><?=$H($k['kasir'] !== '' ? $k['kasir'] : '-')?></td></tr><?php endforeach; ?>
<?php if (!$keluar): ?><tr><td colspan="6">Tidak ada.</td></tr><?php endif; ?>
</table>

<h3>5. KASBON (pinjaman &amp; setoran)</h3>
<table border="1">
<tr><th>Tanggal</th><th>Jenis</th><th>Nama</th><th>Jumlah (Rp)</th><th>Keterangan</th></tr>
<?php foreach ($pinjamList as $p): ?><tr><td><?=$H($p['tanggal'])?></td><td>Pinjam</td><td><?=$H($p['nama'])?></td><td><?=$rp($p['jumlah'])?></td><td><?=$H($p['keterangan'])?></td></tr><?php endforeach; ?>
<?php foreach ($setorList as $p): ?><tr><td><?=$H($p['tanggal'])?></td><td>Setor</td><td><?=$H($p['nama'])?></td><td><?=$rp($p['jumlah'])?></td><td><?=$H($p['keterangan'])?></td></tr><?php endforeach; ?>
<?php if (!$pinjamList && !$setorList): ?><tr><td colspan="5">Tidak ada.</td></tr><?php endif; ?>
</table>

<h3>6. REKAP PER ITEM</h3>
<table border="1">
<tr><th>Kode</th><th>Nama</th><th>Terjual</th><th>Total Penjualan (Rp)</th><?php if ($owner): ?><th>Modal/pcs (Rp)</th><th>Total Modal (Rp)</th><th>Laba (Rp)</th><?php endif; ?></tr>
<?php foreach ($perItem as $r): ?>
<tr><td><?=$H($r['kode'])?></td><td><?=$H($r['nama'])?></td><td><?=$r['qty']?></td><td><?=$rp($r['penjualan'])?></td><?php if ($owner): ?><td><?=$rp($r['modal'])?></td><td><?=$rp($r['total_modal'])?></td><td><?=$rp($r['penjualan'] - $r['total_modal'])?></td><?php endif; ?></tr>
<?php endforeach; ?>
<?php if (!$perItem): ?><tr><td colspan="7">Tidak ada.</td></tr><?php endif; ?>
</table>
</body>
</html>

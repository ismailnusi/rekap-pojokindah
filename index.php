<?php
require __DIR__ . '/config.php';
$pdo = db();

// keluar
if (($_GET['aksi'] ?? '') === 'keluar') {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
    header('Location: login.php');
    exit;
}
require_login(); // dashboard tidak bisa dibuka tanpa login

// segarkan role dari DB (perubahan role / akun langsung berlaku)
$meR = $pdo->prepare("SELECT username, role FROM users WHERE id=?");
$meR->execute([$_SESSION['uid'] ?? 0]);
$meRow = $meR->fetch(PDO::FETCH_ASSOC);
if (!$meRow) {
    header('Location: index.php?aksi=keluar');
    exit;
}
$_SESSION['uname'] = $meRow['username'];
$_SESSION['role'] = $meRow['role'] ?? 'karyawan';
$owner = is_owner();

// penolakan untuk aksi khusus owner
$hanyaOwner = function ($kembali) {
    flash('Aksi ini hanya untuk Owner.');
    header('Location: ' . $kembali);
    exit;
};
$page = $_GET['page'] ?? 'harian';
$allowed = ['barang','harian','rekapan','diagram','invoice'];
if (!in_array($page, $allowed)) $page = 'harian';
$msg = flash();

// JSON kalender libur (dipakai Date Picker kalender, tanpa reload)
if (($_GET['ajax'] ?? '') === 'libur') {
    $yr = (int)($_GET['tahun'] ?? date('Y'));
    header('Content-Type: application/json');
    echo json_encode(hari_libur($yr));
    exit;
}
// tandai notifikasi login dibaca
if (($_GET['ajax'] ?? '') === 'notif_baca') {
    if (!$owner) { http_response_code(403); exit; }
    $_SESSION['notif_seen'] = (int)$pdo->query("SELECT COALESCE(MAX(id),0) FROM login_log")->fetchColumn();
    header('Content-Type: application/json');
    echo json_encode(['ok' => true]);
    exit;
}

// ===== AKSI POST (fungsi tetap, tidak diubah) =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = $_POST['aksi'] ?? '';

    if ($aksi === 'barang_tambah') {
        if (!$owner) $hanyaOwner('index.php?page=barang');
        $kode = strtoupper(trim($_POST['kode'] ?? ''));
        $nama = trim($_POST['nama'] ?? '');
        $harga = (int)($_POST['harga_jual'] ?? 0);
        $modal = (int)($_POST['modal'] ?? 0);
        $stok = max(0, (int)($_POST['stok_awal'] ?? 0));
        $stokMin = max(0, (int)($_POST['stok_min'] ?? 0));
        if ($kode === '' || $nama === '') { flash('Kode dan Nama wajib diisi.'); }
        else {
            $gambar = '';
            if (!empty($_FILES['gambar']['name']) && $_FILES['gambar']['error'] === 0) {
                $ext = strtolower(pathinfo($_FILES['gambar']['name'], PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg','jpeg','png','webp','gif'])) {
                    $fn = $kode . '_' . time() . '.' . $ext;
                    move_uploaded_file($_FILES['gambar']['tmp_name'], UPLOAD_DIR . '/' . $fn);
                    $gambar = $fn;
                }
            }
            try {
                $st = $pdo->prepare("INSERT INTO barang (kode,nama,harga_jual,modal,gambar,stok_awal,stok_min) VALUES (?,?,?,?,?,?,?)");
                $st->execute([$kode,$nama,$harga,$modal,$gambar,$stok,$stokMin]);
                flash("Barang $kode berhasil ditambah.");
            } catch (Exception $ex) { flash('Gagal: kode/nama sudah ada.'); }
        }
        header('Location: index.php?page=barang'); exit;
    }

    if ($aksi === 'barang_edit') {
        if (!$owner) $hanyaOwner('index.php?page=barang');
        $kode_lama = $_POST['kode_lama'] ?? '';
        $kode = strtoupper(trim($_POST['kode'] ?? ''));
        $nama = trim($_POST['nama'] ?? '');
        $harga = (int)($_POST['harga_jual'] ?? 0);
        $modal = (int)($_POST['modal'] ?? 0);
        $stok = max(0, (int)($_POST['stok_awal'] ?? 0));
        $stokMin = max(0, (int)($_POST['stok_min'] ?? 0));
        $row = $pdo->prepare("SELECT * FROM barang WHERE kode=?");
        $row->execute([$kode_lama]); $old = $row->fetch(PDO::FETCH_ASSOC);
        $gambar = $old['gambar'] ?? '';
        if (!empty($_FILES['gambar']['name']) && $_FILES['gambar']['error'] === 0) {
            $ext = strtolower(pathinfo($_FILES['gambar']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','webp','gif'])) {
                if ($gambar && file_exists(UPLOAD_DIR.'/'.$gambar)) @unlink(UPLOAD_DIR.'/'.$gambar);
                $fn = $kode . '_' . time() . '.' . $ext;
                move_uploaded_file($_FILES['gambar']['tmp_name'], UPLOAD_DIR . '/' . $fn);
                $gambar = $fn;
            }
        }
        $pdo->beginTransaction();
        try {
            $pdo->prepare("UPDATE barang SET kode=?,nama=?,harga_jual=?,modal=?,gambar=?,stok_awal=?,stok_min=? WHERE kode=?")
                ->execute([$kode,$nama,$harga,$modal,$gambar,$stok,$stokMin,$kode_lama]);
            if ($kode !== $kode_lama) {
                $pdo->prepare("UPDATE transaksi SET kode_barang=? WHERE kode_barang=?")->execute([$kode,$kode_lama]);
            }
            $pdo->commit(); flash("Barang $kode diperbarui.");
        } catch (Exception $ex) { $pdo->rollBack(); flash('Gagal update: kode/nama bentrok.'); }
        header('Location: index.php?page=barang'); exit;
    }

    if ($aksi === 'barang_hapus') {
        if (!$owner) $hanyaOwner('index.php?page=barang');
        $kode = $_POST['kode'] ?? '';
        $r = $pdo->prepare("SELECT gambar FROM barang WHERE kode=?"); $r->execute([$kode]);
        $g = $r->fetchColumn();
        $pdo->prepare("DELETE FROM barang WHERE kode=?")->execute([$kode]);
        if ($g && file_exists(UPLOAD_DIR.'/'.$g)) @unlink(UPLOAD_DIR.'/'.$g);
        flash("Barang $kode dihapus.");
        header('Location: index.php?page=barang'); exit;
    }

    if ($aksi === 'jual_tambah') {
        $tanggal = $_POST['tanggal'] ?? date('Y-m-d');
        $kode = $_POST['kode_barang'] ?? '';
        $qty = max(0,(int)($_POST['qty'] ?? 0));
        $b = $pdo->prepare("SELECT harga_jual, COALESCE(stok_awal,0) AS stok_awal FROM barang WHERE kode=?"); $b->execute([$kode]);
        $br = $b->fetch(PDO::FETCH_ASSOC);
        $harga = (int)($br['harga_jual'] ?? 0);
        $stokAwal = (int)($br['stok_awal'] ?? 0);
        if (!$br) { flash('Barang tidak ditemukan.'); }
        elseif ($qty <= 0) { flash('Pilih barang dan isi jumlah > 0.'); }
        else {
            // sisa stok = stok awal − total terjual (stok 0 = tanpa batas/jasa)
            $sold = 0;
            if ($stokAwal > 0) {
                $sq = $pdo->prepare("SELECT COALESCE(SUM(qty),0) FROM transaksi WHERE kode_barang=?");
                $sq->execute([$kode]);
                $sold = (int)$sq->fetchColumn();
            }
            if ($stokAwal > 0 && $qty > ($stokAwal - $sold)) {
                flash("Stok $kode tidak cukup (sisa " . ($stokAwal - $sold) . "). Tambah stok di Data Barang.");
            } else {
            $pdo->prepare("INSERT INTO transaksi (tanggal,kode_barang,harga,qty,jumlah) VALUES (?,?,?,?,?)")
                ->execute([$tanggal,$kode,$harga,$qty,$harga*$qty]);
            $dup = $pdo->prepare("SELECT id, qty FROM transaksi WHERE tanggal=? AND kode_barang=? AND harga=? ORDER BY id");
            $dup->execute([$tanggal,$kode,$harga]);
            $rows = $dup->fetchAll(PDO::FETCH_ASSOC);
            if (count($rows) > 1) {
                $total = array_sum(array_column($rows,'qty'));
                $keep = $rows[0]['id'];
                // jumlah dihitung di PHP (SQLite memakai nilai lama jika qty*harga ditulis dalam 1 UPDATE)
                $pdo->prepare("UPDATE transaksi SET qty=?, jumlah=? WHERE id=?")->execute([$total, $total * $harga, $keep]);
                $ids = array_column(array_slice($rows,1),'id');
                $pdo->prepare("DELETE FROM transaksi WHERE id IN (".implode(',',array_fill(0,count($ids),'?')).")")->execute($ids);
            }
            flash("Penjualan $kode x$qty tersimpan.");
            }
        }
        header('Location: index.php?page=harian&tanggal='.urlencode($tanggal).'&bulan='.substr($tanggal,0,7)); exit;
    }

    if ($aksi === 'jual_hapus') {
        $t = $_POST['tanggal'] ?? date('Y-m-d');
        if (!$owner) $hanyaOwner('index.php?page=harian&tanggal='.urlencode($t).'&bulan='.substr($t,0,7));
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare("DELETE FROM transaksi WHERE id=?")->execute([$id]);
        header('Location: index.php?page=harian&tanggal='.urlencode($t).'&bulan='.substr($t,0,7)); exit;
    }

    if ($aksi === 'transfer_simpan') {
        $tanggal = $_POST['tanggal'] ?? date('Y-m-d');
        $jumlah = (int)($_POST['jumlah'] ?? 0);
        $pdo->prepare("INSERT INTO transfer_harian (tanggal,jumlah) VALUES (?,?)
            ON CONFLICT(tanggal) DO UPDATE SET jumlah=excluded.jumlah")->execute([$tanggal,$jumlah]);
        flash('Total Transfer/QRIS hari itu tersimpan.');
        header('Location: index.php?page=harian&tanggal='.urlencode($tanggal).'&bulan='.substr($tanggal,0,7)); exit;
    }

    if ($aksi === 'keluar_tambah') {
        $tanggal = $_POST['tanggal'] ?? date('Y-m-d');
        $nama = trim($_POST['nama'] ?? '');
        $harga = (int)($_POST['harga'] ?? 0);
        $qty = max(1,(int)($_POST['qty'] ?? 1));
        if ($nama !== '') {
            $pdo->prepare("INSERT INTO pengeluaran (tanggal,nama,harga,qty,jumlah) VALUES (?,?,?,?,?)")
                ->execute([$tanggal,$nama,$harga,$qty,$harga*$qty]);
        }
        header('Location: index.php?page=harian&tanggal='.urlencode($tanggal).'&bulan='.substr($tanggal,0,7)); exit;
    }
    if ($aksi === 'keluar_hapus') {
        $t = $_POST['tanggal'] ?? date('Y-m-d');
        if (!$owner) $hanyaOwner('index.php?page=harian&tanggal='.urlencode($t).'&bulan='.substr($t,0,7));
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare("DELETE FROM pengeluaran WHERE id=?")->execute([$id]);
        header('Location: index.php?page=harian&tanggal='.urlencode($t).'&bulan='.substr($t,0,7)); exit;
    }

    // --- Kasbon: pinjaman ---
    if ($aksi === 'kasbon_tambah') {
        $tanggal = $_POST['tanggal'] ?? date('Y-m-d');
        $nama = trim($_POST['nama'] ?? '');
        $jumlah = (int)($_POST['jumlah'] ?? 0);
        $ket = trim($_POST['keterangan'] ?? '');
        if ($nama !== '' && $jumlah > 0) {
            $pdo->prepare("INSERT INTO kasbon (tanggal,nama,jumlah,keterangan) VALUES (?,?,?,?)")
                ->execute([$tanggal,$nama,$jumlah,$ket]);
            flash("Kasbon $nama " . rupiah($jumlah) . " tersimpan.");
        } else flash('Nama dan jumlah pinjaman wajib diisi.');
        header('Location: index.php?page=harian&tanggal='.urlencode($tanggal).'&bulan='.substr($tanggal,0,7)); exit;
    }
    if ($aksi === 'kasbon_hapus') {
        $t = $_POST['tanggal'] ?? date('Y-m-d');
        if (!$owner) $hanyaOwner('index.php?page=harian&tanggal='.urlencode($t).'&bulan='.substr($t,0,7));
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare("DELETE FROM kasbon WHERE id=?")->execute([$id]);
        header('Location: index.php?page=harian&tanggal='.urlencode($t).'&bulan='.substr($t,0,7)); exit;
    }

    // --- Kasbon: setoran / cicilan ---
    if ($aksi === 'setoran_tambah') {
        $tanggal = $_POST['tanggal'] ?? date('Y-m-d');
        $nama = trim($_POST['nama'] ?? '');
        $jumlah = (int)($_POST['jumlah'] ?? 0);
        $ket = trim($_POST['keterangan'] ?? '');
        $map = kasbon_sisa_map($pdo);
        $sisa = $map[strtolower($nama)]['sisa'] ?? 0;
        if ($nama === '' || $jumlah <= 0) { flash('Nama dan jumlah setoran wajib diisi.'); }
        elseif ($jumlah > $sisa) { flash("Setoran melebihi sisa kasbon $nama (" . rupiah($sisa) . ")."); }
        else {
            $pdo->prepare("INSERT INTO kasbon_setoran (tanggal,nama,jumlah,keterangan) VALUES (?,?,?,?)")
                ->execute([$tanggal,$nama,$jumlah,$ket]);
            flash("Setoran $nama " . rupiah($jumlah) . " tersimpan. Sisa: " . rupiah($sisa - $jumlah) . ".");
        }
        header('Location: index.php?page=harian&tanggal='.urlencode($tanggal).'&bulan='.substr($tanggal,0,7)); exit;
    }
    if ($aksi === 'setoran_hapus') {
        $t = $_POST['tanggal'] ?? date('Y-m-d');
        if (!$owner) $hanyaOwner('index.php?page=harian&tanggal='.urlencode($t).'&bulan='.substr($t,0,7));
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare("DELETE FROM kasbon_setoran WHERE id=?")->execute([$id]);
        header('Location: index.php?page=harian&tanggal='.urlencode($t).'&bulan='.substr($t,0,7)); exit;
    }

    // --- Ganti password ---
    if ($aksi === 'ganti_password') {
        $pg = $_POST['page'] ?? 'harian';
        $lama = $_POST['lama'] ?? '';
        $baru = $_POST['baru'] ?? '';
        $konf = $_POST['konfirmasi'] ?? '';
        $st = $pdo->prepare("SELECT * FROM users WHERE id=?");
        $st->execute([$_SESSION['uid'] ?? 0]);
        $me = $st->fetch(PDO::FETCH_ASSOC);
        if (!$me || !password_verify($lama, $me['password_hash'])) flash('Password lama salah.');
        elseif (strlen($baru) < 6) flash('Password baru minimal 6 karakter.');
        elseif ($baru !== $konf) flash('Konfirmasi password tidak sama.');
        else {
            $pdo->prepare("UPDATE users SET password_hash=? WHERE id=?")
                ->execute([password_hash($baru, PASSWORD_DEFAULT), $me['id']]);
            flash('Password berhasil diganti.');
        }
        header('Location: index.php?page=' . urlencode($pg));
        exit;
    }

    // --- Invoice: buat baru ---
    if ($aksi === 'invoice_buat') {
        $tanggal = $_POST['tanggal'] ?? date('Y-m-d');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) $tanggal = date('Y-m-d');
        $pelanggan = trim($_POST['pelanggan'] ?? '');
        $wa = trim($_POST['wa'] ?? '');
        $metode = in_array(($_POST['metode'] ?? ''), ['tunai','qris','transfer']) ? $_POST['metode'] : 'tunai';
        $status = in_array(($_POST['status'] ?? ''), ['lunas','dp','belum']) ? $_POST['status'] : 'belum';
        $dpIn = max(0, (int)($_POST['dp_nominal'] ?? 0));
        $items = json_decode($_POST['items_json'] ?? '[]', true);
        if (!is_array($items)) $items = [];
        $kat = $pdo->query("SELECT kode, nama FROM barang")->fetchAll(PDO::FETCH_KEY_PAIR);
        $baris = [];
        foreach ($items as $it) {
            $kode = strtoupper(trim($it['kode'] ?? ''));
            if (!isset($kat[$kode])) continue;
            $qty = max(1, (int)($it['qty'] ?? 1));
            $harga = max(0, (int)($it['harga'] ?? 0));
            $spek = trim(substr($it['spek'] ?? '', 0, 100));
            $baris[] = ['kode' => $kode, 'nama' => $kat[$kode], 'spek' => $spek, 'qty' => $qty, 'harga' => $harga, 'jumlah' => $qty * $harga];
        }
        if ($pelanggan === '' || !$baris) {
            flash('Nama pemesan dan minimal 1 barang wajib diisi.');
        } else {
            $total = array_sum(array_column($baris, 'jumlah'));
            if ($status === 'lunas') $dp = $total;
            elseif ($status === 'dp') { $dp = min($dpIn, $total); if ($dp >= $total) $status = 'lunas'; }
            else $dp = 0;
            $nomor = invoice_nomor($pdo, $tanggal);
            $pdo->beginTransaction();
            try {
                $pdo->prepare("INSERT INTO invoice (nomor,tanggal,pelanggan,wa,total,status,metode,dp,kasir) VALUES (?,?,?,?,?,?,?,?,?)")
                    ->execute([$nomor,$tanggal,$pelanggan,$wa,$total,$status,$metode,$dp,$_SESSION['uname'] ?? '']);
                $iid = (int)$pdo->lastInsertId();
                $si = $pdo->prepare("INSERT INTO invoice_item (invoice_id,kode_barang,nama,spesifikasi,qty,harga,jumlah) VALUES (?,?,?,?,?,?,?)");
                foreach ($baris as $b) $si->execute([$iid,$b['kode'],$b['nama'],$b['spek'],$b['qty'],$b['harga'],$b['jumlah']]);
                $pdo->commit();
                flash("Invoice $nomor tersimpan (" . rupiah($total) . ").");
            } catch (Exception $ex) { $pdo->rollBack(); flash('Gagal menyimpan invoice.'); }
        }
        header('Location: index.php?page=invoice');
        exit;
    }
    if ($aksi === 'invoice_lunas') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare("UPDATE invoice SET status='lunas' WHERE id=?")->execute([$id]);
        flash('Invoice dilunasi.');
        header('Location: index.php?page=invoice');
        exit;
    }
    if ($aksi === 'invoice_edit') {
        if (!$owner) $hanyaOwner('index.php?page=invoice');
        $id = (int)($_POST['id'] ?? 0);
        $cur = $pdo->prepare("SELECT * FROM invoice WHERE id=?");
        $cur->execute([$id]);
        $cur = $cur->fetch(PDO::FETCH_ASSOC);
        if (!$cur) { flash('Invoice tidak ditemukan.'); header('Location: index.php?page=invoice'); exit; }
        $tanggal = $_POST['tanggal'] ?? $cur['tanggal'];
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) $tanggal = $cur['tanggal'];
        $pelanggan = trim($_POST['pelanggan'] ?? '');
        $wa = trim($_POST['wa'] ?? '');
        $metode = in_array(($_POST['metode'] ?? ''), ['tunai','qris','transfer']) ? $_POST['metode'] : 'tunai';
        $status = in_array(($_POST['status'] ?? ''), ['lunas','dp','belum']) ? $_POST['status'] : 'belum';
        $dpIn = max(0, (int)($_POST['dp_nominal'] ?? 0));
        $items = json_decode($_POST['items_json'] ?? '[]', true);
        if (!is_array($items)) $items = [];
        $kat = $pdo->query("SELECT kode, nama FROM barang")->fetchAll(PDO::FETCH_KEY_PAIR);
        $baris = [];
        foreach ($items as $it) {
            $kode = strtoupper(trim($it['kode'] ?? ''));
            if (!isset($kat[$kode])) continue;
            $qty = max(1, (int)($it['qty'] ?? 1));
            $harga = max(0, (int)($it['harga'] ?? 0));
            $spek = trim(substr($it['spek'] ?? '', 0, 100));
            $baris[] = [$kode, $kat[$kode], $spek, $qty, $harga, $qty * $harga];
        }
        if ($pelanggan === '' || !$baris) {
            flash('Nama pemesan dan minimal 1 barang wajib diisi.');
        } else {
            $total = array_sum(array_column($baris, 5));
            if ($status === 'lunas') $dp = $total;
            elseif ($status === 'dp') { $dp = min($dpIn, $total); if ($dp >= $total) $status = 'lunas'; }
            else $dp = 0;
            $pdo->beginTransaction();
            try {
                $pdo->prepare("UPDATE invoice SET tanggal=?,pelanggan=?,wa=?,total=?,status=?,metode=?,dp=? WHERE id=?")
                    ->execute([$tanggal,$pelanggan,$wa,$total,$status,$metode,$dp,$id]);
                $pdo->prepare("DELETE FROM invoice_item WHERE invoice_id=?")->execute([$id]);
                $si = $pdo->prepare("INSERT INTO invoice_item (invoice_id,kode_barang,nama,spesifikasi,qty,harga,jumlah) VALUES (?,?,?,?,?,?,?)");
                foreach ($baris as $b) $si->execute([$id,$b[0],$b[1],$b[2],$b[3],$b[4],$b[5]]);
                $pdo->commit();
                flash("Invoice {$cur['nomor']} diperbarui (" . rupiah($total) . ").");
            } catch (Exception $ex) { $pdo->rollBack(); flash('Gagal memperbarui invoice.'); }
        }
        header('Location: index.php?page=invoice');
        exit;
    }
    if ($aksi === 'invoice_hapus') {
        if (!$owner) $hanyaOwner('index.php?page=invoice');
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare("DELETE FROM invoice_item WHERE invoice_id=?")->execute([$id]);
        $pdo->prepare("DELETE FROM invoice WHERE id=?")->execute([$id]);
        flash('Invoice dihapus.');
        header('Location: index.php?page=invoice');
        exit;
    }

    // --- Kelola akun (owner) ---
    if ($aksi === 'user_tambah') {
        $pg = $_POST['page'] ?? 'harian';
        if (!$owner) $hanyaOwner('index.php?page=' . urlencode($pg));
        $u = trim($_POST['username'] ?? '');
        $p = $_POST['password'] ?? '';
        $r = ($_POST['role'] ?? 'karyawan') === 'owner' ? 'owner' : 'karyawan';
        if (!preg_match('/^[A-Za-z0-9_]{3,20}$/', $u)) flash('Username 3–20 karakter (huruf/angka/_).');
        elseif (strlen($p) < 6) flash('Password minimal 6 karakter.');
        else {
            try {
                $pdo->prepare("INSERT INTO users (username,password_hash,role) VALUES (?,?,?)")
                    ->execute([$u, password_hash($p, PASSWORD_DEFAULT), $r]);
                flash("Akun $u ($r) dibuat.");
            } catch (Exception $ex) { flash('Username sudah dipakai.'); }
        }
        header('Location: index.php?page=' . urlencode($pg));
        exit;
    }
    if ($aksi === 'user_hapus') {
        $pg = $_POST['page'] ?? 'harian';
        if (!$owner) $hanyaOwner('index.php?page=' . urlencode($pg));
        $id = (int)($_POST['id'] ?? 0);
        if ($id === (int)($_SESSION['uid'] ?? 0)) flash('Tidak bisa menghapus akun sendiri.');
        else {
            $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$id]);
            flash('Akun dihapus.');
        }
        header('Location: index.php?page=' . urlencode($pg));
        exit;
    }
    if ($aksi === 'user_reset') {
        $pg = $_POST['page'] ?? 'harian';
        if (!$owner) $hanyaOwner('index.php?page=' . urlencode($pg));
        $id = (int)($_POST['id'] ?? 0);
        $p = $_POST['password'] ?? '';
        if (strlen($p) < 6) flash('Password minimal 6 karakter.');
        else {
            $pdo->prepare("UPDATE users SET password_hash=? WHERE id=?")
                ->execute([password_hash($p, PASSWORD_DEFAULT), $id]);
            flash('Password akun direset.');
        }
        header('Location: index.php?page=' . urlencode($pg));
        exit;
    }
}

$bulan = bulan_aktif();
$tanggal = tanggal_aktif();
$nav = function($p) use ($page) { return $p === $page ? 'nav-link active' : 'nav-link'; };
$jmlRestock = count(stok_menipis($pdo));
// notifikasi jejak login (owner saja)
$notifUnread = 0;
if ($owner) {
    $seen = (int)($_SESSION['notif_seen'] ?? 0);
    $notifUnread = (int)$pdo->query("SELECT COUNT(*) FROM login_log WHERE id > $seen")->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="id" class="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Pojok Indah — Executive Bookkeeping System</title>
<link rel="icon" type="image/png" href="assets/kop-invoice.png?v=1">
<script src="https://cdn.tailwindcss.com"></script>
<script>
tailwind.config = { darkMode: 'class', theme: { extend: { colors: { brand: {50:'#ecfdf5',500:'#10b981',600:'#059669',400:'#34d399'} } } } };
(function () {
  try {
    var saved = localStorage.getItem('pi-theme');
    var dark = saved ? (saved === 'dark') : true;
    document.documentElement.classList.toggle('dark', dark);
    document.documentElement.setAttribute('data-theme', dark ? 'dark' : 'light');
    document.documentElement.style.colorScheme = dark ? 'dark' : 'light';
  } catch (e) {
    document.documentElement.classList.add('dark');
  }
})();
</script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>
  body { font-family: 'Plus Jakarta Sans', sans-serif; overflow-x: clip; }
  .glass-card { min-width: 0; } /* cegah kartu meluber di grid HP */
  /* Light mode */
  html:not(.dark) body { background-color: #f1f5f9; color: #0f172a; }
  html:not(.dark) .glass-card { background: #ffffff; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(15,23,42,.06); }
  html:not(.dark) .glass-input { background: #f8fafc; border: 1px solid #e2e8f0; color: #0f172a; }
  html:not(.dark) .glass-input:focus { border-color: #10b981; box-shadow: 0 0 10px rgba(16,185,129,.25); outline: none; }
  html:not(.dark) .tbl-head { background: #f1f5f9; color: #64748b; }
  html:not(.dark) .tbl-body { background: #ffffff; }
  html:not(.dark) .tbl-row { border-color: #f1f5f9; }
  /* Dark mode (sesuai template) */
  html.dark body { background-color: #0b0f17; color: #f3f4f6; }
  html.dark .glass-card { background: rgba(17,24,39,.75); backdrop-filter: blur(12px); border: 1px solid rgba(255,255,255,.07); }
  html.dark .glass-input { background: rgba(15,23,42,.6); border: 1px solid rgba(255,255,255,.1); color: #f3f4f6; }
  html.dark .glass-input:focus { border-color: #10b981; box-shadow: 0 0 12px rgba(16,185,129,.25); outline: none; }
  html.dark .tbl-head { background: #0f172a; color: #94a3b8; }
  html.dark .tbl-body { background: rgba(15,23,42,.3); }
  html.dark .tbl-row { border-color: rgba(30,41,59,.5); }
  .nav-link.active { background: linear-gradient(135deg, rgba(16,185,129,.2) 0%, rgba(16,185,129,.05) 100%); border-bottom: 2px solid #10b981; color: #34d399; font-weight: 700; }
  .custom-scrollbar::-webkit-scrollbar { width: 5px; height: 5px; }
  .custom-scrollbar::-webkit-scrollbar-thumb { background: #1f2937; border-radius: 4px; }
  select.glass-input option { background: #0f172a; color: #fff; }
  html:not(.dark) select.glass-input option { background: #fff; color: #0f172a; }
  input[type="month"], input[type="date"] { color-scheme: dark; }
  html:not(.dark) input[type="month"], html:not(.dark) input[type="date"] { color-scheme: light; }
  /* Toggle tombol: ikon diganti via JS (tidak tergantung class dark: Tailwind) */
  /* Searchable dropdown (autocomplete barang) */
  .pi-drop { background: #fff; border: 1px solid #e2e8f0; z-index: 70; }
  html.dark .pi-drop { background: #0f172a; border-color: #334155; }
  .pi-opt { padding: 8px 12px; font-size: 12px; cursor: pointer; }
  .pi-opt.hl, .pi-opt:hover { background: rgba(16,185,129,.18); }
  .pi-empty { padding: 10px 12px; font-size: 12px; color: #94a3b8; font-style: italic; }
  #iconSun { display: none; }
  html.dark #iconSun { display: inline; }
  html.dark #iconMoon { display: none; }
  html:not(.dark) #iconMoon { display: inline; }
  #themeLabel { min-width: 44px; text-align: left; }
</style>
</head>
<body class="antialiased min-h-screen flex flex-col selection:bg-emerald-500 selection:text-slate-900">

<header class="glass-card sticky top-0 z-50 border-b border-slate-200 dark:border-slate-800">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="flex items-center justify-between h-20 gap-3">
      <div class="flex items-center space-x-2 sm:space-x-3.5 min-w-0">
        <div class="w-10 h-10 rounded-full overflow-hidden bg-black flex items-center justify-center shadow-lg shrink-0 relative">
          <span class="absolute inset-0 flex items-center justify-center font-black text-white text-sm tracking-tight">PI</span>
          <img src="assets/kop-invoice.png?v=1" alt="PI" class="relative w-full h-full object-cover" onerror="this.remove()">
        </div>
        <div class="min-w-0">
          <div class="flex items-center space-x-2">
            <h1 class="text-sm min-[400px]:text-base sm:text-lg font-bold tracking-wider uppercase whitespace-nowrap">Pojok Indah</h1>
            <span class="hidden min-[380px]:inline-block text-[10px] bg-emerald-500/10 text-emerald-500 dark:text-emerald-400 border border-emerald-500/20 px-2 py-0.5 rounded-full font-semibold shrink-0">PRO</span>
          </div>
          <p class="hidden min-[420px]:block text-[11px] sm:text-xs text-slate-500 dark:text-slate-400 tracking-wide truncate">Enterprise Financial &amp; POS Portal</p>
        </div>
      </div>
      <div class="flex items-center gap-1.5 sm:gap-2 shrink-0">
        <nav class="hidden md:flex space-x-1 sm:space-x-2 bg-slate-100 dark:bg-slate-900/80 p-1.5 rounded-2xl border border-slate-200 dark:border-slate-800">
          <a href="index.php?page=harian&bulan=<?=e($bulan)?>&tanggal=<?=e($tanggal)?>" class="<?= $nav('harian') ?> px-4 py-2 rounded-xl text-xs sm:text-sm text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white transition-all duration-200 flex items-center space-x-2">
            <i class="fa-regular fa-pen-to-square"></i><span class="hidden sm:inline">Penjualan Harian</span>
          </a>
          <a href="index.php?page=barang" class="<?= $nav('barang') ?> px-4 py-2 rounded-xl text-xs sm:text-sm text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white transition-all duration-200 flex items-center space-x-2">
            <i class="fa-solid fa-boxes-stacked"></i><span class="hidden sm:inline">Data Barang</span><?= $jmlRestock > 0 ? '<span class="text-[10px] font-extrabold bg-rose-500 text-white px-1.5 py-0.5 rounded-full">' . $jmlRestock . '</span>' : '' ?>
          </a>
          <a href="index.php?page=invoice" class="<?= $nav('invoice') ?> px-4 py-2 rounded-xl text-xs sm:text-sm text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white transition-all duration-200 flex items-center space-x-2">
            <i class="fa-solid fa-receipt"></i><span class="hidden sm:inline">Invoice / Nota</span>
          </a>
          <a href="index.php?page=rekapan&bulan=<?=e($bulan)?>" class="<?= $nav('rekapan') ?> px-4 py-2 rounded-xl text-xs sm:text-sm text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white transition-all duration-200 flex items-center space-x-2">
            <i class="fa-solid fa-file-invoice-dollar"></i><span class="hidden sm:inline">Rekapan</span>
          </a>
          <a href="index.php?page=diagram&mode=semua" class="<?= $nav('diagram') ?> px-4 py-2 rounded-xl text-xs sm:text-sm text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white transition-all duration-200 flex items-center space-x-2">
            <i class="fa-solid fa-chart-pie"></i><span class="hidden sm:inline">Analytics</span>
          </a>
        </nav>
        <button id="menuBtn" type="button" title="Menu" class="md:hidden h-10 w-10 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 flex items-center justify-center hover:scale-105 transition text-sm">
          <i class="fa-solid fa-bars"></i>
        </button>
        <button id="themeToggle" type="button" title="Mode terang/gelap" class="h-10 px-2 sm:px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 flex items-center gap-2 text-amber-500 dark:text-amber-300 hover:scale-105 transition text-xs font-bold">
          <i id="iconSun" class="fa-solid fa-sun"></i>
          <i id="iconMoon" class="fa-solid fa-moon"></i>
          <span id="themeLabel" class="hidden min-[400px]:inline">Gelap</span>
        </button>
        <button id="akunBtn" type="button" title="Akun (<?=e($_SESSION['uname'] ?? '')?>)" class="h-10 px-2 sm:px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 flex items-center gap-2 text-emerald-600 dark:text-emerald-400 hover:scale-105 transition text-xs font-bold">
          <i class="fa-solid fa-circle-user"></i>
          <span class="hidden sm:inline max-w-[80px] truncate"><?=e($_SESSION['uname'] ?? '')?></span>
        </button>
        <?php if ($owner): ?>
        <button id="notifBtn" type="button" title="Jejak login" class="relative h-10 w-10 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 flex items-center justify-center hover:scale-105 transition text-sm">
          <i class="fa-solid fa-bell <?= $notifUnread > 0 ? 'text-amber-500' : 'text-slate-400' ?>"></i>
          <?php if ($notifUnread > 0): ?><span id="notifBadge" class="absolute -top-1.5 -right-1.5 text-[10px] font-extrabold bg-rose-500 text-white px-1.5 py-0.5 rounded-full"><?= $notifUnread > 99 ? '99+' : $notifUnread ?></span><?php endif; ?>
        </button>
        <?php endif; ?>
      </div>
    </div>
    <div id="mobileMenu" class="hidden md:hidden absolute top-full left-2 right-2 mt-1 glass-card rounded-2xl p-2 space-y-1 shadow-2xl z-50">
      <a href="index.php?page=harian&bulan=<?=e($bulan)?>&tanggal=<?=e($tanggal)?>" class="<?= $nav('harian') ?> block px-4 py-3 rounded-xl text-sm transition-all"><i class="fa-regular fa-pen-to-square mr-3 w-4"></i>Penjualan Harian</a>
      <a href="index.php?page=barang" class="<?= $nav('barang') ?> block px-4 py-3 rounded-xl text-sm transition-all"><i class="fa-solid fa-boxes-stacked mr-3 w-4"></i>Data Barang<?= $jmlRestock > 0 ? ' <span class="text-[10px] font-extrabold bg-rose-500 text-white px-1.5 py-0.5 rounded-full">' . $jmlRestock . '</span>' : '' ?></a>
      <a href="index.php?page=invoice" class="<?= $nav('invoice') ?> block px-4 py-3 rounded-xl text-sm transition-all"><i class="fa-solid fa-receipt mr-3 w-4"></i>Invoice / Nota</a>
      <a href="index.php?page=rekapan&bulan=<?=e($bulan)?>" class="<?= $nav('rekapan') ?> block px-4 py-3 rounded-xl text-sm transition-all"><i class="fa-solid fa-file-invoice-dollar mr-3 w-4"></i>Rekapan</a>
      <a href="index.php?page=diagram&mode=semua" class="<?= $nav('diagram') ?> block px-4 py-3 rounded-xl text-sm transition-all"><i class="fa-solid fa-chart-pie mr-3 w-4"></i>Analytics</a>
    </div>
  </div>
</header>

<main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 flex-grow w-full">
  <?php if ($msg): ?>
    <div class="glass-card p-4 rounded-2xl mb-6 text-sm font-semibold text-emerald-600 dark:text-emerald-300 border-l-4 border-l-emerald-500">
      <i class="fa-solid fa-circle-check mr-2"></i><?=e($msg)?>
    </div>
  <?php endif; ?>
  <?php
  if ($page === 'barang') include __DIR__.'/hal_barang.php';
  elseif ($page === 'harian') include __DIR__.'/hal_harian.php';
  elseif ($page === 'rekapan') include __DIR__.'/hal_rekapan.php';
  elseif ($page === 'diagram') include __DIR__.'/hal_diagram.php';
  elseif ($page === 'invoice') include __DIR__.'/hal_invoice.php';
  ?>
</main>

<footer class="glass-card border-t border-slate-200 dark:border-slate-800 py-4 text-center text-xs text-slate-500">
  Pojok Indah Enterprise System &bull; Saldo Kas = Pendapatan &minus; Pengeluaran &minus; Transfer &bull; Closed Book Manual Sync
</footer>

<div id="akunModal" class="fixed inset-0 z-[100] hidden items-center justify-center p-4" style="background:rgba(2,6,12,.8);backdrop-filter:blur(6px)">
  <div class="glass-card rounded-3xl w-full max-w-sm p-6 space-y-5 max-h-[85vh] overflow-auto custom-scrollbar">
    <div class="flex items-center justify-between">
      <h3 class="font-bold text-sm flex items-center"><i class="fa-solid fa-circle-user text-emerald-500 mr-2.5"></i> Akun: <?=e($_SESSION['uname'] ?? '')?> <span class="ml-2 text-[10px] px-2 py-0.5 rounded-full <?= $owner ? 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400' : 'bg-blue-500/15 text-blue-500' ?>"><?= $owner ? 'OWNER' : 'KARYAWAN' ?></span></h3>
      <button type="button" id="akunClose" class="w-9 h-9 rounded-xl bg-slate-200 dark:bg-slate-800 hover:bg-slate-300 dark:hover:bg-slate-700 text-sm transition">×</button>
    </div>
    <?php if ($owner):
      $semuaUser = $pdo->query("SELECT id, username, role FROM users ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    ?>
    <div class="space-y-2">
      <p class="text-xs font-bold">Kelola Akun</p>
      <?php foreach ($semuaUser as $usr): ?>
      <div class="flex items-center gap-2 text-xs bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-800 rounded-xl px-3 py-2">
        <i class="fa-solid fa-user text-slate-400"></i>
        <b class="flex-grow truncate"><?=e($usr['username'])?></b>
        <span class="text-[10px] px-1.5 py-0.5 rounded <?= $usr['role'] === 'owner' ? 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400' : 'bg-blue-500/15 text-blue-500' ?>"><?=e($usr['role'])?></span>
        <?php if ((int)$usr['id'] !== (int)($_SESSION['uid'] ?? 0)): ?>
        <form method="post" style="display:inline" onsubmit="return confirm('Hapus akun <?=e($usr['username'])?>?')">
          <input type="hidden" name="aksi" value="user_hapus"><input type="hidden" name="id" value="<?=$usr['id']?>"><input type="hidden" name="page" value="<?=e($page)?>">
          <button title="Hapus akun" class="text-rose-500 font-bold px-1">×</button>
        </form>
        <?php endif; ?>
      </div>
      <form method="post" class="flex gap-1.5">
        <input type="hidden" name="aksi" value="user_reset"><input type="hidden" name="id" value="<?=$usr['id']?>"><input type="hidden" name="page" value="<?=e($page)?>">
        <input type="text" name="password" required minlength="6" placeholder="Password baru <?=e($usr['username'])?>" autocomplete="off" class="flex-grow text-[11px] glass-input rounded-lg px-2.5 py-1.5 focus:outline-none">
        <button title="Reset password" class="text-[11px] font-bold px-2.5 py-1.5 rounded-lg bg-slate-200 dark:bg-slate-800 hover:bg-slate-300 dark:hover:bg-slate-700 transition">Reset</button>
      </form>
      <?php endforeach; ?>
      <form method="post" class="grid grid-cols-12 gap-1.5 pt-1">
        <input type="hidden" name="aksi" value="user_tambah"><input type="hidden" name="page" value="<?=e($page)?>">
        <input type="text" name="username" required pattern="[A-Za-z0-9_]{3,20}" placeholder="Username baru" autocomplete="off" class="col-span-4 text-[11px] glass-input rounded-lg px-2.5 py-2 focus:outline-none">
        <input type="text" name="password" required minlength="6" placeholder="Password" autocomplete="off" class="col-span-4 text-[11px] glass-input rounded-lg px-2.5 py-2 focus:outline-none">
        <select name="role" class="col-span-2 text-[11px] glass-input rounded-lg px-1 py-2 focus:outline-none"><option value="karyawan">Karyawan</option><option value="owner">Owner</option></select>
        <button title="Tambah akun" class="col-span-2 bg-emerald-500 hover:bg-emerald-400 text-slate-950 rounded-lg text-xs font-bold transition">＋</button>
      </form>
    </div>
    <?php endif; ?>
    <form method="post" class="space-y-3">
      <input type="hidden" name="aksi" value="ganti_password">
      <input type="hidden" name="page" value="<?=e($page)?>">
      <p class="text-xs font-bold">Ganti Password Saya</p>
      <input type="password" name="lama" required placeholder="Password lama" autocomplete="current-password" class="w-full text-xs glass-input rounded-xl p-3 focus:outline-none">
      <input type="password" name="baru" required placeholder="Password baru (min. 6)" autocomplete="new-password" class="w-full text-xs glass-input rounded-xl p-3 focus:outline-none">
      <input type="password" name="konfirmasi" required placeholder="Ulangi password baru" autocomplete="new-password" class="w-full text-xs glass-input rounded-xl p-3 focus:outline-none">
      <button class="w-full bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-extrabold py-2.5 rounded-xl text-xs transition">Simpan Password</button>
    </form>
    <a href="index.php?aksi=keluar" onclick="return confirm('Keluar dari pembukuan?')" class="block text-center w-full bg-rose-500/15 hover:bg-rose-500/25 text-rose-500 border border-rose-500/30 font-extrabold py-2.5 rounded-xl text-xs transition"><i class="fa-solid fa-right-from-bracket mr-2"></i>Keluar</a>
  </div>
</div>

<script>
(function () {
  var m = document.getElementById('akunModal');
  var b = document.getElementById('akunBtn');
  if (!m || !b) return;
  function open() { m.classList.remove('hidden'); m.classList.add('flex'); }
  function close() { m.classList.add('hidden'); m.classList.remove('flex'); }
  b.addEventListener('click', open);
  document.getElementById('akunClose').addEventListener('click', close);
  m.addEventListener('click', function (e) { if (e.target === m) close(); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !m.classList.contains('hidden')) close(); });
})();
</script>

<?php if ($owner):
  $logs = $pdo->query("SELECT username, status, ip, waktu FROM login_log ORDER BY id DESC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);
?>
<div id="notifModal" class="fixed inset-0 z-[100] hidden items-center justify-center p-4" style="background:rgba(2,6,12,.8);backdrop-filter:blur(6px)">
  <div class="glass-card rounded-3xl w-full max-w-md max-h-[82vh] flex flex-col overflow-hidden">
    <div class="flex items-center justify-between gap-2 p-5 border-b border-slate-200 dark:border-slate-800">
      <div>
        <h3 class="font-bold text-sm flex items-center"><i class="fa-solid fa-bell text-amber-500 mr-2.5"></i> Jejak Login</h3>
        <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">Siapa masuk &amp; percobaan gagal • 50 terbaru</p>
      </div>
      <button type="button" id="notifClose" class="w-9 h-9 rounded-xl bg-slate-200 dark:bg-slate-800 hover:bg-slate-300 dark:hover:bg-slate-700 text-sm transition shrink-0">×</button>
    </div>
    <div class="overflow-auto custom-scrollbar p-4 space-y-2">
      <?php if (!$logs): ?><p class="text-center text-slate-500 italic text-xs py-6">Belum ada aktivitas login.</p><?php endif; ?>
      <?php foreach ($logs as $l):
        $t = strtotime($l['waktu']);
        $fw = date('d/m/Y H:i', $t);
        $gagal = $l['status'] !== 'sukses';
      ?>
      <div class="flex items-center gap-3 text-xs bg-slate-50 dark:bg-slate-900/50 border <?= $gagal ? 'border-rose-500/40' : 'border-slate-200 dark:border-slate-800' ?> rounded-xl px-3 py-2.5">
        <span class="text-[10px] font-extrabold px-2 py-0.5 rounded-full shrink-0 <?= $gagal ? 'bg-rose-500/15 text-rose-500' : 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400' ?>"><?= $gagal ? 'GAGAL' : 'MASUK' ?></span>
        <div class="flex-grow min-w-0"><b class="truncate block"><?=e($l['username'])?></b><span class="text-slate-500"><?=e($fw)?> • <?=e($l['ip'] ?: '-')?></span></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<script>
(function () {
  var m = document.getElementById('notifModal');
  var b = document.getElementById('notifBtn');
  if (!m || !b) return;
  function open() {
    m.classList.remove('hidden'); m.classList.add('flex');
    fetch('index.php?ajax=notif_baca').then(function () {
      var bd = document.getElementById('notifBadge');
      if (bd) bd.remove();
      b.querySelector('i').classList.remove('text-amber-500');
      b.querySelector('i').classList.add('text-slate-400');
    });
  }
  function close() { m.classList.add('hidden'); m.classList.remove('flex'); }
  b.addEventListener('click', open);
  document.getElementById('notifClose').addEventListener('click', close);
  m.addEventListener('click', function (e) { if (e.target === m) close(); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !m.classList.contains('hidden')) close(); });
})();
</script>
<?php endif; ?>

<script>
(function () {
  var b = document.getElementById('menuBtn');
  var m = document.getElementById('mobileMenu');
  if (!b || !m) return;
  b.addEventListener('click', function (e) {
    e.stopPropagation();
    var open = m.classList.toggle('hidden');
    b.innerHTML = open ? '<i class="fa-solid fa-bars"></i>' : '<i class="fa-solid fa-xmark"></i>';
  });
  document.addEventListener('click', function (e) {
    if (!m.classList.contains('hidden') && !m.contains(e.target)) {
      m.classList.add('hidden');
      b.innerHTML = '<i class="fa-solid fa-bars"></i>';
    }
  });
})();
</script>

<script src="assets/app.js?v=3"></script>
</body>
</html>

<?php
// Konfigurasi utama Pembukuan Pojok Indah
session_start();
date_default_timezone_set('Asia/Jakarta');

define('DB_FILE', __DIR__ . '/database.sqlite');
define('UPLOAD_DIR', __DIR__ . '/uploads');
if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0777, true);

function db() {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO('sqlite:' . DB_FILE);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec("PRAGMA foreign_keys = ON");
        migrate($pdo);
    }
    return $pdo;
}

function migrate($pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS barang (
        kode TEXT PRIMARY KEY,
        nama TEXT UNIQUE NOT NULL,
        harga_jual INTEGER NOT NULL DEFAULT 0,
        modal INTEGER NOT NULL DEFAULT 0,
        gambar TEXT DEFAULT ''
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS transaksi (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        tanggal TEXT NOT NULL,
        kode_barang TEXT NOT NULL,
        harga INTEGER NOT NULL DEFAULT 0,
        qty INTEGER NOT NULL DEFAULT 0,
        jumlah INTEGER NOT NULL DEFAULT 0,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP
    )");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_transaksi_tgl ON transaksi(tanggal)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS transfer_harian (
        tanggal TEXT PRIMARY KEY,
        jumlah INTEGER NOT NULL DEFAULT 0
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS pengeluaran (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        tanggal TEXT NOT NULL,
        nama TEXT NOT NULL,
        harga INTEGER NOT NULL DEFAULT 0,
        qty INTEGER NOT NULL DEFAULT 1,
        jumlah INTEGER NOT NULL DEFAULT 0
    )");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_pengeluaran_tgl ON pengeluaran(tanggal)");
    // kolom stok (migrasi ringan untuk DB lama)
    $cols = $pdo->query("PRAGMA table_info(barang)")->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('stok_awal', $cols, true)) {
        $pdo->exec("ALTER TABLE barang ADD COLUMN stok_awal INTEGER NOT NULL DEFAULT 0");
    }
    if (!in_array('stok_min', $cols, true)) {
        $pdo->exec("ALTER TABLE barang ADD COLUMN stok_min INTEGER NOT NULL DEFAULT 0");
    }
    // kasbon / pinjaman karyawan + setoran (cicilan)
    $pdo->exec("CREATE TABLE IF NOT EXISTS kasbon (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        tanggal TEXT NOT NULL,
        nama TEXT NOT NULL,
        jumlah INTEGER NOT NULL DEFAULT 0,
        keterangan TEXT DEFAULT ''
    )");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_kasbon_tgl ON kasbon(tanggal)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS kasbon_setoran (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        tanggal TEXT NOT NULL,
        nama TEXT NOT NULL,
        jumlah INTEGER NOT NULL DEFAULT 0,
        keterangan TEXT DEFAULT ''
    )");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_setoran_tgl ON kasbon_setoran(tanggal)");
    // invoice / nota percetakan
    $pdo->exec("CREATE TABLE IF NOT EXISTS invoice (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nomor TEXT UNIQUE NOT NULL,
        tanggal TEXT NOT NULL,
        pelanggan TEXT NOT NULL,
        wa TEXT DEFAULT '',
        total INTEGER NOT NULL DEFAULT 0,
        status TEXT NOT NULL DEFAULT 'belum',
        metode TEXT NOT NULL DEFAULT 'tunai',
        dp INTEGER NOT NULL DEFAULT 0,
        kasir TEXT DEFAULT '',
        created_at TEXT DEFAULT CURRENT_TIMESTAMP
    )");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_inv_tgl ON invoice(tanggal)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS invoice_item (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        invoice_id INTEGER NOT NULL REFERENCES invoice(id) ON DELETE CASCADE,
        kode_barang TEXT DEFAULT '',
        nama TEXT NOT NULL,
        spesifikasi TEXT DEFAULT '',
        qty INTEGER NOT NULL DEFAULT 1,
        harga INTEGER NOT NULL DEFAULT 0,
        jumlah INTEGER NOT NULL DEFAULT 0
    )");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_item_inv ON invoice_item(invoice_id)");
    // pengguna (login)
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE NOT NULL,
        password_hash TEXT NOT NULL,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP
    )");
    if ((int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn() === 0) {
        $pdo->prepare("INSERT INTO users (username,password_hash) VALUES (?,?)")
            ->execute(['admin', password_hash('admin123', PASSWORD_DEFAULT)]);
    }
    // kolom role (migrasi DB lama) + akun karyawan bawaan
    $ucols = $pdo->query("PRAGMA table_info(users)")->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('role', $ucols, true)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN role TEXT NOT NULL DEFAULT 'karyawan'");
        $pdo->exec("UPDATE users SET role='owner' WHERE id = (SELECT MIN(id) FROM users)");
    }
    $cekK = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username=?");
    $cekK->execute(['karyawan']);
    if ((int)$cekK->fetchColumn() === 0) {
        $pdo->prepare("INSERT INTO users (username,password_hash,role) VALUES (?,?,?)")
            ->execute(['karyawan', password_hash('karyawan123', PASSWORD_DEFAULT), 'karyawan']);
    }

    // Seed 161 barang jika kosong
    $cek = $pdo->query("SELECT COUNT(*) FROM barang")->fetchColumn();
    if ((int)$cek === 0) {
        seed_barang($pdo);
    }
}

function seed_barang($pdo) {
    $data = [
        ['F001','FOTO COPY',500],['F002','FOTO COPY WARNA',1500],['F003','PRINT HITAM',1000],
        ['F004','PRINT WARNA',1500],['F005','JILID TRANSPARAN',5000],['F006','JILID BUKU/BIASA',10000],
        ['F007','JILID COVER LAMINASI',25000],['F008','JILID SKRIPSI PGSD',40000],['F009','JILID SKRIPSI/TINTA GOLD',40000],
        ['F010','JILID SKRIPSI TINTA BIASA',30000],['F011','JILID JURNAL',10000],['F012','POLPEN EDS NO 700',5000],
        ['F013','BURNING KASET',5000],['F014','PAKET BURNING KASET',15000],['F015','BOX DVD',5000],
        ['F016','BURNING PAKET PGSD',25000],['F017','STIKER KASET',5000],['F018','POLPEN VISION 2 HITAM',3500],
        ['F019','POLPEN VISION 2 BIRU',3500],['F020','JEPIT KERTAS BIG 107 19 mm',8000],['F021','JEPIT KERTAS KENKO 107 19mm',8000],
        ['F022','JEPIT KERTAS JOYKO 25 MM DOS',10000],['F023','JEPIT KERTAS JOYKO 25 MM PICIS',1000],['F024','JEPIT KERTAS KINGCO 41mm',1000],
        ['F025','JEPIT KERTAS WARNA /KECIL',500],['F026','JEPIT KERTAS WARNA /BESAR',2000],['F027','JEPIT KERTAS TRIGONAL/DOS',5000],
        ['F028','TIPE-X JOYKO CF 2323',5000],['F029','TIPE-X JOYKO CF S209A',7000],['F030','TIPE-X V-TEC CP 1008',6000],
        ['F031','TIPE-X JOYKO JK-01',7500],['F032','LEM KERTAS 35ml',3000],['F033','BINDER 0011',20000],
        ['F034','BINDER 0012',17000],['F035','BINDER 0013',17500],['F036','BINDER 0014',20000],
        ['F037','BINDER 0015',17500],['F038','BINDER 0016',20000],['F039','BINDER 0017',20000],
        ['F040','BINDER 0018',25000],['F041','BINDER 0019',27000],['F042','BINDER 0020',25000],
        ['F043','BINDER 0021',30000],['F044','BINDER 0022',35000],['F045','BINDER 0023',27000],
        ['F046','BINDER 0024',38000],['F047','BUFALO',1000],['F048','GUNTING',10000],
        ['F049','STOF MAP FOLIO',2000],['F050','MAP TALI HIJAU',4000],['F051','INDEKS TABS 76 X 19 MM',15000],
        ['F052','STIKY NOTE 76 X 51',10500],['F053','KERTAS DINS / 10 LEMBAR',5000],['F054','ID CARD SET WRN',15000],
        ['F055','ID CARD DOUBLE SIDE',15000],['F056','KERTAS HVS POJOK INDAH 1',50000],['F057','PENDAPATAN TAK TERHITUNG',122000],
        ['F058','DOUBLE TAPE 1 CM',5000],['F059','LAPBAN',10000],['F060','LAPBAN BESAR',18000],
        ['F061','PRINT BUFALO',2500],['F062','BURNING DVD',5000],['F063','map snelhecter (BORNEO BUSINESS PUTIH)',5000],
        ['F064','AMPLOP BESAR',2000],['F065','PASFOTO 4 X 6',5000],['F066','JILID LUX EXPRES',50000],
        ['F067','FOTO COP BOLAK BALIK',1000],['F068','JILID BOOKLET',5000],['F069','1 SET ID CARD STD',6000],
        ['F070','DOUBLE TAPE SEDANG 1/2',4000],['F071','SELOTIP 1 CM',4000],['F072','jepit kerta sedang/hitam/200',2000],
        ['F073','kertas bufalo',1000],['F074','kertas pres',3000],['F075','ID CARD KACA DOUBLE',12000],
        ['F076','ID CAR SET MAC',10000],['F077','POLPEN SNOWMAN V5',6000],['F078','POLPEN JOYKO BP 355',2500],
        ['F079','POLPEN S-1 HITAM',3500],['F080','PENCIL PREMIUM GOLD',2000],['F081','ISI CUTER JOYKO KECIL',5000],
        ['F082','KERTAS HVS 1 RIM',55000],['F083','AMPLOP KECIL',1000],['F084','PRES BERKAS A4/F4',10000],
        ['F085','Buku yasin',15000],['F086','DVD PAKET PGSD',25000],['F087','MAP PLASTIK PUTIH TALI',4000],
        ['F088','PEMBATAS',1500],['F089','JILID STASE EXPRES',50000],['F090','PASFOTO 2 X 3',5000],
        ['F091','POLPEN JOYKO BP 330',2500],['F092','stiker ukuran A4',10000],['F093','polpen blaster',20000],
        ['F094','ISI HEKTER',4000],['F095','HEKTER EDS-ON hd-10',15000],['F096','Jilid lux tinta emas',40000],
        ['F097','Nota Kontan',5000],['F098','PAS FOTO 3X4 3LEMBAR',5000],['F099','MAP KERTAS MERAH',2000],
        ['F100','PRINT LIFLET BUFALO',5000],['F101','JILID LUX TINTA BIASA',35000],['F102','KARTON',5000],
        ['F103','jilid lux std',40000],['F104','boklet fis',10000],['F105','CAP stempel plastik standar',100000],
        ['F106','Buku Kwitansi',5000],['F107','print sertifikat (bufalo)',5000],['F108','JILID STASE',35000],
        ['F109','BUKU COSTUM',10000],['F110','MAP PLASTIK HIJAU',5000],['F111','BINDER CLIPS NO 155',1500],
        ['F112','BINDER CLIPS NO 260',2500],['F113','POLPEN SNOWMAN BP V2',3500],['F114','SELOTIP BERRY 12MM',2000],
        ['F115','STICKY NOTE MULTIPLE 51X38',7500],['F116','STICKY NOTE TZ 6001',15000],['F117','STOMAP FOLIO BATIK',3000],
        ['F118','POLPEN VANCO BP1062',3000],['F119','ID CARD MIKA SET',20000],['F120','HVS 5 LEMBAR',1000],
        ['F121','PENGHAPUS BIG 4B',2000],['F122','BUKU KWARTO',12000],['F123','map snelhecter ( BUSINESS HIJAU)',5000],
        ['F124','set id card hitam',15000],['F125','kertas foto',5000],['F126','ID CARD MIKA',10000],
        ['F127','ID CARD SET HITAM',15000],['F128','ID CARD pink',15000],['F129','stof map folio (BATIK)',4000],
        ['F130','STICK NOTE',10500],['F131','kaset only',5000],['F132','kertas transparan',1000],
        ['F133','Kartu',20000],['F134','print A3',2000],['F135','print kertas foto',10000],
        ['F136','papanama',30000],['F137','polpen vanco 0.5 MM',3000],['F138','kartu',20000],
        ['F139','jilid lux',40000],['F140','JILID BUKU TEBAL',15000],['F141','Tempat Kaset',5000],
        ['F142','pres kecil (ktp-dll)',5000],['F143','Jilid Buku biasa laminating',25000],['F144','foto 10 R',15000],
        ['F145','POLPEN JOYCO BP-264',2500],['F146','BINDER CLIPS NO. 107 SATU DOS',8000],['F147','print kertas bertekstur',5000],
        ['F148','FC BUFALO',2000],['F149','map snalhekter kuning',5000],['F150','JEPIT KERTAS eds-on 32mm',1500],
        ['F151','tempat idcard coklat',10000],['F152','map plastik bening klep',4000],['F153','map coklat kertas tali',3000],
        ['F154','Materai10ribu',13000],['F155','MOKA BUKU',10000],['F156','SPIDOL HITAM',3000],
        ['F157','poster',35000],['F158','jilid buku laminating',20000],['F159','service print',250000],
        ['F160','Jilid Stase',35000],['F161','service laptop',100000],
    ];
    $st = $pdo->prepare("INSERT OR IGNORE INTO barang (kode,nama,harga_jual,modal,gambar) VALUES (?,?,?,0,'')");
    foreach ($data as $d) {
        $st->execute([$d[0], $d[1], $d[2]]);
    }
}

function rupiah($n) {
    return 'Rp' . number_format((int)$n, 0, ',', '.');
}
function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function bulan_aktif() {
    $b = $_GET['bulan'] ?? date('Y-m');
    if (!preg_match('/^\d{4}-\d{2}$/', $b)) $b = date('Y-m');
    return $b;
}
function tanggal_aktif() {
    $t = $_GET['tanggal'] ?? date('Y-m-d');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $t)) $t = date('Y-m-d');
    return $t;
}
function flash($msg = null) {
    if ($msg !== null) $_SESSION['flash'] = $msg;
    else { $m = $_SESSION['flash'] ?? ''; unset($_SESSION['flash']); return $m; }
}

// Auth sederhana (aplikasi lokal)
function require_login() {
    if (!empty($_SESSION['uid'])) return;
    $next = 'index.php' . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '');
    header('Location: login.php?next=' . urlencode($next));
    exit;
}
function is_owner() {
    return ($_SESSION['role'] ?? '') === 'owner';
}

// Nomor invoice otomatis: INV-YYYYMMDD-001 (urut per tanggal)
function invoice_nomor($pdo, $tanggal) {
    $compact = str_replace('-', '', $tanggal);
    $st = $pdo->prepare("SELECT COUNT(*) FROM invoice WHERE tanggal=?");
    $st->execute([$tanggal]);
    $n = (int)$st->fetchColumn() + 1;
    do {
        $no = sprintf('INV-%s-%03d', $compact, $n);
        $c = $pdo->prepare("SELECT COUNT(*) FROM invoice WHERE nomor=?");
        $c->execute([$no]);
        if ((int)$c->fetchColumn() === 0) return $no;
        $n++;
    } while (true);
}

// Barang yang perlu restock: sisa <= batas minimum per barang, plus yang habis (0).
// Jasa (stok_awal = 0) tidak ikut. Urut sisa terkecil dulu.
function stok_menipis($pdo) {
    $rows = $pdo->query("SELECT b.kode, b.nama, b.stok_awal, COALESCE(b.stok_min,0) AS stok_min,
            (b.stok_awal - COALESCE(t.s,0)) AS sisa
        FROM barang b
        LEFT JOIN (SELECT kode_barang, COALESCE(SUM(qty),0) AS s FROM transaksi GROUP BY kode_barang) t
            ON t.kode_barang = b.kode
        WHERE b.stok_awal > 0
          AND (b.stok_awal - COALESCE(t.s,0)) <= CASE WHEN COALESCE(b.stok_min,0) > 0 THEN b.stok_min ELSE 0 END
        ORDER BY sisa ASC")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$r) $r['status'] = ((int)$r['sisa'] <= 0) ? 'habis' : 'menipis';
    unset($r);
    return $rows;
}
// Sisa kasbon per orang (global, semua tanggal): pinjam − setoran.
// key = nama lowercase agar "Budi" dan "BUDI" dianggap orang yang sama.
function kasbon_sisa_map($pdo) {
    $m = [];
    foreach ($pdo->query("SELECT nama, jumlah FROM kasbon")->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $k = strtolower(trim($r['nama']));
        if ($k === '') continue;
        if (!isset($m[$k])) $m[$k] = ['nama' => trim($r['nama']), 'pinjam' => 0, 'setor' => 0];
        $m[$k]['pinjam'] += (int)$r['jumlah'];
    }
    foreach ($pdo->query("SELECT nama, jumlah FROM kasbon_setoran")->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $k = strtolower(trim($r['nama']));
        if ($k === '') continue;
        if (!isset($m[$k])) $m[$k] = ['nama' => trim($r['nama']), 'pinjam' => 0, 'setor' => 0];
        $m[$k]['setor'] += (int)$r['jumlah'];
    }
    foreach ($m as $k => &$v) $v['sisa'] = $v['pinjam'] - $v['setor'];
    unset($v);
    return $m;
}

// Libur nasional Indonesia (YYYY-MM-DD => nama). Coba API daring + cache,
// gagal/offline pakai daftar bawaan. Sabtu & Minggu otomatis merah via JS.
function hari_libur($tahun) {
    $tahun = (int)$tahun;
    if ($tahun < 2020 || $tahun > 2100) $tahun = (int)date('Y');
    $dir = __DIR__ . '/cache';
    if (!is_dir($dir)) @mkdir($dir, 0777, true);
    $cf = $dir . '/libur-' . $tahun . '.json';
    if (is_file($cf) && (time() - filemtime($cf)) < 30*86400) {
        $j = json_decode(@file_get_contents($cf), true);
        if (is_array($j)) return $j;
    }
    $data = null;
    foreach ([
        'https://api-harilibur.vercel.app/api?year=' . $tahun,
        'https://dayoffapi.vercel.app/api?year=' . $tahun,
    ] as $url) {
        $ctx = stream_context_create(['http' => ['timeout' => 2, 'ignore_errors' => true]]);
        $raw = @file_get_contents($url, false, $ctx);
        if (!$raw) continue;
        $arr = json_decode($raw, true);
        if (!is_array($arr)) continue;
        $tmp = [];
        foreach ($arr as $h) {
            $d = $h['holiday_date'] ?? $h['tanggal'] ?? null;
            $n = $h['holiday_name'] ?? $h['keterangan'] ?? $h['name'] ?? '';
            if (!$d) continue;
            $p = explode('-', $d);
            if (count($p) === 3) $d = sprintf('%04d-%02d-%02d', (int)$p[0], (int)$p[1], (int)$p[2]);
            $tmp[$d] = $n;
        }
        if ($tmp) { $data = $tmp; break; }
    }
    if (!$data) $data = libur_bawaan($tahun);
    @file_put_contents($cf, json_encode($data));
    return $data;
}

// Daftar bawaan (perkiraan SKB 3 Menteri). Dikoreksi otomatis dari API saat online.
function libur_bawaan($tahun) {
    $tetap = [
        '01-01' => 'Tahun Baru Masehi',
        '05-01' => 'Hari Buruh Internasional',
        '06-01' => 'Hari Lahir Pancasila',
        '08-17' => 'Hari Kemerdekaan RI',
        '12-25' => 'Hari Raya Natal',
    ];
    $khusus = [
        2025 => [
            '2025-01-27' => 'Isra Mikraj 1446H', '2025-01-28' => 'Cuti Bersama Imlek',
            '2025-01-29' => 'Tahun Baru Imlek 2576', '2025-03-28' => 'Cuti Bersama Nyepi',
            '2025-03-29' => 'Hari Suci Nyepi 1947', '2025-03-31' => 'Idul Fitri 1446H',
            '2025-04-01' => 'Idul Fitri 1446H', '2025-04-02' => 'Cuti Bersama Idul Fitri',
            '2025-04-03' => 'Cuti Bersama Idul Fitri', '2025-04-04' => 'Cuti Bersama Idul Fitri',
            '2025-04-07' => 'Cuti Bersama Idul Fitri', '2025-04-18' => 'Wafat Isa Almasih',
            '2025-05-12' => 'Hari Raya Waisak 2569', '2025-05-13' => 'Cuti Bersama Waisak',
            '2025-05-29' => 'Kenaikan Isa Almasih', '2025-05-30' => 'Cuti Bersama Kenaikan Isa',
            '2025-06-06' => 'Idul Adha 1446H', '2025-06-09' => 'Cuti Bersama Idul Adha',
            '2025-06-27' => 'Tahun Baru Islam 1447H', '2025-08-18' => 'Cuti Bersama Kemerdekaan',
            '2025-09-05' => 'Maulid Nabi 1447H', '2025-12-26' => 'Cuti Bersama Natal',
        ],
        2026 => [
            '2026-01-16' => 'Isra Mikraj 1447H', '2026-02-17' => 'Tahun Baru Imlek 2577',
            '2026-03-19' => 'Hari Suci Nyepi 1948', '2026-03-20' => 'Idul Fitri 1447H',
            '2026-03-21' => 'Idul Fitri 1447H', '2026-03-23' => 'Cuti Bersama Idul Fitri',
            '2026-03-24' => 'Cuti Bersama Idul Fitri', '2026-04-03' => 'Wafat Isa Almasih',
            '2026-05-14' => 'Kenaikan Isa Almasih', '2026-05-27' => 'Idul Adha 1447H',
            '2026-05-31' => 'Hari Raya Waisak 2570', '2026-06-16' => 'Tahun Baru Islam 1448H',
            '2026-08-26' => 'Maulid Nabi 1448H',
        ],
        2027 => [
            '2027-01-08' => 'Isra Mikraj 1448H', '2027-02-06' => 'Tahun Baru Imlek 2578',
            '2027-03-09' => 'Hari Suci Nyepi 1949', '2027-03-10' => 'Idul Fitri 1448H',
            '2027-03-11' => 'Idul Fitri 1448H', '2027-03-26' => 'Wafat Isa Almasih',
            '2027-05-06' => 'Kenaikan Isa Almasih', '2027-05-17' => 'Idul Adha 1448H',
            '2027-05-20' => 'Hari Raya Waisak 2571', '2027-06-05' => 'Tahun Baru Islam 1449H',
            '2027-08-15' => 'Maulid Nabi 1449H',
        ],
    ];
    $out = [];
    foreach ($tetap as $md => $n) $out[$tahun . '-' . $md] = $n;
    foreach (($khusus[$tahun] ?? []) as $d => $n) $out[$d] = $n;
    ksort($out);
    return $out;
}

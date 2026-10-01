<?php
require __DIR__ . '/config.php';
$pdo = db();
if (!empty($_SESSION['uid'])) { header('Location: index.php'); exit; }

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = trim($_POST['username'] ?? '');
    $p = $_POST['password'] ?? '';
    $st = $pdo->prepare("SELECT * FROM users WHERE username=?");
    $st->execute([$u]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if ($row && password_verify($p, $row['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['uid'] = $row['id'];
        $_SESSION['uname'] = $row['username'];
        $_SESSION['role'] = $row['role'] ?? 'karyawan';
        $next = $_GET['next'] ?? 'index.php';
        if (!is_string($next) || strpos($next, 'index.php') !== 0) $next = 'index.php';
        header('Location: ' . $next);
        exit;
    }
    $err = 'Username atau password salah.';
}
?>
<!DOCTYPE html>
<html lang="id" class="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Masuk — Pojok Indah</title>
<link rel="icon" type="image/png" href="assets/kop-invoice.png?v=1">
<script src="https://cdn.tailwindcss.com"></script>
<script>
tailwind.config = { darkMode: 'class' };
(function () {
  try {
    var dark = (localStorage.getItem('pi-theme') || 'dark') === 'dark';
    document.documentElement.classList.toggle('dark', dark);
    document.documentElement.style.colorScheme = dark ? 'dark' : 'light';
  } catch (e) { document.documentElement.classList.add('dark'); }
})();
</script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>
  body { font-family: 'Plus Jakarta Sans', sans-serif; }
  html:not(.dark) body { background-color: #f1f5f9; color: #0f172a; }
  html:not(.dark) .glass-card { background: #ffffff; border: 1px solid #e2e8f0; box-shadow: 0 8px 30px rgba(15,23,42,.08); }
  html:not(.dark) .glass-input { background: #f8fafc; border: 1px solid #e2e8f0; color: #0f172a; }
  html.dark body { background-color: #0b0f17; color: #f3f4f6; }
  html.dark .glass-card { background: rgba(17,24,39,.85); backdrop-filter: blur(12px); border: 1px solid rgba(255,255,255,.07); }
  html.dark .glass-input { background: rgba(15,23,42,.6); border: 1px solid rgba(255,255,255,.1); color: #f3f4f6; }
  .glass-input:focus { border-color: #10b981; outline: none; }
</style>
</head>
<body class="antialiased min-h-screen flex items-center justify-center p-4">
  <div class="glass-card rounded-3xl w-full max-w-sm p-8 space-y-6">
    <div class="text-center space-y-3">
      <div class="w-16 h-16 rounded-full overflow-hidden bg-black mx-auto shadow-lg relative">
        <span class="absolute inset-0 flex items-center justify-center font-black text-white text-xl">PI</span>
        <img src="assets/kop-invoice.png?v=1" alt="PI" class="relative w-full h-full object-cover" onerror="this.remove()">
      </div>
      <div>
        <h1 class="text-lg font-bold tracking-wider uppercase">Pojok Indah</h1>
        <p class="text-xs text-slate-500 dark:text-slate-400">Masuk untuk membuka pembukuan</p>
      </div>
    </div>
    <?php if ($err): ?>
      <div class="text-xs font-bold text-rose-500 bg-rose-500/10 border border-rose-500/30 rounded-xl px-4 py-3"><i class="fa-solid fa-circle-exclamation mr-2"></i><?=e($err)?></div>
    <?php endif; ?>
    <form method="post" class="space-y-4">
      <div>
        <label class="block text-xs font-semibold mb-1.5">Username</label>
        <input type="text" name="username" required autofocus autocomplete="username" placeholder="Username" class="w-full text-sm glass-input rounded-xl p-3">
      </div>
      <div>
        <label class="block text-xs font-semibold mb-1.5">Password</label>
        <input type="password" name="password" required autocomplete="current-password" placeholder="Password" class="w-full text-sm glass-input rounded-xl p-3">
      </div>
      <button class="w-full bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-extrabold py-3 rounded-xl text-sm shadow-lg shadow-emerald-500/20 transition"><i class="fa-solid fa-right-to-bracket mr-2"></i>Masuk</button>
    </form>
    <p class="text-[11px] text-slate-500 dark:text-slate-400 text-center"><i class="fa-solid fa-lock mr-1"></i> Akses terbatas — sesi berakhir saat browser ditutup.</p>
  </div>
</body>
</html>

<?php
session_start();
if (!isset($_SESSION['admin'])) { header('Location: login.php'); exit; }
require __DIR__ . '/../config/db.php';

$adminId = (int)($_SESSION['admin_id'] ?? 0);
if ($adminId <= 0) {
    // fallback untuk session lama yg belum simpan admin_id
    $r = $conn->query("SELECT id FROM admin WHERE username = '" . $conn->real_escape_string($_SESSION['admin']) . "' LIMIT 1")->fetch_assoc();
    if ($r) { $adminId = (int)$r['id']; $_SESSION['admin_id'] = $adminId; }
}
$msg = ''; $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = $_POST['aksi'] ?? '';
    if ($aksi === 'username') {
        $baru = trim($_POST['username'] ?? '');
        if ($baru === '' || strlen($baru) < 3) $err = 'Username minimal 3 karakter.';
        else {
            $c = $conn->prepare('SELECT id FROM admin WHERE username = ? AND id <> ? LIMIT 1');
            $c->bind_param('si', $baru, $adminId);
            $c->execute();
            $c->store_result();
            if ($c->num_rows > 0) $err = "Username \"$baru\" sudah dipakai.";
            else {
                $u = $conn->prepare('UPDATE admin SET username = ? WHERE id = ?');
                $u->bind_param('si', $baru, $adminId);
                if ($u->execute()) { $_SESSION['admin'] = $baru; $msg = "Username diganti menjadi \"$baru\"."; }
                else $err = 'Gagal ganti username.';
                $u->close();
            }
            $c->close();
        }
    } elseif ($aksi === 'password') {
        $lama = $_POST['lama'] ?? '';
        $baru = $_POST['baru'] ?? '';
        $baru2 = $_POST['baru2'] ?? '';
        $s = $conn->prepare('SELECT password_hash FROM admin WHERE id = ? LIMIT 1');
        $s->bind_param('i', $adminId);
        $s->execute();
        $row = $s->get_result()->fetch_assoc();
        $s->close();
        if (!$row || !password_verify($lama, $row['password_hash'])) $err = 'Password lama salah.';
        elseif (strlen($baru) < 6) $err = 'Password baru minimal 6 karakter.';
        elseif ($baru !== $baru2) $err = 'Konfirmasi password tidak sama.';
        else {
            $hash = password_hash($baru, PASSWORD_DEFAULT);
            $u = $conn->prepare('UPDATE admin SET password_hash = ? WHERE id = ?');
            $u->bind_param('si', $hash, $adminId);
            $u->execute();
            $u->close();
            $msg = 'Password admin berhasil diubah.';
        }
    }
}
$cur = $conn->query("SELECT username FROM admin WHERE id = $adminId LIMIT 1")->fetch_assoc();
function e($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="id"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Pengaturan Akun Admin</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
<script src="https://unpkg.com/lucide@latest"></script>
<script src="../assets/tema.js"></script>
<link rel="stylesheet" href="../assets/tema.css">
<style>body{font-family:'Plus Jakarta Sans',sans-serif;background:#022c22;}</style>
</head>
<body class="text-slate-100 min-h-screen">
<div class="max-w-2xl mx-auto px-4 py-8 space-y-5">
<a href="dashboard.php" class="text-xs text-emerald-300">&larr; Kembali ke dashboard</a>
<div class="bg-slate-900/90 border border-emerald-700/50 rounded-3xl p-6 space-y-5">
<h2 class="font-extrabold text-amber-300 flex items-center gap-2"><i data-lucide="settings" class="w-5 h-5"></i> Pengaturan Akun Admin</h2>
<p class="text-xs text-slate-400">Username saat ini: <b class="font-mono text-emerald-300"><?= e($cur['username'] ?? '') ?></b></p>
<?php if ($msg): ?><div class="bg-emerald-900/70 border border-emerald-600 text-xs p-3 rounded-xl"><?= e($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="bg-red-950/60 border border-red-800 text-xs p-3 rounded-xl text-red-300"><?= e($err) ?></div><?php endif; ?>
<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
<form method="POST" class="bg-slate-950 border border-slate-800 rounded-2xl p-4 space-y-3 text-xs">
<input type="hidden" name="aksi" value="username">
<h4 class="font-bold text-emerald-300">Ganti Username</h4>
<div><label class="text-slate-400">Username Baru</label><input name="username" required minlength="3" maxlength="50" class="mt-1 w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 focus:border-amber-400 focus:outline-none"></div>
<button class="w-full py-2.5 bg-emerald-700 hover:bg-emerald-600 font-bold rounded-xl">Simpan Username</button>
</form>
<form method="POST" class="bg-slate-950 border border-slate-800 rounded-2xl p-4 space-y-3 text-xs">
<input type="hidden" name="aksi" value="password">
<h4 class="font-bold text-emerald-300">Ganti Password</h4>
<div><label class="text-slate-400">Password Lama</label><input type="password" name="lama" required class="mt-1 w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 focus:border-amber-400 focus:outline-none"></div>
<div><label class="text-slate-400">Password Baru (min 6)</label><input type="password" name="baru" required minlength="6" class="mt-1 w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 focus:border-amber-400 focus:outline-none"></div>
<div><label class="text-slate-400">Ulangi Baru</label><input type="password" name="baru2" required class="mt-1 w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 focus:border-amber-400 focus:outline-none"></div>
<button class="w-full py-2.5 bg-amber-400 hover:bg-amber-300 text-emerald-950 font-bold rounded-xl">Ubah Password</button>
</form>
</div>
</div>
</div>
<script>lucide.createIcons();</script>
</body></html>

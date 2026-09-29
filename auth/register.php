<?php
session_start();
require __DIR__ . '/../config/db.php';
if (isset($_SESSION['user_id'])) { header('Location: ../dashboard.php'); exit; }
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nim   = trim($_POST['nim'] ?? '');
    $nama  = trim($_POST['nama'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $p1    = $_POST['password'] ?? '';
    $p2    = $_POST['password2'] ?? '';
    if ($nim === '' || $nama === '' || $email === '' || $p1 === '') $error = 'Lengkapi semua field.';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $error = 'Email tidak valid.';
    elseif (strlen($p1) < 6) $error = 'Password minimal 6 karakter.';
    elseif ($p1 !== $p2) $error = 'Konfirmasi password tidak sama.';
    else {
        $c = $conn->prepare('SELECT id FROM users WHERE nim = ? LIMIT 1');
        $c->bind_param('s', $nim);
        $c->execute();
        $c->store_result();
        if ($c->num_rows > 0) $error = 'NIM sudah punya akun. Silakan login.';
        else {
            $hash = password_hash($p1, PASSWORD_DEFAULT);
            $s = $conn->prepare('INSERT INTO users (nim, nama, email, password_hash) VALUES (?,?,?,?)');
            $s->bind_param('ssss', $nim, $nama, $email, $hash);
            if ($s->execute()) {
                $_SESSION['user_id'] = $s->insert_id;
                $_SESSION['nim'] = $nim;
                $_SESSION['nama'] = $nama;
                header('Location: ../dashboard.php');
                exit;
            } else $error = 'Gagal daftar: ' . $s->error;
            $s->close();
        }
        $c->close();
    }
}
?>
<!DOCTYPE html>
<html lang="id"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Daftar Akun - UPT AIK UMGO</title>
<script src="https://cdn.tailwindcss.com"></script>
<script src="../assets/tema.js"></script>
<link rel="stylesheet" href="../assets/tema.css">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>body{font-family:'Plus Jakarta Sans',sans-serif;}</style>
</head>
<body class="min-h-screen flex items-center justify-center p-4">
<form method="POST" class="w-full max-w-md bg-white rounded-3xl shadow-2xl p-8 space-y-5 text-slate-800">
    <div class="flex items-center gap-3">
        <img src="../logo/logo-umgo.jpeg" alt="Logo UPT AIK UMGO" class="w-12 h-12 rounded-xl object-cover border border-slate-200 bg-white">
        <div>
            <h2 class="text-lg font-extrabold text-emerald-950">Daftar Akun</h2>
            <p class="text-xs text-slate-500">1 NIM = 1 akun mahasiswa</p>
        </div>
    </div>
    <?php if ($error): ?><div class="bg-red-50 border border-red-200 text-xs p-3 rounded-xl text-red-600"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <div class="space-y-3">
        <input name="nim" required value="<?= htmlspecialchars($_POST['nim'] ?? '') ?>" placeholder="NIM" class="w-full border border-slate-300 rounded-xl px-4 py-2.5 text-sm placeholder-slate-400 focus:border-emerald-700 focus:outline-none focus:ring-1 focus:ring-emerald-700">
        <input name="nama" required value="<?= htmlspecialchars($_POST['nama'] ?? '') ?>" placeholder="Nama lengkap" class="w-full border border-slate-300 rounded-xl px-4 py-2.5 text-sm placeholder-slate-400 focus:border-emerald-700 focus:outline-none focus:ring-1 focus:ring-emerald-700">
        <input type="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" placeholder="Email aktif" class="w-full border border-slate-300 rounded-xl px-4 py-2.5 text-sm placeholder-slate-400 focus:border-emerald-700 focus:outline-none focus:ring-1 focus:ring-emerald-700">
        <div class="grid grid-cols-2 gap-3">
            <input type="password" name="password" required minlength="6" placeholder="Password (min 6)" class="w-full border border-slate-300 rounded-xl px-4 py-2.5 text-sm placeholder-slate-400 focus:border-emerald-700 focus:outline-none focus:ring-1 focus:ring-emerald-700">
            <input type="password" name="password2" required minlength="6" placeholder="Ulangi password" class="w-full border border-slate-300 rounded-xl px-4 py-2.5 text-sm placeholder-slate-400 focus:border-emerald-700 focus:outline-none focus:ring-1 focus:ring-emerald-700">
        </div>
    </div>
    <button class="w-full py-3 bg-emerald-950 hover:opacity-90 text-white font-bold text-sm rounded-xl transition">Buat Akun</button>
    <p class="text-center text-xs text-slate-500">Sudah punya akun? <a href="../login.php" class="font-bold text-emerald-800">Login</a> &bull; <a href="../index.php" class="text-slate-400 hover:text-slate-600">Beranda</a></p>
</form>
</body></html>

<?php
session_start();
require __DIR__ . '/../config/db.php';
if (isset($_SESSION['user_id'])) { header('Location: ../dashboard.php'); exit; }
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nim = trim($_POST['nim'] ?? '');
    $p   = $_POST['password'] ?? '';
    $s = $conn->prepare('SELECT id, nim, nama, password_hash FROM users WHERE nim = ? LIMIT 1');
    $s->bind_param('s', $nim);
    $s->execute();
    $row = $s->get_result()->fetch_assoc();
    $s->close();
    if ($row && password_verify($p, $row['password_hash'])) {
        $_SESSION['user_id'] = $row['id'];
        $_SESSION['nim'] = $row['nim'];
        $_SESSION['nama'] = $row['nama'];
        header('Location: ../dashboard.php');
        exit;
    }
    $error = 'NIM atau password salah.';
}
?>
<!DOCTYPE html>
<html lang="id"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Masuk — UPT AIK UMGO</title>
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
            <h2 class="text-lg font-extrabold text-emerald-950">UPT AIK UMGO</h2>
            <p class="text-xs text-slate-500">Masuk untuk buka dashboard</p>
        </div>
    </div>
    <?php if ($error): ?><div class="bg-red-50 border border-red-200 text-xs p-3 rounded-xl text-red-600"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <div class="space-y-3">
        <input name="nim" required placeholder="NIM" class="w-full border border-slate-300 rounded-xl px-4 py-2.5 text-sm placeholder-slate-400 focus:border-emerald-700 focus:outline-none focus:ring-1 focus:ring-emerald-700">
        <input type="password" name="password" required placeholder="Password" class="w-full border border-slate-300 rounded-xl px-4 py-2.5 text-sm placeholder-slate-400 focus:border-emerald-700 focus:outline-none focus:ring-1 focus:ring-emerald-700">
    </div>
    <button class="w-full py-3 bg-emerald-950 hover:opacity-90 text-white font-bold text-sm rounded-xl transition">Masuk</button>
    <p class="text-center text-xs text-slate-500">Belum punya akun? <a href="register.php" class="font-bold text-emerald-800">Daftar</a> &bull; <a href="../index.php" class="text-slate-400 hover:text-slate-600">Beranda</a></p>
</form>
</body></html>

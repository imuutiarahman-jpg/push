<?php
session_start();
require __DIR__ . '/../config/db.php';
if (isset($_SESSION['admin'])) { header('Location: dashboard.php'); exit; }
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = trim($_POST['username'] ?? '');
    $p = $_POST['password'] ?? '';
    $stmt = $conn->prepare('SELECT id, username, password_hash FROM admin WHERE username = ? LIMIT 1');
    $stmt->bind_param('s', $u);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($row && password_verify($p, $row['password_hash'])) {
        $_SESSION['admin'] = $row['username'];
        $_SESSION['admin_id'] = $row['id'];
        header('Location: dashboard.php');
        exit;
    }
    $error = 'Username atau password salah.';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Masuk Admin — UPT AIK UMGO</title>
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
            <h2 class="text-lg font-extrabold text-emerald-950">Admin UPT AIK</h2>
            <p class="text-xs text-slate-500">Masuk untuk buka dashboard</p>
        </div>
    </div>
    <?php if ($error): ?><div class="bg-red-50 border border-red-200 text-xs p-3 rounded-xl text-red-600"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <div class="space-y-3">
        <input name="username" required placeholder="Username" class="w-full border border-slate-300 rounded-xl px-4 py-2.5 text-sm placeholder-slate-400 focus:border-emerald-700 focus:outline-none focus:ring-1 focus:ring-emerald-700">
        <input type="password" name="password" required placeholder="Password" class="w-full border border-slate-300 rounded-xl px-4 py-2.5 text-sm placeholder-slate-400 focus:border-emerald-700 focus:outline-none focus:ring-1 focus:ring-emerald-700">
    </div>
    <button class="w-full py-3 bg-emerald-950 hover:opacity-90 text-white font-bold text-sm rounded-xl transition">Masuk</button>
    <p class="text-center text-[11px] text-slate-500">Default: <b>admin / admin123</b> — segera ganti di Pengaturan.</p>
    <a href="../index.php" class="block text-center text-xs text-slate-400 hover:text-slate-600">&larr; Kembali ke beranda</a>
</form>
</body>
</html>

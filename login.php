<?php
session_start();
require __DIR__ . '/config/db.php';
if (isset($_SESSION['user_id'])) { header('Location: index.php'); exit; }
if (isset($_SESSION['admin'])) { header('Location: admin/dashboard.php'); exit; }
if (isset($_SESSION['penguji_id'])) { header('Location: penguji/dashboard.php'); exit; }

$tab = $_GET['tab'] ?? 'user';
if (!in_array($tab, ['user','penguji','admin'], true)) $tab = 'user';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $role = $_POST['role'] ?? 'user';
    if ($role === 'admin') {
        $tab = 'admin';
        $u = trim($_POST['username'] ?? '');
        $p = $_POST['password'] ?? '';
        $s = $conn->prepare('SELECT id, username, password_hash FROM admin WHERE username = ? LIMIT 1');
        $s->bind_param('s', $u);
        $s->execute();
        $row = $s->get_result()->fetch_assoc();
        $s->close();
        if ($row && password_verify($p, $row['password_hash'])) {
            $_SESSION['admin'] = $row['username'];
            $_SESSION['admin_id'] = $row['id'];
            header('Location: admin/dashboard.php');
            exit;
        }
        $error = 'Username atau password admin salah.';
    } elseif ($role === 'penguji') {
        $tab = 'penguji';
        $u = trim($_POST['username'] ?? '');
        $p = $_POST['password'] ?? '';
        $s = $conn->prepare('SELECT id, nama, username, password_hash, aktif FROM penguji WHERE username = ? LIMIT 1');
        $s->bind_param('s', $u);
        $s->execute();
        $row = $s->get_result()->fetch_assoc();
        $s->close();
        if (!$row) {
            $error = 'Username penguji tidak ditemukan. Hubungi admin UPT AIK.';
        } elseif (!(int)$row['aktif']) {
            $error = 'Akun penguji dinonaktifkan. Hubungi admin.';
        } elseif (empty($row['password_hash']) || !password_verify($p, $row['password_hash'])) {
            $error = 'Username atau password penguji salah.';
        } else {
            $_SESSION['penguji_id'] = (int)$row['id'];
            $_SESSION['penguji_nama'] = $row['nama'];
            $_SESSION['penguji_username'] = $row['username'];
            header('Location: penguji/dashboard.php');
            exit;
        }
    } else {
        $tab = 'user';
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
            header('Location: index.php');
            exit;
        }
        $error = 'NIM atau password salah. Belum punya akun? Daftar dulu.';
    }
}
?>
<!DOCTYPE html>
<html lang="id"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Masuk — UPT AIK UMGO</title>
<script src="https://cdn.tailwindcss.com"></script>
<script src="assets/tema.js"></script>
<link rel="stylesheet" href="assets/tema.css">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>body{font-family:'Plus Jakarta Sans',sans-serif;}</style>
</head>
<body class="min-h-screen flex flex-col items-center justify-center p-4 gap-5 py-10">
<div class="w-full max-w-md bg-white rounded-3xl shadow-2xl p-8 space-y-5 text-slate-800">
    <div class="flex items-center gap-3">
        <img src="logo/logo-umgo.jpeg" alt="Logo UPT AIK UMGO" class="w-12 h-12 rounded-xl object-cover border border-slate-200 bg-white">
        <div>
            <h2 class="text-lg font-extrabold text-emerald-950">UPT AIK UMGO</h2>
            <p class="text-xs text-slate-500">Masuk untuk buka dashboard</p>
        </div>
    </div>

    <div class="grid grid-cols-3 gap-1 bg-slate-100 rounded-xl p-1">
        <button type="button" onclick="pilihTab('user')" id="tabbtn-user" class="py-2 rounded-lg text-xs font-bold">User</button>
        <button type="button" onclick="pilihTab('penguji')" id="tabbtn-penguji" class="py-2 rounded-lg text-xs font-bold">Penguji</button>
        <button type="button" onclick="pilihTab('admin')" id="tabbtn-admin" class="py-2 rounded-lg text-xs font-bold">Admin</button>
    </div>

    <?php if ($error): ?><div class="bg-red-50 border border-red-200 text-xs p-3 rounded-xl text-red-600"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <!-- USER -->
    <form method="POST" id="form-user" class="space-y-3 <?= $tab === 'user' ? '' : 'hidden' ?>">
        <input type="hidden" name="role" value="user">
        <input name="nim" required value="<?= htmlspecialchars($_POST['nim'] ?? '') ?>" placeholder="NIM" class="w-full border border-slate-300 rounded-xl px-4 py-2.5 text-sm placeholder-slate-400 focus:border-emerald-700 focus:outline-none focus:ring-1 focus:ring-emerald-700">
        <input type="password" name="password" required placeholder="Password" class="w-full border border-slate-300 rounded-xl px-4 py-2.5 text-sm placeholder-slate-400 focus:border-emerald-700 focus:outline-none focus:ring-1 focus:ring-emerald-700">
        <button class="w-full py-3 bg-emerald-950 hover:opacity-90 text-white font-bold text-sm rounded-xl transition">Masuk</button>
        <p class="text-center text-xs text-slate-500">Belum punya akun? <a href="auth/register.php" class="font-bold text-emerald-800">Daftar</a></p>
    </form>

    <!-- PENGUJI -->
    <form method="POST" id="form-penguji" class="space-y-3 <?= $tab === 'penguji' ? '' : 'hidden' ?>">
        <input type="hidden" name="role" value="penguji">
        <input name="username" required placeholder="Username penguji" class="w-full border border-slate-300 rounded-xl px-4 py-2.5 text-sm placeholder-slate-400 focus:border-emerald-700 focus:outline-none focus:ring-1 focus:ring-emerald-700">
        <input type="password" name="password" required placeholder="Password" class="w-full border border-slate-300 rounded-xl px-4 py-2.5 text-sm placeholder-slate-400 focus:border-emerald-700 focus:outline-none focus:ring-1 focus:ring-emerald-700">
        <button class="w-full py-3 bg-emerald-950 hover:opacity-90 text-white font-bold text-sm rounded-xl transition">Masuk</button>
        <p class="text-center text-[11px] text-slate-500">Akun penguji dibuat admin via Kelola Penguji.</p>
    </form>

    <!-- ADMIN -->
    <form method="POST" id="form-admin" class="space-y-3 <?= $tab === 'admin' ? '' : 'hidden' ?>">
        <input type="hidden" name="role" value="admin">
        <input name="username" required placeholder="Username admin" class="w-full border border-slate-300 rounded-xl px-4 py-2.5 text-sm placeholder-slate-400 focus:border-emerald-700 focus:outline-none focus:ring-1 focus:ring-emerald-700">
        <input type="password" name="password" required placeholder="Password" class="w-full border border-slate-300 rounded-xl px-4 py-2.5 text-sm placeholder-slate-400 focus:border-emerald-700 focus:outline-none focus:ring-1 focus:ring-emerald-700">
        <button class="w-full py-3 bg-emerald-950 hover:opacity-90 text-white font-bold text-sm rounded-xl transition">Masuk</button>
        <p class="text-center text-[11px] text-slate-500">Default: <b>admin / admin123</b> — segera ganti di Pengaturan.</p>
    </form>

    <a href="#cek-status" class="block text-center text-xs text-slate-400 hover:text-slate-600">Cek Status Pendaftaran &bull; FAQ</a>
</div>

<div id="cek-status" class="w-full max-w-md bg-white rounded-3xl shadow-2xl p-8 space-y-4 text-slate-800">
    <div class="text-center space-y-1">
        <h3 class="text-base font-extrabold text-emerald-950">Cek Status Pendaftaran</h3>
        <p class="text-xs text-slate-500">Tanpa login — cukup NIM atau nomor registrasi.</p>
    </div>
    <div class="flex gap-2">
        <input type="text" id="search-query" placeholder="cth: 2024010045" class="flex-1 border border-slate-300 rounded-xl px-4 py-2.5 text-sm placeholder-slate-400 focus:border-emerald-700 focus:outline-none focus:ring-1 focus:ring-emerald-700">
        <button type="button" onclick="searchStatus()" class="px-5 py-2.5 bg-emerald-950 hover:opacity-90 text-white font-bold text-xs rounded-xl transition">Cari</button>
    </div>
    <div id="status-result" class="hidden text-xs"></div>
</div>

<div class="w-full max-w-md bg-white rounded-3xl shadow-2xl p-8 space-y-3 text-slate-800">
    <h3 class="text-base font-extrabold text-emerald-950 text-center">Informasi &amp; FAQ</h3>
    <div class="space-y-2 text-xs">
        <div class="bg-slate-50 border border-slate-200 rounded-xl p-3"><p class="font-bold text-slate-800">Siapa wajib ikut Ujian BTQ?</p><p class="text-slate-500 mt-0.5">Seluruh mahasiswa aktif UMGO sebagai syarat KKD, Proposal/Skripsi.</p></div>
        <div class="bg-slate-50 border border-slate-200 rounded-xl p-3"><p class="font-bold text-slate-800">Materi penilaian?</p><p class="text-slate-500 mt-0.5">(1) Tajwid &amp; makhraj, (2) kelancaran &amp; waqaf, (3) adab membaca Al-Qur'an.</p></div>
        <div class="bg-slate-50 border border-slate-200 rounded-xl p-3"><p class="font-bold text-slate-800">Kapan dan di mana ujiannya?</p><p class="text-slate-500 mt-0.5">Jadwal, waktu, dan tempat tampil di tiket masing-masing setelah daftar.</p></div>
        <div class="bg-slate-50 border border-slate-200 rounded-xl p-3"><p class="font-bold text-slate-800">Tata tertib?</p><p class="text-slate-500 mt-0.5">Pria: kemeja rapi + celana kain. Wanita: busana muslimah syar'i + jilbab. Bawa mushaf masing-masing.</p></div>
    </div>
</div>
<script>
function pilihTab(t){
    document.getElementById('form-user').classList.toggle('hidden', t !== 'user');
    document.getElementById('form-penguji').classList.toggle('hidden', t !== 'penguji');
    document.getElementById('form-admin').classList.toggle('hidden', t !== 'admin');
    const on = 'py-2 rounded-lg text-xs font-bold bg-emerald-950 text-white transition';
    const off = 'py-2 rounded-lg text-xs font-bold text-slate-500 hover:text-slate-800 transition';
    document.getElementById('tabbtn-user').className = t === 'user' ? on : off;
    document.getElementById('tabbtn-penguji').className = t === 'penguji' ? on : off;
    document.getElementById('tabbtn-admin').className = t === 'admin' ? on : off;
    const u = new URL(window.location.href); u.searchParams.set('tab', t); window.history.replaceState({}, '', u);
}
async function searchStatus(){
    const input = document.getElementById('search-query');
    const box = document.getElementById('status-result');
    if(!input || !box) return;
    const q = input.value.trim();
    box.classList.remove('hidden');
    if(!q){ box.innerHTML = '<p class="text-red-500 text-center">Masukkan NIM / No. Registrasi.</p>'; return; }
    box.innerHTML = '<p class="text-slate-400 text-center">Mencari...</p>';
    try{
        const r = await fetch('api_cek_status.php?q=' + encodeURIComponent(q));
        const d = await r.json();
        if(!d.found){ box.innerHTML = '<div class="bg-red-50 border border-red-200 p-4 rounded-xl text-center"><p class="font-bold text-red-600">Data Tidak Ditemukan</p><p class="text-slate-500">Periksa kembali NIM / No. Registrasi.</p></div>'; return; }
        const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
        let badge = '<span class="px-3 py-1 bg-amber-100 text-amber-700 border border-amber-300 rounded-full font-bold">MENUNGGU VERIFIKASI</span>';
        if(d.data.status === 'TERVERIFIKASI') badge = '<span class="px-3 py-1 bg-emerald-100 text-emerald-700 border border-emerald-300 rounded-full font-bold">TERVERIFIKASI</span>';
        if(d.data.status === 'DITOLAK') badge = '<span class="px-3 py-1 bg-red-100 text-red-600 border border-red-200 rounded-full font-bold">DITOLAK</span>';
        box.innerHTML = '<div class="bg-slate-50 border border-slate-200 p-4 rounded-xl space-y-2">'
            + '<div class="flex items-center justify-between gap-2"><span class="font-mono font-bold text-emerald-800">' + esc(d.data.reg_no) + '</span>' + badge + '</div>'
            + '<div class="grid grid-cols-2 gap-2 text-slate-600"><div>Nama:<b class="block text-slate-800">' + esc(d.data.nama) + '</b></div><div>NIM:<b class="block text-slate-800">' + esc(d.data.nim) + '</b></div><div>Prodi:<b class="block text-slate-800">' + esc(d.data.prodi) + '</b></div><div>Ujian:<b class="block text-slate-800">' + esc(d.data.kategori) + '</b></div><div>Jalur:<b class="block text-emerald-800">' + esc(d.data.jalur || 'Reguler') + '</b></div><div>Penguji:<b class="block text-emerald-800">' + esc(d.data.penguji || '-') + '</b></div></div>'
            + '<div class="pt-1 text-right"><a href="tiket.php?reg=' + encodeURIComponent(d.data.reg_no) + '" class="inline-block px-4 py-2 bg-emerald-950 text-white font-bold rounded-xl text-xs">Lihat Tiket</a></div></div>';
    }catch(e){ box.innerHTML = '<p class="text-red-500 text-center">Gagal mengambil data.</p>'; }
}
pilihTab(<?= json_encode($tab) ?>);
</script>
</body></html>

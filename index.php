<?php
session_start();
$isLogin = isset($_SESSION['user_id']);
// Opsi A: tamu langsung ke halaman login. Cek Status & FAQ publik pindah ke login.php.
if (!$isLogin) { header('Location: login.php'); exit; }
$sesNim = $_SESSION['nim'] ?? '';
$sesNama = $_SESSION['nama'] ?? '';
$sesEmail = '';
require_once __DIR__ . '/config/db.php';
if ($isLogin) {
    $q = $conn->prepare('SELECT nim, nama, email, created_at FROM users WHERE id = ? LIMIT 1');    $q->bind_param('i', $_SESSION['user_id']);
    $q->execute();
    $qr = $q->get_result()->fetch_assoc();
    if ($qr) { $sesNim = $qr['nim']; $sesNama = $qr['nama']; $sesEmail = $qr['email']; $sesSince = $qr['created_at']; }
    $q->close();
    $_SESSION['nim'] = $sesNim; $_SESSION['nama'] = $sesNama;
    $uid = (int)$_SESSION['user_id'];
    $rs = $conn->prepare('SELECT p.*, g.nama AS penguji_nama FROM pendaftar p LEFT JOIN penguji g ON g.id = p.penguji_id WHERE p.user_id = ? ORDER BY p.created_at DESC');
    $rs->bind_param('i', $uid);
    $rs->execute();
    $myRows = $rs->get_result()->fetch_all(MYSQLI_ASSOC);
    $rs->close();
    // Petakan hasil ujian per pendaftar agar dashboard bisa tampilkan tombol Hasil PDF
    $hasilMap = [];
    if ($myRows) {
        $ids = array_map(fn($x) => (int)$x['id'], $myRows);
        $in = implode(',', $ids);
        $hq = $conn->query("SELECT * FROM hasil_ujian WHERE pendaftar_id IN ($in)");
        if ($hq) while ($hh = $hq->fetch_assoc()) $hasilMap[(int)$hh['pendaftar_id']] = $hh;
    }
    // Daftar penguji aktif + hitungan kuota per penguji untuk dropdown user
    $pengujiList = $conn->query("SELECT g.id, g.nama, COALESCE(g.kuota,5) AS kuota, (SELECT COUNT(*) FROM pendaftar p WHERE p.penguji_id = g.id) AS terisi FROM penguji g WHERE g.aktif = 1 ORDER BY g.nama")->fetch_all(MYSQLI_ASSOC);
} else {
    $sesSince = '';
    $myRows = [];
    require_once __DIR__ . '/config/db.php';
    $pengujiList = $conn->query("SELECT g.id, g.nama, COALESCE(g.kuota,5) AS kuota, (SELECT COUNT(*) FROM pendaftar p WHERE p.penguji_id = g.id) AS terisi FROM penguji g WHERE g.aktif = 1 ORDER BY g.nama")->fetch_all(MYSQLI_ASSOC);
}
function e_row($s){ return htmlspecialchars($s ?? '-', ENT_QUOTES, 'UTF-8'); }
// Jadwal aktif (ditentukan admin via Kelola Jadwal & Tempat)
$HARI_ID = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
$BULAN_ID = [1=>'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
function tgl_id_fmt($ymd){
    global $HARI_ID, $BULAN_ID;
    $t = strtotime($ymd);
    if (!$t) return $ymd;
    return $HARI_ID[(int)date('w', $t)] . ', ' . (int)date('j', $t) . ' ' . $BULAN_ID[(int)date('n', $t)] . ' ' . date('Y', $t);
}
$jadwalList = [];
$jq = $conn->query("SELECT * FROM jadwal WHERE aktif = 1 ORDER BY tanggal, id");
if ($jq) $jadwalList = $jq->fetch_all(MYSQLI_ASSOC);
// Fakultas + prodi aktif (dikelola admin via Kelola Fakultas & Prodi)
$fakultasList = [];
$fq = $conn->query("SELECT * FROM fakultas WHERE aktif = 1 ORDER BY kode");
if ($fq) $fakultasList = $fq->fetch_all(MYSQLI_ASSOC);
$prodiMap = [];
$pq = $conn->query("SELECT pr.nama, f.kode FROM prodi pr JOIN fakultas f ON f.id = pr.fakultas_id WHERE pr.aktif = 1 AND f.aktif = 1 ORDER BY f.kode, pr.nama");
if ($pq) while ($pp = $pq->fetch_assoc()) $prodiMap[$pp['kode']][] = $pp['nama'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pendaftaran Ujian BQ - UPT AIK UMGO</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Amiri:ital,wght@0,400;0,700;1,400&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="assets/tema.js"></script>
    <link rel="stylesheet" href="assets/tema.css">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #022c22;
            background-image: radial-gradient(#047857 0.8px, transparent 0.8px), radial-gradient(#047857 0.8px, #022c22 0.8px);
            background-size: 32px 32px; background-position: 0 0, 16px 16px; }
        .font-arabic { font-family: 'Amiri', serif; }
    </style>
</head>
<body class="text-slate-100 min-h-screen flex flex-col justify-between selection:bg-amber-400 selection:text-emerald-950">

<header class="sticky top-0 z-40 bg-emerald-950/90 backdrop-blur-md border-b border-emerald-700/50 shadow-xl">
    <div class="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8 h-16 sm:h-20 flex items-center justify-between gap-2">
        <div class="flex items-center gap-2.5 sm:gap-3.5 min-w-0 cursor-pointer" onclick="switchTab('register')">
            <div class="shrink-0">
                <img src="logo/logo-umgo.jpeg" alt="Logo UPT AIK UMGO" class="w-10 h-10 sm:w-12 sm:h-12 rounded-2xl object-cover shadow-lg border border-amber-300 bg-white">
            </div>
            <div class="min-w-0">
                <h1 class="text-base sm:text-lg font-bold tracking-tight text-amber-300 leading-tight truncate">UPT AIK UMGO</h1>
                <p class="hidden sm:block text-xs text-emerald-200/90 font-medium">UPT AIK Dan Berasrama &bull; Universitas Muhammadiyah Gorontalo</p>
            </div>
        </div>
        <div class="hidden md:flex items-center gap-3">
        <nav class="flex items-center gap-1.5 bg-emerald-900/60 border border-emerald-700/50 p-1.5 rounded-2xl">
            <button type="button" onclick="switchTab('register')" id="nav-register" class="px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-2 bg-amber-400 text-emerald-950 shadow-md">
                <i data-lucide="pen-tool" class="w-4 h-4"></i> Pendaftaran Baca Qur'an
            </button>
            <button type="button" onclick="switchTab('status')" id="nav-status" class="px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-2 text-emerald-200 hover:text-white hover:bg-emerald-800/50">
                <i data-lucide="search" class="w-4 h-4"></i> Cek Status Registrasi
            </button>
            <button type="button" onclick="switchTab('faq')" id="nav-faq" class="px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-2 text-emerald-200 hover:text-white hover:bg-emerald-800/50">
                <i data-lucide="help-circle" class="w-4 h-4"></i> Informasi &amp; FAQ
            </button>
            <?php if ($isLogin): ?>
            <button type="button" onclick="switchTab('akun')" id="nav-akun" class="px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-2 text-emerald-200 hover:text-white hover:bg-emerald-800/50"><i data-lucide="user" class="w-4 h-4"></i> <?= htmlspecialchars($sesNim) ?></button>
            <a href="auth/logout.php" class="px-3 py-2 rounded-xl text-xs font-bold text-red-300 hover:bg-red-950">Logout</a>
            <?php else: ?>
            <a href="login.php" class="px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-2 text-emerald-200 hover:text-white hover:bg-emerald-800/50"><i data-lucide="log-in" class="w-4 h-4"></i> Login</a>
            <a href="auth/register.php" class="px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-2 bg-emerald-700 text-white"><i data-lucide="user-plus" class="w-4 h-4"></i> Daftar Akun</a>
            <?php endif; ?>
        </nav>
        <div class="hidden md:flex items-center gap-1 text-[11px] font-semibold">
            <a href="login.php?tab=penguji" class="px-3 py-2 rounded-xl text-amber-300/80 hover:text-amber-300 hover:bg-emerald-900/60 border border-transparent hover:border-emerald-700/50 transition flex items-center gap-1.5" title="Portal Penguji"><i data-lucide="book-open-check" class="w-3.5 h-3.5"></i> Penguji</a>
            <span class="text-emerald-800">•</span>
            <a href="admin/login.php" class="px-3 py-2 rounded-xl text-emerald-200/60 hover:text-white hover:bg-emerald-900/60 transition flex items-center gap-1.5" title="Portal Admin"><i data-lucide="shield-check" class="w-3.5 h-3.5"></i> Admin</a>
        </div>
        </div>
        <button onclick="toggleMobileMenu()" class="md:hidden shrink-0 p-2 text-emerald-200 bg-emerald-900/60 rounded-xl border border-emerald-700/50">
            <i data-lucide="menu" class="w-6 h-6"></i>
        </button>
    </div>
    <div id="mobile-menu" class="hidden md:hidden bg-emerald-950 border-b border-emerald-800 px-4 py-4 space-y-2">
        <button onclick="switchTab('register');toggleMobileMenu();" class="w-full text-left px-4 py-2.5 rounded-xl text-sm font-semibold flex items-center gap-2 bg-amber-400 text-emerald-950"><i data-lucide="pen-tool" class="w-4 h-4"></i> Pendaftaran BQ</button>
        <button onclick="switchTab('status');toggleMobileMenu();" class="w-full text-left px-4 py-2.5 rounded-xl text-sm font-semibold flex items-center gap-2 text-emerald-100 bg-emerald-900/40"><i data-lucide="search" class="w-4 h-4"></i> Cek Status</button>
        <button onclick="switchTab('faq');toggleMobileMenu();" class="w-full text-left px-4 py-2.5 rounded-xl text-sm font-semibold flex items-center gap-2 text-emerald-100 bg-emerald-900/40"><i data-lucide="help-circle" class="w-4 h-4"></i> Informasi &amp; FAQ</button>
        <?php if ($isLogin): ?>
        <button onclick="switchTab('akun');toggleMobileMenu();" class="w-full text-left px-4 py-2.5 rounded-xl text-sm font-semibold flex items-center gap-2 text-white bg-emerald-700"><i data-lucide="user" class="w-4 h-4"></i> Akun Saya (<?= htmlspecialchars($sesNim) ?>)</button>
        <a href="auth/logout.php" class="w-full text-left px-4 py-2.5 rounded-xl text-sm font-semibold flex items-center gap-2 text-red-300 bg-red-950/40"><i data-lucide="log-out" class="w-4 h-4"></i> Logout</a>
        <?php else: ?>
        <a href="login.php" class="w-full text-left px-4 py-2.5 rounded-xl text-sm font-semibold flex items-center gap-2 text-emerald-100 bg-emerald-900/40"><i data-lucide="log-in" class="w-4 h-4"></i> Login Mahasiswa</a>
        <a href="auth/register.php" class="w-full text-left px-4 py-2.5 rounded-xl text-sm font-semibold flex items-center gap-2 text-white bg-emerald-700"><i data-lucide="user-plus" class="w-4 h-4"></i> Daftar Akun</a>
        <?php endif; ?>
        <p class="text-[10px] font-bold uppercase tracking-wider text-emerald-200/50 px-4 pt-2">Portal Lain</p>
        <a href="login.php?tab=penguji" class="w-full text-left px-4 py-2.5 rounded-xl text-sm font-semibold flex items-center gap-2 text-amber-200/80 bg-emerald-900/20"><i data-lucide="book-open-check" class="w-4 h-4"></i> Portal Penguji</a>
        <a href="admin/login.php" class="w-full text-left px-4 py-2.5 rounded-xl text-sm font-semibold flex items-center gap-2 text-emerald-100/60 bg-emerald-900/20"><i data-lucide="shield-check" class="w-4 h-4"></i> Portal Admin</a>
    </div>
</header>

<main class="max-w-7xl w-full mx-auto px-3 sm:px-6 lg:px-8 py-5 sm:py-8 flex-1 space-y-5 sm:space-y-8">

<?php if (isset($_GET['success'])): ?>
<div class="bg-emerald-900/80 border border-emerald-500/50 rounded-2xl p-4 text-sm flex items-center gap-2">
    <i data-lucide="check-circle-2" class="w-5 h-5 text-emerald-400"></i>
    <?= htmlspecialchars($_GET['success']) ?>
    <?php if (isset($_GET['reg'])): ?>
    <a href="tiket.php?reg=<?= urlencode($_GET['reg']) ?>" class="ml-auto px-4 py-2 bg-amber-400 text-emerald-950 font-bold text-xs rounded-xl">Lihat Tiket <?= htmlspecialchars($_GET['reg']) ?></a>
    <?php endif; ?>
</div>
<?php endif; ?>
<?php if (isset($_GET['error'])): ?>
<div class="bg-red-950/60 border border-red-800 rounded-2xl p-4 text-sm flex items-center gap-2">
    <i data-lucide="alert-circle" class="w-5 h-5 text-red-400"></i> <?= htmlspecialchars($_GET['error']) ?>
</div>
<?php endif; ?>

<!-- TAB REGISTER -->
<section id="tab-register" class="tab-content space-y-8">
    <div class="bg-gradient-to-r from-emerald-950 via-emerald-900 to-teal-950 border border-emerald-600/50 rounded-3xl p-5 sm:p-8 text-white shadow-2xl relative overflow-hidden">
        <div class="absolute -right-12 -bottom-16 opacity-10 font-arabic text-[8rem] sm:text-[14rem] pointer-events-none select-none">اقرأ</div>
        <div class="relative z-10 max-w-3xl space-y-3">
            <span class="inline-flex items-center gap-2 px-3.5 py-1.5 bg-amber-400/20 border border-amber-400/40 text-amber-300 text-xs font-bold rounded-full">
                <i data-lucide="sparkles" class="w-3.5 h-3.5"></i> Tahun Akademik 2026/2027 &bull; UPT AIK UMGO
            </span>
            <h2 class="text-xl sm:text-4xl font-black tracking-tight leading-tight">Pendaftaran Online Ujian Baca Al-Qur'an (BQ) &amp; Sertifikat AIK</h2>
            <p class="text-emerald-200/90 text-xs sm:text-sm leading-relaxed">Ujian BQ merupakan salah satu syarat KKD, kelulusan dan pengajuan Proposal/Skripsi bagi seluruh mahasiswa UMGO.</p>
            <?php if (!$isLogin): ?>
            <div class="flex flex-wrap gap-2.5 pt-1">
                <a href="auth/register.php" class="px-5 py-2.5 bg-amber-400 hover:bg-amber-300 text-emerald-950 font-bold text-xs sm:text-sm rounded-xl shadow-lg flex items-center gap-2 transition"><i data-lucide="user-plus" class="w-4 h-4"></i> Buat Akun Dulu</a>
                <a href="login.php" class="px-5 py-2.5 bg-emerald-700/80 hover:bg-emerald-600 border border-emerald-600 text-white font-bold text-xs sm:text-sm rounded-xl flex items-center gap-2 transition"><i data-lucide="log-in" class="w-4 h-4"></i> Login</a>
                <button type="button" onclick="switchTab('status')" class="px-5 py-2.5 bg-transparent hover:bg-emerald-800/50 border border-emerald-600/60 text-emerald-100 font-bold text-xs sm:text-sm rounded-xl flex items-center gap-2 transition"><i data-lucide="search" class="w-4 h-4"></i> Cek Status</button>
            </div>
            <div class="flex flex-wrap items-center gap-x-5 gap-y-1.5 pt-2 text-[11px] sm:text-xs text-emerald-200/80 font-medium">
                <span class="flex items-center gap-1.5"><span class="w-5 h-5 rounded-full bg-amber-400/20 border border-amber-400/50 text-amber-300 flex items-center justify-center text-[10px] font-black">1</span> Buat akun</span>
                <span class="flex items-center gap-1.5"><span class="w-5 h-5 rounded-full bg-amber-400/20 border border-amber-400/50 text-amber-300 flex items-center justify-center text-[10px] font-black">2</span> Login NIM</span>
                <span class="flex items-center gap-1.5"><span class="w-5 h-5 rounded-full bg-amber-400/20 border border-amber-400/50 text-amber-300 flex items-center justify-center text-[10px] font-black">3</span> Isi formulir</span>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="bg-slate-900/90 border border-emerald-700/50 rounded-3xl p-6 sm:p-8 shadow-2xl backdrop-blur-md">
        <div class="border-b border-slate-800 pb-5 mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
            <div>
                <h3 class="text-lg font-bold text-amber-300 flex items-center gap-2"><i data-lucide="clipboard-signature" class="w-5 h-5 text-emerald-400"></i> Formulir Registrasi Ujian</h3>
                <p class="text-xs text-slate-400 mt-0.5">Isi data sesuai KTP / KTM aktif.</p>
            </div>
            <span class="text-xs text-emerald-400 font-mono bg-emerald-950 px-3 py-1 rounded-full border border-emerald-800">* Wajib Diisi</span>
        </div>

        <?php if (!$isLogin): ?>
        <div class="text-center space-y-5 py-8 px-4">
            <img src="logo/logo-umgo.jpeg" alt="Logo UPT AIK UMGO" class="w-20 h-20 rounded-2xl object-cover mx-auto shadow-lg border border-amber-300 bg-white">
            <div class="space-y-2">
                <p class="text-xs text-emerald-200/90 font-medium">Assalamu'alaikum warahmatullahi wabarakatuh</p>
                <h4 class="text-xl sm:text-2xl font-extrabold text-amber-300">Selamat Datang di Pendaftaran BQ</h4>
                <p class="text-xs sm:text-sm text-slate-400 max-w-xl mx-auto leading-relaxed">UPT AIK &amp; Berasrama &bull; Universitas Muhammadiyah Gorontalo. Untuk mengisi formulir pendaftaran, silakan <b class="text-amber-300">login dengan NIM</b> terlebih dahulu — atau buat akun baru jika belum punya.</p>
            </div>
            <div class="flex flex-wrap items-center justify-center gap-x-6 gap-y-2 text-[11px] sm:text-xs text-emerald-200/80 font-medium">
                <span class="flex items-center gap-1.5"><span class="w-5 h-5 rounded-full bg-amber-400/20 border border-amber-400/50 text-amber-300 flex items-center justify-center text-[10px] font-black">1</span> Buat akun</span>
                <span class="flex items-center gap-1.5"><span class="w-5 h-5 rounded-full bg-amber-400/20 border border-amber-400/50 text-amber-300 flex items-center justify-center text-[10px] font-black">2</span> Login NIM</span>
                <span class="flex items-center gap-1.5"><span class="w-5 h-5 rounded-full bg-amber-400/20 border border-amber-400/50 text-amber-300 flex items-center justify-center text-[10px] font-black">3</span> Isi formulir</span>
            </div>
            <div class="flex flex-wrap justify-center gap-2.5 pt-1">
                <a href="login.php" class="px-6 py-2.5 bg-amber-400 hover:bg-amber-300 text-emerald-950 font-bold text-xs sm:text-sm rounded-xl transition flex items-center gap-2"><i data-lucide="log-in" class="w-4 h-4"></i> Login</a>
                <a href="auth/register.php" class="px-6 py-2.5 bg-emerald-700 hover:bg-emerald-600 text-white font-bold text-xs sm:text-sm rounded-xl transition flex items-center gap-2"><i data-lucide="user-plus" class="w-4 h-4"></i> Buat Akun</a>
                <button type="button" onclick="switchTab('status')" class="px-6 py-2.5 bg-transparent hover:bg-emerald-800/50 border border-emerald-600/60 text-emerald-100 font-bold text-xs sm:text-sm rounded-xl transition flex items-center gap-2"><i data-lucide="search" class="w-4 h-4"></i> Cek Status</button>
            </div>
        </div>
        <?php else: ?>
        <form action="proses_daftar.php" method="POST" enctype="multipart/form-data" class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-2">Nama Lengkap *</label>
                    <input type="text" name="nama" required maxlength="100" value="<?= htmlspecialchars($sesNama) ?>" placeholder="Contoh: Muhammad Fadhil" class="w-full bg-slate-950 border border-slate-700 rounded-xl px-4 py-2.5 text-sm focus:border-amber-400 focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-2">NIM * (terkunci ke akun)</label>
                    <input type="text" name="nim" required maxlength="30" value="<?= htmlspecialchars($sesNim) ?>" <?= $isLogin ? 'readonly' : '' ?> placeholder="Contoh: 2024010045" class="w-full bg-slate-950 border border-slate-700 rounded-xl px-4 py-2.5 text-sm focus:border-amber-400 focus:outline-none">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-2">Fakultas *</label>
                    <select name="fakultas" id="reg-fakultas" onchange="updateProdiDropdown()" required class="w-full bg-slate-950 border border-slate-700 rounded-xl px-4 py-2.5 text-sm focus:border-amber-400 focus:outline-none">
                        <?php if (!$fakultasList): ?>
                        <option value="">Belum ada fakultas aktif — hubungi admin</option>
                        <?php else: ?>
                        <?php foreach ($fakultasList as $fl): ?>
                        <option value="<?= e_row($fl['kode']) ?>"><?= e_row($fl['label']) ?></option>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-2">Program Studi *</label>
                    <select name="prodi" id="reg-prodi" required class="w-full bg-slate-950 border border-slate-700 rounded-xl px-4 py-2.5 text-sm focus:border-amber-400 focus:outline-none"></select>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="md:col-span-1 grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-2">Ganjil / Genap *</label>
                        <select name="semester_tipe" id="reg-sem-tipe" onchange="updateSemesterTingkat()" required class="w-full bg-slate-950 border border-slate-700 rounded-xl px-4 py-2.5 text-sm focus:border-amber-400 focus:outline-none">
                            <option value="Ganjil">Ganjil</option>
                            <option value="Genap">Genap</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-2">Semester *</label>
                        <select name="semester_tingkat" id="reg-sem-tingkat" required class="w-full bg-slate-950 border border-slate-700 rounded-xl px-4 py-2.5 text-sm focus:border-amber-400 focus:outline-none"></select>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-2">No. WhatsApp *</label>
                    <input type="tel" name="hp" required maxlength="20" placeholder="0812xxxxxxxx" class="w-full bg-slate-950 border border-slate-700 rounded-xl px-4 py-2.5 text-sm focus:border-amber-400 focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-2">Email Aktif *</label>
                    <input type="email" name="email" required maxlength="100" value="<?= htmlspecialchars($sesEmail) ?>" placeholder="mahasiswa@umgo.ac.id" class="w-full bg-slate-950 border border-slate-700 rounded-xl px-4 py-2.5 text-sm focus:border-amber-400 focus:outline-none">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-2">Kategori Ujian *</label>
                    <select name="kategori" required class="w-full bg-slate-950 border border-slate-700 rounded-xl px-4 py-2.5 text-sm focus:border-amber-400 focus:outline-none">
                        <option>Ujian BQ Reguler Mahasiswa</option>
                        <option>KKD (Kuliah Kerja Dakwah)</option>
                        <option>Sertifikasi AIK 2 (Ibadah &amp; Muamalah)</option>
                        <option>Ujian BQ Susulan / Perbaikan (Remedial)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-2">Jalur Mahasiswa *</label>
                    <select name="jalur" required class="w-full bg-slate-950 border border-slate-700 rounded-xl px-4 py-2.5 text-sm focus:border-amber-400 focus:outline-none">
                        <option value="Reguler">Reguler (Standar lulus 80)</option>
                        <option value="NonReg">NonReg (Standar lulus 60)</option>
                        <option value="S2">S2 (Standar lulus 60)</option>
                        <option value="RPL">RPL (Standar lulus 60)</option>
                    </select>
                    <p class="text-[11px] mt-1.5 text-slate-400">Reguler lulus jika nilai ≥ 80. NonReg / S2 / RPL lulus jika nilai ≥ 60.</p>
                </div>
            </div>

            <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-2">Sesi / Gelombang *</label>
                    <?php if (!$jadwalList): ?>
                    <p class="text-xs text-red-400 bg-red-950/40 border border-red-800 rounded-xl p-3">Belum ada jadwal aktif. Hubungi admin UPT AIK.</p>
                    <?php else: ?>
                    <select name="jadwal_id" required class="w-full bg-slate-950 border border-slate-700 rounded-xl px-4 py-2.5 text-sm focus:border-amber-400 focus:outline-none">
                        <option value="">-- Pilih Sesi / Gelombang --</option>
                        <?php foreach ($jadwalList as $jw): ?>
                        <option value="<?= (int)$jw['id'] ?>"><?= e_row($jw['nama']) ?> - <?= e_row(tgl_id_fmt($jw['tanggal'])) ?> (<?= e_row($jw['jam']) ?>) • <?= e_row($jw['tempat']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php endif; ?>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-300 mb-2">Pilih Penguji * (kuota ditentukan admin)</label>
                <?php if (!$pengujiList): ?>
                <p class="text-xs text-red-400 bg-red-950/40 border border-red-800 rounded-xl p-3">Belum ada penguji aktif. Hubungi admin UPT AIK.</p>
                <?php else: ?>
                <select name="penguji_id" id="reg-penguji" onchange="cekPenguji()" required class="w-full bg-slate-950 border border-slate-700 rounded-xl px-4 py-2.5 text-sm focus:border-amber-400 focus:outline-none">
                    <option value="">-- Pilih Penguji --</option>
                    <?php foreach ($pengujiList as $pg): ?>
                    <?php $kuotaPg = max(1,(int)($pg['kuota'] ?? 5)); $penuh = (int)$pg['terisi'] >= $kuotaPg; ?>
                    <option value="<?= (int)$pg['id'] ?>" data-terisi="<?= (int)$pg['terisi'] ?>" data-kuota="<?= $kuotaPg ?>" <?= $penuh ? 'disabled' : '' ?>>
                        <?= htmlspecialchars($pg['nama']) ?> <?= $penuh ? "(Penuh {$pg['terisi']}/$kuotaPg)" : "({$pg['terisi']}/$kuotaPg)" ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <p id="penguji-info" class="text-[11px] mt-1.5 text-slate-400">Kuota tiap penguji ditentukan admin.</p>
                <?php endif; ?>
            </div>

            <div class="bg-slate-950 border border-slate-800 p-4 rounded-2xl space-y-2">
                <label class="block text-xs font-semibold text-slate-300">Unggah Bukti Pembayaran (PDF/JPG/PNG, maks 2MB) *</label>
                <div onclick="document.getElementById('reg-file').click()" class="border-2 border-dashed border-slate-700 hover:border-emerald-500 rounded-xl p-4 text-center cursor-pointer transition bg-slate-900/40">
                    <i data-lucide="upload-cloud" class="w-8 h-8 mx-auto text-emerald-400 mb-1"></i>
                    <p class="text-xs text-slate-300 font-medium">Klik untuk upload berkas</p>
                    <p class="text-[10px] text-slate-500">Maksimal 2 MB (.pdf, .jpg, .png)</p>
                    <span id="file-name-label" class="hidden mt-2 text-xs font-bold text-amber-300 bg-amber-950/60 px-3 py-1 rounded-full border border-amber-800"></span>
                </div>
                <input type="file" name="berkas" id="reg-file" class="hidden" accept=".pdf,.jpg,.jpeg,.png" required onchange="document.getElementById('file-name-label').textContent='File: '+this.files[0].name;document.getElementById('file-name-label').classList.remove('hidden')">
            </div>

            <div class="pt-4 flex items-center justify-end gap-4 border-t border-slate-800">
                <button type="reset" class="px-5 py-2.5 bg-slate-800 hover:bg-slate-700 text-xs font-semibold rounded-xl border border-slate-600">Reset</button>
                <button type="submit" class="px-7 py-3 bg-gradient-to-r from-emerald-600 to-emerald-500 text-white font-bold text-xs sm:text-sm rounded-xl shadow-lg flex items-center gap-2"><i data-lucide="send" class="w-4 h-4 text-amber-300"></i> Kirim Pendaftaran</button>
            </div>
        </form>
        <?php endif; ?>
    </div>
</section>

<!-- TAB STATUS (pencarian + riwayat di bawah) -->
<section id="tab-status" class="tab-content hidden space-y-6">
    <div class="bg-slate-900/90 border border-emerald-700/50 rounded-3xl p-6 sm:p-8 shadow-2xl max-w-2xl mx-auto space-y-6">
        <div class="text-center space-y-2">
            <div class="w-12 h-12 rounded-full bg-emerald-950 border border-emerald-600 mx-auto flex items-center justify-center text-amber-400"><i data-lucide="search" class="w-6 h-6"></i></div>
            <h3 class="text-xl font-bold text-amber-300">Cek Status Pendaftaran BQ</h3>
            <p class="text-xs text-slate-400">Masukkan NIM atau Nomor Registrasi. <span class="text-emerald-300 font-semibold">Bisa tanpa login.</span></p>
        </div>
        <div class="flex flex-col sm:flex-row gap-3">
            <input type="text" id="search-query" placeholder="cth: 2024010045" class="flex-1 bg-slate-950 border border-slate-700 rounded-xl px-4 py-3 text-sm focus:border-amber-400 focus:outline-none">
            <button onclick="searchStatus()" class="px-6 py-3 bg-amber-400 hover:bg-amber-300 text-emerald-950 font-bold text-xs rounded-xl flex items-center justify-center gap-2"><i data-lucide="search" class="w-4 h-4"></i> Cari Data</button>
        </div>
        <div id="status-result" class="hidden pt-4 border-t border-slate-800 text-xs"></div>
    </div>

    <?php if ($isLogin): ?>
    <div class="bg-slate-900/90 border border-emerald-700/50 rounded-3xl p-6 sm:p-8 shadow-2xl max-w-2xl mx-auto space-y-4">
        <h3 class="font-bold text-amber-300 flex items-center gap-2"><i data-lucide="ticket" class="w-5 h-5"></i> Riwayat Pendaftaran Saya (<?= count($myRows) ?>)</h3>
        <?php if (!$myRows): ?>
        <p class="text-xs text-slate-400">Belum ada pendaftaran. <button onclick="switchTab('register')" class="text-amber-300 font-bold">Daftar ujian sekarang &rarr;</button></p>
        <?php endif; ?>
        <div class="space-y-3">
        <?php foreach ($myRows as $r): ?>
        <?php $b='bg-amber-950 text-amber-400 border-amber-800'; if($r['status']==='TERVERIFIKASI')$b='bg-emerald-950 text-emerald-400 border-emerald-800'; if($r['status']==='DITOLAK')$b='bg-red-950 text-red-400 border-red-800'; ?>
        <?php $hh = $hasilMap[(int)$r['id']] ?? null; ?>
        <div class="bg-slate-950 border border-slate-800 rounded-2xl p-4 text-xs flex flex-col sm:flex-row sm:items-center gap-3">
            <div class="flex-1">
                <span class="font-mono font-bold text-amber-400 text-[11px]"><?= e_row($r['reg_no']) ?></span>
                <p class="font-semibold text-sm text-slate-100"><?= e_row($r['kategori']) ?> &bull; <span class="text-amber-300"><?= e_row($r['jalur'] ?? 'Reguler') ?></span></p>
                <p class="text-slate-400"><?= e_row($r['gelombang']) ?></p>
                <p class="text-emerald-300">Penguji: <?= e_row($r['penguji_nama'] ?? '-') ?></p>
                <?php if ($hh): ?>
                <p class="mt-1.5 inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full border text-[10px] font-bold <?= ($hh['status_lulus']==='LULUS') ? 'bg-emerald-950 text-emerald-300 border-emerald-700' : 'bg-red-950 text-red-300 border-red-800' ?>">
                    Nilai: <?= e_row($hh['total']) ?> (<?= e_row($hh['grade']) ?>) &bull; <?= e_row($hh['status_lulus']) ?>
                </p>
                <?php else: ?>
                <p class="mt-1.5 text-[11px] text-slate-500 italic">Hasil belum keluar — menunggu penilaian penguji.</p>
                <?php endif; ?>
            </div>
            <span class="px-2.5 py-1 rounded-full border text-[10px] font-bold self-start sm:self-center <?= $b ?>"><?= e_row($r['status']) ?></span>
            <div class="flex sm:flex-col gap-2">
                <a href="tiket.php?reg=<?= urlencode($r['reg_no']) ?>" class="px-4 py-2 bg-emerald-700 hover:bg-emerald-600 rounded-xl font-bold text-center">Tiket</a>
                <?php if ($hh): ?>
                <a href="hasil.php?reg=<?= urlencode($r['reg_no']) ?>" class="px-4 py-2 bg-amber-400 hover:bg-amber-300 text-emerald-950 rounded-xl font-bold text-center flex items-center justify-center gap-1.5"><i data-lucide="file-text" class="w-3.5 h-3.5"></i> Hasil PDF</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</section>

<!-- TAB AKUN (menu sendiri via klik NIM) -->
<section id="tab-akun" class="tab-content hidden space-y-6">
    <?php if ($isLogin): ?>
    <!-- Panel Akun Saya: informasi + pengaturan -->
    <div class="bg-slate-900/90 border border-emerald-700/50 rounded-3xl p-6 sm:p-8 shadow-2xl max-w-2xl mx-auto space-y-6">
        <div class="flex items-center gap-4 border-b border-slate-800 pb-4">
            <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-amber-400 to-amber-600 text-emerald-950 flex items-center justify-center font-extrabold text-xl"><?= strtoupper(substr($sesNama,0,1)) ?></div>
            <div class="flex-1">
                <h3 class="font-bold text-amber-300 flex items-center gap-2"><i data-lucide="user-cog" class="w-5 h-5"></i> Akun Saya</h3>
                <p class="text-xs text-slate-300 font-semibold"><?= e_row($sesNama) ?> <span class="font-mono text-emerald-300">(<?= e_row($sesNim) ?>)</span></p>
                <p class="text-[11px] text-slate-500"><?= e_row($sesEmail) ?> &bull; Terdaftar <?= e_row($sesSince ?? '') ?></p>
            </div>
            <a href="auth/logout.php" class="px-3 py-2 text-[11px] font-bold bg-red-950 border border-red-800 text-red-300 rounded-xl">Logout</a>
        </div>

        <?php if (isset($_GET['akun_msg'])): ?><div class="bg-emerald-900/70 border border-emerald-600 text-xs p-3 rounded-xl"><?= htmlspecialchars($_GET['akun_msg']) ?></div><?php endif; ?>
        <?php if (isset($_GET['akun_err'])): ?><div class="bg-red-950/60 border border-red-800 text-xs p-3 rounded-xl text-red-300"><?= htmlspecialchars($_GET['akun_err']) ?></div><?php endif; ?>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <!-- Edit profil -->
            <form action="akun_update.php" method="POST" class="bg-slate-950 border border-slate-800 rounded-2xl p-4 space-y-3 text-xs">
                <h4 class="font-bold text-emerald-300 flex items-center gap-1.5"><i data-lucide="settings-2" class="w-4 h-4"></i> Pengaturan Profil</h4>
                <input type="hidden" name="aksi" value="profil">
                <div><label class="text-slate-400">Nama Lengkap</label><input name="nama" required maxlength="100" value="<?= e_row($sesNama) ?>" class="mt-1 w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 focus:border-amber-400 focus:outline-none"></div>
                <div><label class="text-slate-400">Email</label><input type="email" name="email" required maxlength="100" value="<?= e_row($sesEmail) ?>" class="mt-1 w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 focus:border-amber-400 focus:outline-none"></div>
                <div><label class="text-slate-400">NIM (tidak bisa diubah)</label><input value="<?= e_row($sesNim) ?>" disabled class="mt-1 w-full bg-slate-900/50 border border-slate-800 rounded-xl px-3 py-2 text-slate-500"></div>
                <button class="w-full py-2.5 bg-emerald-700 hover:bg-emerald-600 font-bold rounded-xl">Simpan Profil</button>
            </form>
            <!-- Ganti password -->
            <form action="akun_update.php" method="POST" class="bg-slate-950 border border-slate-800 rounded-2xl p-4 space-y-3 text-xs">
                <h4 class="font-bold text-emerald-300 flex items-center gap-1.5"><i data-lucide="key-round" class="w-4 h-4"></i> Ganti Password</h4>
                <input type="hidden" name="aksi" value="password">
                <div><label class="text-slate-400">Password Lama</label><input type="password" name="lama" required class="mt-1 w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 focus:border-amber-400 focus:outline-none"></div>
                <div><label class="text-slate-400">Password Baru (min 6)</label><input type="password" name="baru" required minlength="6" class="mt-1 w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 focus:border-amber-400 focus:outline-none"></div>
                <div><label class="text-slate-400">Ulangi Baru</label><input type="password" name="baru2" required class="mt-1 w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 focus:border-amber-400 focus:outline-none"></div>
                <button class="w-full py-2.5 bg-amber-400 hover:bg-amber-300 text-emerald-950 font-bold rounded-xl">Ubah Password</button>
            </form>
        </div>
    </div>
    <?php else: ?>
    <div class="bg-slate-900/90 border border-emerald-700/50 rounded-3xl p-8 max-w-2xl mx-auto text-center text-sm space-y-3">
        <p class="text-slate-300">Silakan login dulu untuk mengelola akun.</p>
        <div class="flex justify-center gap-2"><a href="login.php" class="px-4 py-2 bg-amber-400 text-emerald-950 font-bold text-xs rounded-xl">Login</a><a href="auth/register.php" class="px-4 py-2 bg-emerald-700 text-white font-bold text-xs rounded-xl">Buat Akun</a></div>
    </div>
    <?php endif; ?>
</section>

<!-- TAB FAQ -->
<section id="tab-faq" class="tab-content hidden space-y-6">
    <div class="bg-slate-900/90 border border-emerald-700/50 rounded-3xl p-6 sm:p-8 shadow-2xl space-y-6">
        <h3 class="text-xl font-bold text-amber-300 flex items-center gap-2"><i data-lucide="help-circle" class="w-6 h-6 text-emerald-400"></i> Panduan &amp; FAQ</h3>
        <div class="space-y-4 text-xs sm:text-sm">
            <div class="bg-slate-950 border border-slate-800 rounded-2xl p-4"><h4 class="font-bold text-emerald-300">Siapa wajib ikut Ujian BQ?</h4><p class="text-slate-300 mt-1">Seluruh mahasiswa aktif UMGO jenjang S1/S2 sebagai syarat KKD, Proposal/Skripsi.</p></div>
            <div class="bg-slate-950 border border-slate-800 rounded-2xl p-4"><h4 class="font-bold text-emerald-300">Materi penilaian?</h4><p class="text-slate-300 mt-1">(1) Kelancaran bacaan, (2) Tajwid &amp; makharijul huruf, (3) Adab membaca Al-Qur'an.</p></div>
            <div class="bg-slate-950 border border-slate-800 rounded-2xl p-4"><h4 class="font-bold text-emerald-300">Kapan dan di mana ujiannya?</h4><p class="text-slate-300 mt-1">Jadwal, waktu, dan tempat ditentukan admin dan tampil di tiket masing-masing (tab Cek Status → Lihat Tiket). Hadir 10 menit sebelum sesi.</p></div>
            <div class="bg-slate-950 border border-slate-800 rounded-2xl p-4"><h4 class="font-bold text-emerald-300">Tata tertib?</h4><p class="text-slate-300 mt-1">Pria: kemeja rapi + celana kain. Wanita: busana muslimah syar'i + jilbab. Bawa mushaf masing-masing.</p></div>
        </div>
    </div>
</section>
</main>

<footer class="bg-slate-950 border-t border-slate-800 text-slate-400 text-xs py-6 text-center space-y-2">
    <p class="font-semibold text-emerald-400">UPT AIK Dan Berasrama &bull; Universitas Muhammadiyah Gorontalo</p>
    <p class="text-[11px] text-slate-500">Jl. Prof. Dr. Mansoer Pateda, Pentadio Timur, Telaga Biru, Gorontalo</p>
    <p class="text-[11px] text-slate-500 pt-1">
        <a href="login.php?tab=penguji" class="hover:text-amber-300 transition">Portal Penguji</a>
        <span class="text-slate-700"> &bull; </span>
        <a href="admin/login.php" class="hover:text-amber-300 transition">Portal Admin</a>
    </p>
</footer>

<script>
const prodiData = <?= json_encode($prodiMap ?: new stdClass(), JSON_UNESCAPED_UNICODE) ?>;
function updateProdiDropdown(){
    const fak = document.getElementById('reg-fakultas').value;
    const sel = document.getElementById('reg-prodi'); sel.innerHTML='';
    const list = prodiData[fak] || [];
    if (!list.length) { const o=document.createElement('option'); o.value=''; o.textContent='Belum ada prodi aktif — hubungi admin'; sel.appendChild(o); return; }
    list.forEach(p=>{ const o=document.createElement('option'); o.value=p; o.textContent=p; sel.appendChild(o); });
}
function updateSemesterTingkat(){
    const tipe = document.getElementById('reg-sem-tipe').value;
    const sel = document.getElementById('reg-sem-tingkat'); sel.innerHTML='';
    const list = tipe === 'Ganjil' ? ['1','3','5','7','> 8'] : ['2','4','6','8','> 8'];
    list.forEach(n=>{
        const label = n === '> 8' ? 'Semester > 8' : 'Semester ' + n;
        const o = document.createElement('option');
        o.value = n; o.textContent = label;
        sel.appendChild(o);
    });
}
function cekPenguji(){
    const sel = document.getElementById('reg-penguji');
    const info = document.getElementById('penguji-info');
    if(!sel || !info) return;
    const opt = sel.options[sel.selectedIndex];
    if(!opt || !opt.value){ info.textContent = 'Kuota tiap penguji ditentukan admin.'; info.className='text-[11px] mt-1.5 text-slate-400'; return; }
    const terisi = parseInt(opt.dataset.terisi || '0', 10);
    const kuota = parseInt(opt.dataset.kuota || '5', 10);
    const sisa = kuota - terisi;
    if(sisa <= 0){ info.textContent = 'Penguji ini sudah penuh ('+terisi+'/'+kuota+'). Pilih penguji lain.'; info.className='text-[11px] mt-1.5 text-red-400 font-bold'; }
    else if(sisa <= 1){ info.textContent = 'Sisa kuota tinggal 1 peserta. Segera kirim pendaftaran.'; info.className='text-[11px] mt-1.5 text-amber-300 font-bold'; }
    else { info.textContent = 'Sisa kuota: ' + sisa + ' peserta.'; info.className='text-[11px] mt-1.5 text-emerald-300'; }
}
function switchTab(n){
    document.querySelectorAll('.tab-content').forEach(e=>e.classList.add('hidden'));
    document.getElementById('tab-'+n).classList.remove('hidden');
    ['register','status','faq','akun'].forEach(k=>{
        const b=document.getElementById('nav-'+k);
        if(b) b.className="px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-2 text-emerald-200 hover:text-white hover:bg-emerald-800/50";
    });
    const a=document.getElementById('nav-'+n);
    if(a) a.className="px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-2 bg-amber-400 text-emerald-950 shadow-md";
}
function toggleMobileMenu(){ document.getElementById('mobile-menu').classList.toggle('hidden'); }
async function searchStatus(){
    const input=document.getElementById('search-query');
    const box=document.getElementById('status-result');
    if(!input || !box) return;
    const q=input.value.trim();
    box.classList.remove('hidden');
    if(!q){ box.innerHTML='<p class="text-red-400 text-center">Masukkan NIM / No. Registrasi.</p>'; return; }
    box.innerHTML='<p class="text-slate-400 text-center">Mencari...</p>';
    try{
        const r=await fetch('api_cek_status.php?q='+encodeURIComponent(q));
        const d=await r.json();
        if(!d.found){ box.innerHTML='<div class="bg-red-950/40 border border-red-800/60 p-4 rounded-2xl text-center"><h4 class="font-bold text-red-300">Data Tidak Ditemukan</h4><p class="text-slate-400">Periksa kembali NIM / No. Registrasi.</p></div>'; return; }
        const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
        let badge='<span class="px-3 py-1 bg-amber-950 text-amber-400 border border-amber-800 rounded-full font-bold">MENUNGGU VERIFIKASI</span>';
        if(d.data.status==='TERVERIFIKASI') badge='<span class="px-3 py-1 bg-emerald-950 text-emerald-400 border border-emerald-800 rounded-full font-bold">TERVERIFIKASI</span>';
        if(d.data.status==='DITOLAK') badge='<span class="px-3 py-1 bg-red-950 text-red-400 border border-red-800 rounded-full font-bold">DITOLAK</span>';
        box.innerHTML=`<div class="bg-slate-950 border border-slate-800 p-5 rounded-2xl space-y-3">
            <div class="flex items-center justify-between border-b border-slate-800 pb-3"><span class="font-mono font-bold text-amber-400">${esc(d.data.reg_no)}</span>${badge}</div>
            <div class="grid grid-cols-2 gap-2"><div>Nama:<b class="block text-slate-200">${esc(d.data.nama)}</b></div><div>NIM:<b class="block text-slate-200">${esc(d.data.nim)}</b></div><div>Prodi:<b class="block text-slate-200">${esc(d.data.prodi)}</b></div><div>Ujian:<b class="block text-slate-200">${esc(d.data.kategori)}</b></div><div>Jalur:<b class="block text-amber-300">${esc(d.data.jalur || 'Reguler')}</b></div><div>Penguji:<b class="block text-emerald-300">${esc(d.data.penguji || '-')}</b></div></div>
            ${d.hasil ? `<div class="rounded-xl border px-3 py-2 text-center font-bold ${d.hasil.status_lulus==='LULUS' ? 'bg-emerald-950 text-emerald-300 border-emerald-700' : 'bg-red-950 text-red-300 border-red-800'}">Nilai: ${esc(d.hasil.total)} (${esc(d.hasil.grade)}) &bull; ${esc(d.hasil.status_lulus)}</div>` : `<p class="text-center text-slate-500 italic">Hasil belum keluar — menunggu penilaian penguji.</p>`}
            <div class="pt-2 border-t border-slate-800 flex justify-end gap-2"><a href="tiket.php?reg=${encodeURIComponent(d.data.reg_no)}" class="px-4 py-2 bg-emerald-700 text-white font-bold rounded-xl text-xs">Lihat Tiket</a>${d.hasil ? `<a href="hasil.php?reg=${encodeURIComponent(d.data.reg_no)}" class="px-4 py-2 bg-amber-400 text-emerald-950 font-bold rounded-xl text-xs">Hasil PDF</a>` : ``}</div></div>`;
    }catch(e){ box.innerHTML='<p class="text-red-400 text-center">Gagal mengambil data.</p>'; }
}
window.onload=function(){
    lucide.createIcons();
    if(document.getElementById('reg-fakultas')) updateProdiDropdown();
    if(document.getElementById('reg-sem-tipe')) updateSemesterTingkat();
    const params = new URLSearchParams(window.location.search);
    const tab = params.get('tab');
    if (tab === 'status' || params.get('success')) switchTab('status');
    else if (tab === 'akun' || params.get('akun_msg') || params.get('akun_err')) switchTab('akun');
};
</script>
</body>
</html>

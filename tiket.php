<?php
require __DIR__ . '/config/db.php';
$reg = trim($_GET['reg'] ?? '');
if ($reg === '') { header('Location: index.php'); exit; }
$stmt = $conn->prepare('SELECT p.*, g.nama AS penguji_nama FROM pendaftar p LEFT JOIN penguji g ON g.id = p.penguji_id WHERE p.reg_no = ? LIMIT 1');
$stmt->bind_param('s', $reg);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$data) { header('Location: index.php?error=' . urlencode('Tiket tidak ditemukan.')); exit; }
function e($s){ return htmlspecialchars($s ?? '-', ENT_QUOTES, 'UTF-8'); }
$statusClass = 'bg-amber-950 text-amber-400 border-amber-800';
$statusText = 'MENUNGGU VERIFIKASI';
if ($data['status'] === 'TERVERIFIKASI') { $statusClass = 'bg-emerald-950 text-emerald-400 border-emerald-800'; $statusText = 'TERVERIFIKASI'; }
if ($data['status'] === 'DITOLAK') { $statusClass = 'bg-red-950 text-red-400 border-red-800'; $statusText = 'DITOLAK'; }
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Tiket <?= e($data['reg_no']) ?> - UPT AIK UMGO</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
<script src="https://unpkg.com/lucide@latest"></script>
<script src="assets/tema.js"></script>
<link rel="stylesheet" href="assets/tema.css">
<style>
body{font-family:'Plus Jakarta Sans',sans-serif;background:#022c22;}
@media print{ body{background:#fff !important;} .no-print{display:none !important;} #ticket{box-shadow:none !important;border:1px solid #000 !important;} }
</style>
</head>
<body class="text-slate-100 min-h-screen flex items-center justify-center p-4">
<div id="ticket" class="bg-slate-900 border border-emerald-500/50 rounded-3xl max-w-xl w-full p-6 shadow-2xl space-y-5">
    <div class="text-center border-b-2 border-dashed border-emerald-700/60 pb-4">
        <img src="logo/logo-umgo.jpeg" alt="Logo UPT AIK UMGO" class="w-14 h-14 rounded-full object-cover mx-auto mb-2 border-2 border-amber-400 bg-white">
        <h3 class="font-extrabold text-amber-300 uppercase">Kartu Tiket Ujian BTQ</h3>
        <p class="text-[11px] text-emerald-200">UPT AIK Dan Berasrama &bull; Universitas Muhammadiyah Gorontalo</p>
    </div>
    <div class="bg-slate-950 border border-slate-800 p-3 rounded-2xl flex items-center justify-between">
        <div><span class="text-[10px] text-slate-400 block uppercase font-bold">Nomor Registrasi</span><span class="font-mono font-black text-amber-400"><?= e($data['reg_no']) ?></span></div>
        <span class="px-2.5 py-1 text-[10px] font-bold rounded-full border <?= $statusClass ?>"><?= $statusText ?></span>
    </div>
    <div class="grid grid-cols-2 gap-3 text-xs">
        <div><span class="text-slate-400 block text-[10px]">Nama</span><b><?= e($data['nama']) ?></b></div>
        <div><span class="text-slate-400 block text-[10px]">NIM</span><b><?= e($data['nim']) ?></b></div>
        <div><span class="text-slate-400 block text-[10px]">Fakultas / Prodi</span><b><?= e($data['fakultas']) ?> / <?= e($data['prodi']) ?></b></div>
        <div><span class="text-slate-400 block text-[10px]">Jenis Ujian</span><b><?= e($data['kategori']) ?></b></div>
        <div><span class="text-slate-400 block text-[10px]">Jalur Mahasiswa</span><b><?= e($data['jalur'] ?? 'Reguler') ?></b></div>
        <div class="col-span-2"><span class="text-slate-400 block text-[10px]">Penguji</span><b><?= e($data['penguji_nama'] ?? '-') ?></b></div>
    </div>
    <div class="bg-emerald-950/80 border border-emerald-700/60 p-3.5 rounded-2xl text-xs">
        <b class="text-emerald-300 block">Jadwal, Waktu &amp; Tempat (ditentukan admin):</b>
        <p class="font-semibold text-white"><?= e($data['gelombang']) ?></p>
        <p class="text-[11px] text-emerald-200 italic">Hadir 10 menit sebelum sesi dimulai.</p>
    </div>
    <div class="text-[10px] text-slate-400 bg-slate-950 p-3 rounded-2xl border border-slate-800">
        <b class="text-slate-200">Catatan:</b> 1. Tunjukkan kartu ini ke penguji. 2. Busana muslim/muslimah syar'i &amp; bawa mushaf.
    </div>
    <div class="no-print flex justify-end gap-3 border-t border-slate-800 pt-4">
        <a href="index.php" class="px-4 py-2 bg-slate-800 rounded-xl text-xs font-semibold border border-slate-600">Kembali</a>
        <button onclick="window.print()" class="px-5 py-2.5 bg-amber-400 text-emerald-950 font-bold text-xs rounded-xl">Cetak / PDF</button>
    </div>
</div>
<script>lucide.createIcons();</script>
</body>
</html>

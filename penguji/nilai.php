<?php
session_start();
if (!isset($_SESSION['penguji_id'])) { header('Location: login.php'); exit; }
require __DIR__ . '/../config/db.php';
$pengujiId = (int)$_SESSION['penguji_id'];
$pengujiNamaSess = $_SESSION['penguji_nama'] ?? '';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { header('Location: dashboard.php'); exit; }

function e($s){ return htmlspecialchars($s ?? '-', ENT_QUOTES, 'UTF-8'); }
function clampScore($v){ $v = (int)$v; if ($v < 0) $v = 0; if ($v > 100) $v = 100; return $v; }
function ambangLulus($jalur){ $j = trim($jalur ?? 'Reguler'); return ($j === '' || strcasecmp($j,'Reguler') === 0) ? 80 : 60; }
function hitungNilai($t,$k,$a,$jalur='Reguler'){
    $total = $t*0.40 + $k*0.35 + $a*0.25;
    if ($total >= 85) { $grade='A'; $label='Mumtaz / Sangat Memuaskan'; }
    elseif ($total >= 75) { $grade='B'; $label='Jayyid Jiddan / Memuaskan'; }
    elseif ($total >= 65) { $grade='C'; $label='Jayyid / Cukup'; }
    else { $grade='D'; $label='Rasib / Kurang'; }
    $status = ($total >= ambangLulus($jalur)) ? 'LULUS' : 'TIDAK LULUS';
    return [$total,$grade,$status . ' (' . $label . ')'];
}

$msg = $_GET['msg'] ?? '';
$err = '';

// --- Ambil data pendaftar milik penguji ini saja (identitas ujian = data yang sudah mendaftar) ---
$stmt = $conn->prepare('SELECT p.*, g.nama AS penguji_nama FROM pendaftar p LEFT JOIN penguji g ON g.id = p.penguji_id WHERE p.id = ? AND p.penguji_id = ? LIMIT 1');
$stmt->bind_param('ii', $id, $pengujiId);
$stmt->execute();
$r = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$r) { header('Location: dashboard.php?msg=' . urlencode('Data tidak ditemukan / bukan peserta Anda.')); exit; }
$jalurPeserta = $r['jalur'] ?? 'Reguler';
$ambangPeserta = ambangLulus($jalurPeserta);

// --- Simpan nilai (POST) --- 3 komponen: Tajwid 40%, Kelancaran 35%, Adab 25%
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tajwid    = clampScore($_POST['tajwid'] ?? 0);
    $kelancaran= clampScore($_POST['kelancaran'] ?? 0);
    $adab      = clampScore($_POST['adab'] ?? 0);
    $hafalan   = 0; // point 4 dihapus, kolom DB dipertahankan = 0
    $catatan   = trim($_POST['catatan'] ?? '');
    $bahan     = trim($_POST['bahan_ayat'] ?? '');
    $tgl       = trim($_POST['tanggal_ujian'] ?? '');
    if ($tgl === '') $tgl = date('Y-m-d');
    $pengujiNama = $pengujiNamaSess; // kunci ke akun login, tidak bisa diganti manual

    [$total,$grade,$predikat] = hitungNilai($tajwid,$kelancaran,$adab,$jalurPeserta);
    $statusLulus = ($total >= $ambangPeserta) ? 'LULUS' : 'TIDAK LULUS';

    $stmt = $conn->prepare('INSERT INTO hasil_ujian (pendaftar_id, tajwid, kelancaran, adab, hafalan, total, grade, predikat, status_lulus, catatan, bahan_ayat, penguji_nama, tanggal_ujian) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE tajwid=VALUES(tajwid), kelancaran=VALUES(kelancaran), adab=VALUES(adab), hafalan=VALUES(hafalan), total=VALUES(total), grade=VALUES(grade), predikat=VALUES(predikat), status_lulus=VALUES(status_lulus), catatan=VALUES(catatan), bahan_ayat=VALUES(bahan_ayat), penguji_nama=VALUES(penguji_nama), tanggal_ujian=VALUES(tanggal_ujian)');
    $stmt->bind_param('iiiiidsssssss', $id, $tajwid, $kelancaran, $adab, $hafalan, $total, $grade, $predikat, $statusLulus, $catatan, $bahan, $pengujiNama, $tgl);
    if ($stmt->execute()) {
        header("Location: nilai.php?id=$id&msg=" . urlencode("Nilai tersimpan: $total ($grade - $statusLulus)."));
        exit;
    } else {
        $err = 'Gagal menyimpan: ' . $stmt->error;
    }
    $stmt->close();
}

// --- Ambil nilai tersimpan (jika ada) ---
$h = $conn->query("SELECT * FROM hasil_ujian WHERE pendaftar_id = $id LIMIT 1")->fetch_assoc();
$tajwid = (int)($h['tajwid'] ?? 80);
$kelancaran = (int)($h['kelancaran'] ?? 75);
$adab = (int)($h['adab'] ?? 85);
$catatanVal = $h['catatan'] ?? '';
$bahanVal = $h['bahan_ayat'] ?? 'Al-Fatihah (1): ayat 1-7';
$tglVal = $h['tanggal_ujian'] ?? date('Y-m-d');
[$totalHit,$gradeHit,$predikatHit] = hitungNilai($tajwid,$kelancaran,$adab,$jalurPeserta);
$isLulus = ($totalHit >= $ambangPeserta);
$docNo = 'UMGO/LPK-AIK/BQ/' . date('Y') . '/' . str_pad((string)$id, 4, '0', STR_PAD_LEFT);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Nilai BTQ <?= e($r['reg_no']) ?> - <?= e($r['nama']) ?></title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Amiri:ital,wght@0,400;0,700;1,400&family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
<script src="https://unpkg.com/lucide@latest"></script>
<script src="../assets/tema.js"></script>
<link rel="stylesheet" href="../assets/tema.css">
<style>
body{font-family:'Plus Jakarta Sans',sans-serif;background:#022c22;background-image:radial-gradient(#047857 0.8px,transparent 0.8px),radial-gradient(#047857 0.8px,#022c22 0.8px);background-size:32px 32px;background-position:0 0,16px 16px;}
.font-arabic{font-family:'Amiri',serif;}
.bahan-ayah{padding:8px 4px 6px;border-bottom:1px dashed rgba(148,163,184,.18);}
.bahan-ayah:last-child{border-bottom:none;}
.bahan-arab-line{direction:rtl;text-align:center;font-size:20px;line-height:2.2;color:#fcd34d;}
.ayah-mark{display:inline-flex;align-items:center;justify-content:center;min-width:28px;height:28px;padding:0 6px;margin-inline-start:8px;border:1.5px solid #d4a017;border-radius:9999px;font-family:'Amiri',serif;font-size:15px;font-weight:700;color:#fde68a;background:rgba(212,160,23,.08);vertical-align:middle;white-space:nowrap;}
#bahan-arab{max-height:340px;overflow-y:auto;padding-right:6px;scrollbar-width:thin;scrollbar-color:#d4a017 #0f172a;}
#bahan-arab::-webkit-scrollbar{width:6px;}
#bahan-arab::-webkit-scrollbar-track{background:transparent;}
#bahan-arab::-webkit-scrollbar-thumb{background:#475569;border-radius:9999px;}
@page{size:A4 portrait;margin:10mm 10mm 10mm 10mm;}
@media print{
  html,body{margin:0 !important;padding:0 !important;background:#fff !important;}
  body{background:#fff !important;}
  body *{-webkit-print-color-adjust:exact !important;print-color-adjust:exact !important;}
  .no-print{display:none !important;}
  main{max-width:none !important;width:100% !important;margin:0 !important;padding:0 !important;display:block !important;}
  main.space-y-6 > :not([hidden]) ~ :not([hidden]){margin-top:0 !important;}
  #print-area{display:block !important;background:#fff !important;color:#000 !important;margin:0 !important;padding:0 !important;font-size:11px !important;line-height:1.35 !important;box-shadow:none !important;border:none !important;}
  #print-area.space-y-6 > :not([hidden]) ~ :not([hidden]){margin-top:0.5rem !important;}
  #print-area .space-y-2 > :not([hidden]) ~ :not([hidden]){margin-top:0.35rem !important;}
  #print-area .space-y-1 > :not([hidden]) ~ :not([hidden]){margin-top:0.2rem !important;}
  #print-area .p-8{padding:0 !important;}
  #print-area .pb-4{padding-bottom:0.4rem !important;}
  #print-area .pt-8{padding-top:0.6rem !important;}
  #print-area .sig-block{display:flex !important;flex-direction:row !important;justify-content:space-between !important;align-items:flex-start !important;gap:0 !important;}
  #print-area .space-y-12 > :not([hidden]) ~ :not([hidden]){margin-top:1rem !important;}
  #print-area img{width:52px !important;height:52px !important;}
  #print-area h2{font-size:13pt !important;line-height:1.2 !important;}
  #print-area h3{font-size:11pt !important;}
  #print-area table{font-size:10px !important;line-height:1.3 !important;}
  #print-area th,#print-area td{padding:3px 6px !important;}
  #print-area .p-3{padding:0.4rem !important;}
  #print-area .p-2{padding:0.3rem !important;}
  #print-area div,#print-area table,#print-area tr{break-inside:avoid !important;page-break-inside:avoid !important;}
}
.print-only{display:none;}
</style>
</head>
<body class="text-slate-100 min-h-screen">
<header class="no-print bg-emerald-950/90 border-b border-emerald-700/50 sticky top-0 z-40">
<div class="max-w-7xl mx-auto px-3 sm:px-4 h-14 sm:h-16 flex items-center justify-between gap-2">
<div class="flex items-center gap-2 sm:gap-3 min-w-0">
<a href="dashboard.php" class="shrink-0 text-xs text-emerald-300">&larr; Peserta Saya</a>
<span class="text-slate-600 shrink-0">|</span>
<span class="min-w-0 truncate text-xs text-amber-300 font-bold"><?= e($pengujiNamaSess) ?></span>
</div>
<h1 class="font-bold text-amber-300 text-sm hidden sm:block shrink-0">Penilaian BTQ Penguji</h1>
<a href="logout.php" class="shrink-0 px-3 py-2 text-xs font-bold bg-red-950 rounded-xl border border-red-800 text-red-300">Logout</a>
</div>
</header>

<main class="max-w-7xl w-full mx-auto px-3 sm:px-6 py-4 sm:py-6 space-y-4 sm:space-y-6">
<?php if ($msg): ?><div class="no-print bg-emerald-900/70 border border-emerald-600 text-xs p-3 rounded-xl"><?= e($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="no-print bg-red-950/60 border border-red-800 text-xs p-3 rounded-xl text-red-300"><?= e($err) ?></div><?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 no-print">
<!-- KIRI: IDENTITAS UJIAN = DATA PENDAFTAR (LOCKED) -->
<div class="bg-slate-900/90 border border-emerald-700/50 rounded-2xl p-6 shadow-xl">
<h3 class="text-base font-bold text-amber-400 flex items-center gap-2 mb-1 border-b border-slate-800 pb-3">
<i data-lucide="user-check" class="w-5 h-5 text-emerald-400"></i> Identitas Ujian
</h3>
<p class="text-[11px] text-slate-500 mb-4">Otomatis dari data pendaftaran — tidak bisa diketik manual.</p>
<div class="flex items-start justify-between gap-3">
<div>
<p class="font-extrabold text-amber-300 leading-tight"><?= e($r['nama']) ?></p>
<p class="font-mono text-[11px] text-slate-400"><?= e($r['reg_no']) ?> &bull; <?= e($r['status']) ?></p>
</div>
<?php if ($h): ?>
<span class="px-2.5 py-1 rounded-full border text-[10px] font-bold <?= $h['status_lulus']==='LULUS' ? 'bg-emerald-950 text-emerald-400 border-emerald-800' : 'bg-red-950 text-red-400 border-red-800' ?>">Nilai: <?= e($h['total']) ?> (<?= e($h['grade']) ?>)</span>
<?php else: ?>
<span class="px-2.5 py-1 rounded-full border text-[10px] font-bold bg-slate-800 text-slate-400 border-slate-700">Belum dinilai</span>
<?php endif; ?>
</div>
<div class="grid grid-cols-2 gap-3 text-xs mt-4">
<div><span class="text-slate-500 block text-[10px] uppercase">NIM</span><b><?= e($r['nim']) ?></b></div>
<div><span class="text-slate-500 block text-[10px] uppercase">Semester</span><b><?= e($r['semester']) ?></b></div>
<div><span class="text-slate-500 block text-[10px] uppercase">Fakultas</span><b><?= e($r['fakultas_label']) ?></b></div>
<div><span class="text-slate-500 block text-[10px] uppercase">Prodi</span><b><?= e($r['prodi']) ?></b></div>
<div><span class="text-slate-500 block text-[10px] uppercase">HP</span><b><?= e($r['hp']) ?></b></div>
<div><span class="text-slate-500 block text-[10px] uppercase">Email</span><b class="break-all"><?= e($r['email']) ?></b></div>
<div><span class="text-slate-500 block text-[10px] uppercase">Kategori</span><b><?= e($r['kategori']) ?></b></div>
<div><span class="text-slate-500 block text-[10px] uppercase">Jalur (Standar <?= (int)$ambangPeserta ?>)</span><b class="text-amber-300"><?= e($jalurPeserta) ?></b></div>
<div class="col-span-2"><span class="text-slate-500 block text-[10px] uppercase">Gelombang</span><b><?= e($r['gelombang']) ?></b></div>
<div class="col-span-2"><span class="text-slate-500 block text-[10px] uppercase">Penguji saat ini</span><b class="text-amber-300"><?= e($r['penguji_nama'] ?? '-') ?></b></div>
</div>
<div class="mt-4 flex gap-2">
<a href="../tiket.php?reg=<?= urlencode($r['reg_no']) ?>" target="_blank" class="flex-1 text-center px-3 py-2 bg-emerald-700 rounded-xl text-xs font-bold">Lihat Tiket</a>
<a href="dashboard.php" class="flex-1 text-center px-3 py-2 bg-slate-800 border border-slate-700 rounded-xl text-xs font-bold">Kembali</a>
</div>

<!-- Bahan ujian manual (satu kolom, diketik penguji) -->
<div class="mt-5 bg-slate-950 border border-slate-800 rounded-xl p-4 space-y-2">
<h4 class="text-xs font-bold text-amber-400 flex items-center gap-1.5"><i data-lucide="book-open" class="w-4 h-4"></i> Bahan Ujian</h4>
<p class="text-[11px] text-slate-500">Ketik manual surah &amp; ayat di sini:</p>
<input type="text" id="bahan-ayat-visible" maxlength="200" value="<?= e($bahanVal) ?>" placeholder="cth: Al-Fatihah (1): ayat 1-7" oninput="document.getElementById('bahan-ayat-input').value=this.value;refreshBahanPreview()" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-xs focus:border-amber-400 focus:outline-none">
<div class="bg-slate-900/50 border border-slate-800 rounded-xl p-3 space-y-1">
<div id="bahan-arab" class="font-arabic">Memuat teks ayat...</div>
<p id="bahan-info" class="text-[11px] text-slate-400 text-center">Teks ayat tampil otomatis dari ketikan di atas.</p>
</div>
</div>
</div>

<!-- KANAN: FORM PENILAIAN -->
<div class="lg:col-span-2 space-y-6">
<form method="POST" class="bg-slate-900/90 border border-emerald-700/50 rounded-2xl p-6 shadow-xl space-y-6">
<div class="flex items-center justify-between border-b border-slate-800 pb-3">
<div>
<h3 class="text-lg font-bold text-white flex items-center gap-2"><i data-lucide="sliders" class="w-5 h-5 text-emerald-400"></i> Form Penilaian Standar</h3>
<p class="text-xs text-slate-400 mt-0.5">Geser slider atau ketik angka 0–100. Total bobot 100%. Standar lulus jalur <?= e($jalurPeserta) ?>: <b class="text-amber-300">≥ <?= (int)$ambangPeserta ?></b>.</p>
</div>
<span class="text-xs px-3 py-1 bg-amber-400/10 text-amber-300 border border-amber-400/30 font-semibold rounded-full">Tajwid 40% • Lancar 35% • Adab 25%</span>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-5">
<div class="bg-slate-950 border border-slate-800 p-4 rounded-xl space-y-3">
<div class="flex justify-between items-start"><div><h4 class="font-bold text-sm text-emerald-300">1. Tajwid &amp; Makhraj</h4><p class="text-[11px] text-slate-400">Makharijul huruf, Nun/Mim mati, Mad</p></div><span class="text-xs font-bold text-emerald-400 bg-emerald-950 px-2 py-0.5 rounded border border-emerald-800">40%</span></div>
<div class="flex items-center gap-3">
<input type="range" id="score-tajwid-range" min="0" max="100" value="<?= $tajwid ?>" oninput="syncScore('tajwid','range')" class="w-full accent-emerald-500">
<input type="number" name="tajwid" id="score-tajwid" min="0" max="100" value="<?= $tajwid ?>" oninput="syncScore('tajwid','input')" class="w-16 bg-slate-900 border border-slate-700 rounded-lg px-2 py-1 text-center font-bold text-amber-300 text-sm">
</div>
</div>
<div class="bg-slate-950 border border-slate-800 p-4 rounded-xl space-y-3">
<div class="flex justify-between items-start"><div><h4 class="font-bold text-sm text-emerald-300">2. Kelancaran &amp; Waqaf</h4><p class="text-[11px] text-slate-400">Kelancaran, waqaf &amp; ibtida'</p></div><span class="text-xs font-bold text-emerald-400 bg-emerald-950 px-2 py-0.5 rounded border border-emerald-800">35%</span></div>
<div class="flex items-center gap-3">
<input type="range" id="score-kelancaran-range" min="0" max="100" value="<?= $kelancaran ?>" oninput="syncScore('kelancaran','range')" class="w-full accent-emerald-500">
<input type="number" name="kelancaran" id="score-kelancaran" min="0" max="100" value="<?= $kelancaran ?>" oninput="syncScore('kelancaran','input')" class="w-16 bg-slate-900 border border-slate-700 rounded-lg px-2 py-1 text-center font-bold text-amber-300 text-sm">
</div>
</div>
<div class="bg-slate-950 border border-slate-800 p-4 rounded-xl space-y-3">
<div class="flex justify-between items-start"><div><h4 class="font-bold text-sm text-emerald-300">3. Adab &amp; Sikap</h4><p class="text-[11px] text-slate-400">Kesiapan, kerapian, penghormatan</p></div><span class="text-xs font-bold text-emerald-400 bg-emerald-950 px-2 py-0.5 rounded border border-emerald-800">25%</span></div>
<div class="flex items-center gap-3">
<input type="range" id="score-adab-range" min="0" max="100" value="<?= $adab ?>" oninput="syncScore('adab','range')" class="w-full accent-emerald-500">
<input type="number" name="adab" id="score-adab" min="0" max="100" value="<?= $adab ?>" oninput="syncScore('adab','input')" class="w-16 bg-slate-900 border border-slate-700 rounded-lg px-2 py-1 text-center font-bold text-amber-300 text-sm">
</div>
</div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
<div><label class="block text-xs font-semibold text-slate-300 mb-1.5">Penguji (otomatis sesuai pilihan peserta)</label>
<input type="text" value="<?= e($pengujiNamaSess) ?>" disabled class="w-full bg-slate-900/50 border border-slate-800 rounded-xl px-3.5 py-2 text-sm text-slate-300 font-semibold"></div>
<div><label class="block text-xs font-semibold text-slate-300 mb-1.5">Tanggal Ujian</label>
<input type="date" name="tanggal_ujian" value="<?= e($tglVal) ?>" class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3.5 py-2 text-sm focus:border-amber-400 focus:outline-none"></div>
</div>
<input type="hidden" name="bahan_ayat" id="bahan-ayat-input" value="<?= e($bahanVal) ?>">

<div><label class="block text-xs font-semibold text-slate-300 mb-1.5">Catatan Perbaikan / Evaluasi Penguji</label>
<textarea name="catatan" rows="3" placeholder="cth: Perhatikan Mad Thabi'i dan makhraj 'Ain..." class="w-full bg-slate-950 border border-slate-700 rounded-xl p-3 text-sm focus:border-amber-400 focus:outline-none"><?= e($catatanVal) ?></textarea></div>

<div class="bg-gradient-to-br from-slate-900 to-slate-950 border-2 border-amber-400/40 rounded-2xl p-5 flex flex-col md:flex-row items-center justify-between gap-5">
<div class="flex items-center gap-4">
<div id="grade-badge" class="w-20 h-20 rounded-2xl bg-emerald-600 border-2 border-amber-400 flex flex-col items-center justify-center text-center shadow-lg">
<span id="res-grade" class="text-3xl font-extrabold leading-none"><?= e($gradeHit) ?></span>
<span class="text-[10px] text-amber-200 font-arabic mt-0.5"><?= $gradeHit==='A'?'ممتاز':($gradeHit==='B'?'جيد جداً':($gradeHit==='C'?'جيد':'راسب')) ?></span>
</div>
<div>
<span class="text-xs text-slate-400 uppercase font-bold tracking-wider">Hasil Akhir Kalkulasi</span>
<div class="flex items-baseline gap-2 mt-0.5"><h3 id="res-total-score" class="text-3xl font-black text-amber-400"><?= number_format($totalHit,2) ?></h3><span class="text-xs text-slate-400">/ 100</span></div>
<p id="res-predicate" class="text-xs text-emerald-400 font-semibold mt-1">Status: <?= e($predikatHit) ?></p>
</div>
</div>
<div class="flex flex-wrap gap-3 w-full md:w-auto justify-end">
<button type="submit" class="flex-1 md:flex-none px-6 py-3 bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-sm rounded-xl shadow-lg flex items-center justify-center gap-2"><i data-lucide="save" class="w-4 h-4 text-amber-300"></i> Simpan Nilai</button>
<button type="button" onclick="window.print()" class="flex-1 md:flex-none px-6 py-3 bg-slate-800 hover:bg-slate-700 border border-slate-600 text-white font-semibold text-sm rounded-xl flex items-center justify-center gap-2"><i data-lucide="printer" class="w-4 h-4"></i> Cetak Berita Acara</button>
</div>
</div>
</form>
</div>
</div>

<!-- PRINT: BERITA ACARA (data dari DB, bukan input manual) -->
<div id="print-area" class="print-only p-8 bg-white text-black space-y-6">
<div class="border-b-4 border-double border-emerald-900 pb-4 flex items-center justify-center gap-4 text-center">
<img src="../logo/logo-umgo.jpeg" alt="Logo UPT AIK UMGO" class="w-20 h-20 rounded-xl object-cover border border-emerald-900">
<div>
<h2 class="text-xl font-extrabold tracking-wide uppercase text-emerald-950">Universitas Muhammadiyah Gorontalo</h2>
<h3 class="text-base font-bold text-slate-800">Lembaga Pengkajian Al-Qur'an &amp; Keislaman (LPK-AIK)</h3>
<p class="text-xs text-slate-600 italic">Jl. Prof. Dr. H. Aloei Saboe, Pentadio Timur, Telaga Biru, Gorontalo</p>
</div>
</div>
<div class="text-center space-y-1">
<h3 class="text-lg font-bold underline uppercase tracking-wider">BERITA ACARA UJIAN BACA AL-QUR'AN</h3>
<p class="text-xs text-slate-600">Nomor: <?= e($docNo) ?> &bull; No.Reg: <?= e($r['reg_no']) ?></p>
</div>
<div class="space-y-2">
<h4 class="font-bold text-xs uppercase border-b border-slate-300 pb-1">I. IDENTITAS MAHASISWA &amp; PENGUJI</h4>
<table class="w-full text-xs">
<tr><td class="w-32 py-1 font-semibold">Nama Mahasiswa</td><td class="w-4">:</td><td class="font-bold"><?= e($r['nama']) ?></td><td class="w-32 font-semibold">Fakultas</td><td class="w-4">:</td><td><?= e($r['fakultas_label']) ?></td></tr>
<tr><td class="py-1 font-semibold">NIM</td><td>:</td><td><?= e($r['nim']) ?></td><td class="font-semibold">Program Studi</td><td>:</td><td><?= e($r['prodi']) ?></td></tr>
<tr><td class="py-1 font-semibold">Jalur</td><td>:</td><td><?= e($jalurPeserta) ?> (Standar lulus <?= (int)$ambangPeserta ?>)</td><td class="font-semibold">Tanggal Ujian</td><td>:</td><td><?= e($tglVal) ?></td></tr>
<tr><td class="py-1 font-semibold">Dosen Penguji</td><td>:</td><td colspan="4"><?= e($h['penguji_nama'] ?? $r['penguji_nama'] ?? '-') ?></td></tr>
<tr><td class="py-1 font-semibold">Bahan Ayat</td><td>:</td><td colspan="4"><?= e($bahanVal) ?></td></tr>
</table>
</div>
<div class="space-y-2">
<h4 class="font-bold text-xs uppercase border-b border-slate-300 pb-1">II. RINCIAN HASIL PENILAIAN</h4>
<table class="w-full text-xs border-collapse border border-slate-400">
<thead><tr class="bg-slate-100"><th class="border border-slate-400 p-2 w-10">No</th><th class="border border-slate-400 p-2 text-left">Komponen</th><th class="border border-slate-400 p-2 w-20">Bobot</th><th class="border border-slate-400 p-2 w-24">Nilai</th><th class="border border-slate-400 p-2 w-24">Terbobot</th></tr></thead>
<tbody>
<tr><td class="border border-slate-400 p-2 text-center">1</td><td class="border border-slate-400 p-2">Makharijul Huruf &amp; Tajwid</td><td class="border border-slate-400 p-2 text-center">40%</td><td class="border border-slate-400 p-2 text-center"><?= $tajwid ?></td><td class="border border-slate-400 p-2 text-center font-semibold"><?= number_format($tajwid*0.40,2) ?></td></tr>
<tr><td class="border border-slate-400 p-2 text-center">2</td><td class="border border-slate-400 p-2">Kelancaran &amp; Waqaf-Ibtida'</td><td class="border border-slate-400 p-2 text-center">35%</td><td class="border border-slate-400 p-2 text-center"><?= $kelancaran ?></td><td class="border border-slate-400 p-2 text-center font-semibold"><?= number_format($kelancaran*0.35,2) ?></td></tr>
<tr><td class="border border-slate-400 p-2 text-center">3</td><td class="border border-slate-400 p-2">Adab &amp; Sikap</td><td class="border border-slate-400 p-2 text-center">25%</td><td class="border border-slate-400 p-2 text-center"><?= $adab ?></td><td class="border border-slate-400 p-2 text-center font-semibold"><?= number_format($adab*0.25,2) ?></td></tr>
</tbody>
<tfoot><tr class="bg-slate-100 font-bold"><td colspan="4" class="border border-slate-400 p-2 text-right">TOTAL NILAI AKHIR:</td><td class="border border-slate-400 p-2 text-center"><?= number_format($totalHit,2) ?></td></tr></tfoot>
</table>
</div>
<div class="border border-slate-300 p-3 rounded bg-slate-50 flex justify-between items-center text-xs">
<div><span class="font-bold block">PREDIKAT:</span><span class="text-sm font-extrabold uppercase"><?= e($predikatHit) ?></span></div>
<div class="text-right"><span class="font-bold block">STATUS:</span><span class="text-sm font-extrabold"><?= $isLulus ? 'LULUS' : 'TIDAK LULUS' ?></span></div>
</div>
<div class="space-y-1 text-xs"><span class="font-bold">CATATAN PENGUJI:</span><p class="p-2 border border-slate-300 rounded italic"><?= e($catatanVal !== '' ? $catatanVal : 'Tidak ada catatan khusus.') ?></p></div>
<div class="sig-block pt-8 flex flex-col min-[480px]:flex-row items-center min-[480px]:items-start justify-between gap-8 min-[480px]:gap-0 text-xs">
<div class="text-center w-full min-[480px]:w-48 space-y-12"><p>Mahasiswa Teruji,</p><p class="font-bold underline uppercase break-words">( <?= e($r['nama']) ?> )</p></div>
<div class="text-center w-full min-[480px]:w-56 space-y-12"><p>Gorontalo, <?= e($tglVal) ?><br>Dosen Penguji,</p><p class="font-bold underline uppercase break-words">( <?= e($h['penguji_nama'] ?? $r['penguji_nama'] ?? '.......................') ?> )</p></div>
</div>
</div>

</main>
<footer class="no-print bg-slate-950 border-t border-slate-800 text-slate-400 text-xs py-4 text-center"><p>© 2026 UMGO • LPK-AIK — Nilai tersimpan di database, bukan localStorage.</p></footer>

<script>
function getScore(c){ return parseFloat(document.getElementById('score-'+c).value)||0; }
function syncScore(c,src){
  const range=document.getElementById('score-'+c+'-range'), input=document.getElementById('score-'+c);
  if(src==='range'){ input.value=range.value; } else { let v=parseInt(input.value)||0; if(v>100)v=100; if(v<0)v=0; input.value=v; range.value=v; }
  calculateFinalScore();
}
function calculateFinalScore(){
  const t=getScore('tajwid'), k=getScore('kelancaran'), a=getScore('adab');
  const total=t*0.40+k*0.35+a*0.25;
  const ambang = <?= (int)$ambangPeserta ?>;
  document.getElementById('res-total-score').innerText=total.toFixed(2);
  let grade='D', label='Rasib / Kurang', bg='bg-red-600';
  if(total>=85){grade='A';label='Mumtaz / Sangat Memuaskan';bg='bg-emerald-600';}
  else if(total>=75){grade='B';label='Jayyid Jiddan / Memuaskan';bg='bg-emerald-600';}
  else if(total>=65){grade='C';label='Jayyid / Cukup';bg='bg-amber-600';}
  const lulus = total >= ambang;
  if(!lulus) bg='bg-red-600';
  const pred=(lulus?'LULUS (':'TIDAK LULUS (')+label+')';
  document.getElementById('res-grade').innerText=grade;
  document.getElementById('res-predicate').innerText='Status: '+pred+' • Standar <?= e($jalurPeserta) ?> ≥ '+ambang;
  document.getElementById('grade-badge').className='w-20 h-20 rounded-2xl '+bg+' border-2 border-amber-400 flex flex-col items-center justify-center text-center shadow-lg';
}
// --- Tampilan ayat Bahan Ujian (baca ketikan manual, tampil di bawahnya) ---
const SURAH_MAP = [[1,"Al-Fatihah",7],[2,"Al-Baqarah",286],[3,"Ali 'Imran",200],[4,"An-Nisa'",176],[5,"Al-Ma'idah",120],[6,"Al-An'am",165],[7,"Al-A'raf",206],[8,"Al-Anfal",75],[9,"At-Taubah",129],[10,"Yunus",109],[11,"Hud",123],[12,"Yusuf",111],[13,"Ar-Ra'd",43],[14,"Ibrahim",52],[15,"Al-Hijr",99],[16,"An-Nahl",128],[17,"Al-Isra'",111],[18,"Al-Kahf",110],[19,"Maryam",98],[20,"Taha",135],[21,"Al-Anbiya'",112],[22,"Al-Hajj",78],[23,"Al-Mu'minun",118],[24,"An-Nur",64],[25,"Al-Furqan",77],[26,"Asy-Syu'ara'",227],[27,"An-Naml",93],[28,"Al-Qasas",88],[29,"Al-'Ankabut",69],[30,"Ar-Rum",60],[31,"Luqman",34],[32,"As-Sajdah",30],[33,"Al-Ahzab",73],[34,"Saba'",54],[35,"Fatir",45],[36,"Yasin",83],[37,"As-Saffat",182],[38,"Sad",88],[39,"Az-Zumar",75],[40,"Gafir",60],[41,"Fussilat",54],[42,"Asy-Syura",53],[43,"Az-Zukhruf",89],[44,"Ad-Dukhan",59],[45,"Al-Jasiyah",37],[46,"Al-Ahqaf",35],[47,"Muhammad",38],[48,"Al-Fath",29],[49,"Al-Hujurat",18],[50,"Qaf",45],[51,"Az-Zariyat",60],[52,"At-Tur",49],[53,"An-Najm",62],[54,"Al-Qamar",55],[55,"Ar-Rahman",78],[56,"Al-Waqi'ah",96],[57,"Al-Hadid",29],[58,"Al-Mujadalah",22],[59,"Al-Hasyr",24],[60,"Al-Mumtahanah",13],[61,"As-Saff",14],[62,"Al-Jumu'ah",11],[63,"Al-Munafiqun",11],[64,"At-Tagabun",18],[65,"At-Talaq",12],[66,"At-Tahrim",12],[67,"Al-Mulk",30],[68,"Al-Qalam",52],[69,"Al-Haqqah",52],[70,"Al-Ma'arij",44],[71,"Nuh",28],[72,"Al-Jinn",28],[73,"Al-Muzzammil",20],[74,"Al-Muddassir",56],[75,"Al-Qiyamah",40],[76,"Al-Insan",31],[77,"Al-Mursalat",50],[78,"An-Naba'",40],[79,"An-Nazi'at",46],[80,"'Abasa",42],[81,"At-Takwir",29],[82,"Al-Infitar",19],[83,"Al-Mutaffifin",36],[84,"Al-Insyiqaq",25],[85,"Al-Buruj",22],[86,"At-Tariq",17],[87,"Al-A'la",19],[88,"Al-Gasyiyah",26],[89,"Al-Fajr",30],[90,"Al-Balad",20],[91,"Asy-Syams",15],[92,"Al-Lail",21],[93,"Ad-Duha",11],[94,"Asy-Syarh",8],[95,"At-Tin",8],[96,"Al-'Alaq",19],[97,"Al-Qadr",5],[98,"Al-Bayyinah",8],[99,"Az-Zalzalah",8],[100,"Al-'Adiyat",11],[101,"Al-Qari'ah",11],[102,"At-Takasur",8],[103,"Al-'Asr",3],[104,"Al-Humazah",9],[105,"Al-Fil",5],[106,"Quraisy",4],[107,"Al-Ma'un",7],[108,"Al-Kausar",3],[109,"Al-Kafirun",6],[110,"An-Nasr",3],[111,"Al-Lahab",5],[112,"Al-Ikhlas",4],[113,"Al-Falaq",5],[114,"An-Nas",6]];
function normNama(s){ return (s || '').toLowerCase().replace(/[^a-z]/g, ''); }
function parseBahan(teks){
  if (!teks || !teks.trim()) return { ok: false, pesan: 'Ketik bahan dulu, misal: Al-Fatihah (1): ayat 1-7' };
  let no = 0;
  const mn = teks.match(/\((\d{1,3})\)/);
  if (mn && +mn[1] >= 1 && +mn[1] <= 114) no = +mn[1];
  if (!no) {
    const nt = normNama(teks);
    const f = SURAH_MAP.find(s => nt.includes(normNama(s[1])));
    if (!f) return { ok: false, pesan: 'Nama surah tidak dikenali. Contoh: Al-Fatihah (1): ayat 1-7' };
    no = f[0];
  }
  const meta = SURAH_MAP.find(s => s[0] === no);
  let dari = 1, sampai = meta[2];
  const m = teks.match(/ayat\s+(\d+)(?:\s*[-\u2013\u2014]+\s*(\d+))?/i);
  if (m) { dari = parseInt(m[1], 10); sampai = m[2] ? parseInt(m[2], 10) : dari; }
  dari = Math.min(Math.max(1, dari), meta[2]);
  sampai = Math.min(Math.max(dari, sampai), meta[2]);
  return { ok: true, no, nama: meta[1], max: meta[2], dari, sampai };
}
let bahanTimer = null;
function refreshBahanPreview(){
  const val = document.getElementById('bahan-ayat-visible').value;
  document.getElementById('bahan-ayat-input').value = val;
  clearTimeout(bahanTimer);
  bahanTimer = setTimeout(() => loadBahanPreview(val), 600);
}
function escHtml(s){ return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
function toArabDigit(n){ return String(n).replace(/[0-9]/g, d => '٠١٢٣٤٥٦٧٨٩'[+d]); }
async function ambilAyat(no, dari, sampai){
  let err1 = '';
  try {
    // API utama: alquran.cloud arab Utsmani saja
    const r = await fetch('https://api.alquran.cloud/v1/surah/' + no + '/editions/quran-uthmani');
    if (!r.ok) throw new Error('HTTP ' + r.status);
    const j = await r.json();
    const d = Array.isArray(j.data) ? j.data[0] : (j.data || j);
    const list = (d && d.ayahs) || [];
    const pot = list.slice(dari - 1, sampai);
    if (pot.length && pot[0].text) {
      return pot.map((a, i) => ({ num: dari + i, arab: a.text || '' }));
    }
    throw new Error('isi kosong');
  } catch (e) { err1 = (e && e.message) || 'gagal'; }
  try {
    // API cadangan: equran.ninja (ambil teksArab saja)
    const r2 = await fetch('https://equran.ninja/api/v2/surat/' + no);
    if (!r2.ok) throw new Error('HTTP ' + r2.status);
    const j2 = await r2.json();
    const d2 = j2.data || j2;
    const list2 = d2.ayat || d2.ayahs || [];
    const pot2 = list2.slice(dari - 1, sampai);
    if (pot2.length) {
      return pot2.map((a, i) => ({
        num: a.nomorAyat || a.nomor || (dari + i),
        arab: a.teksArab || a.teks || a.text || ''
      })).filter(x => x.arab);
    }
    throw new Error('isi kosong');
  } catch (e2) { throw new Error('API1: ' + err1 + ' • API2: ' + ((e2 && e2.message) || 'gagal')); }
}
async function loadBahanPreview(val){
  const arabEl = document.getElementById('bahan-arab');
  const infoEl = document.getElementById('bahan-info');
  if (!arabEl || !infoEl) return;
  const p = parseBahan(val);
  if (!p.ok) { arabEl.innerHTML = '<div class="text-center text-slate-500 text-sm">—</div>'; infoEl.innerText = p.pesan; return; }
  arabEl.innerHTML = '<div class="text-center text-slate-500 text-sm">Memuat teks ayat...</div>';
  infoEl.innerText = p.nama + ' • ayat ' + p.dari + (p.sampai > p.dari ? '–' + p.sampai : '') + ' • memuat...';
  try {
    const list = await ambilAyat(p.no, p.dari, p.sampai);
    arabEl.innerHTML = list.map(a =>
      '<div class="bahan-ayah">' +
        '<div class="bahan-arab-line">' + escHtml(a.arab) + '<span class="ayah-mark">' + escHtml(toArabDigit(a.num)) + '</span></div>' +
      '</div>'
    ).join('');
    infoEl.innerText = p.nama + ' • ayat ' + p.dari + (p.sampai > p.dari ? '–' + p.sampai : '') + ' dari ' + p.max + '.';
  } catch (e) {
    arabEl.innerHTML = '<div class="text-center text-red-400 text-xs">Teks tidak bisa dimuat: ' + escHtml(e.message || 'koneksi gagal') + '.</div>';
    infoEl.innerText = 'Simpan & cetak tetap aman. Coba lagi saat online.';
  }
}
window.onload=function(){ lucide.createIcons(); calculateFinalScore(); refreshBahanPreview(); };
</script>
</body>
</html>

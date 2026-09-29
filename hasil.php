<?php
// hasil.php - Berita Acara / Hasil Penilaian BTQ untuk mahasiswa (tampil setelah penilaian selesai)
require __DIR__ . '/config/db.php';
$reg = trim($_GET['reg'] ?? '');
if ($reg === '') { header('Location: index.php'); exit; }

$stmt = $conn->prepare('SELECT p.*, g.nama AS penguji_nama FROM pendaftar p LEFT JOIN penguji g ON g.id = p.penguji_id WHERE p.reg_no = ? LIMIT 1');
$stmt->bind_param('s', $reg);
$stmt->execute();
$r = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$r) { header('Location: index.php?error=' . urlencode('Data tidak ditemukan.')); exit; }

$h = null;
$stmt2 = $conn->prepare('SELECT * FROM hasil_ujian WHERE pendaftar_id = ? LIMIT 1');
$stmt2->bind_param('i', $r['id']);
$stmt2->execute();
$h = $stmt2->get_result()->fetch_assoc();
$stmt2->close();

function e($s){ return htmlspecialchars($s ?? '-', ENT_QUOTES, 'UTF-8'); }
function ambangLulus($jalur){ $j = trim($jalur ?? 'Reguler'); return ($j === '' || strcasecmp($j,'Reguler') === 0) ? 80 : 60; }
$jalurPeserta = $r['jalur'] ?? 'Reguler';
$ambangPeserta = ambangLulus($jalurPeserta);
$sudahDinilai = (bool)$h;
if ($h) {
    $tajwid = (int)$h['tajwid']; $kelancaran = (int)$h['kelancaran']; $adab = (int)$h['adab'];
    $totalHit = (float)$h['total']; $predikatHit = $h['predikat']; $isLulus = ($h['status_lulus'] === 'LULUS');
    $catatanVal = $h['catatan'] ?? ''; $bahanVal = $h['bahan_ayat'] ?? '-'; $tglVal = $h['tanggal_ujian'] ?? '-';
    $pengujiNama = $h['penguji_nama'] ?? $r['penguji_nama'] ?? '-';
} else {
    $tajwid = $kelancaran = $adab = 0; $totalHit = 0; $predikatHit = '-'; $isLulus = false;
    $catatanVal = ''; $bahanVal = '-'; $tglVal = '-'; $pengujiNama = $r['penguji_nama'] ?? '-';
}
$docNo = 'UMGO/LPK-AIK/BQ/' . date('Y') . '/' . str_pad((string)$r['id'], 4, '0', STR_PAD_LEFT);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Hasil BTQ <?= e($r['reg_no']) ?> - <?= e($r['nama']) ?></title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
<script src="https://unpkg.com/lucide@latest"></script>
<style>
body{font-family:'Plus Jakarta Sans',sans-serif;background:#022c22;}
@page{size:A4 portrait;margin:10mm 10mm 10mm 10mm;}
@media print{
  html,body{margin:0 !important;padding:0 !important;background:#fff !important;}
  body *{-webkit-print-color-adjust:exact !important;print-color-adjust:exact !important;}
  .no-print{display:none !important;}
  main{max-width:none !important;width:100% !important;margin:0 !important;padding:0 !important;display:block !important;}
  #print-area{display:block !important;background:#fff !important;color:#000 !important;margin:0 !important;padding:0 !important;font-size:11px !important;line-height:1.35 !important;box-shadow:none !important;border:none !important;border-radius:0 !important;}
  #print-area.space-y-6 > :not([hidden]) ~ :not([hidden]){margin-top:0.5rem !important;}
  #print-area .space-y-2 > :not([hidden]) ~ :not([hidden]){margin-top:0.35rem !important;}
  #print-area .space-y-1 > :not([hidden]) ~ :not([hidden]){margin-top:0.2rem !important;}
  #print-area .pb-4{padding-bottom:0.4rem !important;}
  #print-area .pt-8{padding-top:0.6rem !important;}
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
</style>
</head>
<body class="text-slate-100 min-h-screen flex flex-col items-center p-4">
<div class="no-print w-full max-w-3xl flex items-center justify-between py-3">
  <a href="index.php?tab=status" class="text-xs text-emerald-300">&larr; Kembali</a>
  <?php if ($sudahDinilai): ?>
  <button onclick="window.print()" class="px-5 py-2.5 bg-amber-400 text-emerald-950 font-bold text-xs rounded-xl flex items-center gap-2"><i data-lucide="printer" class="w-4 h-4"></i> Cetak / Simpan PDF</button>
  <?php endif; ?>
</div>

<main class="w-full max-w-3xl">
<?php if (!$sudahDinilai): ?>
  <div class="bg-slate-900 border border-amber-800 rounded-2xl p-8 text-center space-y-3">
    <p class="text-amber-300 font-bold">Hasil belum tersedia</p>
    <p class="text-xs text-slate-400">Penilaian untuk <b class="text-slate-200"><?= e($r['reg_no']) ?> - <?= e($r['nama']) ?></b> belum diinput penguji. Silakan cek kembali nanti.</p>
    <a href="tiket.php?reg=<?= urlencode($r['reg_no']) ?>" class="inline-block px-4 py-2 bg-emerald-700 rounded-xl text-xs font-bold">Lihat Tiket</a>
  </div>
<?php else: ?>
  <div id="print-area" class="bg-white text-black rounded-2xl p-6 sm:p-8 space-y-6 shadow-2xl">
    <div class="border-b-4 border-double border-emerald-900 pb-4 flex items-center justify-center gap-4 text-center">
      <img src="logo/logo-umgo.jpeg" alt="Logo UPT AIK UMGO" class="w-20 h-20 rounded-xl object-cover border border-emerald-900">
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
        <tr><td class="py-1 font-semibold">Dosen Penguji</td><td>:</td><td colspan="4"><?= e($pengujiNama) ?></td></tr>
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
    <div class="pt-8 flex justify-between text-xs">
      <div class="text-center w-48 space-y-12"><p>Mahasiswa Teruji,</p><p class="font-bold underline uppercase">( <?= e($r['nama']) ?> )</p></div>
      <div class="text-center w-56 space-y-12"><p>Gorontalo, <?= e($tglVal) ?><br>Dosen Penguji,</p><p class="font-bold underline uppercase">( <?= e($pengujiNama) ?> )</p></div>
    </div>
  </div>
<?php endif; ?>
</main>
<script>lucide.createIcons();</script>
</body>
</html>

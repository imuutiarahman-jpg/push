<?php
session_start();
if (!isset($_SESSION['penguji_id'])) { header('Location: login.php'); exit; }
require __DIR__ . '/../config/db.php';
$pengujiId = (int)$_SESSION['penguji_id'];
$pengujiNama = $_SESSION['penguji_nama'] ?? '';
$me = $conn->query("SELECT COALESCE(kuota,5) AS kuota FROM penguji WHERE id = $pengujiId LIMIT 1")->fetch_assoc();
$kuotaSaya = max(1, (int)($me['kuota'] ?? 5));

$q = trim($_GET['q'] ?? '');
$filter = $_GET['filter'] ?? 'ALL'; // ALL, BELUM, SUDAH, LULUS
$allowed = ['ALL','BELUM','SUDAH','LULUS'];
if (!in_array($filter, $allowed)) $filter = 'ALL';

$sql = 'SELECT p.*, h.total, h.grade, h.status_lulus FROM pendaftar p LEFT JOIN hasil_ujian h ON h.pendaftar_id = p.id WHERE p.penguji_id = ?';
$params = [$pengujiId]; $types = 'i';
if ($q !== '') { $sql .= ' AND (p.nama LIKE ? OR p.nim LIKE ? OR p.reg_no LIKE ?)'; $like = "%$q%"; $params[] = $like; $params[] = $like; $params[] = $like; $types .= 'sss'; }
if ($filter === 'BELUM') $sql .= ' AND h.pendaftar_id IS NULL';
if ($filter === 'SUDAH') $sql .= ' AND h.pendaftar_id IS NOT NULL';
if ($filter === 'LULUS') $sql .= " AND h.status_lulus = 'LULUS'";
$sql .= ' ORDER BY p.created_at DESC';

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$total = count($rows);
$sudah = 0; $lulus = 0;
foreach ($rows as $x) { if ($x['pendaftar_id'] ?? $x['total'] !== null) { if ($x['total'] !== null) { $sudah++; if (($x['status_lulus'] ?? '') === 'LULUS') $lulus++; } } }

function e($s){ return htmlspecialchars($s ?? '-', ENT_QUOTES, 'UTF-8'); }
$flash = $_GET['msg'] ?? '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard Penguji - <?= e($pengujiNama) ?></title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
<script src="https://unpkg.com/lucide@latest"></script>
<script src="../assets/tema.js"></script>
<link rel="stylesheet" href="../assets/tema.css">
<style>body{font-family:'Plus Jakarta Sans',sans-serif;background:#022c22;}</style>
</head>
<body class="text-slate-100 min-h-screen">
<header class="bg-emerald-950/90 border-b border-emerald-700/50 sticky top-0 z-40">
<div class="max-w-7xl mx-auto px-4 h-16 flex items-center justify-between">
<div class="flex items-center gap-3">
<img src="../logo/logo-umgo.jpeg" alt="Logo UPT AIK UMGO" class="w-10 h-10 rounded-xl object-cover border border-amber-300 bg-white">
<div><h1 class="font-bold text-amber-300 text-sm">Portal Penguji BTQ</h1><p class="text-[11px] text-emerald-200"><?= e($pengujiNama) ?> &bull; <?= $total ?>/<?= $kuotaSaya ?> peserta</p></div>
</div>
<a href="logout.php" class="px-3 py-2 text-xs font-bold bg-red-950 rounded-xl border border-red-800 text-red-300">Logout</a>
</div>
</header>
<main class="max-w-7xl mx-auto px-4 py-6 space-y-5">
<?php if ($flash): ?><div class="bg-emerald-900/70 border border-emerald-600 text-xs p-3 rounded-xl"><?= e($flash) ?></div><?php endif; ?>

<div class="grid grid-cols-3 gap-3">
<div class="bg-slate-900/90 border border-emerald-700/50 rounded-2xl p-4 text-center"><p class="text-2xl font-black text-amber-400"><?= $total ?></p><p class="text-[11px] text-slate-400">Total Peserta</p></div>
<div class="bg-slate-900/90 border border-emerald-700/50 rounded-2xl p-4 text-center"><p class="text-2xl font-black text-emerald-400"><?= $sudah ?></p><p class="text-[11px] text-slate-400">Sudah Dinilai</p></div>
<div class="bg-slate-900/90 border border-emerald-700/50 rounded-2xl p-4 text-center"><p class="text-2xl font-black text-emerald-300"><?= $lulus ?></p><p class="text-[11px] text-slate-400">Lulus</p></div>
</div>

<div class="bg-slate-900/90 border border-emerald-700/50 rounded-3xl p-5 space-y-4">
<form method="GET" class="flex flex-col sm:flex-row gap-3 text-xs">
<select name="filter" class="bg-slate-950 border border-slate-700 rounded-xl px-3 py-2.5">
<?php foreach ($allowed as $f): ?><option value="<?= $f ?>" <?= $filter===$f?'selected':'' ?>><?= $f==='ALL'?'Semua':($f==='BELUM'?'Belum dinilai':($f==='SUDAH'?'Sudah dinilai':'Lulus')) ?></option><?php endforeach; ?>
</select>
<input name="q" value="<?= e($q) ?>" placeholder="Cari nama / NIM / reg_no..." class="flex-1 bg-slate-950 border border-slate-700 rounded-xl px-4 py-2.5 focus:border-amber-400 focus:outline-none">
<button class="px-5 py-2.5 bg-amber-400 text-emerald-950 font-bold rounded-xl">Filter</button>
</form>
<div class="overflow-x-auto rounded-xl border border-slate-800">
<table class="w-full text-left text-xs">
<thead class="bg-slate-950 text-slate-400 uppercase border-b border-slate-800"><tr><th class="p-3">No.Reg / Mahasiswa</th><th class="p-3">NIM / Prodi</th><th class="p-3">Status Daftar</th><th class="p-3 text-center">Nilai</th><th class="p-3 text-right">Aksi</th></tr></thead>
<tbody class="divide-y divide-slate-800/60">
<?php if (!$rows): ?><tr><td colspan="5" class="p-4 text-center text-slate-500 italic">Belum ada peserta untuk Anda. Hubungi admin.</td></tr><?php endif; ?>
<?php foreach ($rows as $r): ?>
<?php $dinilai = $r['total'] !== null; ?>
<tr class="hover:bg-slate-800/50">
<td class="p-3"><span class="font-mono text-amber-300 font-bold"><?= e($r['reg_no']) ?></span><br><span class="font-semibold"><?= e($r['nama']) ?></span> <span class="px-1.5 py-0.5 rounded text-[10px] font-bold border border-amber-800 bg-amber-950 text-amber-300"><?= e($r['jalur'] ?? 'Reguler') ?></span><br><span class="text-[10px] text-slate-500"><?= e($r['gelombang']) ?></span></td>
<td class="p-3"><?= e($r['nim']) ?><br><span class="text-[10px] text-emerald-400"><?= e($r['prodi']) ?></span></td>
<td class="p-3"><span class="px-2 py-0.5 rounded text-[10px] font-bold border <?= $r['status']==='TERVERIFIKASI'?'bg-emerald-950 text-emerald-400 border-emerald-800':($r['status']==='DITOLAK'?'bg-red-950 text-red-400 border-red-800':'bg-amber-950 text-amber-400 border-amber-800') ?>"><?= e($r['status']) ?></span></td>
<td class="p-3 text-center"><?php if ($dinilai): ?><span class="font-bold text-amber-300"><?= e($r['total']) ?></span> <span class="px-1.5 py-0.5 rounded text-[10px] font-bold border <?= $r['status_lulus']==='LULUS'?'bg-emerald-950 text-emerald-400 border-emerald-800':'bg-red-950 text-red-400 border-red-800' ?>"><?= e($r['grade']) ?></span><?php else: ?><span class="text-slate-500 italic">-</span><?php endif; ?></td>
<td class="p-3 text-right whitespace-nowrap"><a href="nilai.php?id=<?= (int)$r['id'] ?>" class="inline-block px-4 py-2 <?= $dinilai?'bg-emerald-700':'bg-amber-400 text-emerald-950' ?> rounded-xl font-bold"><?= $dinilai?'Lihat / Edit':'Nilai' ?></a></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</div>
</main>
<script>lucide.createIcons();</script>
</body>
</html>

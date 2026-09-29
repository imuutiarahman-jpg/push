<?php
session_start();
if (!isset($_SESSION['admin'])) { header('Location: login.php'); exit; }
require __DIR__ . '/../config/db.php';

$status = $_GET['status'] ?? 'ALL';
$search = trim($_GET['q'] ?? '');
$allowed = ['ALL','MENUNGGU','TERVERIFIKASI','DITOLAK'];
if (!in_array($status, $allowed)) $status = 'ALL';

$sql = 'SELECT p.*, g.nama AS penguji_nama FROM pendaftar p LEFT JOIN penguji g ON g.id = p.penguji_id WHERE 1=1';
$params = []; $types = '';
if ($status !== 'ALL') { $sql .= ' AND p.status = ?'; $params[] = $status; $types .= 's'; }
if ($search !== '') { $sql .= ' AND (p.nama LIKE ? OR p.nim LIKE ? OR p.reg_no LIKE ? OR g.nama LIKE ?)'; $like = "%$search%"; $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like; $types .= 'ssss'; }
$sql .= ' ORDER BY created_at DESC';
$stmt = $conn->prepare($sql);
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Hitung total
$total = $conn->query('SELECT COUNT(*) c FROM pendaftar')->fetch_assoc()['c'];

function e($s){ return htmlspecialchars($s ?? '-', ENT_QUOTES, 'UTF-8'); }
$flash = $_GET['msg'] ?? '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard Admin - UPT AIK UMGO</title>
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
            <div><h1 class="font-bold text-amber-300 text-sm">Dasbor Admin UPT AIK</h1><p class="text-[11px] text-emerald-200">Login: <?= e($_SESSION['admin']) ?> &bull; Total: <?= (int)$total ?></p></div>
        </div>
        <div class="flex gap-2">
            <a href="penguji.php" class="px-3 py-2 text-xs font-bold bg-amber-400 text-emerald-950 rounded-xl">Kelola Penguji</a>
            <a href="jadwal.php" class="px-3 py-2 text-xs font-bold bg-emerald-800 rounded-xl border border-emerald-600">Jadwal &amp; Tempat</a>
            <a href="pengaturan.php" class="px-3 py-2 text-xs font-bold bg-emerald-900 rounded-xl border border-emerald-700">Pengaturan</a>
            <a href="logout.php" class="px-3 py-2 text-xs font-bold bg-red-950 rounded-xl border border-red-800 text-red-300">Logout</a>
        </div>
    </div>
</header>

<main class="max-w-7xl mx-auto px-4 py-6 space-y-5">
<?php if ($flash): ?><div class="bg-emerald-900/70 border border-emerald-600 text-xs p-3 rounded-xl"><?= e($flash) ?></div><?php endif; ?>

<div class="bg-slate-900/90 border border-emerald-700/50 rounded-3xl p-5 space-y-4">
    <form method="GET" class="flex flex-col sm:flex-row gap-3 text-xs">
        <select name="status" class="bg-slate-950 border border-slate-700 rounded-xl px-3 py-2.5">
            <?php foreach ($allowed as $s): ?><option value="<?= $s ?>" <?= $status===$s?'selected':'' ?>><?= $s==='ALL'?'Semua Status':$s ?></option><?php endforeach; ?>
        </select>
        <input name="q" value="<?= e($search) ?>" placeholder="Cari nama / NIM / reg_no..." class="flex-1 bg-slate-950 border border-slate-700 rounded-xl px-4 py-2.5 focus:border-amber-400 focus:outline-none">
        <button class="px-5 py-2.5 bg-amber-400 text-emerald-950 font-bold rounded-xl">Filter</button>
        <a href="export_csv.php?status=<?= urlencode($status) ?>&q=<?= urlencode($search) ?>" class="px-4 py-2.5 bg-emerald-700 rounded-xl font-bold flex items-center gap-1"><i data-lucide="download" class="w-4 h-4"></i> CSV</a>
    </form>

    <div class="overflow-x-auto rounded-xl border border-slate-800">
    <table class="w-full text-left text-xs">
        <thead class="bg-slate-950 text-slate-400 uppercase border-b border-slate-800">
            <tr><th class="p-3">No.Reg</th><th class="p-3">Mahasiswa</th><th class="p-3">NIM/Prodi</th><th class="p-3">Ujian &amp; Sesi</th><th class="p-3">Penguji</th><th class="p-3 text-center">Status</th><th class="p-3 text-right">Aksi</th></tr>
        </thead>
        <tbody class="divide-y divide-slate-800/60">
        <?php if (!$rows): ?><tr><td colspan="7" class="p-4 text-center text-slate-500 italic">Belum ada data.</td></tr><?php endif; ?>
        <?php foreach ($rows as $r): ?>
        <?php
          $badge='bg-amber-950 text-amber-400 border-amber-800';
          if($r['status']==='TERVERIFIKASI') $badge='bg-emerald-950 text-emerald-400 border-emerald-800';
          if($r['status']==='DITOLAK') $badge='bg-red-950 text-red-400 border-red-800';
        ?>
        <tr class="hover:bg-slate-800/50">
            <td class="p-3 font-mono text-amber-300 font-bold"><?= e($r['reg_no']) ?><br><span class="text-[10px] text-slate-500"><?= e($r['created_at']) ?></span></td>
            <td class="p-3 font-semibold"><?= e($r['nama']) ?></td>
            <td class="p-3"><?= e($r['nim']) ?><br><span class="text-[10px] text-emerald-400"><?= e($r['prodi']) ?></span></td>
            <td class="p-3"><?= e($r['kategori']) ?> <span class="px-1.5 py-0.5 rounded text-[10px] font-bold border border-amber-800 bg-amber-950 text-amber-300"><?= e($r['jalur'] ?? 'Reguler') ?></span><br><span class="text-[10px] text-slate-400"><?= e($r['gelombang']) ?></span></td>
            <td class="p-3 text-emerald-300 font-semibold"><?= e($r['penguji_nama'] ?? '-') ?></td>
            <td class="p-3 text-center"><span class="px-2 py-0.5 rounded text-[10px] font-bold border <?= $badge ?>"><?= e($r['status']) ?></span></td>
            <td class="p-3 text-right whitespace-nowrap">
                <a href="nilai.php?id=<?= (int)$r['id'] ?>" title="Nilai BTQ" class="inline-block p-1.5 bg-amber-400 text-emerald-950 rounded-lg font-bold"><i data-lucide="pen-tool" class="w-3.5 h-3.5"></i></a>
                <a href="detail.php?id=<?= (int)$r['id'] ?>" title="Detail" class="inline-block p-1.5 bg-slate-700 rounded-lg"><i data-lucide="eye" class="w-3.5 h-3.5"></i></a>
                <a href="verifikasi.php?id=<?= (int)$r['id'] ?>&aksi=TERVERIFIKASI" title="Setujui" class="inline-block p-1.5 bg-emerald-800 rounded-lg" onclick="return confirm('Setujui <?= e($r['reg_no']) ?>?')"><i data-lucide="check" class="w-3.5 h-3.5"></i></a>
                <a href="verifikasi.php?id=<?= (int)$r['id'] ?>&aksi=DITOLAK" title="Tolak" class="inline-block p-1.5 bg-red-950 rounded-lg" onclick="return confirm('Tolak <?= e($r['reg_no']) ?>?')"><i data-lucide="x" class="w-3.5 h-3.5"></i></a>
                <a href="verifikasi.php?id=<?= (int)$r['id'] ?>&aksi=HAPUS" title="Hapus" class="inline-block p-1.5 bg-slate-800 border border-red-900 text-red-300 rounded-lg" onclick="return confirm('Hapus permanen?')"><i data-lucide="trash-2" class="w-3.5 h-3.5"></i></a>
            </td>
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

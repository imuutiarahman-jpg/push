<?php
session_start();
if (!isset($_SESSION['admin'])) { header('Location: login.php'); exit; }
require __DIR__ . '/../config/db.php';
$id = (int)($_GET['id'] ?? 0);
// Update penguji dari admin (untuk data lama NULL / pindah penguji)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['penguji_id'])) {
    $pid = (int)$_POST['penguji_id'];
    if ($pid === 0) {
        $conn->query("UPDATE pendaftar SET penguji_id = NULL WHERE id = $id");
        header("Location: detail.php?id=$id&msg=" . urlencode('Penguji dikosongkan.'));
        exit;
    }
    $cekP = $conn->query("SELECT id, nama, COALESCE(kuota,5) AS kuota FROM penguji WHERE id = $pid LIMIT 1")->fetch_assoc();
    if (!$cekP) { header("Location: detail.php?id=$id&msg=" . urlencode('Penguji tidak ditemukan.')); exit; }
    $kuotaP = max(1, (int)($cekP['kuota'] ?? 5));
    $c = $conn->query("SELECT COUNT(*) c FROM pendaftar WHERE penguji_id = $pid AND id <> $id")->fetch_assoc()['c'];
    if ((int)$c >= $kuotaP) { header("Location: detail.php?id=$id&msg=" . urlencode('Penguji ' . $cekP['nama'] . " sudah penuh ($c/$kuotaP).")); exit; }
    $u = $conn->prepare('UPDATE pendaftar SET penguji_id = ? WHERE id = ?');
    $u->bind_param('ii', $pid, $id);
    $u->execute();
    $u->close();
    header("Location: detail.php?id=$id&msg=" . urlencode('Penguji diubah ke ' . $cekP['nama']));
    exit;
}
$stmt = $conn->prepare('SELECT p.*, g.nama AS penguji_nama FROM pendaftar p LEFT JOIN penguji g ON g.id = p.penguji_id WHERE p.id = ? LIMIT 1');
$stmt->bind_param('i', $id);
$stmt->execute();
$r = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$r) { header('Location: dashboard.php?msg=' . urlencode('Data tidak ditemukan.')); exit; }
$allPenguji = $conn->query("SELECT g.*, COALESCE(g.kuota,5) AS kuota, (SELECT COUNT(*) FROM pendaftar p WHERE p.penguji_id = g.id) AS terisi FROM penguji g ORDER BY g.nama")->fetch_all(MYSQLI_ASSOC);
$flash = $_GET['msg'] ?? '';
function e($s){ return htmlspecialchars($s ?? '-', ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Detail <?= e($r['reg_no']) ?></title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
<script src="https://unpkg.com/lucide@latest"></script>
<script src="../assets/tema.js"></script>
<link rel="stylesheet" href="../assets/tema.css">
<style>body{font-family:'Plus Jakarta Sans',sans-serif;background:#022c22;}</style>
</head>
<body class="text-slate-100 min-h-screen">
<div class="max-w-3xl mx-auto px-4 py-8 space-y-5">
<a href="dashboard.php" class="text-xs text-emerald-300">&larr; Kembali ke dashboard</a>
<?php if ($flash): ?><div class="bg-emerald-900/70 border border-emerald-600 text-xs p-3 rounded-xl"><?= e($flash) ?></div><?php endif; ?>
<div class="bg-slate-900/90 border border-emerald-700/50 rounded-3xl p-6 space-y-4">
    <div class="flex items-center justify-between border-b border-slate-800 pb-4">
        <div><h2 class="font-extrabold text-amber-300"><?= e($r['nama']) ?></h2><p class="font-mono text-xs text-slate-400"><?= e($r['reg_no']) ?> &bull; <?= e($r['status']) ?></p></div>
        <div class="flex gap-2">
            <a href="nilai.php?id=<?= (int)$r['id'] ?>" class="px-4 py-2 bg-amber-400 text-emerald-950 rounded-xl text-xs font-bold">Nilai / Berita Acara</a>
            <a href="verifikasi.php?id=<?= (int)$r['id'] ?>&aksi=TERVERIFIKASI" onclick="return confirm('Setujui?')" class="px-4 py-2 bg-emerald-700 rounded-xl text-xs font-bold">Setujui</a>
            <a href="verifikasi.php?id=<?= (int)$r['id'] ?>&aksi=DITOLAK" onclick="return confirm('Tolak?')" class="px-4 py-2 bg-red-950 border border-red-800 text-red-300 rounded-xl text-xs font-bold">Tolak</a>
        </div>
    </div>
    <div class="grid grid-cols-2 gap-3 text-xs">
        <div>NIM<b class="block text-sm"><?= e($r['nim']) ?></b></div>
        <div>Semester<b class="block text-sm"><?= e($r['semester']) ?></b></div>
        <div>Fakultas<b class="block text-sm"><?= e($r['fakultas_label']) ?></b></div>
        <div>Prodi<b class="block text-sm"><?= e($r['prodi']) ?></b></div>
        <div>HP<b class="block text-sm"><?= e($r['hp']) ?></b></div>
        <div>Email<b class="block text-sm"><?= e($r['email']) ?></b></div>
        <div>Kategori<b class="block text-sm"><?= e($r['kategori']) ?></b></div>
        <div>Jalur<b class="block text-sm text-amber-300"><?= e($r['jalur'] ?? 'Reguler') ?></b></div>
        <div>Gelombang<b class="block text-sm"><?= e($r['gelombang']) ?></b></div>
        <div class="col-span-2">Penguji saat ini<b class="block text-sm text-amber-300"><?= e($r['penguji_nama'] ?? '-') ?></b>
            <form method="POST" class="mt-2 flex gap-2">
                <select name="penguji_id" class="flex-1 bg-slate-950 border border-slate-700 rounded-xl px-3 py-2">
                    <option value="0">-- Kosongkan --</option>
                    <?php foreach ($allPenguji as $pg): ?>
                    <?php $kuotaPg = max(1,(int)($pg['kuota'] ?? 5)); $penuh = (int)$pg['terisi'] >= $kuotaPg && (int)$pg['id'] !== (int)$r['penguji_id']; ?>
                    <option value="<?= (int)$pg['id'] ?>" <?= (int)$pg['id'] === (int)$r['penguji_id'] ? 'selected' : '' ?> <?= $penuh ? 'disabled' : '' ?>><?= e($pg['nama']) ?> (<?= (int)$pg['terisi'] ?>/<?= $kuotaPg ?><?= $penuh ? ' Penuh' : '' ?>)</option>
                    <?php endforeach; ?>
                </select>
                <button class="px-4 py-2 bg-amber-400 text-emerald-950 font-bold rounded-xl">Ubah</button>
            </form>
        </div>
    </div>
    <div class="bg-slate-950 border border-slate-800 rounded-2xl p-4 text-xs">
        <b>Bukti Pembayaran:</b> <?= e($r['file_krs']) ?>
        <?php if ($r['file_krs'] && file_exists(__DIR__ . '/../uploads/' . $r['file_krs'])): ?>
        <div class="mt-2 flex gap-2">
            <a href="../uploads/<?= urlencode($r['file_krs']) ?>" target="_blank" class="px-4 py-2 bg-amber-400 text-emerald-950 font-bold rounded-xl">Buka / Unduh Berkas</a>
            <a href="../tiket.php?reg=<?= urlencode($r['reg_no']) ?>" target="_blank" class="px-4 py-2 bg-emerald-700 rounded-xl font-bold">Lihat Tiket</a>
        </div>
        <?php else: ?><p class="text-red-400 mt-1">File tidak ditemukan di server.</p><?php endif; ?>
    </div>
</div>
</div>
<script>lucide.createIcons();</script>
</body>
</html>

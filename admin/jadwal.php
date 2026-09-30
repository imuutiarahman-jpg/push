<?php
session_start();
if (!isset($_SESSION['admin'])) { header('Location: login.php'); exit; }
require __DIR__ . '/../config/db.php';

$HARI = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
$BULAN = [1=>'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
function tgl_id($ymd){
    global $HARI, $BULAN;
    $t = strtotime($ymd);
    if (!$t) return $ymd;
    return $HARI[(int)date('w', $t)] . ', ' . (int)date('j', $t) . ' ' . $BULAN[(int)date('n', $t)] . ' ' . date('Y', $t);
}
function label_jadwal($r){ return $r['nama'] . ' • ' . tgl_id($r['tanggal']) . ' • ' . $r['jam'] . ' • ' . $r['tempat']; }
function e($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

$msg = $_GET['msg'] ?? '';
$err = $_GET['error'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = $_POST['aksi'] ?? '';
    if ($aksi === 'tambah' || $aksi === 'ubah') {
        $id = (int)($_POST['id'] ?? 0);
        $nama = trim($_POST['nama'] ?? '');
        $tanggal = trim($_POST['tanggal'] ?? '');
        $jam = trim($_POST['jam'] ?? '');
        $tempat = trim($_POST['tempat'] ?? '');
        if ($nama === '' || $tanggal === '' || $jam === '' || $tempat === '') $err = 'Nama, tanggal, jam, dan tempat wajib diisi.';
        elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal) || !strtotime($tanggal)) $err = 'Format tanggal tidak valid.';
        else {
            if ($aksi === 'tambah') {
                $s = $conn->prepare('INSERT INTO jadwal (nama, tanggal, jam, tempat) VALUES (?,?,?,?)');
                $s->bind_param('ssss', $nama, $tanggal, $jam, $tempat);
                if ($s->execute()) $msg = "Jadwal \"$nama\" ditambahkan.";
                else $err = 'Gagal tambah: ' . $s->error;
                $s->close();
            } else {
                if ($id <= 0) $err = 'ID tidak valid.';
                else {
                    $s = $conn->prepare('UPDATE jadwal SET nama=?, tanggal=?, jam=?, tempat=? WHERE id=?');
                    $s->bind_param('ssssi', $nama, $tanggal, $jam, $tempat, $id);
                    if ($s->execute()) $msg = "Jadwal \"$nama\" diperbarui. Pendaftar lama tetap memakai snapshot lama.";
                    else $err = 'Gagal ubah: ' . $s->error;
                    $s->close();
                }
            }
        }
    } elseif ($aksi === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        $conn->query("UPDATE jadwal SET aktif = 1 - aktif WHERE id = $id");
        $msg = 'Status jadwal diubah.';
    } elseif ($aksi === 'hapus') {
        $id = (int)($_POST['id'] ?? 0);
        $conn->query("DELETE FROM jadwal WHERE id = $id");
        $msg = 'Jadwal dihapus. (Data pendaftar lama tidak ikut terhapus karena menyimpan snapshot teks.)';
    }
}

$editRow = null;
if (isset($_GET['edit'])) {
    $eid = (int)$_GET['edit'];
    $editRow = $conn->query("SELECT * FROM jadwal WHERE id = $eid LIMIT 1")->fetch_assoc();
}
$rows = $conn->query("SELECT * FROM jadwal ORDER BY tanggal, id")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="id"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Kelola Jadwal &amp; Tempat - UPT AIK UMGO</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
<script src="https://unpkg.com/lucide@latest"></script>
<script src="../assets/tema.js"></script>
<link rel="stylesheet" href="../assets/tema.css">
<style>body{font-family:'Plus Jakarta Sans',sans-serif;background:#022c22;}</style>
</head>
<body class="text-slate-100 min-h-screen">
<header class="bg-emerald-950/90 border-b border-emerald-700/50 sticky top-0 z-40">
<div class="max-w-5xl mx-auto px-3 sm:px-4 h-14 sm:h-16 flex items-center justify-between gap-2">
<a href="dashboard.php" class="shrink-0 text-xs text-emerald-300">&larr; Dashboard</a>
<h1 class="min-w-0 flex-1 text-center font-bold text-amber-300 text-xs sm:text-sm truncate">Jadwal &amp; Tempat Ujian</h1>
<a href="logout.php" class="shrink-0 px-3 py-2 text-xs font-bold bg-red-950 rounded-xl border border-red-800 text-red-300">Logout</a>
</div></header>
<main class="max-w-5xl mx-auto px-3 sm:px-4 py-4 sm:py-6 space-y-4 sm:space-y-5">
<?php if ($msg): ?><div class="bg-emerald-900/70 border border-emerald-600 text-xs p-3 rounded-xl"><?= e($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="bg-red-950/60 border border-red-800 text-xs p-3 rounded-xl text-red-300"><?= e($err) ?></div><?php endif; ?>

<form method="POST" class="bg-slate-900/90 border border-emerald-700/50 rounded-2xl p-4 grid grid-cols-1 sm:grid-cols-2 gap-3">
<input type="hidden" name="aksi" value="<?= $editRow ? 'ubah' : 'tambah' ?>">
<?php if ($editRow): ?><input type="hidden" name="id" value="<?= (int)$editRow['id'] ?>"><?php endif; ?>
<input name="nama" required maxlength="100" value="<?= e($editRow['nama'] ?? '') ?>" placeholder="Nama sesi, cth: Gelombang I" class="bg-slate-950 border border-slate-700 rounded-xl px-4 py-2.5 text-sm focus:border-amber-400 focus:outline-none">
<input type="date" name="tanggal" required value="<?= e($editRow['tanggal'] ?? '') ?>" class="bg-slate-950 border border-slate-700 rounded-xl px-4 py-2.5 text-sm focus:border-amber-400 focus:outline-none">
<input name="jam" required maxlength="50" value="<?= e($editRow['jam'] ?? '') ?>" placeholder="Jam, cth: 08.00–11.00 WITA" class="bg-slate-950 border border-slate-700 rounded-xl px-4 py-2.5 text-sm focus:border-amber-400 focus:outline-none">
<input name="tempat" required maxlength="150" value="<?= e($editRow['tempat'] ?? '') ?>" placeholder="Tempat, cth: Gedung UPT AIK UMGO" class="bg-slate-950 border border-slate-700 rounded-xl px-4 py-2.5 text-sm focus:border-amber-400 focus:outline-none">
<div class="sm:col-span-2 flex gap-2">
<button class="flex-1 px-5 py-2.5 bg-amber-400 text-emerald-950 font-bold text-xs rounded-xl"><?= $editRow ? 'Simpan Perubahan' : '+ Tambah Jadwal' ?></button>
<?php if ($editRow): ?><a href="jadwal.php" class="px-5 py-2.5 bg-slate-800 border border-slate-600 font-bold text-xs rounded-xl">Batal</a><?php endif; ?>
</div>
</form>

<div class="bg-slate-900/90 border border-emerald-700/50 rounded-2xl overflow-hidden">
<table class="w-full text-xs text-left">
<thead class="bg-slate-950 text-slate-400 uppercase border-b border-slate-800"><tr><th class="p-3">Sesi / Jadwal</th><th class="p-3 text-center">Status</th><th class="p-3 text-right">Aksi</th></tr></thead>
<tbody class="divide-y divide-slate-800/60">
<?php if (!$rows): ?><tr><td colspan="3" class="p-4 text-center text-slate-500 italic">Belum ada jadwal.</td></tr><?php endif; ?>
<?php foreach ($rows as $r): ?>
<tr class="hover:bg-slate-800/50">
<td class="p-3"><span class="font-bold text-amber-300"><?= e($r['nama']) ?></span><br><span class="text-slate-300"><?= e(tgl_id($r['tanggal'])) ?> • <?= e($r['jam']) ?></span><br><span class="text-emerald-300"><?= e($r['tempat']) ?></span></td>
<td class="p-3 text-center"><span class="px-2 py-0.5 rounded text-[10px] font-bold border <?= $r['aktif'] ? 'bg-emerald-950 text-emerald-400 border-emerald-800' : 'bg-slate-800 text-slate-400 border-slate-700' ?>"><?= $r['aktif'] ? 'AKTIF' : 'NONAKTIF' ?></span></td>
<td class="p-3 text-right">
<div class="flex justify-end gap-1 flex-wrap">
<a href="jadwal.php?edit=<?= (int)$r['id'] ?>" class="px-3 py-1.5 bg-emerald-700 rounded-lg font-bold">Edit</a>
<form method="POST" class="inline"><input type="hidden" name="aksi" value="toggle"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="px-3 py-1.5 bg-slate-700 rounded-lg font-bold"><?= $r['aktif'] ? 'Nonaktifkan' : 'Aktifkan' ?></button></form>
<form method="POST" class="inline" onsubmit="return confirm('Hapus jadwal ini? Pendaftar lama tetap tersimpan.')"><input type="hidden" name="aksi" value="hapus"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="px-3 py-1.5 bg-red-950 border border-red-800 text-red-300 rounded-lg font-bold">Hapus</button></form>
</div>
</td></tr>
<?php endforeach; ?>
</tbody></table>
</div>
<p class="text-[11px] text-slate-500">Hanya jadwal <b>aktif</b> yang muncul di form pendaftaran user. Perubahan jadwal tidak mengubah data pendaftar yang sudah masuk (mereka menyimpan snapshot teks saat daftar).</p>
</main>
<script>lucide.createIcons();</script>
</body></html>

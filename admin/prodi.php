<?php
session_start();
if (!isset($_SESSION['admin'])) { header('Location: login.php'); exit; }
require __DIR__ . '/../config/db.php';
function e($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

$msg = $_GET['msg'] ?? '';
$err = $_GET['error'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = $_POST['aksi'] ?? '';
    if ($aksi === 'fak_tambah') {
        $kode = strtoupper(trim($_POST['kode'] ?? ''));
        $label = trim($_POST['label'] ?? '');
        if ($kode === '' || $label === '') $err = 'Kode dan nama fakultas wajib diisi.';
        elseif (!preg_match('/^[A-Z0-9]{2,10}$/', $kode)) $err = 'Kode fakultas 2–10 karakter huruf/angka (cth: FST).';
        else {
            $s = $conn->prepare('INSERT INTO fakultas (kode, label) VALUES (?, ?)');
            $s->bind_param('ss', $kode, $label);
            if ($s->execute()) $msg = "Fakultas \"$kode\" ditambahkan.";
            else $err = 'Gagal tambah: ' . $s->error;
            $s->close();
        }
    } elseif ($aksi === 'fak_ubah') {
        $id = (int)($_POST['id'] ?? 0);
        $label = trim($_POST['label'] ?? '');
        if ($id <= 0 || $label === '') $err = 'Data fakultas tidak valid.';
        else {
            $s = $conn->prepare('UPDATE fakultas SET label=? WHERE id=?');
            $s->bind_param('si', $label, $id);
            if ($s->execute()) $msg = 'Nama fakultas diperbarui. Data pendaftar lama tetap memakai snapshot lama.';
            else $err = 'Gagal ubah: ' . $s->error;
            $s->close();
        }
    } elseif ($aksi === 'fak_toggle') {
        $id = (int)($_POST['id'] ?? 0);
        $conn->query("UPDATE fakultas SET aktif = 1 - aktif WHERE id = $id");
        $msg = 'Status fakultas diubah. Fakultas nonaktif (+ prodinya) tidak muncul di form pendaftaran.';
    } elseif ($aksi === 'fak_hapus') {
        $id = (int)($_POST['id'] ?? 0);
        // Aman: pendaftar menyimpan snapshot teks, jadi hapus master tidak merusak riwayat.
        $conn->query("DELETE FROM fakultas WHERE id = $id");
        $msg = 'Fakultas (+ semua prodinya) dihapus. Riwayat pendaftar lama tetap utuh.';
    } elseif ($aksi === 'prodi_tambah') {
        $fid = (int)($_POST['fakultas_id'] ?? 0);
        $nama = trim($_POST['nama'] ?? '');
        if ($fid <= 0 || $nama === '') $err = 'Pilih fakultas dan isi nama prodi.';
        else {
            $s = $conn->prepare('INSERT INTO prodi (fakultas_id, nama) VALUES (?, ?)');
            $s->bind_param('is', $fid, $nama);
            if ($s->execute()) $msg = "Prodi \"$nama\" ditambahkan.";
            else $err = 'Gagal tambah: ' . $s->error;
            $s->close();
        }
    } elseif ($aksi === 'prodi_ubah') {
        $id = (int)($_POST['id'] ?? 0);
        $nama = trim($_POST['nama'] ?? '');
        $fid = (int)($_POST['fakultas_id'] ?? 0);
        if ($id <= 0 || $nama === '' || $fid <= 0) $err = 'Data prodi tidak valid.';
        else {
            $s = $conn->prepare('UPDATE prodi SET nama=?, fakultas_id=? WHERE id=?');
            $s->bind_param('sii', $nama, $fid, $id);
            if ($s->execute()) $msg = 'Prodi diperbarui. Data pendaftar lama tetap memakai snapshot lama.';
            else $err = 'Gagal ubah: ' . $s->error;
            $s->close();
        }
    } elseif ($aksi === 'prodi_toggle') {
        $id = (int)($_POST['id'] ?? 0);
        $conn->query("UPDATE prodi SET aktif = 1 - aktif WHERE id = $id");
        $msg = 'Status prodi diubah. Prodi nonaktif tidak muncul di form pendaftaran.';
    } elseif ($aksi === 'prodi_hapus') {
        $id = (int)($_POST['id'] ?? 0);
        $conn->query("DELETE FROM prodi WHERE id = $id");
        $msg = 'Prodi dihapus. Riwayat pendaftar lama tetap utuh.';
    }
}

$editFak = null;
if (isset($_GET['edit_fak'])) {
    $eid = (int)$_GET['edit_fak'];
    $editFak = $conn->query("SELECT * FROM fakultas WHERE id = $eid LIMIT 1")->fetch_assoc();
}
$editProdi = null;
if (isset($_GET['edit_prodi'])) {
    $eid = (int)$_GET['edit_prodi'];
    $editProdi = $conn->query("SELECT * FROM prodi WHERE id = $eid LIMIT 1")->fetch_assoc();
}
$faks = $conn->query("SELECT * FROM fakultas ORDER BY kode")->fetch_all(MYSQLI_ASSOC);
$prodis = $conn->query("SELECT pr.*, f.kode AS fkode, f.label AS flabel FROM prodi pr JOIN fakultas f ON f.id = pr.fakultas_id ORDER BY f.kode, pr.nama")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="id"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Kelola Fakultas &amp; Prodi - UPT AIK UMGO</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
<script src="https://unpkg.com/lucide@latest"></script>
<script src="../assets/tema.js"></script>
<link rel="stylesheet" href="../assets/tema.css">
<style>body{font-family:'Plus Jakarta Sans',sans-serif;background:#022c22;}</style>
</head>
<body class="text-slate-100 min-h-screen">
<header class="bg-emerald-950/90 border-b border-emerald-700/50 sticky top-0 z-40">
<div class="max-w-5xl mx-auto px-4 h-16 flex items-center justify-between">
<a href="dashboard.php" class="text-xs text-emerald-300">&larr; Dashboard</a>
<h1 class="font-bold text-amber-300 text-sm">Fakultas &amp; Prodi</h1>
<a href="logout.php" class="px-3 py-2 text-xs font-bold bg-red-950 rounded-xl border border-red-800 text-red-300">Logout</a>
</div></header>
<main class="max-w-5xl mx-auto px-4 py-6 space-y-5">
<?php if ($msg): ?><div class="bg-emerald-900/70 border border-emerald-600 text-xs p-3 rounded-xl"><?= e($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="bg-red-950/60 border border-red-800 text-xs p-3 rounded-xl text-red-300"><?= e($err) ?></div><?php endif; ?>

<div class="grid grid-cols-1 md:grid-cols-2 gap-5">
<!-- FAKULTAS -->
<div class="space-y-3">
<form method="POST" class="bg-slate-900/90 border border-emerald-700/50 rounded-2xl p-4 space-y-3">
<input type="hidden" name="aksi" value="<?= $editFak ? 'fak_ubah' : 'fak_tambah' ?>">
<?php if ($editFak): ?><input type="hidden" name="id" value="<?= (int)$editFak['id'] ?>"><?php endif; ?>
<h3 class="font-bold text-amber-300 text-sm"><?= $editFak ? 'Edit Fakultas ' . e($editFak['kode']) : '+ Tambah Fakultas' ?></h3>
<?php if (!$editFak): ?>
<input name="kode" required maxlength="10" placeholder="Kode, cth: FST" class="w-full bg-slate-950 border border-slate-700 rounded-xl px-4 py-2.5 text-sm focus:border-amber-400 focus:outline-none uppercase">
<?php endif; ?>
<input name="label" required maxlength="150" value="<?= e($editFak['label'] ?? '') ?>" placeholder="Nama fakultas, cth: Fakultas Sains & Teknologi (FST)" class="w-full bg-slate-950 border border-slate-700 rounded-xl px-4 py-2.5 text-sm focus:border-amber-400 focus:outline-none">
<div class="flex gap-2">
<button class="flex-1 px-5 py-2.5 bg-amber-400 text-emerald-950 font-bold text-xs rounded-xl"><?= $editFak ? 'Simpan' : '+ Tambah' ?></button>
<?php if ($editFak): ?><a href="prodi.php" class="px-5 py-2.5 bg-slate-800 border border-slate-600 font-bold text-xs rounded-xl">Batal</a><?php endif; ?>
</div>
</form>
<div class="bg-slate-900/90 border border-emerald-700/50 rounded-2xl overflow-hidden">
<table class="w-full text-xs text-left">
<thead class="bg-slate-950 text-slate-400 uppercase border-b border-slate-800"><tr><th class="p-3">Fakultas</th><th class="p-3 text-center">Status</th><th class="p-3 text-right">Aksi</th></tr></thead>
<tbody class="divide-y divide-slate-800/60">
<?php if (!$faks): ?><tr><td colspan="3" class="p-4 text-center text-slate-500 italic">Belum ada fakultas.</td></tr><?php endif; ?>
<?php foreach ($faks as $f): ?>
<tr class="hover:bg-slate-800/50">
<td class="p-3"><span class="font-mono font-bold text-amber-300"><?= e($f['kode']) ?></span><br><span class="text-slate-300"><?= e($f['label']) ?></span></td>
<td class="p-3 text-center"><span class="px-2 py-0.5 rounded text-[10px] font-bold border <?= $f['aktif'] ? 'bg-emerald-950 text-emerald-400 border-emerald-800' : 'bg-slate-800 text-slate-400 border-slate-700' ?>"><?= $f['aktif'] ? 'AKTIF' : 'NONAKTIF' ?></span></td>
<td class="p-3 text-right whitespace-nowrap">
<a href="prodi.php?edit_fak=<?= (int)$f['id'] ?>" class="inline-block px-3 py-1.5 bg-emerald-700 rounded-lg font-bold">Edit</a>
<form method="POST" class="inline"><input type="hidden" name="aksi" value="fak_toggle"><input type="hidden" name="id" value="<?= (int)$f['id'] ?>"><button class="px-3 py-1.5 bg-slate-700 rounded-lg font-bold"><?= $f['aktif'] ? 'Nonaktifkan' : 'Aktifkan' ?></button></form>
<form method="POST" class="inline" onsubmit="return confirm('Hapus fakultas <?= e($f['kode']) ?> beserta semua prodinya? Riwayat pendaftar tetap aman.')"><input type="hidden" name="aksi" value="fak_hapus"><input type="hidden" name="id" value="<?= (int)$f['id'] ?>"><button class="px-3 py-1.5 bg-red-950 border border-red-800 text-red-300 rounded-lg font-bold">Hapus</button></form>
</td></tr>
<?php endforeach; ?>
</tbody></table>
</div>
</div>

<!-- PRODI -->
<div class="space-y-3">
<form method="POST" class="bg-slate-900/90 border border-emerald-700/50 rounded-2xl p-4 space-y-3">
<input type="hidden" name="aksi" value="<?= $editProdi ? 'prodi_ubah' : 'prodi_tambah' ?>">
<?php if ($editProdi): ?><input type="hidden" name="id" value="<?= (int)$editProdi['id'] ?>"><?php endif; ?>
<h3 class="font-bold text-amber-300 text-sm"><?= $editProdi ? 'Edit Prodi' : '+ Tambah Prodi' ?></h3>
<select name="fakultas_id" required class="w-full bg-slate-950 border border-slate-700 rounded-xl px-4 py-2.5 text-sm focus:border-amber-400 focus:outline-none">
<option value="">-- Pilih Fakultas --</option>
<?php foreach ($faks as $f): ?><option value="<?= (int)$f['id'] ?>" <?= (($editProdi['fakultas_id'] ?? '') == $f['id']) ? 'selected' : '' ?>><?= e($f['kode']) ?> - <?= e($f['label']) ?></option><?php endforeach; ?>
</select>
<input name="nama" required maxlength="150" value="<?= e($editProdi['nama'] ?? '') ?>" placeholder="Nama prodi, cth: S1 Bisnis Digital" class="w-full bg-slate-950 border border-slate-700 rounded-xl px-4 py-2.5 text-sm focus:border-amber-400 focus:outline-none">
<div class="flex gap-2">
<button class="flex-1 px-5 py-2.5 bg-amber-400 text-emerald-950 font-bold text-xs rounded-xl"><?= $editProdi ? 'Simpan' : '+ Tambah' ?></button>
<?php if ($editProdi): ?><a href="prodi.php" class="px-5 py-2.5 bg-slate-800 border border-slate-600 font-bold text-xs rounded-xl">Batal</a><?php endif; ?>
</div>
</form>
<div class="bg-slate-900/90 border border-emerald-700/50 rounded-2xl overflow-hidden">
<table class="w-full text-xs text-left">
<thead class="bg-slate-950 text-slate-400 uppercase border-b border-slate-800"><tr><th class="p-3">Prodi</th><th class="p-3 text-center">Status</th><th class="p-3 text-right">Aksi</th></tr></thead>
<tbody class="divide-y divide-slate-800/60">
<?php if (!$prodis): ?><tr><td colspan="3" class="p-4 text-center text-slate-500 italic">Belum ada prodi.</td></tr><?php endif; ?>
<?php foreach ($prodis as $p): ?>
<tr class="hover:bg-slate-800/50">
<td class="p-3"><span class="font-bold text-slate-100"><?= e($p['nama']) ?></span><br><span class="text-[11px] text-emerald-300"><?= e($p['fkode']) ?></span></td>
<td class="p-3 text-center"><span class="px-2 py-0.5 rounded text-[10px] font-bold border <?= $p['aktif'] ? 'bg-emerald-950 text-emerald-400 border-emerald-800' : 'bg-slate-800 text-slate-400 border-slate-700' ?>"><?= $p['aktif'] ? 'AKTIF' : 'NONAKTIF' ?></span></td>
<td class="p-3 text-right whitespace-nowrap">
<a href="prodi.php?edit_prodi=<?= (int)$p['id'] ?>" class="inline-block px-3 py-1.5 bg-emerald-700 rounded-lg font-bold">Edit</a>
<form method="POST" class="inline"><input type="hidden" name="aksi" value="prodi_toggle"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><button class="px-3 py-1.5 bg-slate-700 rounded-lg font-bold"><?= $p['aktif'] ? 'Nonaktifkan' : 'Aktifkan' ?></button></form>
<form method="POST" class="inline" onsubmit="return confirm('Hapus prodi ini? Riwayat pendaftar tetap aman.')"><input type="hidden" name="aksi" value="prodi_hapus"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><button class="px-3 py-1.5 bg-red-950 border border-red-800 text-red-300 rounded-lg font-bold">Hapus</button></form>
</td></tr>
<?php endforeach; ?>
</tbody></table>
</div>
</div>
</div>
<p class="text-[11px] text-slate-500">Hanya fakultas &amp; prodi <b>aktif</b> yang muncul di form pendaftaran. Menonaktifkan (bukan menghapus) disarankan jika ada pergantian prodi dari kampus — riwayat, tiket &amp; berita acara lama tetap utuh karena menyimpan snapshot teks.</p>
</main>
<script>lucide.createIcons();</script>
</body></html>

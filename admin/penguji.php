<?php
session_start();
if (!isset($_SESSION['admin'])) { header('Location: login.php'); exit; }
require __DIR__ . '/../config/db.php';

$msg = $_GET['msg'] ?? '';
$err = $_GET['error'] ?? '';

function clampKuota($v){ $v = (int)$v; if ($v < 1) $v = 1; if ($v > 100) $v = 100; return $v; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = $_POST['aksi'] ?? '';
    if ($aksi === 'tambah') {
        $nama = trim($_POST['nama'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $kuota = clampKuota($_POST['kuota'] ?? 5);
        if ($nama === '' || $username === '' || $password === '') $err = 'Nama, username, dan password wajib diisi.';
        elseif (strlen($username) < 4) $err = 'Username minimal 4 karakter (tanpa spasi).';
        elseif (strlen($password) < 6) $err = 'Password minimal 6 karakter.';
        elseif (!preg_match('/^[A-Za-z0-9_.-]+$/', $username)) $err = 'Username hanya boleh huruf, angka, titik, _ dan -.';
        else {
            $cek = $conn->prepare('SELECT id FROM penguji WHERE LOWER(nama) = LOWER(?) OR LOWER(username) = LOWER(?) LIMIT 1');
            $cek->bind_param('ss', $nama, $username);
            $cek->execute();
            $cek->store_result();
            if ($cek->num_rows > 0) {
                $err = "Nama / username sudah dipakai. Gunakan yang lain.";
            } else {
                try {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $s = $conn->prepare('INSERT INTO penguji (nama, username, password_hash, kuota) VALUES (?,?,?,?)');
                    $s->bind_param('sssi', $nama, $username, $hash, $kuota);
                    $s->execute();
                    $msg = "Penguji $nama (login: $username, kuota: $kuota) ditambahkan.";
                    $s->close();
                } catch (mysqli_sql_exception $ex) {
                    $err = "Gagal tambah: nama / username sudah dipakai.";
                }
            }
            $cek->close();
        }
    } elseif ($aksi === 'resetpw') {
        $id = (int)($_POST['id'] ?? 0);
        $baru = $_POST['baru'] ?? '';
        $userBaru = trim($_POST['username'] ?? '');
        if ($id <= 0) $err = 'ID tidak valid.';
        elseif ($userBaru !== '' && !preg_match('/^[A-Za-z0-9_.-]{4,50}$/', $userBaru)) $err = 'Username baru tidak valid (min 4, hanya huruf/angka/._-).';
        elseif ($baru !== '' && strlen($baru) < 6) $err = 'Password baru minimal 6 karakter.';
        elseif ($userBaru === '' && $baru === '') $err = 'Isi username baru dan/atau password baru.';
        else {
            $row = $conn->query("SELECT id FROM penguji WHERE id = $id LIMIT 1")->fetch_assoc();
            if (!$row) $err = 'Penguji tidak ditemukan.';
            else {
                if ($userBaru !== '') {
                    $c = $conn->prepare('SELECT id FROM penguji WHERE LOWER(username) = LOWER(?) AND id <> ? LIMIT 1');
                    $c->bind_param('si', $userBaru, $id);
                    $c->execute(); $c->store_result();
                    if ($c->num_rows > 0) { $err = "Username \"$userBaru\" sudah dipakai penguji lain."; }
                    else { $u = $conn->prepare('UPDATE penguji SET username = ? WHERE id = ?'); $u->bind_param('si', $userBaru, $id); $u->execute(); $u->close(); $msg = 'Username diperbarui.'; }
                    $c->close();
                    if ($err) { /* stop */ }
                }
                if (!$err && $baru !== '') {
                    $hash = password_hash($baru, PASSWORD_DEFAULT);
                    $u = $conn->prepare('UPDATE penguji SET password_hash = ? WHERE id = ?');
                    $u->bind_param('si', $hash, $id);
                    $u->execute(); $u->close();
                    $msg = trim($msg . ' Password direset.');
                }
            }
        }
    } elseif ($aksi === 'kuota') {
        $id = (int)($_POST['id'] ?? 0);
        $kuota = clampKuota($_POST['kuota'] ?? 5);
        if ($id <= 0) $err = 'ID tidak valid.';
        else {
            $terisi = (int)($conn->query("SELECT COUNT(*) c FROM pendaftar WHERE penguji_id = $id")->fetch_assoc()['c'] ?? 0);
            if ($kuota < $terisi) $err = "Kuota tidak boleh di bawah jumlah terisi ($terisi). Pindahkan peserta dulu atau naikkan kuota.";
            else { $conn->query("UPDATE penguji SET kuota = $kuota WHERE id = $id"); $msg = "Kuota penguji diubah menjadi $kuota."; }
        }
    } elseif ($aksi === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        $conn->query("UPDATE penguji SET aktif = 1 - aktif WHERE id = $id");
        $msg = 'Status penguji diubah.';
    } elseif ($aksi === 'hapus') {
        $id = (int)($_POST['id'] ?? 0);
        $c = $conn->query("SELECT COUNT(*) c FROM pendaftar WHERE penguji_id = $id")->fetch_assoc()['c'];
        if ((int)$c > 0) $err = "Tidak bisa hapus: sudah ada $c peserta. Nonaktifkan saja.";
        else { $conn->query("DELETE FROM penguji WHERE id = $id"); $msg = 'Penguji dihapus.'; }
    }
}

$rows = $conn->query("SELECT g.*, (SELECT COUNT(*) FROM pendaftar p WHERE p.penguji_id = g.id) AS terisi FROM penguji g ORDER BY g.nama")->fetch_all(MYSQLI_ASSOC);
function e($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="id"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Kelola Penguji - UPT AIK UMGO</title>
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
<h1 class="font-bold text-amber-300 text-sm">Kelola Penguji (kuota per penguji)</h1>
<a href="logout.php" class="px-3 py-2 text-xs font-bold bg-red-950 rounded-xl border border-red-800 text-red-300">Logout</a>
</div></header>
<main class="max-w-5xl mx-auto px-4 py-6 space-y-5">
<?php if ($msg): ?><div class="bg-emerald-900/70 border border-emerald-600 text-xs p-3 rounded-xl"><?= e($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="bg-red-950/60 border border-red-800 text-xs p-3 rounded-xl text-red-300"><?= e($err) ?></div><?php endif; ?>
<form method="POST" class="bg-slate-900/90 border border-emerald-700/50 rounded-2xl p-4 grid grid-cols-1 sm:grid-cols-5 gap-3">
<input type="hidden" name="aksi" value="tambah">
<input name="nama" required maxlength="100" placeholder="Nama, cth: Ust. Ahmad" class="bg-slate-950 border border-slate-700 rounded-xl px-4 py-2.5 text-sm focus:border-amber-400 focus:outline-none">
<input name="username" required maxlength="50" placeholder="Username login, cth: ust.ahmad" class="bg-slate-950 border border-slate-700 rounded-xl px-4 py-2.5 text-sm focus:border-amber-400 focus:outline-none">
<input type="text" name="password" required minlength="6" placeholder="Password awal (min 6)" class="bg-slate-950 border border-slate-700 rounded-xl px-4 py-2.5 text-sm focus:border-amber-400 focus:outline-none">
<input type="number" name="kuota" min="1" max="100" value="5" title="Jumlah peserta untuk penguji ini" class="bg-slate-950 border border-slate-700 rounded-xl px-4 py-2.5 text-sm focus:border-amber-400 focus:outline-none">
<button class="px-5 py-2.5 bg-amber-400 text-emerald-950 font-bold text-xs rounded-xl sm:col-span-5">+ Tambah Penguji</button>
</form>
<p class="text-[11px] text-slate-500">Penguji login di <a href="../penguji/login.php" class="text-amber-300 font-bold">/penguji/login.php</a> pakai username + password ini. Untuk penguji lama yang belum punya akun, isi form reset di tabel.</p>
<div class="bg-slate-900/90 border border-emerald-700/50 rounded-2xl overflow-hidden">
<table class="w-full text-xs text-left">
<thead class="bg-slate-950 text-slate-400 uppercase border-b border-slate-800"><tr><th class="p-3">Penguji / Login</th><th class="p-3 text-center">Terisi</th><th class="p-3 text-center">Status</th><th class="p-3 text-right">Aksi</th></tr></thead>
<tbody class="divide-y divide-slate-800/60">
<?php if (!$rows): ?><tr><td colspan="4" class="p-4 text-center text-slate-500 italic">Belum ada penguji.</td></tr><?php endif; ?>
<?php foreach ($rows as $r): ?>
<?php $kuotaRow = max(1, (int)($r['kuota'] ?? 5)); $penuh = (int)$r['terisi'] >= $kuotaRow; $punyaAkun = !empty($r['username']) && !empty($r['password_hash']); ?>
<tr class="hover:bg-slate-800/50">
<td class="p-3"><span class="font-semibold"><?= e($r['nama']) ?></span><br>
<span class="font-mono text-[11px] <?= $punyaAkun ? 'text-emerald-300' : 'text-red-400' ?>">@<?= e($r['username'] ?: '(belum ada username)') ?></span>
<?php if (!$punyaAkun): ?><span class="ml-1 px-1.5 py-0.5 rounded text-[10px] font-bold bg-red-950 text-red-300 border border-red-800">BELUM BISA LOGIN</span><?php endif; ?>
</td>
<td class="p-3 text-center font-mono <?= $penuh ? 'text-red-400 font-bold' : 'text-emerald-300' ?>"><?= (int)$r['terisi'] ?>/<?= $kuotaRow ?><?= $penuh ? ' (Penuh)' : '' ?>
<form method="POST" class="mt-1 flex gap-1 justify-center" onsubmit="return confirm('Ubah kuota penguji ini?')">
<input type="hidden" name="aksi" value="kuota"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
<input type="number" name="kuota" min="1" max="100" value="<?= $kuotaRow ?>" title="Kuota peserta" class="w-16 bg-slate-950 border border-slate-700 rounded-lg px-2 py-1 text-center text-[11px] focus:border-amber-400 focus:outline-none">
<button class="px-2 py-1 bg-emerald-700 rounded-lg font-bold text-[11px]">OK</button>
</form>
</td>
<td class="p-3 text-center"><span class="px-2 py-0.5 rounded text-[10px] font-bold border <?= $r['aktif'] ? 'bg-emerald-950 text-emerald-400 border-emerald-800' : 'bg-slate-800 text-slate-400 border-slate-700' ?>"><?= $r['aktif'] ? 'AKTIF' : 'NONAKTIF' ?></span></td>
<td class="p-3 text-right">
<div class="flex justify-end gap-1 flex-wrap">
<form method="POST" class="inline"><input type="hidden" name="aksi" value="toggle"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="px-3 py-1.5 bg-slate-700 rounded-lg font-bold" title="Aktif/Nonaktif"><?= $r['aktif'] ? 'Nonaktifkan' : 'Aktifkan' ?></button></form>
<form method="POST" class="inline" onsubmit="return confirm('Hapus penguji ini?')"><input type="hidden" name="aksi" value="hapus"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="px-3 py-1.5 bg-red-950 border border-red-800 text-red-300 rounded-lg font-bold">Hapus</button></form>
</div>
<form method="POST" class="mt-2 flex gap-1 justify-end" onsubmit="return confirm('Reset akun login penguji ini?')">
<input type="hidden" name="aksi" value="resetpw"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
<input name="username" maxlength="50" placeholder="username baru (opsional)" value="<?= e($r['username'] ?? '') ?>" class="w-32 bg-slate-950 border border-slate-700 rounded-lg px-2 py-1.5 text-[11px] focus:border-amber-400 focus:outline-none">
<input name="baru" placeholder="password baru" class="w-28 bg-slate-950 border border-slate-700 rounded-lg px-2 py-1.5 text-[11px] focus:border-amber-400 focus:outline-none">
<button class="px-3 py-1.5 bg-amber-400 text-emerald-950 rounded-lg font-bold text-[11px]">Reset</button>
</form>
</td></tr>
<?php endforeach; ?>
</tbody></table>
</div>
<p class="text-[11px] text-slate-500">Kuota ditentukan admin per penguji. Penguji penuh otomatis disabled di form user. Kuota dihitung total selamanya, bukan per gelombang.</p>
</main>
<script>lucide.createIcons();</script>
</body></html>

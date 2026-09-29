<?php
session_start();
require __DIR__ . '/config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?error=' . urlencode('Silakan login dulu dengan NIM + password.'));
    exit;
}
$userId = (int)$_SESSION['user_id'];
$sessionNim = $_SESSION['nim'] ?? '';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$nama      = trim($_POST['nama'] ?? '');
$nim       = trim($_POST['nim'] ?? '');
// NIM dikunci ke akun login agar tidak daftar pakai NIM orang lain
if ($nim === '') $nim = $sessionNim;
if ($nim !== $sessionNim) {
    header('Location: index.php?error=' . urlencode('NIM harus sama dengan akun login (' . $sessionNim . ').'));
    exit;
}
$fakultas  = $_POST['fakultas'] ?? '';
$prodi     = trim($_POST['prodi'] ?? '');
// Semester baru: tipe Ganjil/Genap + tingkat; tetap dukung format lama
$semTipe = $_POST['semester_tipe'] ?? '';
$semTingkat = trim($_POST['semester_tingkat'] ?? '');
if ($semTipe !== '' && $semTingkat !== '') {
    if (!in_array($semTipe, ['Ganjil','Genap'], true)) {
        header('Location: index.php?error=' . urlencode('Pilihan Ganjil/Genap tidak valid.'));
        exit;
    }
    $validTingkat = $semTipe === 'Ganjil' ? ['1','3','5','7','> 8'] : ['2','4','6','8','> 8'];
    if (!in_array($semTingkat, $validTingkat, true)) {
        header('Location: index.php?error=' . urlencode('Semester tidak sesuai dengan pilihan ' . $semTipe . '.'));
        exit;
    }
    $semester = $semTingkat === '> 8' ? "Semester > 8 ($semTipe)" : "Semester $semTingkat ($semTipe)";
} else {
    $semester = $_POST['semester'] ?? ''; // fallback data lama
}
$hp        = trim($_POST['hp'] ?? '');
$email     = trim($_POST['email'] ?? '');
$kategori  = $_POST['kategori'] ?? '';
$jalur     = trim($_POST['jalur'] ?? 'Reguler');
$jalurAllowed = ['Reguler','NonReg','S2','RPL'];
if (!in_array($jalur, $jalurAllowed, true)) fail('Jalur mahasiswa tidak valid. Pilih Reguler / NonReg / S2 / RPL.');
$jadwalId  = (int)($_POST['jadwal_id'] ?? 0);

$fakultasMap = [
    'FST' => 'Fakultas Sains & Teknologi (FST)',
    'FIKES' => 'Fakultas Ilmu Kesehatan (FIKES)',
    'FKIP' => 'Fakultas Keguruan & Ilmu Pendidikan (FKIP)',
    'FAI' => 'Fakultas Agama Islam (FAI)',
    'FIS' => 'Fakultas Ilmu Sosial (FIS)',
    'FK' => 'Fakultas Kedokteran (FK)',
];

function fail($msg) {
    header('Location: index.php?error=' . urlencode($msg));
    exit;
}

if ($nama === '' || $nim === '' || $prodi === '' || $hp === '' || $email === '' ) fail('Lengkapi semua field wajib.');
if (!isset($fakultasMap[$fakultas])) fail('Fakultas tidak valid.');
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) fail('Format email tidak valid.');

// Validasi jadwal (ditentukan admin via Kelola Jadwal & Tempat) -> snapshot teks
if ($jadwalId <= 0) fail('Pilih sesi/gelombang terlebih dahulu.');
$jw = $conn->prepare('SELECT nama, tanggal, jam, tempat, aktif FROM jadwal WHERE id = ? LIMIT 1');
$jw->bind_param('i', $jadwalId);
$jw->execute();
$jwRow = $jw->get_result()->fetch_assoc();
$jw->close();
if (!$jwRow) fail('Jadwal tidak ditemukan.');
if (!(int)$jwRow['aktif']) fail('Jadwal tersebut sudah nonaktif. Pilih jadwal lain.');
$HARI = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
$BULAN = [1=>'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
$t = strtotime($jwRow['tanggal']);
$tglFmt = $t ? $HARI[(int)date('w', $t)] . ', ' . (int)date('j', $t) . ' ' . $BULAN[(int)date('n', $t)] . ' ' . date('Y', $t) : $jwRow['tanggal'];
$gelombang = $jwRow['nama'] . ' • ' . $tglFmt . ' • ' . $jwRow['jam'] . ' • ' . $jwRow['tempat'];

// Validasi penguji + kuota per penguji (ditentukan admin)
$pengujiId = (int)($_POST['penguji_id'] ?? 0);
if ($pengujiId <= 0) fail('Pilih penguji terlebih dahulu.');
$pg = $conn->prepare('SELECT id, nama, aktif, COALESCE(kuota,5) AS kuota FROM penguji WHERE id = ? LIMIT 1');
$pg->bind_param('i', $pengujiId);
$pg->execute();
$pgRow = $pg->get_result()->fetch_assoc();
$pg->close();
if (!$pgRow) fail('Penguji tidak ditemukan.');
if (!(int)$pgRow['aktif']) fail('Penguji ' . $pgRow['nama'] . ' sedang nonaktif. Pilih penguji lain.');
$kuotaPg = max(1, (int)($pgRow['kuota'] ?? 5));
$cnt = $conn->prepare('SELECT COUNT(*) c FROM pendaftar WHERE penguji_id = ?');
$cnt->bind_param('i', $pengujiId);
$cnt->execute();
$terisi = (int)$cnt->get_result()->fetch_assoc()['c'];
$cnt->close();
if ($terisi >= $kuotaPg) fail('Penguji ' . $pgRow['nama'] . " sudah penuh ($terisi/$kuotaPg). Pilih penguji lain.");
if (!isset($_FILES['berkas']) || $_FILES['berkas']['error'] !== UPLOAD_ERR_OK) fail('Upload bukti pembayaran wajib.');

// Validasi file: maks 2MB, pdf/jpg/png
$allowed = ['pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png'];
$maxSize = 2 * 1024 * 1024;
$f = $_FILES['berkas'];
if ($f['size'] > $maxSize) fail('Ukuran file maksimal 2 MB.');
$ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
if (!array_key_exists($ext, $allowed)) fail('Format file harus PDF/JPG/PNG.');
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($f['tmp_name']);
if (!in_array($mime, ['application/pdf','image/jpeg','image/png'])) fail('Tipe file tidak diizinkan.');

// Cegah NIM ganda pada gelombang yang sama (opsional tapi disarankan)
$stmt = $conn->prepare('SELECT id FROM pendaftar WHERE nim = ? AND gelombang = ? LIMIT 1');
$stmt->bind_param('ss', $nim, $gelombang);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) fail('NIM ini sudah terdaftar pada gelombang tersebut. Cek status pendaftaran.');
$stmt->close();

// Generate reg_no unik
do {
    $regNo = 'AIK-UMGO-2026-' . random_int(1000, 9999);
    $c = $conn->prepare('SELECT id FROM pendaftar WHERE reg_no = ? LIMIT 1');
    $c->bind_param('s', $regNo);
    $c->execute();
    $c->store_result();
    $exists = $c->num_rows > 0;
    $c->close();
} while ($exists);

// Simpan file
$uploadDir = __DIR__ . '/uploads';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
$safeName = $regNo . '_' . preg_replace('/[^A-Za-z0-9._-]/', '_', basename($f['name']));
$target = $uploadDir . '/' . $safeName;
if (!move_uploaded_file($f['tmp_name'], $target)) fail('Gagal menyimpan berkas.');

$fakultasLabel = $fakultasMap[$fakultas];
$stmt = $conn->prepare('INSERT INTO pendaftar (user_id, penguji_id, reg_no, nama, nim, fakultas, fakultas_label, prodi, semester, hp, email, kategori, jalur, gelombang, file_krs, status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,"MENUNGGU")');
$stmt->bind_param('iisssssssssssss', $userId, $pengujiId, $regNo, $nama, $nim, $fakultas, $fakultasLabel, $prodi, $semester, $hp, $email, $kategori, $jalur, $gelombang, $safeName);
if (!$stmt->execute()) {
    // Kemungkinan race-condition: kuota habis bersamaan
    if (strpos($stmt->error, 'Duplicate') === false) {
        $cekAkhir = $conn->query("SELECT COUNT(*) c FROM pendaftar WHERE penguji_id = $pengujiId")->fetch_assoc()['c'];
        if ((int)$cekAkhir >= $kuotaPg) { @unlink($target); fail('Penguji ' . $pgRow['nama'] . " baru saja penuh ($cekAkhir/$kuotaPg). Pilih penguji lain."); }
    }
    @unlink($target);
    fail('Gagal menyimpan: ' . $stmt->error);
}
$stmt->close();

header('Location: index.php?tab=status&success=' . urlencode('Pendaftaran berhasil! No. Registrasi: ' . $regNo) . '&reg=' . urlencode($regNo));
exit;

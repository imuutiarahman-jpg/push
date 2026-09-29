<?php
// config/db.php - Koneksi MySQL XAMPP + auto-install tabel
$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = ''; // default XAMPP kosong
$DB_NAME = 'db_btq_umgo';

// 1. Konek tanpa DB dulu untuk auto-create database
$tmp = new mysqli($DB_HOST, $DB_USER, $DB_PASS);
if ($tmp->connect_error) {
    die('MySQL belum jalan: ' . htmlspecialchars($tmp->connect_error) .
        '<br>1. Buka XAMPP Control Panel<br>2. Klik Start pada Apache + MySQL');
}
$tmp->query("CREATE DATABASE IF NOT EXISTS `$DB_NAME` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
$tmp->close();

// 2. Konek ke DB
$conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($conn->connect_error) {
    die('Koneksi database gagal: ' . htmlspecialchars($conn->connect_error));
}
$conn->set_charset('utf8mb4');

// 3. Auto-create tabel jika belum ada (fix error "Table doesn't exist")
$conn->query("CREATE TABLE IF NOT EXISTS pendaftar (
  id INT AUTO_INCREMENT PRIMARY KEY,
  reg_no VARCHAR(30) NOT NULL UNIQUE,
  nama VARCHAR(100) NOT NULL,
  nim VARCHAR(30) NOT NULL,
  fakultas VARCHAR(10) NOT NULL,
  fakultas_label VARCHAR(100) NOT NULL,
  prodi VARCHAR(100) NOT NULL,
  semester VARCHAR(30) NOT NULL,
  hp VARCHAR(20) NOT NULL,
  email VARCHAR(100) NOT NULL,
  kategori VARCHAR(100) NOT NULL,
  jalur ENUM('Reguler','NonReg','S2','RPL') NOT NULL DEFAULT 'Reguler',
  gelombang VARCHAR(150) NOT NULL,
  file_krs VARCHAR(255) DEFAULT NULL,
  status ENUM('MENUNGGU','TERVERIFIKASI','DITOLAK') NOT NULL DEFAULT 'MENUNGGU',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_nim (nim),
  INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS admin (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// 4. Seed akun default admin/admin123 jika kosong
$cek = $conn->query("SELECT COUNT(*) c FROM admin");
if ($cek && (int)$cek->fetch_assoc()['c'] === 0) {
    $hash = '$2y$10$EgzvNAQGRilJAdTcFxbtX.O4IxSIPXLMw8gLdx5OgyJemR6P3XQKG'; // admin123
    $s = $conn->prepare("INSERT INTO admin (username, password_hash) VALUES ('admin', ?)");
    $s->bind_param('s', $hash);
    $s->execute();
    $s->close();
}

// 5. Tabel users (login NIM + password) + relasi ke pendaftar
$conn->query("CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nim VARCHAR(30) NOT NULL UNIQUE,
  nama VARCHAR(100) NOT NULL,
  email VARCHAR(100) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_nim (nim)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Tambah kolom user_id di pendaftar jika belum ada (untuk data lama NULL)
$col = $conn->query("SHOW COLUMNS FROM pendaftar LIKE 'user_id'");
if ($col && $col->num_rows === 0) {
    $conn->query("ALTER TABLE pendaftar ADD COLUMN user_id INT DEFAULT NULL AFTER id, ADD INDEX idx_user (user_id)");
}

// 6. Tabel penguji (nama + akun login + kuota per penguji) + relasi ke pendaftar
$conn->query("CREATE TABLE IF NOT EXISTS penguji (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nama VARCHAR(100) NOT NULL UNIQUE,
  username VARCHAR(50) DEFAULT NULL UNIQUE,
  password_hash VARCHAR(255) DEFAULT NULL,
  kuota INT NOT NULL DEFAULT 5,
  aktif TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Migrasi untuk data lama: tambah username + password_hash + kuota jika belum ada
$colU = $conn->query("SHOW COLUMNS FROM penguji LIKE 'username'");
if ($colU && $colU->num_rows === 0) {
    $conn->query("ALTER TABLE penguji ADD COLUMN username VARCHAR(50) DEFAULT NULL UNIQUE AFTER nama");
}
$colPw = $conn->query("SHOW COLUMNS FROM penguji LIKE 'password_hash'");
if ($colPw && $colPw->num_rows === 0) {
    $conn->query("ALTER TABLE penguji ADD COLUMN password_hash VARCHAR(255) DEFAULT NULL AFTER username");
}
$colK = $conn->query("SHOW COLUMNS FROM penguji LIKE 'kuota'");
if ($colK && $colK->num_rows === 0) {
    $conn->query("ALTER TABLE penguji ADD COLUMN kuota INT NOT NULL DEFAULT 5 AFTER password_hash");
}

$colP = $conn->query("SHOW COLUMNS FROM pendaftar LIKE 'penguji_id'");
if ($colP && $colP->num_rows === 0) {
    $conn->query("ALTER TABLE pendaftar ADD COLUMN penguji_id INT DEFAULT NULL AFTER user_id, ADD INDEX idx_penguji (penguji_id)");
}

// Migrasi jalur mahasiswa (Reguler/NonReg/S2/RPL) untuk data lama
$colJ = $conn->query("SHOW COLUMNS FROM pendaftar LIKE 'jalur'");
if ($colJ && $colJ->num_rows === 0) {
    $conn->query("ALTER TABLE pendaftar ADD COLUMN jalur ENUM('Reguler','NonReg','S2','RPL') NOT NULL DEFAULT 'Reguler' AFTER kategori");
}

// 8. Tabel jadwal (sesi/gelombang + tempat & waktu, dikelola admin)
$conn->query("CREATE TABLE IF NOT EXISTS jadwal (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nama VARCHAR(100) NOT NULL,
  tanggal DATE NOT NULL,
  jam VARCHAR(50) NOT NULL DEFAULT '',
  tempat VARCHAR(150) NOT NULL DEFAULT '',
  aktif TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_aktif_tanggal (aktif, tanggal)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Seed 3 sesi awal jika kosong (menggantikan opsi hardcoded lama)
$cekJ = $conn->query("SELECT COUNT(*) c FROM jadwal");
if ($cekJ && (int)$cekJ->fetch_assoc()['c'] === 0) {
    $seed = $conn->prepare("INSERT INTO jadwal (nama, tanggal, jam, tempat) VALUES (?,?,?,?)");
    $rows = [
        ['Gelombang I', '2026-09-26', '08.00 WITA', 'Gedung UPT AIK UMGO'],
        ['Gelombang II', '2026-10-03', '08.00 WITA', 'Gedung UPT AIK UMGO'],
        ['Gelombang III', '2026-10-10', '13.00 WITA', 'Gedung UPT AIK UMGO'],
    ];
    foreach ($rows as $rw) { $seed->bind_param('ssss', $rw[0], $rw[1], $rw[2], $rw[3]); $seed->execute(); }
    $seed->close();
}

// 7. Tabel hasil_ujian (nilai penguji per pendaftar, 1 baris per pendaftar)
$conn->query("CREATE TABLE IF NOT EXISTS hasil_ujian (
  id INT AUTO_INCREMENT PRIMARY KEY,
  pendaftar_id INT NOT NULL UNIQUE,
  tajwid INT NOT NULL DEFAULT 0,
  kelancaran INT NOT NULL DEFAULT 0,
  adab INT NOT NULL DEFAULT 0,
  hafalan INT NOT NULL DEFAULT 0,
  total DECIMAL(5,2) NOT NULL DEFAULT 0,
  grade VARCHAR(2) NOT NULL DEFAULT 'D',
  predikat VARCHAR(100) NOT NULL DEFAULT '',
  status_lulus ENUM('LULUS','TIDAK LULUS') NOT NULL DEFAULT 'TIDAK LULUS',
  catatan TEXT DEFAULT NULL,
  bahan_ayat VARCHAR(200) DEFAULT NULL,
  penguji_nama VARCHAR(100) DEFAULT NULL,
  tanggal_ujian DATE DEFAULT NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_pendaftar (pendaftar_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// 9. Tabel fakultas + prodi (dikelola admin, form pendaftaran dinamis dari sini)
$conn->query("CREATE TABLE IF NOT EXISTS fakultas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  kode VARCHAR(10) NOT NULL UNIQUE,
  label VARCHAR(150) NOT NULL,
  aktif TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$conn->query("CREATE TABLE IF NOT EXISTS prodi (
  id INT AUTO_INCREMENT PRIMARY KEY,
  fakultas_id INT NOT NULL,
  nama VARCHAR(150) NOT NULL,
  aktif TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_prodi (fakultas_id, nama),
  INDEX idx_fak (fakultas_id),
  CONSTRAINT fk_prodi_fak FOREIGN KEY (fakultas_id) REFERENCES fakultas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Seed fakultas + prodi awal jika kosong (data existing dari form lama)
$cekF = $conn->query("SELECT COUNT(*) c FROM fakultas");
if ($cekF && (int)$cekF->fetch_assoc()['c'] === 0) {
    $seedFak = [
        ['FST', 'Fakultas Sains & Teknologi (FST)'],
        ['FIKES', 'Fakultas Ilmu Kesehatan (FIKES)'],
        ['FKIP', 'Fakultas Keguruan & Ilmu Pendidikan (FKIP)'],
        ['FAI', 'Fakultas Agama Islam (FAI)'],
        ['FIS', 'Fakultas Ilmu Sosial (FIS)'],
        ['FK', 'Fakultas Kedokteran (FK)'],
    ];
    $seedProdi = [
        'FST' => ['S1 Bisnis Digital','S1 Sistem Informasi','S1 Ilmu Komputer','S1 Agribisnis','S1 Peternakan'],
        'FIKES' => ['S1 Keperawatan','Profesi Ners','S1 Kebidanan','S1 Informatika Medis','S1 Keperawatan Anestesi'],
        'FKIP' => ['S1 PGSD','S1 Pendidikan Olahraga','S1 Pendidikan Matematika'],
        'FAI' => ['S1 Hukum Keluarga Islam (Ahwal Syakhshiyyah)','S1 Komunikasi Penyiaran Islam'],
        'FIS' => ['S1 Manajemen','S1 Akuntansi','S1 Ekonomi Syariah'],
        'FK' => ['S1 Kedokteran'],
    ];
    $insF = $conn->prepare("INSERT INTO fakultas (kode, label) VALUES (?, ?)");
    foreach ($seedFak as $sf) { $insF->bind_param('ss', $sf[0], $sf[1]); $insF->execute(); }
    $insF->close();
    $insP = $conn->prepare("INSERT INTO prodi (fakultas_id, nama) SELECT id, ? FROM fakultas WHERE kode = ?");
    foreach ($seedProdi as $kode => $list) {
        foreach ($list as $nm) { $insP->bind_param('ss', $nm, $kode); $insP->execute(); }
    }
    $insP->close();
}

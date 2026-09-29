-- Database UPT AIK UMGO - Ujian BTQ
-- Import via phpMyAdmin: http://localhost/phpmyadmin -> Import -> pilih file ini
-- Atau via shell: mysql -u root < database.sql

CREATE DATABASE IF NOT EXISTS db_btq_umgo CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE db_btq_umgo;

-- Tabel pendaftar
CREATE TABLE IF NOT EXISTS pendaftar (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT DEFAULT NULL,
  penguji_id INT DEFAULT NULL,
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
  INDEX idx_status (status),
  INDEX idx_user (user_id),
  INDEX idx_penguji (penguji_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabel penguji (nama + akun login + kuota per penguji)
CREATE TABLE IF NOT EXISTS penguji (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nama VARCHAR(100) NOT NULL UNIQUE,
  username VARCHAR(50) DEFAULT NULL UNIQUE,
  password_hash VARCHAR(255) DEFAULT NULL,
  kuota INT NOT NULL DEFAULT 5,
  aktif TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabel users (login NIM + password)
CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nim VARCHAR(30) NOT NULL UNIQUE,
  nama VARCHAR(100) NOT NULL,
  email VARCHAR(100) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_nim (nim)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabel admin (single admin)
CREATE TABLE IF NOT EXISTS admin (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabel jadwal (sesi/gelombang + tempat & waktu, dikelola admin)
CREATE TABLE IF NOT EXISTS jadwal (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nama VARCHAR(100) NOT NULL,
  tanggal DATE NOT NULL,
  jam VARCHAR(50) NOT NULL DEFAULT '',
  tempat VARCHAR(150) NOT NULL DEFAULT '',
  aktif TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_aktif_tanggal (aktif, tanggal)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO jadwal (nama, tanggal, jam, tempat) VALUES
('Gelombang I', '2026-09-26', '08.00 WITA', 'Gedung UPT AIK UMGO'),
('Gelombang II', '2026-10-03', '08.00 WITA', 'Gedung UPT AIK UMGO'),
('Gelombang III', '2026-10-10', '13.00 WITA', 'Gedung UPT AIK UMGO');

-- Tabel hasil_ujian (nilai penguji, 1 baris per pendaftar)
CREATE TABLE IF NOT EXISTS hasil_ujian (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Akun default: username `admin`, password `admin123`
-- Ganti password setelah login via SQL: UPDATE admin SET password_hash = ... ;
INSERT INTO admin (username, password_hash) VALUES
('admin', '$2y$10$EgzvNAQGRilJAdTcFxbtX.O4IxSIPXLMw8gLdx5OgyJemR6P3XQKG')
ON DUPLICATE KEY UPDATE username = VALUES(username);

<?php
session_start();
if (!isset($_SESSION['admin'])) { header('Location: login.php'); exit; }
require __DIR__ . '/../config/db.php';

$status = $_GET['status'] ?? 'ALL';
$search = trim($_GET['q'] ?? '');

$sql = 'SELECT p.reg_no,p.nama,p.nim,p.fakultas,p.prodi,p.semester,p.hp,p.email,p.kategori,p.jalur,p.gelombang,g.nama AS penguji,p.status,p.created_at FROM pendaftar p LEFT JOIN penguji g ON g.id = p.penguji_id WHERE 1=1';
$params = []; $types = '';
if (in_array($status, ['MENUNGGU','TERVERIFIKASI','DITOLAK'], true)) { $sql .= ' AND p.status = ?'; $params[] = $status; $types .= 's'; }
if ($search !== '') { $sql .= ' AND (p.nama LIKE ? OR p.nim LIKE ? OR p.reg_no LIKE ?)'; $like = "%$search%"; array_push($params, $like, $like, $like); $types .= 'sss'; }
$sql .= ' ORDER BY created_at DESC';
$stmt = $conn->prepare($sql);
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=Rekap_BTQ_UMGO_' . date('Ymd_His') . '.csv');
$out = fopen('php://output', 'w');
fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM agar Excel tampil benar
fputcsv($out, ['No Registrasi','Nama','NIM','Fakultas','Prodi','Semester','HP','Email','Kategori','Jalur','Gelombang','Penguji','Status','Waktu Daftar']);
foreach ($rows as $r) fputcsv($out, $r);
fclose($out);
exit;

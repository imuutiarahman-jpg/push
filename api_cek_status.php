<?php
header('Content-Type: application/json');
require __DIR__ . '/config/db.php';
$q = trim($_GET['q'] ?? '');
if ($q === '') { echo json_encode(['found' => false]); exit; }
$stmt = $conn->prepare('SELECT p.id, p.reg_no, p.nama, p.nim, p.prodi, p.kategori, p.jalur, p.gelombang, p.status, g.nama AS penguji FROM pendaftar p LEFT JOIN penguji g ON g.id = p.penguji_id WHERE p.nim = ? OR p.reg_no = ? LIMIT 1');
$stmt->bind_param('ss', $q, $q);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$data) { echo json_encode(['found' => false]); exit; }
// Sertakan hasil ujian jika sudah dinilai
$hasil = null;
$stmt2 = $conn->prepare('SELECT total, grade, predikat, status_lulus FROM hasil_ujian WHERE pendaftar_id = ? LIMIT 1');
$stmt2->bind_param('i', $data['id']);
$stmt2->execute();
$hasil = $stmt2->get_result()->fetch_assoc();
$stmt2->close();
echo json_encode(['found' => true, 'data' => $data, 'hasil' => $hasil]);

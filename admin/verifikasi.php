<?php
session_start();
if (!isset($_SESSION['admin'])) { header('Location: login.php'); exit; }
require __DIR__ . '/../config/db.php';

$id = (int)($_GET['id'] ?? 0);
$aksi = $_GET['aksi'] ?? '';
if ($id <= 0) { header('Location: dashboard.php'); exit; }

if ($aksi === 'HAPUS') {
    $s = $conn->prepare('SELECT file_krs FROM pendaftar WHERE id = ? LIMIT 1');
    $s->bind_param('i', $id);
    $s->execute();
    $row = $s->get_result()->fetch_assoc();
    $s->close();
    if ($row && $row['file_krs']) @unlink(__DIR__ . '/../uploads/' . $row['file_krs']);
    $d = $conn->prepare('DELETE FROM pendaftar WHERE id = ?');
    $d->bind_param('i', $id);
    $d->execute();
    $d->close();
    header('Location: dashboard.php?msg=' . urlencode('Data dihapus.'));
    exit;
}

if (in_array($aksi, ['TERVERIFIKASI','DITOLAK'], true)) {
    $u = $conn->prepare('UPDATE pendaftar SET status = ? WHERE id = ?');
    $u->bind_param('si', $aksi, $id);
    $u->execute();
    $u->close();
    header('Location: dashboard.php?msg=' . urlencode("Status ID $id -> $aksi."));
    exit;
}

header('Location: dashboard.php');
exit;

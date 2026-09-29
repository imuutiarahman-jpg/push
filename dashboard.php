<?php
// dashboard.php lama sudah dipindah ke tab "Cek Status Registrasi" di index.php
// File ini dipertahankan agar link lama tidak rusak.
session_start();
$q = http_build_query(array_filter([
    'tab' => 'status',
    'success' => $_GET['success'] ?? null,
    'reg' => $_GET['reg'] ?? null,
]));
header('Location: index.php' . ($q ? "?$q" : ''));
exit;

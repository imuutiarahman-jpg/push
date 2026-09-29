<?php
session_start();
require __DIR__ . '/config/db.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
$uid = (int)$_SESSION['user_id'];
$aksi = $_POST['aksi'] ?? '';
$back = 'index.php?tab=akun';

if ($aksi === 'profil') {
    $nama = trim($_POST['nama'] ?? '');
    $email = trim($_POST['email'] ?? '');
    if ($nama === '' || $email === '') $err = 'Nama dan email wajib diisi.';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $err = 'Email tidak valid.';
    else {
        $s = $conn->prepare('UPDATE users SET nama = ?, email = ? WHERE id = ?');
        $s->bind_param('ssi', $nama, $email, $uid);
        if ($s->execute()) {
            $_SESSION['nama'] = $nama;
            // sinkron nama/email ke pendaftaran yg masih MENUNGGU (opsional)
            $p = $conn->prepare('UPDATE pendaftar SET nama = ?, email = ? WHERE user_id = ? AND status = "MENUNGGU"');
            $p->bind_param('ssi', $nama, $email, $uid);
            $p->execute();
            $p->close();
            header("Location: $back&akun_msg=" . urlencode('Profil berhasil disimpan.'));
            exit;
        } else $err = 'Gagal simpan: ' . $s->error;
        $s->close();
    }
    header("Location: $back&akun_err=" . urlencode($err ?? 'Gagal.'));
    exit;
}

if ($aksi === 'password') {
    $lama = $_POST['lama'] ?? '';
    $baru = $_POST['baru'] ?? '';
    $baru2 = $_POST['baru2'] ?? '';
    $s = $conn->prepare('SELECT password_hash FROM users WHERE id = ? LIMIT 1');
    $s->bind_param('i', $uid);
    $s->execute();
    $row = $s->get_result()->fetch_assoc();
    $s->close();
    if (!$row || !password_verify($lama, $row['password_hash'])) $err = 'Password lama salah.';
    elseif (strlen($baru) < 6) $err = 'Password baru minimal 6 karakter.';
    elseif ($baru !== $baru2) $err = 'Konfirmasi password tidak sama.';
    else {
        $hash = password_hash($baru, PASSWORD_DEFAULT);
        $u = $conn->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $u->bind_param('si', $hash, $uid);
        $u->execute();
        $u->close();
        header("Location: $back&akun_msg=" . urlencode('Password berhasil diubah.'));
        exit;
    }
    header("Location: $back&akun_err=" . urlencode($err));
    exit;
}

header("Location: $back");
exit;

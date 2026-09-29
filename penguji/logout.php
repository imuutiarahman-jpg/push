<?php
session_start();
unset($_SESSION['penguji_id'], $_SESSION['penguji_nama'], $_SESSION['penguji_username']);
header('Location: login.php');
exit;

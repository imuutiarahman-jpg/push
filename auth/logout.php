<?php
session_start();
unset($_SESSION['user_id'], $_SESSION['nim'], $_SESSION['nama']);
header('Location: login.php');
exit;

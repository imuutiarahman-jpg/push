<?php
// penguji/index.php - redirect cerdas
session_start();
if (isset($_SESSION['penguji_id'])) { header('Location: dashboard.php'); exit; }
header('Location: login.php');
exit;

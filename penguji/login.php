<?php
// Portal penguji dilebur ke login utama agar tidak dikira bagian admin.
header('Location: ../login.php?tab=penguji');
exit;

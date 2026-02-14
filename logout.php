<?php
// logout.php
session_start();
session_unset();
session_destroy();
header("Location: /local_services/index.php");
exit;
?>
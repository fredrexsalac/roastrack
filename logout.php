<?php
session_start();
session_unset();
session_destroy();
$base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
header('Location: ' . ($base ?: '/') . '/catalog.php');
exit;
?>

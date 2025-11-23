<?php
session_start();
session_unset();
session_destroy();
require_once __DIR__ . '/db.php';
$pdo = db();

// Base path for links when hosted under a subfolder
$base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
if ($base === '.' || $base === '\\') {
  $base = '';
}
// If current script is inside /admin, lift base one level up so assets resolve from /public
if (basename($base) === 'admin') {
  $base = rtrim(dirname($base), '/\\');
}

header('Location: ' . $base . '/catalog.php');
exit;
?>

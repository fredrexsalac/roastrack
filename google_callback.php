<?php
session_start();
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

$configPath = __DIR__ . '/google_oauth.php';
if (!file_exists($configPath)) { $configPath = __DIR__ . '/google_oauth.sample.php'; }
$cfg = require $configPath;

if (isset($_GET['error'])){
  header('Location: ' . $base . '/login.php');
  exit;
}

$code = $_GET['code'] ?? '';
if ($code === ''){
  header('Location: ' . $base . '/login.php');
  exit;
}

// 1) Exchange code for tokens
$tokenUrl = 'https://oauth2.googleapis.com/token';
$post = [
  'code' => $code,
  'client_id' => $cfg['client_id'],
  'client_secret' => $cfg['client_secret'],
  'redirect_uri' => $cfg['redirect_uri'],
  'grant_type' => 'authorization_code',
];

$ch = curl_init($tokenUrl);
curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_POST => true,
  CURLOPT_POSTFIELDS => http_build_query($post),
  CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
]);
$resp = curl_exec($ch);
$err = curl_error($ch);
curl_close($ch);
if ($err || !$resp){ header('Location: ' . $base . '/login.php'); exit; }
$tok = json_decode($resp, true);
$access = $tok['access_token'] ?? null;
if (!$access){ header('Location: ' . $base . '/login.php'); exit; }

// 2) Get userinfo
$ch = curl_init('https://openidconnect.googleapis.com/v1/userinfo');
curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $access],
]);
$uinfo = curl_exec($ch);
$err = curl_error($ch);
curl_close($ch);
if ($err || !$uinfo){ header('Location: ' . $base . '/login.php'); exit; }
$u = json_decode($uinfo, true);
$googleId = $u['sub'] ?? null;
$email = $u['email'] ?? '';
$name = $u['name'] ?? '';
$username = $email ? strtok($email, '@') : ('google_' . substr($googleId ?? bin2hex(random_bytes(4)), 0, 8));

$hasGoogleId = true;
try {
  // Will fail if column doesn't exist
  $stmt = $pdo->prepare('SELECT id, username, full_name, phone, role FROM users WHERE google_id = ?');
  $stmt->execute([$googleId]);
  $existing = $stmt->fetch();
} catch (Throwable $e) {
  $hasGoogleId = false;
  $stmt = $pdo->prepare('SELECT id, username, full_name, phone, role FROM users WHERE username = ?');
  $stmt->execute([$username]);
  $existing = $stmt->fetch();
}

if ($existing){
  $_SESSION['user'] = [
    'id'=>$existing['id'],
    'username'=>$existing['username'],
    'full_name'=>$existing['full_name'] ?? null,
    'phone'=>$existing['phone'] ?? null,
    'role'=>$existing['role'] ?? 'customer'
  ];
  header('Location: ' . $base . '/catalog.php');
  exit;
}

// Create user as customer
try {
  if ($hasGoogleId) {
    $ins = $pdo->prepare('INSERT INTO users (username, password_hash, role, full_name, phone, email, google_id) VALUES (?,?,?,?,?,?,?)');
    $ins->execute([$username, password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT), 'customer', $name ?: $username, '', $email ?: null, $googleId]);
  } else {
    $ins = $pdo->prepare('INSERT INTO users (username, password_hash, role, full_name, phone, email) VALUES (?,?,?,?,?,?)');
    $ins->execute([$username, password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT), 'customer', $name ?: $username, '', $email ?: null]);
  }
  $uid = (int)$pdo->lastInsertId();
  $_SESSION['user'] = [ 'id'=>$uid, 'username'=>$username, 'full_name'=>$name ?: $username, 'phone'=>null, 'role'=>'customer' ];
  header('Location: ' . $base . '/catalog.php');
  exit;
} catch (Throwable $e) {
  header('Location: ' . $base . '/login.php');
  exit;
}
?>

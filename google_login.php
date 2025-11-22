<?php
session_start();
$configPath = __DIR__ . '/google_oauth.php';
if (!file_exists($configPath)) { $configPath = __DIR__ . '/google_oauth.sample.php'; }
$cfg = require $configPath;

$params = [
  'client_id' => $cfg['client_id'],
  'redirect_uri' => $cfg['redirect_uri'],
  'response_type' => 'code',
  'scope' => implode(' ', $cfg['scopes']),
  'access_type' => 'online',
  'include_granted_scopes' => 'true',
  'prompt' => 'select_account',
];

$url = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
header('Location: ' . $url);
exit;

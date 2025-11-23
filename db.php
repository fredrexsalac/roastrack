<?php
// Simple direct DB connection for XAMPP
// Adjust credentials if your MySQL setup differs.

function db(): PDO {
  static $pdo = null;
  if ($pdo instanceof PDO) return $pdo;

  $host = getenv('DB_HOST') ?: 'db.fr-pari1.bengt.wasmernet.com';
  $port = getenv('DB_PORT') ?: '10272';
  $db   = getenv('DB_NAME') ?: 'dbWmD9B8xfgps59qq7muuCyz';
  $user = getenv('DB_USER') ?: '292fd2c57330800077c7e94f2811';
  $pass = getenv('DB_PASS') ?: '0692292f-d2c5-74e8-8000-90f1075ff0e6';
  $charset = 'utf8mb4';

  $dsn = "mysql:host={$host};port={$port};dbname={$db};charset={$charset}";
  $options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
  ];

  $pdo = new PDO($dsn, $user, $pass, $options);
  return $pdo;
}

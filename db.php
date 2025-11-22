<?php
// Simple direct DB connection for XAMPP
// Adjust credentials if your MySQL setup differs.

function db(): PDO {
  static $pdo = null;
  if ($pdo instanceof PDO) return $pdo;

  $host = getenv('DB_HOST') ?: 'db.fr-pari1.bengt.wasmernet.com';
  $port = getenv('DB_PORT') ?: '10272';
  $db   = getenv('DB_NAME') ?: 'dbhQeyWKy3w75MzrX86oRDec';
  $user = getenv('DB_USER') ?: '1f503aaa78138000849f189a3aea';
  $pass = getenv('DB_PASS') ?: '06921f50-3aaa-7958-8000-9e75d554b5bb';
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

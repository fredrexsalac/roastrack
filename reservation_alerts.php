<?php
session_start();
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
require_once __DIR__ . '/db.php';

$user = $_SESSION['user'] ?? null;
if (!$user) {
  http_response_code(401);
  echo json_encode(['error' => 'auth']);
  exit;
}
if (in_array(($user['role'] ?? ''), ['admin', 'staff'], true)) {
  http_response_code(403);
  echo json_encode(['error' => 'forbidden']);
  exit;
}

$pdo = db();
$userId = (int)($user['id'] ?? 0);
if ($userId <= 0) {
  http_response_code(400);
  echo json_encode(['error' => 'invalid']);
  exit;
}

$stmt = $pdo->prepare("SELECT id, status FROM reservations WHERE user_id = ? AND status IN ('PENDING','CONFIRMED','IN_PROCESS','READY') ORDER BY id DESC LIMIT 200");
$stmt->execute([$userId]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
$payload = array_map(static function(array $row){
  return [
    'id' => (int)$row['id'],
    'status' => $row['status']
  ];
}, $rows);

echo json_encode(['reservations' => $payload]);

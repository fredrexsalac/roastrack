<?php
// Returns JSON { id, status } for a reservation
header('Content-Type: application/json');
session_start();
require_once __DIR__ . '/db.php';
$pdo = db();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0){ echo json_encode(['error'=>'invalid']); exit; }

$stmt = $pdo->prepare('SELECT id, status FROM reservations WHERE id = ?');
$stmt->execute([$id]);
$row = $stmt->fetch();
if (!$row){ echo json_encode(['error'=>'not_found']); exit; }

echo json_encode(['id'=>(int)$row['id'], 'status'=>$row['status']]);

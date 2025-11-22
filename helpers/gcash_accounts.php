<?php
require_once __DIR__ . '/../db.php';

function gcash_table_name(): string {
  return 'gcash_accounts';
}

function gcash_ensure_table(PDO $pdo): void {
  static $done = false;
  if ($done) return;
  $table = gcash_table_name();
  $exists = $pdo->query("SHOW TABLES LIKE '" . $table . "'")->fetchColumn();
  if (!$exists) {
    $pdo->exec("CREATE TABLE `$table` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `label` VARCHAR(100) NOT NULL,
      `account_name` VARCHAR(150) NOT NULL,
      `account_number` VARCHAR(50) NOT NULL,
      `instructions` TEXT NULL,
      `is_active` TINYINT(1) NOT NULL DEFAULT 1,
      `is_verified` TINYINT(1) NOT NULL DEFAULT 0,
      `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
  }
  gcash_ensure_verified_column($pdo);
  $done = true;
}

function gcash_get_accounts(PDO $pdo, bool $onlyActive = true): array {
  gcash_ensure_table($pdo);
  $sql = 'SELECT id, label, account_name, account_number, instructions, is_active, is_verified FROM ' . gcash_table_name();
  if ($onlyActive) {
    $sql .= ' WHERE is_active = 1';
  }
  $sql .= ' ORDER BY id ASC';
  return $pdo->query($sql)->fetchAll();
}

function gcash_get_account(PDO $pdo, int $id): ?array {
  gcash_ensure_table($pdo);
  $stmt = $pdo->prepare('SELECT id, label, account_name, account_number, instructions, is_active, is_verified FROM ' . gcash_table_name() . ' WHERE id = ?');
  $stmt->execute([$id]);
  $row = $stmt->fetch();
  return $row ?: null;
}

function gcash_ensure_reservation_columns(PDO $pdo): void {
  static $checked = false;
  if ($checked) return;
  $describe = $pdo->query("SHOW COLUMNS FROM reservations LIKE 'gcash_account_label'")->fetch();
  if (!$describe) {
    $pdo->exec("ALTER TABLE reservations ADD COLUMN gcash_account_label VARCHAR(150) NULL AFTER gcash_proof_path, ADD COLUMN gcash_account_number VARCHAR(50) NULL AFTER gcash_account_label");
  }
  $checked = true;
}

function gcash_create_account(PDO $pdo, string $label, string $accountName, string $accountNumber, ?string $instructions = null, bool $isActive = true, bool $isVerified = false): int {
  gcash_ensure_table($pdo);
  $stmt = $pdo->prepare('INSERT INTO ' . gcash_table_name() . ' (label, account_name, account_number, instructions, is_active, is_verified) VALUES (?,?,?,?,?,?)');
  $stmt->execute([$label, $accountName, $accountNumber, $instructions, $isActive ? 1 : 0, $isVerified ? 1 : 0]);
  return (int)$pdo->lastInsertId();
}

function gcash_update_account(PDO $pdo, int $id, string $label, string $accountName, string $accountNumber, ?string $instructions = null, bool $isActive = true, bool $isVerified = false): bool {
  if ($id <= 0) {
    return false;
  }
  gcash_ensure_table($pdo);
  $stmt = $pdo->prepare('UPDATE ' . gcash_table_name() . ' SET label = ?, account_name = ?, account_number = ?, instructions = ?, is_active = ?, is_verified = ? WHERE id = ?');
  return $stmt->execute([$label, $accountName, $accountNumber, $instructions, $isActive ? 1 : 0, $isVerified ? 1 : 0, $id]);
}

function gcash_delete_account(PDO $pdo, int $id): bool {
  if ($id <= 0) {
    return false;
  }
  gcash_ensure_table($pdo);
  $stmt = $pdo->prepare('DELETE FROM ' . gcash_table_name() . ' WHERE id = ?');
  return $stmt->execute([$id]);
}

function gcash_ensure_verified_column(PDO $pdo): void {
  $table = gcash_table_name();
  $col = $pdo->query("SHOW COLUMNS FROM `$table` LIKE 'is_verified'")->fetch();
  if (!$col) {
    $pdo->exec("ALTER TABLE `$table` ADD COLUMN `is_verified` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_active`");
  }
}

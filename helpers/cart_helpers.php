<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

const RR_CART_MAX_QTY = 50;

function rr_get_cart(): array {
  if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
  }
  return $_SESSION['cart'];
}

function rr_cart_count(): int {
  return array_sum(array_map('intval', rr_get_cart()));
}

function rr_cart_add(int $itemId, int $qty): void {
  $qty = rr_normalize_qty($qty);
  $cart = rr_get_cart();
  $cart[$itemId] = ($cart[$itemId] ?? 0) + $qty;
  if ($cart[$itemId] > RR_CART_MAX_QTY) {
    $cart[$itemId] = RR_CART_MAX_QTY;
  }
  rr_save_cart($cart);
}

function rr_cart_set(int $itemId, int $qty): void {
  $cart = rr_get_cart();
  $qty = rr_normalize_qty($qty);
  if ($qty <= 0) {
    unset($cart[$itemId]);
  } else {
    $cart[$itemId] = $qty;
  }
  rr_save_cart($cart);
}

function rr_cart_remove(int $itemId): void {
  $cart = rr_get_cart();
  unset($cart[$itemId]);
  rr_save_cart($cart);
}

function rr_cart_clear(): void {
  $_SESSION['cart'] = [];
}

function rr_normalize_qty(int $qty): int {
  if ($qty < 1) {
    return 1;
  }
  if ($qty > RR_CART_MAX_QTY) {
    return RR_CART_MAX_QTY;
  }
  return $qty;
}

function rr_save_cart(array $cart): void {
  $_SESSION['cart'] = array_filter(
    array_map('intval', $cart),
    fn($qty) => $qty > 0
  );
}

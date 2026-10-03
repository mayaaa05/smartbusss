<?php
require_once 'config/db_connect.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$pnr = trim($_GET['pnr'] ?? '');
if (empty($pnr)) {
    header("Location: my_bookings.php");
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM bookings WHERE booking_pnr = ? AND user_id = ?");
$stmt->execute([$pnr, $_SESSION['user_id']]);
$booking = $stmt->fetch();

if (!$booking) {
    header("Location: my_bookings.php?msg=" . urlencode("Error: Booking not found or unauthorized."));
    exit;
}

if ($booking['booking_status'] === 'Cancelled') {
    header("Location: my_bookings.php?msg=" . urlencode("This booking ($pnr) is already cancelled."));
    exit;
}

try {
    $pdo->beginTransaction();

    // 1. Calculate standard 90% refund
    $refund = round($booking['total_amount'] * 0.90, 2);

    // 2. Mark booking as Cancelled
    $update = $pdo->prepare("UPDATE bookings SET booking_status = 'Cancelled', payment_status = 'Refunded', cancellation_date = NOW(), refund_amount = ? WHERE id = ?");
    $update->execute([$refund, $booking['id']]);

    // 3. Fetch user's current wallet balance and credit refund
    $u_stmt = $pdo->prepare("SELECT wallet_balance FROM users WHERE id = ?");
    $u_stmt->execute([$booking['user_id']]);
    $current_bal = (float)$u_stmt->fetchColumn();

    $new_balance = $current_bal + $refund;
    $upd_w = $pdo->prepare("UPDATE users SET wallet_balance = ? WHERE id = ?");
    $upd_w->execute([$new_balance, $booking['user_id']]);

    // 4. Record Credit Transaction in wallet_transactions
    $ref_id = 'REF-' . date('Ymd') . '-' . rand(1000, 9999);
    $tx_stmt = $pdo->prepare("INSERT INTO wallet_transactions (user_id, type, amount, description, reference_id, balance_after) 
                              VALUES (?, 'Credit', ?, ?, ?, ?)");
    $desc = "90% Cancellation Refund for PNR: " . $pnr;
    $tx_stmt->execute([$booking['user_id'], $refund, $desc, $ref_id, $new_balance]);

    $pdo->commit();
    header("Location: my_bookings.php?msg=" . urlencode("Booking $pnr successfully cancelled. Refund of ₹" . number_format($refund, 2) . " has been credited directly to your SmartBus Wallet!"));
    exit;
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    header("Location: my_bookings.php?msg=" . urlencode("Cancellation error: " . $e->getMessage()));
    exit;
}
?>
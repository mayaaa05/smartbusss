<?php
require_once 'config/db_connect.php';
session_start();
$page_title = "Complete Payment - UPI QR & Gateway";

if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$schedule_id = (int)($_POST['schedule_id'] ?? 0);
$selected_seats_str = trim($_POST['selected_seats'] ?? '');
$selected_seats = array_filter(explode(',', $selected_seats_str));
$total_amount = (float)($_POST['total_amount'] ?? 0);
$contact_phone = trim($_POST['contact_phone'] ?? '');
$contact_email = trim($_POST['contact_email'] ?? '');
$boarding_point = trim($_POST['boarding_point'] ?? '');
$dropping_point = trim($_POST['dropping_point'] ?? '');
$passenger_names = $_POST['passenger_name'] ?? [];
$passenger_ages = $_POST['passenger_age'] ?? [];
$passenger_genders = $_POST['passenger_gender'] ?? [];

if ($schedule_id <= 0 || empty($selected_seats) || empty($passenger_names)) {
    header("Location: search_bus.php");
    exit;
}

// Fetch commuter's live wallet balance
$u_bal_stmt = $pdo->prepare("SELECT wallet_balance FROM users WHERE id = ?");
$u_bal_stmt->execute([$user_id]);
$commuter_wallet_balance = (float)$u_bal_stmt->fetchColumn();

// Final Payment Confirmation execution
if (isset($_POST['confirm_pay'])) {
    $payment_method = $_POST['payment_method'] ?? 'UPI';
    $pnr = 'SB-' . date('Y') . '-' . rand(1000, 9999);

    try {
        $pdo->beginTransaction();

        // 1. Double check seat availability inside transaction to prevent race conditions
        $placeholders = implode(',', array_fill(0, count($selected_seats), '?'));
        $check_sql = "SELECT bs.seat_number FROM booking_seats bs 
                      JOIN bookings bk ON bs.booking_id = bk.id 
                      WHERE bk.schedule_id = ? AND bk.booking_status = 'Confirmed' AND bs.seat_number IN ($placeholders)";
        $check_stmt = $pdo->prepare($check_sql);
        $check_stmt->execute(array_merge([$schedule_id], $selected_seats));
        
        if ($check_stmt->rowCount() > 0) {
            $pdo->rollBack();
            $already_booked = implode(', ', $check_stmt->fetchAll(PDO::FETCH_COLUMN));
            die('<div class="container py-5 text-center"><div class="alert alert-danger">Seat(s) ' . htmlspecialchars($already_booked) . ' were just reserved by another commuter. Please choose different seats.</div><a href="select_seat.php?schedule_id=' . $schedule_id . '" class="btn btn-primary">Return to Seat Map</a></div>');
        }

        // 2. Fetch journey date and bus fare
        $s_stmt = $pdo->prepare("SELECT journey_date, fare FROM schedules WHERE id = ?");
        $s_stmt->execute([$schedule_id]);
        $s_data = $s_stmt->fetch();

        // 3. Handle Wallet Payment or Instant Promotional Cashback
        $cashback_awarded = 0;
        if ($payment_method === 'SmartBus Wallet') {
            $u_stmt = $pdo->prepare("SELECT wallet_balance FROM users WHERE id = ?");
            $u_stmt->execute([$user_id]);
            $current_bal = (float)$u_stmt->fetchColumn();

            if ($current_bal < $total_amount) {
                $pdo->rollBack();
                die('<div class="container py-5 text-center"><div class="alert alert-danger">Insufficient wallet balance (₹' . number_format($current_bal, 2) . ' available, ₹' . number_format($total_amount, 2) . ' required). Please top up your wallet or choose UPI/Card.</div><a href="wallet.php" class="btn btn-success me-2">Go to Wallet</a><a href="select_seat.php?schedule_id=' . $schedule_id . '" class="btn btn-primary">Back to Booking</a></div>');
            }

            $new_balance = $current_bal - $total_amount;
            $upd_w = $pdo->prepare("UPDATE users SET wallet_balance = ? WHERE id = ?");
            $upd_w->execute([$new_balance, $user_id]);

            // Record Debit Transaction in Wallet
            $tx_stmt = $pdo->prepare("INSERT INTO wallet_transactions (user_id, type, amount, description, reference_id, balance_after) 
                                      VALUES (?, 'Debit', ?, ?, ?, ?)");
            $desc = "Ticket Booking Fare for PNR: " . $pnr . " (" . count($selected_seats) . " Seats)";
            $tx_stmt->execute([$user_id, $total_amount, $desc, $pnr, $new_balance]);
        } else {
            // Instant 5% Cashback on UPI / Card / Net Banking payment
            $cashback_awarded = round($total_amount * 0.05, 2);
            if ($cashback_awarded > 0) {
                $u_stmt = $pdo->prepare("SELECT wallet_balance FROM users WHERE id = ?");
                $u_stmt->execute([$user_id]);
                $current_bal = (float)$u_stmt->fetchColumn();

                $new_balance = $current_bal + $cashback_awarded;
                $upd_w = $pdo->prepare("UPDATE users SET wallet_balance = ? WHERE id = ?");
                $upd_w->execute([$new_balance, $user_id]);

                // Record Credit Cashback Transaction
                $tx_stmt = $pdo->prepare("INSERT INTO wallet_transactions (user_id, type, amount, description, reference_id, balance_after) 
                                          VALUES (?, 'Credit', ?, ?, ?, ?)");
                $desc = "5% Instant Booking Cashback for PNR: " . $pnr . " (Paid via " . $payment_method . ")";
                $tx_stmt->execute([$user_id, $cashback_awarded, $desc, 'CB-' . $pnr, $new_balance]);
            }
        }

        // 4. Insert into bookings
        $b_stmt = $pdo->prepare("INSERT INTO bookings (booking_pnr, user_id, schedule_id, journey_date, total_seats, total_amount, payment_method, payment_status, booking_status, contact_phone, contact_email, boarding_point, dropping_point) 
                                 VALUES (?, ?, ?, ?, ?, ?, ?, 'Paid', 'Confirmed', ?, ?, ?, ?)");
        $b_stmt->execute([
            $pnr, 
            $user_id, 
            $schedule_id, 
            $s_data['journey_date'], 
            count($selected_seats), 
            $total_amount, 
            $payment_method, 
            $contact_phone, 
            $contact_email, 
            $boarding_point, 
            $dropping_point
        ]);
        $booking_id = $pdo->lastInsertId();

        // 5. Insert booking seats with passenger names
        $bs_stmt = $pdo->prepare("INSERT INTO booking_seats (booking_id, seat_number, passenger_name, passenger_age, passenger_gender, fare) 
                                  VALUES (?, ?, ?, ?, ?, ?)");
        foreach ($selected_seats as $idx => $seat) {
            $name = trim($passenger_names[$idx] ?? 'Passenger ' . ($idx + 1));
            $age = (int)($passenger_ages[$idx] ?? 25);
            $gender = $passenger_genders[$idx] ?? 'Male';
            $bs_stmt->execute([$booking_id, $seat, $name, $age, $gender, $s_data['fare']]);
        }

        $pdo->commit();
        $redirect_url = "ticket.php?pnr=" . urlencode($pnr);
        if ($cashback_awarded > 0) {
            $redirect_url .= "&cb=" . urlencode($cashback_awarded);
        }
        header("Location: " . $redirect_url);
        exit;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        die("Booking Error: " . htmlspecialchars($e->getMessage()));
    }
}

// Fetch schedule details for review
$s_stmt = $pdo->prepare("SELECT s.*, b.bus_name, b.bus_type, r.source, r.destination 
                         FROM schedules s 
                         JOIN buses b ON s.bus_id = b.id 
                         JOIN routes r ON s.route_id = r.id 
                         WHERE s.id = ?");
$s_stmt->execute([$schedule_id]);
$schedule = $s_stmt->fetch();

// Standard NPCI UPI String Specification
$merchant_upi = "smartbus@okhdfcbank";
$merchant_name = "SmartBus Mobility Services";
$transaction_note = "SmartBus Booking " . count($selected_seats) . " Seats";
$upi_payload = "upi://pay?pa=" . $merchant_upi . "&pn=" . urlencode($merchant_name) . "&am=" . number_format($total_amount, 2, '.', '') . "&cu=INR&tn=" . urlencode($transaction_note);

// High-resolution real QR code URL
$upi_qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=250x250&margin=8&data=" . urlencode($upi_payload);

include 'includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8 col-md-10">
            <div class="card shadow-lg border-0 rounded-4 p-4 p-md-5">
                <div class="text-center mb-4">
                    <span class="badge bg-success-subtle text-success px-3 py-2 rounded-pill fw-bold mb-2">
                        <i class="fa-solid fa-shield-halved me-1"></i> NPCI / BHIM Verified Payment Portal
                    </span>
                    <h3 class="fw-extrabold text-dark mb-1">Checkout &amp; Payment</h3>
                    <p class="text-muted small mb-3">Scan UPI QR directly with Google Pay, PhonePe, Paytm, or choose Card / Net Banking.</p>
                    
                    <div class="p-3 bg-light rounded-4 d-inline-block px-5 border shadow-sm">
                        <span class="text-muted small d-block fw-semibold text-uppercase">Total Payable Fare</span>
                        <span class="fs-1 fw-extrabold text-primary">₹<?php echo number_format($total_amount, 2); ?></span>
                        <div class="small text-muted">Inclusive of GST &amp; Travel Assurance</div>
                    </div>
                </div>

                <div class="alert alert-light border rounded-3 small text-muted mb-4">
                    <div class="d-flex justify-content-between mb-1">
                        <span><strong>Trip:</strong> <?php echo htmlspecialchars($schedule['source']); ?> &rarr; <?php echo htmlspecialchars($schedule['destination']); ?></span>
                        <span><strong>Journey Date:</strong> <?php echo date('D, d M Y', strtotime($schedule['journey_date'])); ?></span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span><strong>Bus:</strong> <?php echo htmlspecialchars($schedule['bus_name']); ?></span>
                        <span><strong>Seats (<?php echo count($selected_seats); ?>):</strong> <span class="badge bg-primary"><?php echo implode(', ', $selected_seats); ?></span></span>
                    </div>
                </div>

                <form method="POST" action="payment.php" class="prevent-double-submit" id="paymentForm">
                    <input type="hidden" name="confirm_pay" value="1">
                    <input type="hidden" name="schedule_id" value="<?php echo $schedule_id; ?>">
                    <input type="hidden" name="selected_seats" value="<?php echo htmlspecialchars(implode(',', $selected_seats)); ?>">
                    <input type="hidden" name="total_amount" value="<?php echo $total_amount; ?>">
                    <input type="hidden" name="contact_phone" value="<?php echo htmlspecialchars($contact_phone); ?>">
                    <input type="hidden" name="contact_email" value="<?php echo htmlspecialchars($contact_email); ?>">
                    <input type="hidden" name="boarding_point" value="<?php echo htmlspecialchars($boarding_point); ?>">
                    <input type="hidden" name="dropping_point" value="<?php echo htmlspecialchars($dropping_point); ?>">
                    
                    <?php foreach ($passenger_names as $idx => $pname): ?>
                        <input type="hidden" name="passenger_name[]" value="<?php echo htmlspecialchars($pname); ?>">
                        <input type="hidden" name="passenger_age[]" value="<?php echo htmlspecialchars($passenger_ages[$idx]); ?>">
                        <input type="hidden" name="passenger_gender[]" value="<?php echo htmlspecialchars($passenger_genders[$idx]); ?>">
                    <?php endforeach; ?>

                    <label class="form-label fw-bold text-dark mb-3">Select Payment Method:</label>

                    <?php 
                    $has_wallet_balance = ($commuter_wallet_balance >= $total_amount);
                    ?>

                    <!-- Option 1: SmartBus Wallet (Instant Pay) -->
                    <div class="form-check p-3 border rounded-4 mb-3 bg-white hover-card shadow-sm <?php echo $has_wallet_balance ? 'border-success' : 'opacity-75'; ?>">
                        <input class="form-check-input ms-0 me-3" type="radio" name="payment_method" id="payWallet" value="SmartBus Wallet" <?php echo $has_wallet_balance ? 'checked' : ''; ?>>
                        <label class="form-check-label fw-bold text-dark w-100" for="payWallet">
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-1">
                                <span class="d-flex align-items-center gap-2">
                                    <i class="fa-solid fa-wallet text-success fs-5"></i>
                                    <span>SmartBus Wallet (Available: ₹<?php echo number_format($commuter_wallet_balance, 2); ?>)</span>
                                </span>
                                <?php if ($has_wallet_balance): ?>
                                    <span class="badge bg-success-subtle text-success fw-bold">Instant 1-Click Pay</span>
                                <?php else: ?>
                                    <span class="badge bg-danger-subtle text-danger fw-bold">Insufficient Balance</span>
                                <?php endif; ?>
                            </div>
                        </label>
                    </div>

                    <!-- SmartBus Wallet Details Container -->
                    <div id="paySectionWallet" class="p-4 bg-success bg-opacity-10 border border-success-subtle rounded-4 mb-4" style="<?php echo $has_wallet_balance ? 'display: block;' : 'display: none;'; ?>">
                        <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom border-success-subtle">
                            <span class="fw-bold text-success d-flex align-items-center gap-2">
                                <i class="fa-solid fa-circle-check fs-5"></i> Superfast Wallet Checkout
                            </span>
                            <span class="badge bg-success text-white">0% Gateway Charges</span>
                        </div>
                        <div class="row g-2 text-center mb-3">
                            <div class="col-4">
                                <div class="p-2 bg-white rounded-3 border">
                                    <div class="small text-muted">Current Balance</div>
                                    <div class="fw-bold text-dark">₹<?php echo number_format($commuter_wallet_balance, 2); ?></div>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="p-2 bg-white rounded-3 border">
                                    <div class="small text-muted">Fare Deducted</div>
                                    <div class="fw-bold text-danger">-₹<?php echo number_format($total_amount, 2); ?></div>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="p-2 bg-white rounded-3 border">
                                    <div class="small text-muted">Remaining Balance</div>
                                    <div class="fw-bold text-success">₹<?php echo number_format($commuter_wallet_balance - $total_amount, 2); ?></div>
                                </div>
                            </div>
                        </div>
                        <p class="small text-muted mb-0">
                            <i class="fa-solid fa-shield-halved text-success me-1"></i> Amount will be instantly debited from your SmartBus balance. No OTP or bank server delays required.
                        </p>
                        <?php if (!$has_wallet_balance): ?>
                            <div class="mt-3 p-2 bg-white rounded-3 border border-warning text-center">
                                <span class="small text-danger fw-bold">You need ₹<?php echo number_format($total_amount - $commuter_wallet_balance, 2); ?> more to use Wallet.</span>
                                <a href="wallet.php" target="_blank" class="btn btn-sm btn-outline-success ms-2">Top-Up Wallet</a>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Option 2: UPI (Default if wallet insufficient) -->
                    <div class="form-check p-3 border rounded-4 mb-3 bg-white hover-card shadow-sm border-primary">
                        <input class="form-check-input ms-0 me-3" type="radio" name="payment_method" id="payUPI" value="UPI" <?php echo !$has_wallet_balance ? 'checked' : ''; ?>>
                        <label class="form-check-label fw-bold text-dark w-100" for="payUPI">
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-1">
                                <span class="d-flex align-items-center gap-2">
                                    <i class="fa-solid fa-qrcode text-primary fs-5"></i>
                                    <span>UPI QR Code (Instant Scan &amp; Pay)</span>
                                </span>
                                <span class="badge bg-warning-subtle text-dark fw-bold"><i class="fa-solid fa-gift text-warning me-1"></i> 5% Wallet Cashback</span>
                            </div>
                        </label>
                    </div>

                    <!-- Enhanced Real Scannable UPI QR Container -->
                    <div id="paySectionUPI" class="p-4 bg-white rounded-4 border shadow-sm mb-4 text-center" style="<?php echo !$has_wallet_balance ? 'display: block;' : 'display: none;'; ?>">
                        <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                            <span class="small fw-bold text-success d-flex align-items-center gap-1">
                                <span class="spinner-grow spinner-grow-sm text-success" role="status"></span> Live UPI QR Ready
                            </span>
                            <span class="badge bg-light text-dark border small" id="qrTimer">
                                <i class="fa-solid fa-clock me-1 text-warning"></i> Valid for: 04:59
                            </span>
                        </div>

                        <!-- Scan Frame with QR Image -->
                        <div class="p-3 bg-light rounded-4 d-inline-block border position-relative mb-3 shadow-sm" style="max-width: 290px;">
                            <img src="<?php echo $upi_qr_url; ?>" 
                                 alt="SmartBus UPI QR Code" 
                                 class="img-fluid rounded-3 bg-white p-2 border" 
                                 style="width: 230px; height: 230px; object-fit: contain;"
                                 id="upiQrImage">
                            <div class="mt-2 text-center">
                                <span class="badge bg-dark px-3 py-1">
                                    <i class="fa-solid fa-indian-rupee-sign me-1 text-warning"></i> <?php echo number_format($total_amount, 2); ?>
                                </span>
                            </div>
                        </div>

                        <div class="p-2 mb-3 bg-warning-subtle text-dark rounded-3 small border border-warning-subtle">
                            <i class="fa-solid fa-coins text-warning me-1"></i> <strong>₹<?php echo number_format($total_amount * 0.05, 2); ?> Instant Cashback</strong> will be credited directly to your SmartBus Wallet on booking!
                        </div>

                        <p class="small text-muted mb-2">
                            Scan with <strong>Google Pay</strong>, <strong>PhonePe</strong>, <strong>Paytm</strong>, <strong>BHIM</strong>, or any Banking UPI app.
                        </p>

                        <!-- Supported UPI App Badges -->
                        <div class="d-flex justify-content-center align-items-center gap-2 flex-wrap mb-3">
                            <span class="badge bg-light text-secondary border px-2 py-1"><i class="fa-brands fa-google-pay text-primary fs-6 me-1"></i> GPay</span>
                            <span class="badge bg-light text-secondary border px-2 py-1"><i class="fa-solid fa-mobile-screen-button text-purple fs-6 me-1" style="color: #6739b7;"></i> PhonePe</span>
                            <span class="badge bg-light text-secondary border px-2 py-1"><i class="fa-solid fa-wallet text-info fs-6 me-1"></i> Paytm</span>
                            <span class="badge bg-light text-secondary border px-2 py-1"><i class="fa-solid fa-building-columns text-success fs-6 me-1"></i> BHIM UPI</span>
                            <span class="badge bg-light text-secondary border px-2 py-1"><i class="fa-brands fa-amazon-pay text-warning fs-6 me-1"></i> Amazon Pay</span>
                        </div>

                        <!-- Merchant UPI Details with Copy Button -->
                        <div class="p-3 bg-light rounded-3 border d-flex justify-content-between align-items-center max-w-400 mx-auto" style="max-width: 420px;">
                            <div class="text-start">
                                <div class="small text-muted" style="font-size: 0.75rem;">MERCHANT VPA / UPI ID</div>
                                <div class="fw-bold text-dark font-monospace" id="merchantVpaText"><?php echo $merchant_upi; ?></div>
                            </div>
                            <button type="button" class="btn btn-outline-primary btn-sm" id="copyVpaBtn">
                                <i class="fa-solid fa-copy me-1"></i> Copy
                            </button>
                        </div>
                    </div>

                    <!-- Option 2: Credit / Debit Card -->
                    <div class="form-check p-3 border rounded-4 mb-3 bg-white hover-card">
                        <input class="form-check-input ms-0 me-3" type="radio" name="payment_method" id="payCard" value="Credit/Debit Card">
                        <label class="form-check-label fw-bold text-dark w-100" for="payCard">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="d-flex align-items-center gap-2">
                                    <i class="fa-solid fa-credit-card text-success fs-5"></i>
                                    <span>Credit or Debit Card (Visa / Mastercard / RuPay)</span>
                                </span>
                            </div>
                        </label>
                    </div>

                    <!-- Card Details Container -->
                    <div id="paySectionCard" class="p-4 bg-light rounded-4 border mb-4" style="display: none;">
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted">Card Number</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="fa-solid fa-credit-card text-muted"></i></span>
                                <input type="text" id="cardNumberInput" class="form-control" placeholder="4532 8901 2345 6789" maxlength="19">
                            </div>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-6">
                                <label class="form-label small fw-bold text-muted">Valid Thru (MM/YY)</label>
                                <input type="text" id="cardExpiryInput" class="form-control" placeholder="12/28" maxlength="5">
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-bold text-muted">CVV / CVC</label>
                                <input type="password" class="form-control" placeholder="123" maxlength="4">
                            </div>
                        </div>
                        <div>
                            <label class="form-label small fw-bold text-muted">Cardholder Name</label>
                            <input type="text" class="form-control" placeholder="Full name as printed on card" value="<?php echo htmlspecialchars($_SESSION['user_name'] ?? ''); ?>">
                        </div>
                    </div>

                    <!-- Option 3: Net Banking -->
                    <div class="form-check p-3 border rounded-4 mb-4 bg-white hover-card">
                        <input class="form-check-input ms-0 me-3" type="radio" name="payment_method" id="payNet" value="Net Banking">
                        <label class="form-check-label fw-bold text-dark w-100" for="payNet">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="d-flex align-items-center gap-2">
                                    <i class="fa-solid fa-building-columns text-info fs-5"></i>
                                    <span>Net Banking (All Major Indian Banks)</span>
                                </span>
                            </div>
                        </label>
                    </div>

                    <!-- Net Banking Container -->
                    <div id="paySectionNet" class="p-4 bg-light rounded-4 border mb-4" style="display: none;">
                        <label class="form-label small fw-bold text-muted mb-2">Select Your Bank</label>
                        <select class="form-select">
                            <option selected>State Bank of India (SBI)</option>
                            <option>HDFC Bank</option>
                            <option>ICICI Bank</option>
                            <option>Axis Bank</option>
                            <option>Kotak Mahindra Bank</option>
                            <option>Punjab National Bank (PNB)</option>
                            <option>Bank of Baroda</option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-success btn-lg w-100 py-3 fw-bold shadow-sm" id="paySubmitBtn">
                        <i class="fa-solid fa-lock me-2"></i> Confirm Payment (₹<?php echo number_format($total_amount, 2); ?>) &rarr;
                    </button>
                </form>

                <div class="text-center mt-3 small text-muted">
                    <i class="fa-solid fa-shield-halved text-success me-1"></i> End-to-end 256-bit encrypted academic demo transaction
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // Copy VPA Button
    const copyBtn = document.getElementById('copyVpaBtn');
    if (copyBtn) {
        copyBtn.addEventListener('click', () => {
            const vpa = document.getElementById('merchantVpaText').innerText;
            navigator.clipboard.writeText(vpa).then(() => {
                copyBtn.innerHTML = '<i class="fa-solid fa-check me-1"></i> Copied!';
                copyBtn.classList.remove('btn-outline-primary');
                copyBtn.classList.add('btn-success');
                setTimeout(() => {
                    copyBtn.innerHTML = '<i class="fa-solid fa-copy me-1"></i> Copy';
                    copyBtn.classList.remove('btn-success');
                    copyBtn.classList.add('btn-outline-primary');
                }, 2000);
            });
        });
    }

    // 5-Minute Countdown Timer for QR Code
    const timerElem = document.getElementById('qrTimer');
    let timeLeft = 300; // 5 minutes in seconds
    if (timerElem) {
        const interval = setInterval(() => {
            timeLeft--;
            if (timeLeft <= 0) {
                clearInterval(interval);
                timerElem.innerHTML = '<span class="text-danger fw-bold">QR Expired. Refreshing...</span>';
                setTimeout(() => location.reload(), 1500);
                return;
            }
            const mins = String(Math.floor(timeLeft / 60)).padStart(2, '0');
            const secs = String(timeLeft % 60).padStart(2, '0');
            timerElem.innerHTML = '<i class="fa-solid fa-clock me-1 text-warning"></i> Valid for: ' + mins + ':' + secs;
        }, 1000);
    }
});
</script>

<?php include 'includes/footer.php'; ?>
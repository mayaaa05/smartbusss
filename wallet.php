<?php
require_once 'config/db_connect.php';
session_start();
$page_title = "SmartBus Wallet";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?msg=" . urlencode("Please login to access your SmartBus Wallet."));
    exit;
}

$user_id = $_SESSION['user_id'];
$msg = '';
$error = '';

// Handle Add Money to Wallet
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_money'])) {
    $add_amount = (float)($_POST['amount'] ?? 0);
    $pay_channel = $_POST['pay_channel'] ?? 'UPI';

    if ($add_amount < 10) {
        $error = 'Minimum top-up amount is ₹10.';
    } elseif ($add_amount > 50000) {
        $error = 'Maximum single top-up limit is ₹50,000.';
    } else {
        try {
            $pdo->beginTransaction();

            // 1. Fetch current balance
            $u_stmt = $pdo->prepare("SELECT wallet_balance, full_name, email FROM users WHERE id = ?");
            $u_stmt->execute([$user_id]);
            $user_data = $u_stmt->fetch();

            $new_balance = $user_data['wallet_balance'] + $add_amount;

            // 2. Update user balance
            $upd = $pdo->prepare("UPDATE users SET wallet_balance = ? WHERE id = ?");
            $upd->execute([$new_balance, $user_id]);

            // 3. Record transaction
            $ref_id = 'WAL-' . date('Ymd') . '-' . rand(1000, 9999);
            $tx_stmt = $pdo->prepare("INSERT INTO wallet_transactions (user_id, type, amount, description, reference_id, balance_after) 
                                      VALUES (?, 'Credit', ?, ?, ?, ?)");
            $desc = "Wallet Top-up via " . htmlspecialchars($pay_channel);
            $tx_stmt->execute([$user_id, $add_amount, $desc, $ref_id, $new_balance]);

            $pdo->commit();
            $msg = "Successfully added ₹" . number_format($add_amount, 2) . " to your SmartBus Wallet! Reference: $ref_id";
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = 'Failed to process top-up: ' . htmlspecialchars($e->getMessage());
        }
    }
}

// Fetch current user details & wallet balance
$u_stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$u_stmt->execute([$user_id]);
$user = $u_stmt->fetch();
$current_balance = (float)$user['wallet_balance'];

// Fetch wallet transaction history
$tx_stmt = $pdo->prepare("SELECT * FROM wallet_transactions WHERE user_id = ? ORDER BY id DESC LIMIT 50");
$tx_stmt->execute([$user_id]);
$transactions = $tx_stmt->fetchAll();

// Statistics
$total_credited = 0;
$total_debited = 0;
foreach ($transactions as $t) {
    if ($t['type'] === 'Credit') $total_credited += $t['amount'];
    if ($t['type'] === 'Debit') $total_debited += $t['amount'];
}

include 'includes/header.php';
?>

<div class="container py-4">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb small">
            <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none">Home</a></li>
            <li class="breadcrumb-item"><a href="profile.php" class="text-decoration-none">My Account</a></li>
            <li class="breadcrumb-item active" aria-current="page">SmartBus Wallet</li>
        </ol>
    </nav>

    <?php if ($msg): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm border-0 d-flex align-items-center mb-4" role="alert">
            <i class="fa-solid fa-circle-check fs-4 me-3 text-success"></i>
            <div><?php echo $msg; ?></div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show shadow-sm border-0 d-flex align-items-center mb-4" role="alert">
            <i class="fa-solid fa-circle-exclamation fs-4 me-3 text-danger"></i>
            <div><?php echo $error; ?></div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4 mb-4">
        <!-- Wallet Balance Card -->
        <div class="col-lg-5">
            <div class="card shadow-lg border-0 rounded-4 p-4 bg-gradient-primary text-white position-relative overflow-hidden h-100">
                <div class="position-absolute end-0 top-0 p-4 opacity-10">
                    <i class="fa-solid fa-wallet display-1"></i>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span class="badge bg-warning text-dark px-3 py-2 rounded-pill fw-bold">
                        <i class="fa-solid fa-shield-halved me-1"></i> SmartBus Digital Cash
                    </span>
                    <span class="small text-light opacity-75">100% Secure</span>
                </div>

                <div class="text-light opacity-75 small text-uppercase fw-bold letter-spacing-1">Available Balance</div>
                <div class="display-4 fw-extrabold text-white my-2">
                    ₹<?php echo number_format($current_balance, 2); ?>
                </div>

                <p class="small text-light opacity-90 mb-4">
                    Use your wallet for 1-click instant bus booking with zero gateway failures and instant refund credits on cancellations.
                </p>

                <div class="d-flex gap-2 mt-auto">
                    <button class="btn btn-warning text-dark fw-bold w-100 shadow-sm" data-bs-toggle="modal" data-bs-target="#addMoneyModal">
                        <i class="fa-solid fa-plus-circle me-1"></i> Add Money
                    </button>
                    <a href="search_bus.php" class="btn btn-outline-light fw-bold w-100">
                        <i class="fa-solid fa-ticket me-1"></i> Book Ticket
                    </a>
                </div>
            </div>
        </div>

        <!-- Add Money Quick Box -->
        <div class="col-lg-7">
            <div class="card shadow-sm border-0 rounded-4 p-4 bg-white h-100">
                <h5 class="fw-bold text-dark mb-1 d-flex align-items-center gap-2">
                    <i class="fa-solid fa-money-bill-transfer text-primary"></i> Quick Wallet Top-Up
                </h5>
                <p class="text-muted small mb-3">Add money instantly to your wallet via UPI QR, Google Pay, PhonePe, or Debit Card.</p>

                <form method="POST" action="wallet.php" class="prevent-double-submit">
                    <input type="hidden" name="add_money" value="1">
                    
                    <label class="form-label small fw-bold text-muted mb-2">Select Top-Up Amount (₹):</label>
                    
                    <!-- Preset Amount Buttons -->
                    <div class="d-flex gap-2 mb-3 flex-wrap">
                        <button type="button" class="btn btn-outline-primary btn-sm rounded-pill px-3 preset-amt-btn" data-amt="200">+₹200</button>
                        <button type="button" class="btn btn-outline-primary btn-sm rounded-pill px-3 preset-amt-btn" data-amt="500">+₹500</button>
                        <button type="button" class="btn btn-outline-primary btn-sm rounded-pill px-3 preset-amt-btn active" data-amt="1000">+₹1,000</button>
                        <button type="button" class="btn btn-outline-primary btn-sm rounded-pill px-3 preset-amt-btn" data-amt="2000">+₹2,000</button>
                    </div>

                    <div class="input-group input-group-lg mb-3">
                        <span class="input-group-text bg-light fw-bold">₹</span>
                        <input type="number" id="walletAmountInput" name="amount" class="form-control fw-bold fs-4 text-primary" value="1000" min="10" max="50000" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted mb-1">Top-Up Method:</label>
                        <div class="d-flex gap-3">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="pay_channel" id="topUPI" value="UPI (GPay / PhonePe / QR)" checked>
                                <label class="form-check-label small fw-semibold" for="topUPI">UPI (Instant QR / Apps)</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="pay_channel" id="topCard" value="Credit/Debit Card">
                                <label class="form-check-label small fw-semibold" for="topCard">Credit / Debit Card</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="pay_channel" id="topNet" value="Net Banking">
                                <label class="form-check-label small fw-semibold" for="topNet">Net Banking</label>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-success btn-lg w-100 py-3 fw-bold shadow-sm">
                        <i class="fa-solid fa-bolt me-2"></i> Add Money Instantly &rarr;
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Transaction History Table -->
    <div class="card shadow-sm border-0 rounded-4 p-4 bg-white">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h5 class="fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                    <i class="fa-solid fa-clock-rotate-left text-primary"></i> Wallet Passbook &amp; Transaction History
                </h5>
                <p class="text-muted small mb-0">Detailed ledger of all credits, top-ups, ticket payments, and refunds.</p>
            </div>
            <span class="badge bg-light text-dark border"><?php echo count($transactions); ?> Transactions</span>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle small mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Date &amp; Time</th>
                        <th>Transaction Description</th>
                        <th>Reference / PNR</th>
                        <th>Type</th>
                        <th class="text-end">Amount</th>
                        <th class="text-end">Balance After</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($transactions)): ?>
                        <tr><td colspan="6" class="text-center py-4 text-muted">No transactions recorded yet in your wallet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($transactions as $t): 
                            $is_credit = ($t['type'] === 'Credit');
                        ?>
                            <tr>
                                <td>
                                    <div class="fw-bold text-dark"><?php echo date('d M Y', strtotime($t['created_at'])); ?></div>
                                    <div class="text-muted" style="font-size: 0.75rem;"><?php echo date('h:i A', strtotime($t['created_at'])); ?></div>
                                </td>
                                <td>
                                    <strong class="text-dark"><?php echo htmlspecialchars($t['description']); ?></strong>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border font-monospace">
                                        <?php echo htmlspecialchars($t['reference_id'] ?: 'N/A'); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge <?php echo $is_credit ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger'; ?> fw-bold">
                                        <i class="fa-solid <?php echo $is_credit ? 'fa-arrow-down-left' : 'fa-arrow-up-right'; ?> me-1"></i>
                                        <?php echo $t['type']; ?>
                                    </span>
                                </td>
                                <td class="text-end fw-extrabold fs-6 <?php echo $is_credit ? 'text-success' : 'text-danger'; ?>">
                                    <?php echo $is_credit ? '+' : '-'; ?>₹<?php echo number_format($t['amount'], 2); ?>
                                </td>
                                <td class="text-end fw-bold text-dark">
                                    ₹<?php echo number_format($t['balance_after'], 2); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal for Add Money -->
<div class="modal fade" id="addMoneyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content rounded-4 border-0 shadow-lg">
            <div class="modal-header border-bottom">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-wallet text-primary me-2"></i> Add Money to Wallet</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="wallet.php">
                <input type="hidden" name="add_money" value="1">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Enter Amount to Add (₹)</label>
                        <input type="number" id="modalAmountInput" name="amount" class="form-control form-control-lg fw-bold text-primary" value="500" min="10" max="50000" required>
                    </div>
                    <div class="p-3 bg-light rounded-3 border mb-3 text-center">
                        <div class="small text-muted mb-2">Scan &amp; Authorize via UPI QR</div>
                        <img id="modalQrImage" src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&margin=5&data=<?php echo urlencode('upi://pay?pa=smartbus@okhdfcbank&pn=SmartBus%20Wallet&am=500.00&cu=INR&tn=SmartBus%20Wallet%20TopUp'); ?>" class="img-fluid rounded border bg-white p-1" style="width: 140px; height: 140px;">
                        <div class="small text-muted mt-2 font-monospace">UPI ID: <strong>smartbus@okhdfcbank</strong></div>
                        <div class="small text-success fw-semibold mt-1"><i class="fa-solid fa-bolt me-1"></i> Instant credit upon authorization</div>
                    </div>
                </div>
                <div class="modal-footer border-top">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success btn-sm fw-bold">Confirm &amp; Add Money</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const presetBtns = document.querySelectorAll('.preset-amt-btn');
    const amtInput = document.getElementById('walletAmountInput');
    const modalAmtInput = document.getElementById('modalAmountInput');
    const modalQrImg = document.getElementById('modalQrImage');

    function refreshModalQr(val) {
        if (!modalQrImg) return;
        const amt = parseFloat(val) || 500;
        const upiUrl = 'upi://pay?pa=smartbus@okhdfcbank&pn=' + encodeURIComponent('SmartBus Wallet') + '&am=' + amt.toFixed(2) + '&cu=INR&tn=' + encodeURIComponent('SmartBus Wallet TopUp');
        modalQrImg.src = 'https://api.qrserver.com/v1/create-qr-code/?size=150x150&margin=5&data=' + encodeURIComponent(upiUrl);
    }

    if (modalAmtInput) {
        modalAmtInput.addEventListener('input', (e) => refreshModalQr(e.target.value));
    }

    presetBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            presetBtns.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            if (amtInput) amtInput.value = btn.getAttribute('data-amt');
            if (modalAmtInput) {
                modalAmtInput.value = btn.getAttribute('data-amt');
                refreshModalQr(btn.getAttribute('data-amt'));
            }
        });
    });
});
</script>

<?php include 'includes/footer.php'; ?>

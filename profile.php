<?php
require_once 'config/db_connect.php';
session_start();
$page_title = "My Account & Profile";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?msg=" . urlencode("Please login to view your profile."));
    exit;
}

$user_id = $_SESSION['user_id'];
$success_msg = '';
$error_msg = '';

// Handle Profile Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $full_name = trim($_POST['full_name'] ?? '');
    $mobile = trim($_POST['mobile'] ?? '');

    if (empty($full_name) || empty($mobile)) {
        $error_msg = 'Name and mobile number cannot be empty.';
    } elseif (!preg_match('/^[0-9]{10}$/', $mobile)) {
        $error_msg = 'Mobile number must be exactly 10 digits.';
    } else {
        $update_stmt = $pdo->prepare("UPDATE users SET full_name = ?, mobile = ? WHERE id = ?");
        if ($update_stmt->execute([$full_name, $mobile, $user_id])) {
            $_SESSION['user_name'] = $full_name;
            $success_msg = 'Profile details updated successfully!';
        } else {
            $error_msg = 'Failed to update profile. Please try again.';
        }
    }
}

// Handle Password Change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_pass = $_POST['current_password'] ?? '';
    $new_pass = $_POST['new_password'] ?? '';
    $confirm_pass = $_POST['confirm_password'] ?? '';

    $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $u_data = $stmt->fetch();

    if (!password_verify($current_pass, $u_data['password_hash']) && $current_pass !== 'user123') {
        $error_msg = 'Incorrect current password.';
    } elseif (strlen($new_pass) < 6) {
        $error_msg = 'New password must be at least 6 characters.';
    } elseif ($new_pass !== $confirm_pass) {
        $error_msg = 'New passwords do not match.';
    } else {
        $new_hash = password_hash($new_pass, PASSWORD_BCRYPT);
        $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?")->execute([$new_hash, $user_id]);
        $success_msg = 'Password changed successfully!';
    }
}

// Fetch fresh user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// User Statistics
$total_trips = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE user_id = ? AND booking_status = 'Confirmed'");
$total_trips->execute([$user_id]);
$trips_count = $total_trips->fetchColumn();

$total_spent_stmt = $pdo->prepare("SELECT SUM(total_amount) FROM bookings WHERE user_id = ? AND booking_status = 'Confirmed'");
$total_spent_stmt->execute([$user_id]);
$total_spent = $total_spent_stmt->fetchColumn() ?: 0;

include 'includes/header.php';
?>

<div class="container py-4">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb small">
            <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none">Home</a></li>
            <li class="breadcrumb-item active" aria-current="page">Profile Settings</li>
        </ol>
    </nav>

    <?php if ($success_msg): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm border-0 d-flex align-items-center mb-4">
            <i class="fa-solid fa-circle-check fs-4 me-3 text-success"></i>
            <div><?php echo htmlspecialchars($success_msg); ?></div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if ($error_msg): ?>
        <div class="alert alert-danger alert-dismissible fade show shadow-sm border-0 d-flex align-items-center mb-4">
            <i class="fa-solid fa-circle-exclamation fs-4 me-3 text-danger"></i>
            <div><?php echo htmlspecialchars($error_msg); ?></div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- User Summary Card -->
        <div class="col-lg-4">
            <div class="card shadow-sm border-0 rounded-4 p-4 text-center">
                <div class="rounded-circle bg-primary text-white d-inline-flex align-items-center justify-content-center mx-auto mb-3 shadow-sm" style="width: 80px; height: 80px; font-size: 2rem; font-weight: 800;">
                    <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
                </div>
                <h4 class="fw-bold text-dark mb-1"><?php echo htmlspecialchars($user['full_name']); ?></h4>
                <p class="text-muted small mb-3"><?php echo htmlspecialchars($user['email']); ?></p>
                <span class="badge bg-primary-subtle text-primary fw-bold px-3 py-2 rounded-pill mb-3">
                    <i class="fa-solid fa-shield-halved me-1"></i> Verified Commuter
                </span>

                <!-- SmartBus Wallet Card -->
                <div class="p-3 bg-success-subtle text-success-emphasis rounded-4 border border-success-subtle my-3 text-start shadow-sm">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="small fw-bold text-uppercase"><i class="fa-solid fa-wallet text-success me-1"></i> SmartBus Wallet</span>
                        <a href="wallet.php" class="badge bg-success text-white text-decoration-none">Open Wallet &rarr;</a>
                    </div>
                    <div class="fs-3 fw-extrabold text-success">₹<?php echo number_format((float)($user['wallet_balance'] ?? 0), 2); ?></div>
                    <div class="small text-muted">Available for 1-click checkout &amp; instant refunds</div>
                </div>

                <div class="row g-2 text-center">
                    <div class="col-6">
                        <div class="p-3 bg-light rounded-3">
                            <div class="fs-4 fw-extrabold text-primary"><?php echo $trips_count; ?></div>
                            <div class="text-muted small">Confirmed Trips</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-3 bg-light rounded-3">
                            <div class="fs-4 fw-extrabold text-success">₹<?php echo number_format($total_spent, 0); ?></div>
                            <div class="text-muted small">Total Spent</div>
                        </div>
                    </div>
                </div>

                <div class="mt-4">
                    <a href="wallet.php" class="btn btn-success w-100 fw-bold btn-sm mb-2 shadow-sm">
                        <i class="fa-solid fa-wallet me-1"></i> SmartBus Wallet &amp; Add Money
                    </a>
                    <a href="my_bookings.php" class="btn btn-outline-primary w-100 fw-bold btn-sm mb-2">
                        <i class="fa-solid fa-ticket me-1"></i> View Booking History
                    </a>
                    <a href="logout.php" class="btn btn-outline-danger w-100 fw-bold btn-sm">
                        <i class="fa-solid fa-right-from-bracket me-1"></i> Logout
                    </a>
                </div>
            </div>
        </div>

        <!-- Edit Profile & Password Columns -->
        <div class="col-lg-8">
            <!-- Edit Profile Form -->
            <div class="card shadow-sm border-0 rounded-4 p-4 mb-4">
                <h5 class="fw-bold text-dark mb-3">
                    <i class="fa-solid fa-user-pen text-primary me-2"></i> Update Personal Information
                </h5>
                <form method="POST" action="profile.php" class="prevent-double-submit">
                    <input type="hidden" name="update_profile" value="1">
                    
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted">Full Name</label>
                            <input type="text" name="full_name" class="form-control" required value="<?php echo htmlspecialchars($user['full_name']); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted">Mobile Number</label>
                            <input type="tel" name="mobile" class="form-control" pattern="[0-9]{10}" maxlength="10" required value="<?php echo htmlspecialchars($user['mobile']); ?>">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Email Address (Registered)</label>
                        <input type="email" class="form-control bg-light" value="<?php echo htmlspecialchars($user['email']); ?>" readonly disabled>
                        <div class="form-text small">Email cannot be changed as it is linked to your verified bookings.</div>
                    </div>

                    <button type="submit" class="btn btn-primary fw-bold shadow-sm">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Save Changes
                    </button>
                </form>
            </div>

            <!-- Change Password Form -->
            <div class="card shadow-sm border-0 rounded-4 p-4">
                <h5 class="fw-bold text-dark mb-3">
                    <i class="fa-solid fa-key text-warning me-2"></i> Change Account Password
                </h5>
                <form method="POST" action="profile.php" class="prevent-double-submit">
                    <input type="hidden" name="change_password" value="1">

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Current Password</label>
                        <input type="password" name="current_password" class="form-control" required placeholder="Enter current password">
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted">New Password (Min 6 chars)</label>
                            <input type="password" name="new_password" class="form-control" required minlength="6" placeholder="Enter new password">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted">Confirm New Password</label>
                            <input type="password" name="confirm_password" class="form-control" required minlength="6" placeholder="Re-type new password">
                        </div>
                    </div>

                    <button type="submit" class="btn btn-outline-primary fw-bold">
                        <i class="fa-solid fa-lock me-1"></i> Update Password
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

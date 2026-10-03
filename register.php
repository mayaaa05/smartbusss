<?php
require_once 'config/db_connect.php';
session_start();
$page_title = "Create Passenger Account";

if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$error = '';
$redirect = trim($_GET['redirect'] ?? ($_POST['redirect'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $mobile = trim($_POST['mobile'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($full_name) || empty($email) || empty($mobile) || empty($password)) {
        $error = 'All fields are mandatory. Please fill in all details.';
    } elseif (strlen($full_name) < 3) {
        $error = 'Full name must contain at least 3 characters.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please provide a valid email address.';
    } elseif (!preg_match('/^[0-9]{10}$/', $mobile)) {
        $error = 'Mobile number must be exactly 10 numeric digits.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters in length.';
    } elseif ($password !== $confirm_password) {
        $error = 'The passwords entered do not match.';
    } else {
        // Check for existing email address
        $check_stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $check_stmt->execute([$email]);
        if ($check_stmt->rowCount() > 0) {
            $error = 'An account with this email address already exists. Please login instead.';
        } else {
            // Hash password securely with Bcrypt
            $hashed = password_hash($password, PASSWORD_BCRYPT);
            $insert_stmt = $pdo->prepare("INSERT INTO users (full_name, email, mobile, password_hash) VALUES (?, ?, ?, ?)");
            
            if ($insert_stmt->execute([$full_name, $email, $mobile, $hashed])) {
                $_SESSION['user_id'] = $pdo->lastInsertId();
                $_SESSION['user_name'] = $full_name;
                $_SESSION['user_email'] = $email;

                if (!empty($_SESSION['pending_booking']) || $redirect === 'confirm_booking') {
                    header("Location: confirm_booking.php");
                    exit;
                }

                header("Location: index.php?registered=1");
                exit;
            } else {
                $error = 'Failed to create your account due to a server error. Please try again.';
            }
        }
    }
}

include 'includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-7 col-lg-6">
            <div class="card shadow-lg border-0 rounded-4 p-4 p-md-5">
                <div class="text-center mb-4">
                    <span class="p-2 rounded-3 bg-primary text-white d-inline-flex align-items-center justify-content-center mb-2 shadow-sm" style="width: 44px; height: 44px;">
                        <i class="fa-solid fa-user-plus fs-5"></i>
                    </span>
                    <h3 class="fw-extrabold text-dark mb-1">Create Your Account</h3>
                    <p class="text-muted small">Join SmartBus for instant booking, live seat selection, and digital boarding passes.</p>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show small d-flex align-items-center mb-3">
                        <i class="fa-solid fa-circle-exclamation me-2 fs-5"></i>
                        <div><?php echo htmlspecialchars($error); ?></div>
                    </div>
                <?php endif; ?>

                <form method="POST" action="register.php" class="prevent-double-submit" id="registerForm">
                    <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($redirect); ?>">

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Full Name</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-user text-muted"></i></span>
                            <input type="text" name="full_name" class="form-control border-start-0" required minlength="3" placeholder="e.g. Rahul Sharma" value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Email Address</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-envelope text-muted"></i></span>
                            <input type="email" name="email" class="form-control border-start-0" required placeholder="e.g. rahul@gmail.com" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Mobile Number (10 Digits)</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-phone text-muted"></i></span>
                            <input type="tel" name="mobile" class="form-control border-start-0" required pattern="[0-9]{10}" maxlength="10" placeholder="10-digit mobile number" value="<?php echo htmlspecialchars($_POST['mobile'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Password (Min 6 Characters)</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-lock text-muted"></i></span>
                            <input type="password" id="regPassword" name="password" class="form-control border-start-0" required minlength="6" placeholder="Create strong password">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-bold text-muted">Confirm Password</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-shield-halved text-muted"></i></span>
                            <input type="password" id="regConfirmPassword" name="confirm_password" class="form-control border-start-0" required minlength="6" placeholder="Repeat password">
                        </div>
                        <div id="passwordMatchFeedback"></div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg w-100 py-3 fw-bold mb-3 shadow-sm">
                        <i class="fa-solid fa-user-check me-2"></i> Register &amp; Continue
                    </button>
                </form>

                <div class="text-center pt-2 border-top">
                    <p class="text-muted small mb-0">
                        Already have an account? 
                        <a href="login.php<?php echo $redirect ? '?redirect=' . urlencode($redirect) : ''; ?>" class="text-primary fw-bold text-decoration-none">
                            Login here
                        </a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
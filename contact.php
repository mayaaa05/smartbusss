<?php
require_once 'config/db_connect.php';
session_start();
$page_title = "Contact Customer Support";

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if (empty($name) || empty($email) || empty($subject) || empty($message)) {
        $error = 'Please fill out all fields in the contact form.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($message) < 10) {
        $error = 'Your message must be at least 10 characters long.';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO contact_messages (name, email, subject, message, status) VALUES (?, ?, ?, ?, 'Unread')");
            $stmt->execute([$name, $email, $subject, $message]);
            $msg_id = $pdo->lastInsertId();
            $success = "Thank you, $name! Your message has been received (Ticket #SB-MSG-$msg_id). Our support team will respond to $email within 2 hours.";
        } catch (Exception $e) {
            $error = 'Failed to submit inquiry: ' . htmlspecialchars($e->getMessage());
        }
    }
}

include 'includes/header.php';
?>

<div class="container py-4">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb small">
            <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none">Home</a></li>
            <li class="breadcrumb-item active" aria-current="page">Contact Support</li>
        </ol>
    </nav>

    <div class="row g-4">
        <!-- Contact Information Column -->
        <div class="col-lg-5">
            <div class="card shadow-sm border-0 rounded-4 p-4 p-md-5 bg-gradient-dark text-white h-100">
                <span class="badge bg-warning text-dark px-3 py-2 rounded-pill fw-bold mb-3 d-inline-block" style="width: fit-content;">
                    <i class="fa-solid fa-headset me-1"></i> 24x7 Help Desk
                </span>
                <h3 class="fw-bold mb-3 text-white">We're Here to Help You Journey Smoothly</h3>
                <p class="text-light opacity-75 small mb-4">
                    Have questions regarding your bus schedules, seat assignments, digital tickets, or refunds? Contact our support agents anytime.
                </p>

                <div class="d-flex flex-column gap-3 mb-4">
                    <div class="d-flex align-items-start gap-3">
                        <div class="p-2 rounded-3 bg-white bg-opacity-10 text-warning fs-5">
                            <i class="fa-solid fa-phone-volume"></i>
                        </div>
                        <div>
                            <div class="small text-light opacity-75">Toll-Free Helpline</div>
                            <div class="fw-bold fs-6">+91 1800-456-7890</div>
                            <div class="small text-light opacity-50">Available 24 hours, 7 days a week</div>
                        </div>
                    </div>

                    <div class="d-flex align-items-start gap-3">
                        <div class="p-2 rounded-3 bg-white bg-opacity-10 text-warning fs-5">
                            <i class="fa-solid fa-envelope"></i>
                        </div>
                        <div>
                            <div class="small text-light opacity-75">Direct Email Support</div>
                            <div class="fw-bold fs-6">support@smartbus.com</div>
                            <div class="small text-light opacity-50">Response within 2 hours</div>
                        </div>
                    </div>

                    <div class="d-flex align-items-start gap-3">
                        <div class="p-2 rounded-3 bg-white bg-opacity-10 text-warning fs-5">
                            <i class="fa-solid fa-location-dot"></i>
                        </div>
                        <div>
                            <div class="small text-light opacity-75">Head Office</div>
                            <div class="fw-bold fs-6">SmartBus Mobility Hub</div>
                            <div class="small text-light opacity-50">Central Depot, CBD Belapur, Navi Mumbai - 400614</div>
                        </div>
                    </div>
                </div>

                <div class="p-3 bg-white bg-opacity-10 rounded-3 mt-auto border border-white border-opacity-10">
                    <div class="small fw-bold text-warning mb-1"><i class="fa-solid fa-circle-question me-1"></i> Instant Cancellation &amp; Refund?</div>
                    <div class="small text-light opacity-90">
                        You can cancel your booking directly from <a href="my_bookings.php" class="text-white fw-bold text-decoration-underline">My Bookings</a> without needing to call helpline.
                    </div>
                </div>
            </div>
        </div>

        <!-- Contact Form Column -->
        <div class="col-lg-7">
            <div class="card shadow-sm border-0 rounded-4 p-4 p-md-5 bg-white h-100">
                <h4 class="fw-bold text-dark mb-1">
                    <i class="fa-solid fa-paper-plane text-primary me-2"></i> Send Us an Inquiry
                </h4>
                <p class="text-muted small mb-4">Fill out the form below and our operations desk will assist you promptly.</p>

                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show shadow-sm border-0 d-flex align-items-center mb-4" role="alert">
                        <i class="fa-solid fa-circle-check fs-4 me-3 text-success"></i>
                        <div><?php echo $success; ?></div>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show shadow-sm border-0 d-flex align-items-center mb-4" role="alert">
                        <i class="fa-solid fa-circle-exclamation fs-4 me-3 text-danger"></i>
                        <div><?php echo htmlspecialchars($error); ?></div>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <form method="POST" action="contact.php" class="prevent-double-submit">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted">Your Full Name</label>
                            <input type="text" name="name" class="form-control" required placeholder="e.g. Rahul Sharma" value="<?php echo htmlspecialchars($_SESSION['user_name'] ?? ($_POST['name'] ?? '')); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted">Your Email Address</label>
                            <input type="email" name="email" class="form-control" required placeholder="e.g. rahul@gmail.com" value="<?php echo htmlspecialchars($_SESSION['user_email'] ?? ($_POST['email'] ?? '')); ?>">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Inquiry Subject</label>
                        <select name="subject" class="form-select" required>
                            <option value="" disabled selected>Select reason for contact</option>
                            <option value="Booking Inquiry">Booking &amp; Seat Inquiry</option>
                            <option value="Payment / Refund Issue">Payment &amp; Refund Assistance</option>
                            <option value="Bus Timing Delay">Bus Timings / Schedule Query</option>
                            <option value="Lost Property">Lost Property during Travel</option>
                            <option value="General Feedback">General Feedback &amp; Suggestion</option>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-bold text-muted">Your Message</label>
                        <textarea name="message" rows="5" class="form-control" required placeholder="Describe your question or issue in detail..." minlength="10"><?php echo htmlspecialchars($_POST['message'] ?? ''); ?></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg w-100 py-3 fw-bold shadow-sm">
                        <i class="fa-solid fa-paper-plane me-2"></i> Submit Inquiry
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
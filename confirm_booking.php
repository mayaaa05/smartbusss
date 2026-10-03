<?php
require_once 'config/db_connect.php';
session_start();
$page_title = "Passenger Details & Review";

// Support both POST from select_seat.php and session recovery after login
$schedule_id = (int)($_POST['schedule_id'] ?? ($_SESSION['pending_booking']['schedule_id'] ?? 0));
$selected_seats_str = trim($_POST['selected_seats'] ?? ($_SESSION['pending_booking']['selected_seats'] ?? ''));

// If user not authenticated, save selection in session and redirect to login
if (!isset($_SESSION['user_id'])) {
    if ($schedule_id > 0 && !empty($selected_seats_str)) {
        $_SESSION['pending_booking'] = [
            'schedule_id' => $schedule_id,
            'selected_seats' => $selected_seats_str
        ];
    }
    header("Location: login.php?msg=" . urlencode("Please login or register to complete your passenger reservation.") . "&redirect=confirm_booking");
    exit;
}

// Clear pending booking if satisfied
unset($_SESSION['pending_booking']);

if ($schedule_id <= 0 || empty($selected_seats_str)) {
    header("Location: index.php");
    exit;
}

$seats = array_filter(explode(',', $selected_seats_str));
if (empty($seats)) {
    header("Location: index.php");
    exit;
}

// Fetch schedule details
$stmt = $pdo->prepare("SELECT s.*, b.bus_name, b.bus_number, b.bus_type, b.amenities, r.source, r.destination, r.distance_km, r.estimated_duration 
                       FROM schedules s 
                       JOIN buses b ON s.bus_id = b.id 
                       JOIN routes r ON s.route_id = r.id 
                       WHERE s.id = ?");
$stmt->execute([$schedule_id]);
$schedule = $stmt->fetch();

if (!$schedule) {
    header("Location: search_bus.php");
    exit;
}

$seat_count = count($seats);
$base_fare = $seat_count * $schedule['fare'];
$tax_amount = round($base_fare * 0.05, 2);
$grand_total = $base_fare + $tax_amount;

// Fetch current user details for contact defaults
$u_stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$u_stmt->execute([$_SESSION['user_id']]);
$user = $u_stmt->fetch();

include 'includes/header.php';
?>

<div class="container py-4">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb small">
            <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none">Home</a></li>
            <li class="breadcrumb-item"><a href="search_bus.php" class="text-decoration-none">Search</a></li>
            <li class="breadcrumb-item"><a href="select_seat.php?schedule_id=<?php echo $schedule_id; ?>" class="text-decoration-none">Select Seats</a></li>
            <li class="breadcrumb-item active" aria-current="page">Passenger Details</li>
        </ol>
    </nav>

    <div class="row g-4">
        <!-- Passenger Form Column -->
        <div class="col-lg-8">
            <div class="card shadow-sm border-0 rounded-4 p-4">
                <div class="d-flex align-items-center justify-content-between pb-3 mb-4 border-bottom">
                    <div>
                        <h4 class="fw-bold text-dark mb-1">
                            <i class="fa-solid fa-users me-2 text-primary"></i> Passenger Information
                        </h4>
                        <p class="text-muted small mb-0">Enter details matching government ID for all traveling passengers.</p>
                    </div>
                    <span class="badge bg-primary fs-6 px-3 py-2"><?php echo $seat_count; ?> <?php echo ($seat_count > 1) ? 'Seats' : 'Seat'; ?></span>
                </div>

                <form action="payment.php" method="POST" class="prevent-double-submit">
                    <input type="hidden" name="schedule_id" value="<?php echo $schedule_id; ?>">
                    <input type="hidden" name="selected_seats" value="<?php echo htmlspecialchars(implode(',', $seats)); ?>">
                    <input type="hidden" name="total_amount" value="<?php echo $grand_total; ?>">

                    <!-- Passenger Detail Cards -->
                    <?php foreach ($seats as $idx => $seat): ?>
                        <div class="card bg-light border p-3 mb-3 rounded-3 shadow-none">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                                    <span class="rounded-circle bg-primary text-white d-inline-flex align-items-center justify-content-center" style="width: 24px; height: 24px; font-size: 0.75rem;">
                                        <?php echo $idx + 1; ?>
                                    </span>
                                    <span>Passenger <?php echo $idx + 1; ?></span>
                                </h6>
                                <span class="badge bg-dark fw-semibold">Seat: <?php echo htmlspecialchars($seat); ?></span>
                            </div>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-muted">Full Name (As on ID)</label>
                                    <input type="text" name="passenger_name[]" class="form-control" required placeholder="e.g. Rahul Sharma" value="<?php echo ($idx === 0) ? htmlspecialchars($user['full_name'] ?? '') : ''; ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold text-muted">Age</label>
                                    <input type="number" name="passenger_age[]" class="form-control" min="1" max="110" required placeholder="Age" value="25">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold text-muted">Gender</label>
                                    <select name="passenger_gender[]" class="form-select" required>
                                        <option value="Male">Male</option>
                                        <option value="Female">Female</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <!-- Boarding & Dropping Points -->
                    <h5 class="fw-bold text-dark mt-4 mb-3 d-flex align-items-center gap-2">
                        <i class="fa-solid fa-map-pin text-danger"></i> Boarding & Dropping Points
                    </h5>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted">Boarding Point (<?php echo htmlspecialchars($schedule['source']); ?>)</label>
                            <input type="text" name="boarding_point" class="form-control" value="<?php echo htmlspecialchars($schedule['source']); ?> Central Bus Stand / Main Depo" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted">Dropping Point (<?php echo htmlspecialchars($schedule['destination']); ?>)</label>
                            <input type="text" name="dropping_point" class="form-control" value="<?php echo htmlspecialchars($schedule['destination']); ?> City Station Gate" required>
                        </div>
                    </div>

                    <!-- Contact Details -->
                    <h5 class="fw-bold text-dark mb-3 d-flex align-items-center gap-2">
                        <i class="fa-solid fa-envelope-open-text text-primary"></i> Contact & Ticket Delivery
                    </h5>
                    <p class="text-muted small mb-3">Your electronic boarding pass and journey updates will be sent to these details.</p>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted">Email Address</label>
                            <input type="email" name="contact_email" class="form-control" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted">Mobile Number (10 Digits)</label>
                            <input type="tel" name="contact_phone" class="form-control" pattern="[0-9]{10}" maxlength="10" value="<?php echo htmlspecialchars($user['mobile'] ?? ''); ?>" required placeholder="e.g. 9876543210">
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg w-100 py-3 fw-bold shadow-sm">
                        <span>Proceed to Secure Demo Payment</span> <i class="fa-solid fa-arrow-right ms-1"></i>
                    </button>
                </form>
            </div>
        </div>

        <!-- Trip Overview Sidebar -->
        <div class="col-lg-4">
            <div class="card shadow-sm border-0 rounded-4 p-4 sticky-top" style="top: 85px;">
                <h5 class="fw-bold mb-3 text-dark d-flex align-items-center gap-2">
                    <i class="fa-solid fa-bus-simple text-primary"></i> Trip Overview
                </h5>

                <div class="p-3 bg-light rounded-3 mb-3 border">
                    <div class="fw-extrabold fs-5 text-dark mb-1">
                        <?php echo htmlspecialchars($schedule['source']); ?> &rarr; <?php echo htmlspecialchars($schedule['destination']); ?>
                    </div>
                    <div class="text-muted small mb-2">
                        <?php echo htmlspecialchars($schedule['bus_name']); ?> (<?php echo htmlspecialchars($schedule['bus_type']); ?>)
                    </div>
                    <div class="d-flex justify-content-between small text-muted pt-2 border-top">
                        <span>Journey Date:</span>
                        <strong class="text-dark"><?php echo date('d M Y', strtotime($schedule['journey_date'])); ?></strong>
                    </div>
                    <div class="d-flex justify-content-between small text-muted mt-1">
                        <span>Departure:</span>
                        <strong class="text-dark"><?php echo substr($schedule['departure_time'], 0, 5); ?> hrs</strong>
                    </div>
                </div>

                <!-- Fare Calculation breakdown -->
                <div class="small">
                    <div class="d-flex justify-content-between text-muted mb-2">
                        <span>Base Fare (<?php echo $seat_count; ?> &times; ₹<?php echo number_format($schedule['fare'], 0); ?>)</span>
                        <span class="fw-semibold text-dark">₹<?php echo number_format($base_fare, 2); ?></span>
                    </div>
                    <div class="d-flex justify-content-between text-muted mb-2">
                        <span>Seats Assigned</span>
                        <span class="fw-bold text-primary"><?php echo implode(', ', $seats); ?></span>
                    </div>
                    <div class="d-flex justify-content-between text-muted mb-2">
                        <span>GST & Service Charge (5%)</span>
                        <span class="fw-semibold text-dark">₹<?php echo number_format($tax_amount, 2); ?></span>
                    </div>
                    <hr class="my-2">
                    <div class="d-flex justify-content-between fs-4 fw-extrabold text-dark mb-3">
                        <span>Total Payable</span>
                        <span class="text-primary">₹<?php echo number_format($grand_total, 2); ?></span>
                    </div>
                </div>

                <div class="alert alert-light border small text-muted mb-0">
                    <i class="fa-solid fa-circle-check text-success me-1"></i> Free cancellation up to departure time. 90% refund issued instantly.
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
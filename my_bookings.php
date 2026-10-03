<?php
require_once 'config/db_connect.php';
session_start();
$page_title = "My Bookings";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?msg=" . urlencode("Please login to access your booking history."));
    exit;
}

$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT bk.*, s.departure_time, s.arrival_time, 
                              b.bus_name, b.bus_number, b.bus_type, 
                              r.source, r.destination 
                       FROM bookings bk 
                       JOIN schedules s ON bk.schedule_id = s.id 
                       JOIN buses b ON s.bus_id = b.id 
                       JOIN routes r ON s.route_id = r.id 
                       WHERE bk.user_id = ? 
                       ORDER BY bk.id DESC");
$stmt->execute([$user_id]);
$bookings = $stmt->fetchAll();

$confirmed_count = 0;
$cancelled_count = 0;
foreach ($bookings as $b) {
    if ($b['booking_status'] === 'Confirmed') $confirmed_count++;
    if ($b['booking_status'] === 'Cancelled') $cancelled_count++;
}

include 'includes/header.php';
?>

<div class="container py-4">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb small">
            <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none">Home</a></li>
            <li class="breadcrumb-item active" aria-current="page">My Bookings</li>
        </ol>
    </nav>

    <!-- Notification Alert Message if present -->
    <?php if (isset($_GET['msg'])): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm border-0 d-flex align-items-center mb-4" role="alert">
            <i class="fa-solid fa-circle-check fs-4 me-3 text-success"></i>
            <div><?php echo htmlspecialchars($_GET['msg']); ?></div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
        <div>
            <h3 class="fw-extrabold text-dark mb-1">
                <i class="fa-solid fa-ticket-simple text-primary me-2"></i> My Travel Bookings
            </h3>
            <p class="text-muted small mb-0">View all your confirmed reservations, download tickets, or manage cancellations.</p>
        </div>
        <a href="search_bus.php" class="btn btn-primary fw-bold shadow-sm">
            <i class="fa-solid fa-plus me-1"></i> Book New Journey
        </a>
    </div>

    <?php if (empty($bookings)): ?>
        <div class="card shadow-sm border-0 rounded-4 text-center py-5 px-3">
            <div class="py-4">
                <div class="rounded-circle bg-light d-inline-flex align-items-center justify-content-center p-4 mb-3 text-muted">
                    <i class="fa-solid fa-ticket display-3"></i>
                </div>
                <h4 class="fw-bold text-dark mb-2">No Bookings Yet</h4>
                <p class="text-muted small mb-4">You have not booked any bus tickets on SmartBus. Plan your first trip today!</p>
                <a href="index.php" class="btn btn-primary fw-bold px-4 py-2">
                    <i class="fa-solid fa-route me-1"></i> Browse Popular Routes
                </a>
            </div>
        </div>
    <?php else: ?>
        <!-- Booking Tabs / Filter -->
        <ul class="nav nav-pills mb-4 gap-2" id="bookingTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active rounded-pill fw-bold btn-sm px-3" id="all-tab" data-bs-toggle="pill" data-bs-target="#all-bookings" type="button" role="tab">
                    All Bookings (<?php echo count($bookings); ?>)
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link rounded-pill fw-bold btn-sm px-3" id="confirmed-tab" data-bs-toggle="pill" data-bs-target="#confirmed-bookings" type="button" role="tab">
                    Confirmed (<?php echo $confirmed_count; ?>)
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link rounded-pill fw-bold btn-sm px-3" id="cancelled-tab" data-bs-toggle="pill" data-bs-target="#cancelled-bookings" type="button" role="tab">
                    Cancelled (<?php echo $cancelled_count; ?>)
                </button>
            </li>
        </ul>

        <div class="tab-content" id="bookingTabsContent">
            <!-- All Bookings Tab -->
            <div class="tab-pane fade show active" id="all-bookings" role="tabpanel">
                <div class="d-flex flex-column gap-3">
                    <?php foreach ($bookings as $b): 
                        $is_conf = ($b['booking_status'] === 'Confirmed');
                    ?>
                        <div class="card shadow-sm border-0 rounded-4 p-4 hover-card">
                            <div class="row align-items-center g-3">
                                <!-- PNR & Status -->
                                <div class="col-md-3">
                                    <div class="text-muted small fw-bold">PNR NUMBER</div>
                                    <div class="fw-extrabold fs-5 text-primary mb-1"><?php echo htmlspecialchars($b['booking_pnr']); ?></div>
                                    <span class="badge <?php echo $is_conf ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger'; ?> fw-bold">
                                        <?php echo $b['booking_status']; ?>
                                    </span>
                                    <div class="text-muted mt-1" style="font-size: 0.75rem;">
                                        Booked on <?php echo date('d M Y, h:i A', strtotime($b['booking_date'])); ?>
                                    </div>
                                </div>

                                <!-- Route & Schedule -->
                                <div class="col-md-4">
                                    <div class="fw-bold fs-5 text-dark">
                                        <?php echo htmlspecialchars($b['source']); ?> &rarr; <?php echo htmlspecialchars($b['destination']); ?>
                                    </div>
                                    <div class="text-muted small mb-1">
                                        <?php echo htmlspecialchars($b['bus_name']); ?> (<?php echo htmlspecialchars($b['bus_number']); ?>)
                                    </div>
                                    <div class="small fw-semibold text-secondary">
                                        <i class="fa-solid fa-calendar-day me-1 text-primary"></i> <?php echo date('D, d M Y', strtotime($b['journey_date'])); ?> &bull; <?php echo substr($b['departure_time'], 0, 5); ?> hrs
                                    </div>
                                </div>

                                <!-- Fare & Seats -->
                                <div class="col-md-2 text-md-center">
                                    <div class="text-muted small fw-bold">Total Fare</div>
                                    <div class="fs-4 fw-extrabold text-dark">₹<?php echo number_format($b['total_amount'], 2); ?></div>
                                    <span class="badge bg-light text-dark border"><?php echo $b['total_seats']; ?> <?php echo ($b['total_seats'] > 1) ? 'Seats' : 'Seat'; ?></span>
                                    <?php if (!$is_conf && $b['refund_amount'] > 0): ?>
                                        <div class="small text-success mt-1">Refund: ₹<?php echo number_format($b['refund_amount'], 2); ?></div>
                                    <?php endif; ?>
                                </div>

                                <!-- Actions -->
                                <div class="col-md-3 text-md-end">
                                    <a href="ticket.php?pnr=<?php echo urlencode($b['booking_pnr']); ?>" class="btn btn-outline-primary btn-sm fw-bold me-1 mb-1">
                                        <i class="fa-solid fa-eye me-1"></i> E-Ticket
                                    </a>
                                    <?php if ($is_conf): ?>
                                        <a href="cancel_booking.php?pnr=<?php echo urlencode($b['booking_pnr']); ?>" 
                                           class="btn btn-outline-danger btn-sm fw-bold mb-1" 
                                           onclick="return confirm('Are you sure you want to cancel ticket <?php echo $b['booking_pnr']; ?>? 90% refund (₹<?php echo number_format($b['total_amount'] * 0.90, 2); ?>) will be credited.');">
                                            <i class="fa-solid fa-ban me-1"></i> Cancel
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Confirmed Bookings Tab -->
            <div class="tab-pane fade" id="confirmed-bookings" role="tabpanel">
                <div class="d-flex flex-column gap-3">
                    <?php 
                    $has_confirmed = false;
                    foreach ($bookings as $b): 
                        if ($b['booking_status'] !== 'Confirmed') continue;
                        $has_confirmed = true;
                    ?>
                        <div class="card shadow-sm border-0 rounded-4 p-4 hover-card">
                            <div class="row align-items-center g-3">
                                <div class="col-md-3">
                                    <div class="text-muted small fw-bold">PNR NUMBER</div>
                                    <div class="fw-extrabold fs-5 text-primary mb-1"><?php echo htmlspecialchars($b['booking_pnr']); ?></div>
                                    <span class="badge bg-success-subtle text-success fw-bold">Confirmed</span>
                                </div>
                                <div class="col-md-4">
                                    <div class="fw-bold fs-5 text-dark">
                                        <?php echo htmlspecialchars($b['source']); ?> &rarr; <?php echo htmlspecialchars($b['destination']); ?>
                                    </div>
                                    <div class="text-muted small mb-1">
                                        <?php echo htmlspecialchars($b['bus_name']); ?>
                                    </div>
                                    <div class="small fw-semibold text-secondary">
                                        <i class="fa-solid fa-calendar-day me-1 text-primary"></i> <?php echo date('D, d M Y', strtotime($b['journey_date'])); ?> &bull; <?php echo substr($b['departure_time'], 0, 5); ?> hrs
                                    </div>
                                </div>
                                <div class="col-md-2 text-md-center">
                                    <div class="fs-4 fw-extrabold text-dark">₹<?php echo number_format($b['total_amount'], 2); ?></div>
                                    <span class="badge bg-light text-dark border"><?php echo $b['total_seats']; ?> Seats</span>
                                </div>
                                <div class="col-md-3 text-md-end">
                                    <a href="ticket.php?pnr=<?php echo urlencode($b['booking_pnr']); ?>" class="btn btn-outline-primary btn-sm fw-bold me-1">
                                        <i class="fa-solid fa-eye me-1"></i> E-Ticket
                                    </a>
                                    <a href="cancel_booking.php?pnr=<?php echo urlencode($b['booking_pnr']); ?>" 
                                       class="btn btn-outline-danger btn-sm fw-bold" 
                                       onclick="return confirm('Cancel ticket <?php echo $b['booking_pnr']; ?>? 90% refund will be issued.');">
                                        <i class="fa-solid fa-ban me-1"></i> Cancel
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; 
                    if (!$has_confirmed):
                    ?>
                        <div class="text-center py-5 text-muted">No active confirmed bookings found.</div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Cancelled Bookings Tab -->
            <div class="tab-pane fade" id="cancelled-bookings" role="tabpanel">
                <div class="d-flex flex-column gap-3">
                    <?php 
                    $has_cancelled = false;
                    foreach ($bookings as $b): 
                        if ($b['booking_status'] !== 'Cancelled') continue;
                        $has_cancelled = true;
                    ?>
                        <div class="card shadow-sm border-0 rounded-4 p-4 opacity-75">
                            <div class="row align-items-center g-3">
                                <div class="col-md-3">
                                    <div class="text-muted small fw-bold">PNR NUMBER</div>
                                    <div class="fw-bold fs-5 text-secondary mb-1"><?php echo htmlspecialchars($b['booking_pnr']); ?></div>
                                    <span class="badge bg-danger-subtle text-danger fw-bold">Cancelled & Refunded</span>
                                </div>
                                <div class="col-md-4">
                                    <div class="fw-bold fs-5 text-dark">
                                        <?php echo htmlspecialchars($b['source']); ?> &rarr; <?php echo htmlspecialchars($b['destination']); ?>
                                    </div>
                                    <div class="text-muted small">Cancelled on <?php echo $b['cancellation_date'] ? date('d M Y', strtotime($b['cancellation_date'])) : 'N/A'; ?></div>
                                </div>
                                <div class="col-md-2 text-md-center">
                                    <div class="text-muted small">Refunded Amount</div>
                                    <div class="fs-4 fw-extrabold text-success">₹<?php echo number_format($b['refund_amount'], 2); ?></div>
                                </div>
                                <div class="col-md-3 text-md-end">
                                    <a href="ticket.php?pnr=<?php echo urlencode($b['booking_pnr']); ?>" class="btn btn-outline-secondary btn-sm">
                                        View Details
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; 
                    if (!$has_cancelled):
                    ?>
                        <div class="text-center py-5 text-muted">No cancelled bookings.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
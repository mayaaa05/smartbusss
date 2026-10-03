<?php
require_once 'config/db_connect.php';
session_start();
$page_title = "E-Ticket Boarding Pass";

$pnr = trim($_GET['pnr'] ?? '');
if (empty($pnr)) {
    header("Location: index.php");
    exit;
}

$stmt = $pdo->prepare("SELECT bk.*, s.departure_time, s.arrival_time, s.fare,
                              b.bus_name, b.bus_number, b.bus_type, 
                              r.source, r.destination, r.distance_km, r.estimated_duration,
                              u.full_name as booked_by, u.email as user_email 
                       FROM bookings bk 
                       JOIN schedules s ON bk.schedule_id = s.id 
                       JOIN buses b ON s.bus_id = b.id 
                       JOIN routes r ON s.route_id = r.id 
                       JOIN users u ON bk.user_id = u.id 
                       WHERE bk.booking_pnr = ?");
$stmt->execute([$pnr]);
$ticket = $stmt->fetch();

if (!$ticket) {
    include 'includes/header.php';
    echo '<div class="container py-5 text-center"><div class="alert alert-danger">Ticket with PNR ' . htmlspecialchars($pnr) . ' not found.</div><a href="my_bookings.php" class="btn btn-primary">My Bookings</a></div>';
    include 'includes/footer.php';
    exit;
}

$seat_stmt = $pdo->prepare("SELECT * FROM booking_seats WHERE booking_id = ?");
$seat_stmt->execute([$ticket['id']]);
$passengers = $seat_stmt->fetchAll();

$is_confirmed = ($ticket['booking_status'] === 'Confirmed');
$qr_data = urlencode("SmartBus PNR: {$ticket['booking_pnr']} | Route: {$ticket['source']} -> {$ticket['destination']} | Date: {$ticket['journey_date']} | Seats: {$ticket['total_seats']}");

include 'includes/header.php';
?>

<div class="container py-4">
    <?php if (isset($_GET['cb'])): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm border-0 d-flex align-items-center mb-4 d-print-none" role="alert">
            <i class="fa-solid fa-gift fs-3 me-3 text-warning"></i>
            <div>
                <strong class="d-block">Congratulations! ₹<?php echo htmlspecialchars($_GET['cb']); ?> Instant Cashback Credited!</strong>
                <span class="small">5% promo cashback was deposited into your <a href="wallet.php" class="text-success fw-bold text-decoration-underline">SmartBus Wallet</a> for your next journey.</span>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Action Bar (Hidden on Print) -->
    <div class="d-flex justify-content-between align-items-center mb-4 d-print-none flex-wrap gap-2">
        <div>
            <h3 class="fw-extrabold text-dark mb-0">
                <?php if ($is_confirmed): ?>
                    <i class="fa-solid fa-circle-check text-success me-2"></i> Booking Confirmed!
                <?php else: ?>
                    <i class="fa-solid fa-circle-xmark text-danger me-2"></i> Booking <?php echo $ticket['booking_status']; ?>
                <?php endif; ?>
            </h3>
            <p class="text-muted small mb-0">Digital boarding pass generated. Keep this handy at boarding time.</p>
        </div>

        <div class="d-flex gap-2">
            <button onclick="window.print()" class="btn btn-outline-primary btn-sm fw-bold">
                <i class="fa-solid fa-print me-1"></i> Print / Save PDF
            </button>
            <a href="my_bookings.php" class="btn btn-primary btn-sm fw-bold">
                <i class="fa-solid fa-ticket me-1"></i> View My Bookings
            </a>
            <?php if ($is_confirmed && isset($_SESSION['user_id']) && $_SESSION['user_id'] == $ticket['user_id']): ?>
                <a href="cancel_booking.php?pnr=<?php echo urlencode($ticket['booking_pnr']); ?>" class="btn btn-outline-danger btn-sm" onclick="return confirm('Are you sure you want to cancel this ticket? 90% refund will be issued.');">
                    <i class="fa-solid fa-ban me-1"></i> Cancel Ticket
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Ticket Wrapper -->
    <div class="ticket-wrapper">
        <div class="ticket-card shadow-lg">
            <!-- Ticket Top Header -->
            <div class="ticket-header">
                <div class="row align-items-center g-3">
                    <div class="col-sm-7">
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <span class="p-1 rounded bg-white text-primary d-inline-flex align-items-center justify-content-center" style="width: 28px; height: 28px;">
                                <i class="fa-solid fa-bus-simple fs-6"></i>
                            </span>
                            <span class="fw-bold tracking-wide">SmartBus Boarding Pass</span>
                        </div>
                        <h2 class="fw-extrabold mb-0 text-white">
                            <?php echo htmlspecialchars($ticket['source']); ?> &rarr; <?php echo htmlspecialchars($ticket['destination']); ?>
                        </h2>
                        <div class="small text-light opacity-90 mt-1">
                            <?php echo htmlspecialchars($ticket['bus_name']); ?> &bull; <?php echo htmlspecialchars($ticket['bus_type']); ?> (<?php echo htmlspecialchars($ticket['bus_number']); ?>)
                        </div>
                    </div>
                    
                    <div class="col-sm-5 text-sm-end">
                        <div class="small text-light opacity-75">BOOKING REFERENCE / PNR</div>
                        <div class="fs-3 fw-extrabold letter-spacing-1 text-warning">
                            <?php echo htmlspecialchars($ticket['booking_pnr']); ?>
                        </div>
                        <span class="badge <?php echo $is_confirmed ? 'bg-success' : 'bg-danger'; ?> text-white fw-bold">
                            <?php echo $ticket['booking_status']; ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Ticket Body Details -->
            <div class="p-4 p-md-5 bg-white">
                <div class="row g-4 pb-4 border-bottom">
                    <div class="col-6 col-md-3">
                        <div class="text-muted small fw-bold text-uppercase">Journey Date</div>
                        <div class="fs-5 fw-bold text-dark"><?php echo date('d M Y', strtotime($ticket['journey_date'])); ?></div>
                        <div class="small text-muted"><?php echo date('l', strtotime($ticket['journey_date'])); ?></div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="text-muted small fw-bold text-uppercase">Departure Time</div>
                        <div class="fs-5 fw-bold text-dark"><?php echo substr($ticket['departure_time'], 0, 5); ?> hrs</div>
                        <div class="small text-muted">Arrival ~<?php echo substr($ticket['arrival_time'], 0, 5); ?> hrs</div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="text-muted small fw-bold text-uppercase">Total Seats</div>
                        <div class="fs-5 fw-bold text-primary"><?php echo $ticket['total_seats']; ?> Seats</div>
                        <div class="small text-muted">
                            <?php 
                                $seat_nums = array_map(function($p) { return $p['seat_number']; }, $passengers);
                                echo implode(', ', $seat_nums);
                            ?>
                        </div>
                    </div>
                    <div class="col-6 col-md-3 text-md-end">
                        <div class="text-muted small fw-bold text-uppercase">Total Amount Paid</div>
                        <div class="fs-4 fw-extrabold text-success">₹<?php echo number_format($ticket['total_amount'], 2); ?></div>
                        <?php if ($ticket['payment_method'] === 'SmartBus Wallet'): ?>
                            <span class="badge bg-success-subtle text-success border border-success fw-bold"><i class="fa-solid fa-wallet me-1"></i> SmartBus Wallet (Paid)</span>
                        <?php else: ?>
                            <span class="badge bg-light text-dark border"><?php echo htmlspecialchars($ticket['payment_method']); ?> (Paid)</span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Boarding & Dropping Point Highlights -->
                <div class="row g-3 py-4 border-bottom bg-light bg-opacity-50 px-2 rounded-3 my-2">
                    <div class="col-md-6">
                        <div class="d-flex align-items-start gap-2">
                            <i class="fa-solid fa-location-dot text-danger fs-5 mt-1"></i>
                            <div>
                                <div class="text-muted small fw-bold">BOARDING POINT</div>
                                <div class="fw-bold text-dark"><?php echo htmlspecialchars($ticket['boarding_point']); ?></div>
                                <div class="text-muted small">Please report 15 minutes before scheduled departure</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="d-flex align-items-start gap-2">
                            <i class="fa-solid fa-location-crosshairs text-success fs-5 mt-1"></i>
                            <div>
                                <div class="text-muted small fw-bold">DROPPING POINT</div>
                                <div class="fw-bold text-dark"><?php echo htmlspecialchars($ticket['dropping_point']); ?></div>
                                <div class="text-muted small"><?php echo htmlspecialchars($ticket['destination']); ?> Central Drop Zone</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Passenger Details Table -->
                <div class="my-4">
                    <h6 class="fw-bold text-dark mb-3"><i class="fa-solid fa-users text-primary me-2"></i> Passenger & Seat Allocation</h6>
                    <div class="table-responsive">
                        <table class="table table-bordered align-middle text-center small mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th class="text-start">Passenger Name</th>
                                    <th>Age</th>
                                    <th>Gender</th>
                                    <th>Seat Assigned</th>
                                    <th class="text-end">Seat Fare</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($passengers as $idx => $p): ?>
                                    <tr>
                                        <td><?php echo $idx + 1; ?></td>
                                        <td class="text-start fw-bold text-dark"><?php echo htmlspecialchars($p['passenger_name']); ?></td>
                                        <td><?php echo $p['passenger_age']; ?> yrs</td>
                                        <td><?php echo $p['passenger_gender']; ?></td>
                                        <td><span class="badge bg-primary fs-6 px-2"><?php echo htmlspecialchars($p['seat_number']); ?></span></td>
                                        <td class="text-end fw-semibold">₹<?php echo number_format($p['fare'], 2); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- QR Code & Boarding Pass Scanner Bar -->
                <div class="row align-items-center g-4 p-3 bg-light rounded-4 border">
                    <div class="col-md-3 text-center">
                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=130x130&data=<?php echo $qr_data; ?>" 
                             alt="QR Code" 
                             class="img-fluid rounded border p-1 bg-white shadow-sm"
                             style="max-width: 130px;"
                             onerror="this.style.display='none'; document.getElementById('qrFallback').style.display='block';">
                        <div id="qrFallback" style="display:none;" class="p-3 bg-white border rounded">
                            <i class="fa-solid fa-qrcode fs-1 text-dark"></i>
                        </div>
                        <div class="text-muted mt-1" style="font-size: 0.7rem;">Scan at Boarding Gate</div>
                    </div>
                    <div class="col-md-9">
                        <h6 class="fw-bold text-dark mb-1">Important Travel Instructions</h6>
                        <ul class="text-muted small mb-0 ps-3">
                            <li>Keep this digital boarding pass or SMS ready on your phone during boarding.</li>
                            <li>Carry one valid Government photo ID proof (Aadhaar, Voter ID, PAN, Driving License).</li>
                            <li>Luggage allowance: Up to 20 kg per passenger in the luggage compartment.</li>
                            <li>Helpline assistance: <strong>1800-456-7890</strong> or email <strong>support@smartbus.com</strong>.</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Footer Strip of Ticket -->
            <div class="p-3 bg-dark text-white text-center small">
                Booked by: <strong><?php echo htmlspecialchars($ticket['booked_by']); ?></strong> &bull; Contact: <?php echo htmlspecialchars($ticket['contact_phone']); ?> &bull; SmartBus Intercity Services
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
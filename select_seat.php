<?php
require_once 'config/db_connect.php';
$page_title = "Select Your Seats";

$schedule_id = (int)($_GET['schedule_id'] ?? 0);
if ($schedule_id <= 0) {
    header("Location: search_bus.php");
    exit;
}

// Fetch schedule, bus, and route details
$stmt = $pdo->prepare("SELECT s.*, b.bus_name, b.bus_number, b.bus_type, b.total_seats, b.amenities, b.rating,
                              r.source, r.destination, r.distance_km, r.estimated_duration 
                       FROM schedules s 
                       JOIN buses b ON s.bus_id = b.id 
                       JOIN routes r ON s.route_id = r.id 
                       WHERE s.id = ?");
$stmt->execute([$schedule_id]);
$details = $stmt->fetch();

if (!$details) {
    include 'includes/header.php';
    echo '<div class="container py-5 text-center"><h3>Invalid schedule selected.</h3><a href="search_bus.php" class="btn btn-primary mt-3">Back to Search</a></div>';
    include 'includes/footer.php';
    exit;
}

// Fetch currently booked seat numbers for this schedule
$booked_stmt = $pdo->prepare("SELECT bs.seat_number FROM booking_seats bs 
                              JOIN bookings bk ON bs.booking_id = bk.id 
                              WHERE bk.schedule_id = ? AND bk.booking_status = 'Confirmed'");
$booked_stmt->execute([$schedule_id]);
$booked_seats = $booked_stmt->fetchAll(PDO::FETCH_COLUMN);

include 'includes/header.php';
?>

<div class="container py-4">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb small">
            <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none">Home</a></li>
            <li class="breadcrumb-item"><a href="search_bus.php?source=<?php echo urlencode($details['source']); ?>&destination=<?php echo urlencode($details['destination']); ?>" class="text-decoration-none">Search</a></li>
            <li class="breadcrumb-item active" aria-current="page">Select Seats</li>
        </ol>
    </nav>

    <div class="row g-4">
        <!-- Main Seat Selection Coach -->
        <div class="col-lg-8">
            <div class="card shadow-sm border-0 rounded-4 p-4">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center pb-3 mb-4 border-bottom g-2">
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <h4 class="fw-bold text-dark mb-0"><?php echo htmlspecialchars($details['bus_name']); ?></h4>
                            <span class="badge bg-primary-subtle text-primary"><?php echo htmlspecialchars($details['bus_type']); ?></span>
                        </div>
                        <p class="text-muted small mb-0">
                            <strong><?php echo htmlspecialchars($details['source']); ?></strong> &rarr; <strong><?php echo htmlspecialchars($details['destination']); ?></strong> 
                            &bull; Date: <?php echo date('D, d M Y', strtotime($details['journey_date'])); ?> 
                            &bull; Dep: <?php echo substr($details['departure_time'], 0, 5); ?>
                        </p>
                    </div>
                    <div class="mt-2 mt-md-0 text-md-end">
                        <span class="badge bg-success-subtle text-success fs-6 py-2 px-3 fw-bold">
                            ₹<?php echo number_format($details['fare'], 0); ?> / seat
                        </span>
                    </div>
                </div>

                <!-- Seat Legend -->
                <div class="d-flex justify-content-center flex-wrap gap-4 mb-4 p-3 bg-light rounded-3 border">
                    <div class="d-flex align-items-center gap-2">
                        <div class="seat-legend-box available"></div> 
                        <span class="small fw-semibold text-muted">Available</span>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <div class="seat-legend-box selected"></div> 
                        <span class="small fw-semibold text-muted">Selected</span>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <div class="seat-legend-box booked"></div> 
                        <span class="small fw-semibold text-muted">Booked / Reserved</span>
                    </div>
                </div>

                <!-- Bus Coach Container -->
                <div class="bus-coach-container shadow-inner">
                    <!-- Driver Cabin Area -->
                    <div class="driver-cabin">
                        <div class="d-flex align-items-center gap-2 text-muted small fw-bold">
                            <i class="fa-solid fa-arrow-up"></i> FRONT / ENTRY
                        </div>
                        <div class="d-flex align-items-center gap-2 text-secondary" title="Driver Steering Area">
                            <span class="small fw-bold text-muted">DRIVER</span>
                            <i class="fa-solid fa-dharmachakra fs-4 text-dark"></i>
                        </div>
                    </div>

                    <!-- Seat Layout Grid -->
                    <div class="seat-grid">
                        <?php
                        $total = (int)$details['total_seats'];
                        $rows = max(1, ceil($total / 4));
                        
                        for ($r = 1; $r <= $rows; $r++):
                        ?>
                            <div class="seat-row">
                                <!-- Left Pair (A, B) -->
                                <div class="seat-pair">
                                    <?php foreach (['A', 'B'] as $col): 
                                        $seat = $r . $col;
                                        $is_booked = in_array($seat, $booked_seats);
                                    ?>
                                        <button type="button" 
                                            class="seat-btn <?php echo $is_booked ? 'booked' : 'available'; ?>" 
                                            data-seat="<?php echo $seat; ?>" 
                                            aria-label="Seat <?php echo $seat; ?> <?php echo $is_booked ? 'Booked' : 'Available'; ?>"
                                            <?php echo $is_booked ? 'disabled' : ''; ?>>
                                            <?php echo $seat; ?>
                                        </button>
                                    <?php endforeach; ?>
                                </div>

                                <!-- Aisle -->
                                <div class="seat-aisle">AISLE</div>

                                <!-- Right Pair (C, D) -->
                                <div class="seat-pair">
                                    <?php foreach (['C', 'D'] as $col): 
                                        $seat = $r . $col;
                                        $is_booked = in_array($seat, $booked_seats);
                                    ?>
                                        <button type="button" 
                                            class="seat-btn <?php echo $is_booked ? 'booked' : 'available'; ?>" 
                                            data-seat="<?php echo $seat; ?>" 
                                            aria-label="Seat <?php echo $seat; ?> <?php echo $is_booked ? 'Booked' : 'Available'; ?>"
                                            <?php echo $is_booked ? 'disabled' : ''; ?>>
                                            <?php echo $seat; ?>
                                        </button>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>

                <div class="alert alert-light border small text-muted mt-4 mb-0 text-center">
                    <i class="fa-solid fa-circle-info text-primary me-1"></i> You can select up to <strong>6 seats</strong> in a single transaction. Click an available seat to select or unselect.
                </div>
            </div>
        </div>

        <!-- Sticky Booking Summary Sidebar -->
        <div class="col-lg-4">
            <div class="card shadow-sm border-0 rounded-4 p-4 sticky-top" style="top: 85px;">
                <h5 class="fw-bold mb-3 text-dark d-flex align-items-center gap-2">
                    <i class="fa-solid fa-receipt text-primary"></i> Booking Summary
                </h5>

                <div class="p-3 bg-light rounded-3 mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="small text-muted">Selected Seats:</span>
                        <span id="seatCountBadge" class="badge bg-primary">0 / 6</span>
                    </div>
                    <div id="selectedSeatsDisplay" class="fw-extrabold text-primary fs-5">
                        None selected
                    </div>
                </div>

                <div class="d-flex justify-content-between small text-muted mb-2">
                    <span>Base Fare per Seat</span>
                    <span class="fw-semibold text-dark">₹<?php echo number_format($details['fare'], 2); ?></span>
                </div>

                <div class="d-flex justify-content-between small text-muted mb-2">
                    <span>GST & Travel Assurance (5%)</span>
                    <span id="taxDisplay" class="fw-semibold text-dark">₹0.00</span>
                </div>

                <div class="d-flex justify-content-between fs-4 fw-extrabold text-dark border-top pt-2 mb-3">
                    <span>Total Amount</span>
                    <span id="totalAmountDisplay" class="text-primary">₹0.00</span>
                </div>

                <!-- Form Proceeding to Passenger Confirmation -->
                <form action="confirm_booking.php" method="POST" id="seatForm" class="prevent-double-submit">
                    <input type="hidden" name="schedule_id" value="<?php echo $details['id']; ?>">
                    <input type="hidden" name="selected_seats" id="selectedSeatsInput" value="">
                    
                    <button type="submit" id="proceedBtn" class="btn btn-primary btn-lg w-100 py-3 fw-bold shadow-sm" disabled>
                        <span>Continue to Passengers</span> <i class="fa-solid fa-arrow-right ms-1"></i>
                    </button>
                </form>

                <div class="text-center mt-3">
                    <span class="small text-muted"><i class="fa-solid fa-lock text-success me-1"></i> Safe & Secure 256-bit Booking</span>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const selectedSeats = new Set();
    const fare = <?php echo (float)$details['fare']; ?>;
    const maxSeats = 6;
    
    const seatBtns = document.querySelectorAll('.seat-btn.available');
    const display = document.getElementById('selectedSeatsDisplay');
    const countBadge = document.getElementById('seatCountBadge');
    const taxDisplay = document.getElementById('taxDisplay');
    const totalDisplay = document.getElementById('totalAmountDisplay');
    const input = document.getElementById('selectedSeatsInput');
    const proceedBtn = document.getElementById('proceedBtn');

    seatBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            const seat = btn.getAttribute('data-seat');
            
            if (selectedSeats.has(seat)) {
                selectedSeats.delete(seat);
                btn.classList.remove('selected');
            } else {
                if (selectedSeats.size >= maxSeats) {
                    alert('You can select a maximum of ' + maxSeats + ' seats per booking.');
                    return;
                }
                selectedSeats.add(seat);
                btn.classList.add('selected');
            }

            const arr = Array.from(selectedSeats);
            const count = arr.length;
            const subtotal = count * fare;
            const tax = subtotal * 0.05;
            const grandTotal = subtotal + tax;

            display.textContent = count > 0 ? arr.join(', ') : 'None selected';
            countBadge.textContent = count + ' / ' + maxSeats;
            taxDisplay.textContent = '₹' + tax.toFixed(2);
            totalDisplay.textContent = '₹' + grandTotal.toFixed(2);
            input.value = arr.join(',');
            proceedBtn.disabled = count === 0;
        });
    });
});
</script>

<?php include 'includes/footer.php'; ?>
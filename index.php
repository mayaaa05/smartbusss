<?php
require_once 'config/db_connect.php';
$page_title = "Book Bus Tickets Online - Instant Seat Selection";
include 'includes/header.php';

// Fetch distinct cities for autocomplete dropdowns (ordered alphabetically)
$cities_stmt = $pdo->query("SELECT DISTINCT source AS city FROM routes UNION SELECT DISTINCT destination AS city FROM routes ORDER BY city");
$cities = $cities_stmt->fetchAll(PDO::FETCH_COLUMN);

// Fetch total stats for live counters
$stat_routes = $pdo->query("SELECT COUNT(*) FROM routes")->fetchColumn() ?: 600;
$stat_buses = $pdo->query("SELECT COUNT(*) FROM buses WHERE status = 'Active'")->fetchColumn() ?: 6;
$stat_bookings = $pdo->query("SELECT COUNT(*) FROM bookings WHERE booking_status = 'Confirmed'")->fetchColumn() ?: 500;
?>

<!-- Alert Banners for Notifications -->
<div class="container mt-3">
    <?php if (isset($_GET['registered'])): ?>
        <div class="alert alert-success alert-dismissible fade show alert-dismissible-auto shadow-sm border-0 d-flex align-items-center" role="alert">
            <i class="fa-solid fa-circle-check fs-4 me-3 text-success"></i>
            <div>
                <strong>Welcome to SmartBus!</strong> Your account was created successfully. You can now choose your seats and book tickets.
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'logged_out'): ?>
        <div class="alert alert-info alert-dismissible fade show alert-dismissible-auto shadow-sm border-0 d-flex align-items-center" role="alert">
            <i class="fa-solid fa-circle-info fs-4 me-3 text-primary"></i>
            <div>
                You have been logged out securely. Have a great journey ahead!
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
</div>

<!-- Hero Section with Booking Form -->
<section class="hero-section text-white bg-gradient-primary">
    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-lg-6">
                <span class="badge bg-warning text-dark px-3 py-2 rounded-pill fw-bold mb-3 shadow-sm">
                    <i class="fa-solid fa-bolt me-1"></i> Fast, Safe & Reliable Travel
                </span>
                <h1 class="display-4 fw-extrabold mb-3 text-white">
                    India's Smartest Bus Reservation Platform
                </h1>
                <p class="lead text-light opacity-90 mb-4">
                    Explore <strong><?php echo number_format($stat_routes); ?>+</strong> verified intercity routes. Select your exact window or aisle seats in real-time with instant digital boarding passes.
                </p>

                <!-- Quick Stats Pills -->
                <div class="d-flex flex-wrap gap-3 pt-2">
                    <div class="d-flex align-items-center gap-2 bg-white bg-opacity-10 px-3 py-2 rounded-3 border border-white border-opacity-10">
                        <i class="fa-solid fa-route text-warning fs-5"></i>
                        <div>
                            <div class="fw-bold small"><?php echo number_format($stat_routes); ?>+ Routes</div>
                            <div class="text-light opacity-75" style="font-size: 0.7rem;">Intercity Network</div>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-2 bg-white bg-opacity-10 px-3 py-2 rounded-3 border border-white border-opacity-10">
                        <i class="fa-solid fa-bus text-warning fs-5"></i>
                        <div>
                            <div class="fw-bold small">Top Fleets</div>
                            <div class="text-light opacity-75" style="font-size: 0.7rem;">Volvo Multi-Axle & AC</div>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-2 bg-white bg-opacity-10 px-3 py-2 rounded-3 border border-white border-opacity-10">
                        <i class="fa-solid fa-ticket text-warning fs-5"></i>
                        <div>
                            <div class="fw-bold small">Zero Hidden Fee</div>
                            <div class="text-light opacity-75" style="font-size: 0.7rem;">100% Transparent Fares</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Booking Form Card -->
            <div class="col-lg-6">
                <div class="search-card">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h4 class="fw-bold mb-0 text-primary d-flex align-items-center gap-2">
                            <i class="fa-solid fa-route"></i> Plan Your Journey
                        </h4>
                        <span class="badge badge-subtle-success">Live Seats</span>
                    </div>

                    <form action="search_bus.php" method="GET" class="prevent-double-submit">
                        <div class="position-relative mb-3">
                            <label class="form-label">
                                <i class="fa-solid fa-location-dot text-danger me-1"></i> From (Origin City)
                            </label>
                            <input type="text" name="source" class="form-control form-control-lg" list="originCitiesList" placeholder="e.g. Mumbai" value="Mumbai" required autocomplete="off">
                        </div>

                        <!-- Swap Origin & Destination Button -->
                        <div class="d-flex justify-content-center my-n2 position-relative" style="z-index: 5; margin-top: -12px; margin-bottom: -12px;">
                            <button type="button" id="swapCitiesBtn" class="swap-btn shadow-sm" title="Swap Origin & Destination" aria-label="Swap Cities">
                                <i class="fa-solid fa-arrow-down-up-across-line"></i>
                            </button>
                        </div>

                        <div class="position-relative mb-3">
                            <label class="form-label">
                                <i class="fa-solid fa-location-crosshairs text-success me-1"></i> To (Destination City)
                            </label>
                            <input type="text" name="destination" class="form-control form-control-lg" list="destCitiesList" placeholder="e.g. Pune" value="Pune" required autocomplete="off">
                        </div>

                        <div class="mb-4">
                            <label class="form-label">
                                <i class="fa-solid fa-calendar-day text-primary me-1"></i> Date of Journey
                            </label>
                            <input type="date" name="journey_date" class="form-control form-control-lg" value="<?php echo date('Y-m-d'); ?>" min="<?php echo date('Y-m-d'); ?>" required>
                        </div>

                        <!-- Autocomplete Datalists -->
                        <datalist id="originCitiesList">
                            <?php foreach ($cities as $c): ?>
                                <option value="<?php echo htmlspecialchars($c); ?>">
                            <?php endforeach; ?>
                        </datalist>
                        <datalist id="destCitiesList">
                            <?php foreach ($cities as $c): ?>
                                <option value="<?php echo htmlspecialchars($c); ?>">
                            <?php endforeach; ?>
                        </datalist>

                        <button type="submit" class="btn btn-primary btn-lg w-100 py-3 fw-bold shadow-sm">
                            <i class="fa-solid fa-magnifying-glass me-2"></i> Search Available Buses
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Why SmartBus Section -->
<section class="py-5 bg-white border-bottom">
    <div class="container py-2">
        <div class="text-center mb-5">
            <span class="badge bg-primary-subtle text-primary px-3 py-2 rounded-pill fw-bold mb-2">Key Advantages</span>
            <h2 class="fw-bold">Why Travel With SmartBus?</h2>
            <p class="text-muted">Designed specifically to eliminate bus stand queues and manual paperwork</p>
        </div>

        <div class="row g-4">
            <div class="col-md-6 col-lg-3">
                <div class="stat-box h-100">
                    <div class="stat-icon bg-primary-subtle text-primary">
                        <i class="fa-solid fa-chair"></i>
                    </div>
                    <h5 class="fw-bold mb-2">Live Seat Selection</h5>
                    <p class="text-muted small mb-0">Choose your preferred window or aisle seat from an interactive real-time visual coach layout.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="stat-box h-100">
                    <div class="stat-icon bg-success-subtle text-success">
                        <i class="fa-solid fa-bolt"></i>
                    </div>
                    <h5 class="fw-bold mb-2">Instant E-Ticket & QR</h5>
                    <p class="text-muted small mb-0">Board directly using your phone! Digital QR boarding passes are generated instantly upon confirmation.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="stat-box h-100">
                    <div class="stat-icon bg-warning-subtle text-warning">
                        <i class="fa-solid fa-shield-halved"></i>
                    </div>
                    <h5 class="fw-bold mb-2">Safe & Verified Buses</h5>
                    <p class="text-muted small mb-0">All buses are sanitised, air-conditioned, GPS tracked, and operated by professional certified staff.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="stat-box h-100">
                    <div class="stat-icon bg-info-subtle text-info">
                        <i class="fa-solid fa-rotate-left"></i>
                    </div>
                    <h5 class="fw-bold mb-2">Easy 1-Click Cancel</h5>
                    <p class="text-muted small mb-0">Change of plans? Cancel your ticket directly from My Bookings and receive a prompt 90% refund.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Popular Routes Section -->
<section class="py-5 bg-light">
    <div class="container py-2">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-end mb-4">
            <div>
                <span class="badge bg-secondary-subtle text-secondary px-3 py-2 rounded-pill fw-bold mb-2">Trending</span>
                <h2 class="fw-bold mb-1">Popular Bus Routes</h2>
                <p class="text-muted mb-0">Most frequently booked intercity luxury schedules</p>
            </div>
            <a href="search_bus.php" class="btn btn-outline-primary btn-sm mt-3 mt-md-0">
                View All Routes <i class="fa-solid fa-arrow-right ms-1"></i>
            </a>
        </div>

        <div class="row g-4">
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 shadow-sm border-0 p-4 hover-card">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="badge bg-primary-subtle text-primary fw-bold">Daily 12+ Buses</span>
                        <span class="text-success fw-bold">From ₹450</span>
                    </div>
                    <h5 class="fw-bold mb-1">Mumbai &rarr; Pune</h5>
                    <p class="text-muted small mb-3"><i class="fa-solid fa-road me-1 text-secondary"></i> 150 km &bull; Approx 3h 30m</p>
                    <div class="d-flex align-items-center justify-content-between pt-2 border-top">
                        <span class="small text-muted"><i class="fa-solid fa-star text-warning me-1"></i> 4.8 / 5.0</span>
                        <a href="search_bus.php?source=Mumbai&destination=Pune" class="btn btn-sm btn-primary">Book Now</a>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-lg-4">
                <div class="card h-100 shadow-sm border-0 p-4 hover-card">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="badge bg-primary-subtle text-primary fw-bold">Overnight Sleeper</span>
                        <span class="text-success fw-bold">From ₹1,100</span>
                    </div>
                    <h5 class="fw-bold mb-1">Pune &rarr; Goa</h5>
                    <p class="text-muted small mb-3"><i class="fa-solid fa-road me-1 text-secondary"></i> 440 km &bull; Approx 9h 15m</p>
                    <div class="d-flex align-items-center justify-content-between pt-2 border-top">
                        <span class="small text-muted"><i class="fa-solid fa-star text-warning me-1"></i> 4.9 / 5.0</span>
                        <a href="search_bus.php?source=Pune&destination=Goa" class="btn btn-sm btn-primary">Book Now</a>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-lg-4">
                <div class="card h-100 shadow-sm border-0 p-4 hover-card">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="badge bg-primary-subtle text-primary fw-bold">Express Service</span>
                        <span class="text-success fw-bold">From ₹650</span>
                    </div>
                    <h5 class="fw-bold mb-1">Bangalore &rarr; Chennai</h5>
                    <p class="text-muted small mb-3"><i class="fa-solid fa-road me-1 text-secondary"></i> 345 km &bull; Approx 6h 00m</p>
                    <div class="d-flex align-items-center justify-content-between pt-2 border-top">
                        <span class="small text-muted"><i class="fa-solid fa-star text-warning me-1"></i> 4.7 / 5.0</span>
                        <a href="search_bus.php?source=Bangalore&destination=Chennai" class="btn btn-sm btn-primary">Book Now</a>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-lg-4">
                <div class="card h-100 shadow-sm border-0 p-4 hover-card">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="badge bg-primary-subtle text-primary fw-bold">Highway Cruiser</span>
                        <span class="text-success fw-bold">From ₹520</span>
                    </div>
                    <h5 class="fw-bold mb-1">Delhi &rarr; Jaipur</h5>
                    <p class="text-muted small mb-3"><i class="fa-solid fa-road me-1 text-secondary"></i> 280 km &bull; Approx 5h 15m</p>
                    <div class="d-flex align-items-center justify-content-between pt-2 border-top">
                        <span class="small text-muted"><i class="fa-solid fa-star text-warning me-1"></i> 4.6 / 5.0</span>
                        <a href="search_bus.php?source=Delhi&destination=Jaipur" class="btn btn-sm btn-primary">Book Now</a>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-lg-4">
                <div class="card h-100 shadow-sm border-0 p-4 hover-card">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="badge bg-primary-subtle text-primary fw-bold">Superfast</span>
                        <span class="text-success fw-bold">From ₹480</span>
                    </div>
                    <h5 class="fw-bold mb-1">Hyderabad &rarr; Vijayawada</h5>
                    <p class="text-muted small mb-3"><i class="fa-solid fa-road me-1 text-secondary"></i> 275 km &bull; Approx 4h 45m</p>
                    <div class="d-flex align-items-center justify-content-between pt-2 border-top">
                        <span class="small text-muted"><i class="fa-solid fa-star text-warning me-1"></i> 4.7 / 5.0</span>
                        <a href="search_bus.php?source=Hyderabad&destination=Vijayawada" class="btn btn-sm btn-primary">Book Now</a>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-lg-4">
                <div class="card h-100 shadow-sm border-0 p-4 hover-card">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="badge bg-primary-subtle text-primary fw-bold">Direct Sleeper</span>
                        <span class="text-success fw-bold">From ₹1,299</span>
                    </div>
                    <h5 class="fw-bold mb-1">Mumbai &rarr; Goa</h5>
                    <p class="text-muted small mb-3"><i class="fa-solid fa-road me-1 text-secondary"></i> 590 km &bull; Approx 10h 30m</p>
                    <div class="d-flex align-items-center justify-content-between pt-2 border-top">
                        <span class="small text-muted"><i class="fa-solid fa-star text-warning me-1"></i> 4.9 / 5.0</span>
                        <a href="search_bus.php?source=Mumbai&destination=Goa" class="btn btn-sm btn-primary">Book Now</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- How It Works (3 Steps) -->
<section class="py-5 bg-white">
    <div class="container py-2">
        <div class="text-center mb-5">
            <span class="badge bg-primary-subtle text-primary px-3 py-2 rounded-pill fw-bold mb-2">Simple & Intuitive</span>
            <h2 class="fw-bold">How SmartBus Works in 3 Steps</h2>
            <p class="text-muted">Book your bus ticket in less than 60 seconds</p>
        </div>

        <div class="row g-4 text-center">
            <div class="col-md-4">
                <div class="p-3">
                    <div class="rounded-circle bg-primary text-white d-inline-flex align-items-center justify-content-center mb-3 shadow-sm" style="width: 64px; height: 64px; font-size: 1.5rem; font-weight: 800;">
                        1
                    </div>
                    <h5 class="fw-bold mb-2">Search Your Route</h5>
                    <p class="text-muted small">Enter origin city, destination, and journey date to instantly view all available schedules and fares.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="p-3">
                    <div class="rounded-circle bg-primary text-white d-inline-flex align-items-center justify-content-center mb-3 shadow-sm" style="width: 64px; height: 64px; font-size: 1.5rem; font-weight: 800;">
                        2
                    </div>
                    <h5 class="fw-bold mb-2">Pick Your Preferred Seat</h5>
                    <p class="text-muted small">View the live coach map. Click to select your window or aisle seats with transparent real-time pricing.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="p-3">
                    <div class="rounded-circle bg-primary text-white d-inline-flex align-items-center justify-content-center mb-3 shadow-sm" style="width: 64px; height: 64px; font-size: 1.5rem; font-weight: 800;">
                        3
                    </div>
                    <h5 class="fw-bold mb-2">Get Instant E-Ticket</h5>
                    <p class="text-muted small">Complete the demo payment and instantly download your boarding pass with a verifiable QR code.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- FAQ Accordion Section -->
<section class="py-5 bg-light border-top">
    <div class="container py-2">
        <div class="text-center mb-5">
            <span class="badge bg-secondary-subtle text-secondary px-3 py-2 rounded-pill fw-bold mb-2">Got Questions?</span>
            <h2 class="fw-bold">Frequently Asked Questions</h2>
            <p class="text-muted">Everything you need to know about SmartBus reservations</p>
        </div>

        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="accordion shadow-sm rounded-4 overflow-hidden border-0" id="smartBusFAQ">
                    <div class="accordion-item border-0 border-bottom">
                        <h2 class="accordion-header" id="faqHeadingOne">
                            <button class="accordion-button fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#faqOne" aria-expanded="true" aria-controls="faqOne">
                                Do I need to print my bus ticket?
                            </button>
                        </h2>
                        <div id="faqOne" class="accordion-collapse collapse show" aria-labelledby="faqHeadingOne" data-bs-parent="#smartBusFAQ">
                            <div class="accordion-body text-muted small">
                                No physical printout is necessary! SmartBus generates a digital boarding pass with a QR code that can be shown on your mobile device directly to the bus conductor before boarding.
                            </div>
                        </div>
                    </div>

                    <div class="accordion-item border-0 border-bottom">
                        <h2 class="accordion-header" id="faqHeadingTwo">
                            <button class="accordion-button collapsed fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#faqTwo" aria-expanded="false" aria-controls="faqTwo">
                                How does ticket cancellation and refund work?
                            </button>
                        </h2>
                        <div id="faqTwo" class="accordion-collapse collapse" aria-labelledby="faqHeadingTwo" data-bs-parent="#smartBusFAQ">
                            <div class="accordion-body text-muted small">
                                You can cancel any confirmed ticket with a single click from the <strong>My Bookings</strong> section. A standard 90% refund is immediately calculated and credited back to your account.
                            </div>
                        </div>
                    </div>

                    <div class="accordion-item border-0 border-bottom">
                        <h2 class="accordion-header" id="faqHeadingThree">
                            <button class="accordion-button collapsed fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#faqThree" aria-expanded="false" aria-controls="faqThree">
                                How many seats can I book at once?
                            </button>
                        </h2>
                        <div id="faqThree" class="accordion-collapse collapse" aria-labelledby="faqHeadingThree" data-bs-parent="#smartBusFAQ">
                            <div class="accordion-body text-muted small">
                                You can select and book up to 6 seats in a single reservation transaction. For each seat, passenger details (name, age, gender) are recorded.
                            </div>
                        </div>
                    </div>

                    <div class="accordion-item border-0">
                        <h2 class="accordion-header" id="faqHeadingFour">
                            <button class="accordion-button collapsed fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#faqFour" aria-expanded="false" aria-controls="faqFour">
                                Is this real payment or demo simulation?
                            </button>
                        </h2>
                        <div id="faqFour" class="accordion-collapse collapse" aria-labelledby="faqHeadingFour" data-bs-parent="#smartBusFAQ">
                            <div class="accordion-body text-muted small">
                                As part of this Diploma / TYCS academic engineering project, SmartBus features an interactive simulation payment gateway. No real credit/debit card or UPI account will be charged.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>
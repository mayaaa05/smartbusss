<?php
require_once 'config/db_connect.php';
$page_title = "About Us - SmartBus";
include 'includes/header.php';

$routes_count = $pdo->query("SELECT COUNT(*) FROM routes")->fetchColumn() ?: 600;
$buses_count = $pdo->query("SELECT COUNT(*) FROM buses WHERE status = 'Active'")->fetchColumn() ?: 6;
$bookings_count = $pdo->query("SELECT COUNT(*) FROM bookings")->fetchColumn() ?: 1200;
?>

<!-- About Hero Section -->
<section class="py-5 bg-gradient-primary text-white text-center">
    <div class="container py-4">
        <span class="badge bg-warning text-dark px-3 py-2 rounded-pill fw-bold mb-3 shadow-sm">
            <i class="fa-solid fa-bus-simple me-1"></i> About SmartBus
        </span>
        <h1 class="display-4 fw-extrabold mb-3 text-white">Digitizing Intercity Bus Travel Across India</h1>
        <p class="lead text-light opacity-90 max-w-700 mx-auto" style="max-width: 720px;">
            SmartBus was developed to eliminate long bus depot queues, manual paper ticketing, and opaque pricing. We offer real-time seat availability, transparent fares, and instant verifiable e-boarding passes.
        </p>
    </div>
</section>

<!-- Mission & Stats Section -->
<section class="py-5 bg-white">
    <div class="container py-2">
        <div class="row align-items-center g-5 mb-5">
            <div class="col-lg-6">
                <span class="badge bg-primary-subtle text-primary fw-bold mb-2">Our Mission</span>
                <h2 class="fw-bold mb-3">Modern, Safe &amp; Convenient Travel For Every Commuter</h2>
                <p class="text-muted">
                    In the traditional bus reservation process, passengers frequently travel to bus booking counters or depend on agents, leading to wasted time, manual record issues, and booking uncertainty.
                </p>
                <p class="text-muted">
                    SmartBus simplifies the complete journey cycle: search routes by source, destination, and date; view live coach configurations; select preferred window or aisle seats; and receive an instant e-ticket with a digital QR code ready for gate boarding.
                </p>
                <div class="d-flex gap-3 pt-2">
                    <a href="search_bus.php" class="btn btn-primary fw-bold">
                        <i class="fa-solid fa-magnifying-glass me-1"></i> Book a Ticket
                    </a>
                    <a href="contact.php" class="btn btn-outline-secondary fw-bold">
                        <i class="fa-solid fa-envelope me-1"></i> Contact Us
                    </a>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="row g-3">
                    <div class="col-6">
                        <div class="stat-box h-100">
                            <div class="stat-icon bg-primary-subtle text-primary">
                                <i class="fa-solid fa-route"></i>
                            </div>
                            <div class="fs-2 fw-extrabold text-primary"><?php echo number_format($routes_count); ?>+</div>
                            <div class="text-muted small fw-semibold">Connected Routes</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="stat-box h-100">
                            <div class="stat-icon bg-success-subtle text-success">
                                <i class="fa-solid fa-bus"></i>
                            </div>
                            <div class="fs-2 fw-extrabold text-success"><?php echo $buses_count; ?>+</div>
                            <div class="text-muted small fw-semibold">Modern Fleets</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="stat-box h-100">
                            <div class="stat-icon bg-warning-subtle text-warning">
                                <i class="fa-solid fa-ticket"></i>
                            </div>
                            <div class="fs-2 fw-extrabold text-warning"><?php echo number_format($bookings_count); ?>+</div>
                            <div class="text-muted small fw-semibold">Tickets Reserved</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="stat-box h-100">
                            <div class="stat-icon bg-info-subtle text-info">
                                <i class="fa-solid fa-star"></i>
                            </div>
                            <div class="fs-2 fw-extrabold text-info">4.8 / 5.0</div>
                            <div class="text-muted small fw-semibold">Passenger Rating</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Core Values Section -->
        <div class="pt-4 border-top">
            <div class="text-center mb-5">
                <span class="badge bg-secondary-subtle text-secondary px-3 py-2 rounded-pill fw-bold mb-2">Our Pillars</span>
                <h2 class="fw-bold">What Sets SmartBus Apart</h2>
            </div>

            <div class="row g-4">
                <div class="col-md-4">
                    <div class="card h-100 shadow-sm border-0 p-4 rounded-4 text-center">
                        <div class="rounded-circle bg-primary-subtle text-primary d-inline-flex align-items-center justify-content-center mx-auto mb-3" style="width: 60px; height: 60px; font-size: 1.5rem;">
                            <i class="fa-solid fa-clock-rotate-left"></i>
                        </div>
                        <h5 class="fw-bold mb-2">Punctuality Commitment</h5>
                        <p class="text-muted small mb-0">Our partnered operators follow precise departure schedules on major expressways with live tracking updates.</p>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card h-100 shadow-sm border-0 p-4 rounded-4 text-center">
                        <div class="rounded-circle bg-success-subtle text-success d-inline-flex align-items-center justify-content-center mx-auto mb-3" style="width: 60px; height: 60px; font-size: 1.5rem;">
                            <i class="fa-solid fa-shield-virus"></i>
                        </div>
                        <h5 class="fw-bold mb-2">Cleanliness &amp; Safety</h5>
                        <p class="text-muted small mb-0">Deep sanitization before every trip, fresh blankets, bottled mineral water, and CCTV cameras on all luxury coaches.</p>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card h-100 shadow-sm border-0 p-4 rounded-4 text-center">
                        <div class="rounded-circle bg-warning-subtle text-warning d-inline-flex align-items-center justify-content-center mx-auto mb-3" style="width: 60px; height: 60px; font-size: 1.5rem;">
                            <i class="fa-solid fa-hand-holding-dollar"></i>
                        </div>
                        <h5 class="fw-bold mb-2">Zero Markup Pricing</h5>
                        <p class="text-muted small mb-0">No booking commission or agency surcharge. Clear breakdown of base fare and statutory taxes.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>
<?php
require_once 'config/db_connect.php';
$page_title = "Search Buses & Fares";
include 'includes/header.php';

$source = trim($_GET['source'] ?? '');
$destination = trim($_GET['destination'] ?? '');
$journey_date = trim($_GET['journey_date'] ?? date('Y-m-d'));

// Prevent selecting past dates
$today = date('Y-m-d');
if ($journey_date < $today) {
    $journey_date = $today;
}

$prev_date = date('Y-m-d', strtotime($journey_date . ' -1 day'));
$next_date = date('Y-m-d', strtotime($journey_date . ' +1 day'));
// Auto-Schedule Generator:
// Ensures that whenever a commuter searches for ANY date (today, tomorrow, or in the future)
// on existing or requested routes, active bus departures are automatically scheduled!
if (!empty($journey_date) && $journey_date >= $today) {
    if (!empty($source) && !empty($destination)) {
        $chk_r = $pdo->prepare("SELECT * FROM routes WHERE LOWER(TRIM(source)) = LOWER(TRIM(?)) AND LOWER(TRIM(destination)) = LOWER(TRIM(?))");
        $chk_r->execute([$source, $destination]);
        $target_route = $chk_r->fetch();

        // If route does not exist yet, automatically provision it with realistic parameters
        if (!$target_route) {
            $src_c = ucfirst(trim($source));
            $dst_c = ucfirst(trim($destination));

            // Known intercity distances and durations
            $matrix = [
                'bangalore-nagpur' => [1080, '18h 00m'],
                'nagpur-bangalore' => [1080, '18h 00m'],
                'bangalore-pune' => [840, '14h 00m'],
                'pune-bangalore' => [840, '14h 00m'],
                'mumbai-goa' => [585, '11h 30m'],
                'goa-mumbai' => [585, '11h 30m'],
                'delhi-chandigarh' => [245, '4h 30m'],
                'chandigarh-delhi' => [245, '4h 30m'],
                'mumbai-pune' => [150, '3h 30m'],
                'pune-mumbai' => [150, '3h 30m'],
                'pune-goa' => [440, '9h 15m'],
                'goa-pune' => [440, '9h 15m'],
                'bangalore-chennai' => [345, '6h 00m'],
                'chennai-bangalore' => [345, '6h 00m'],
                'delhi-jaipur' => [280, '5h 15m'],
                'jaipur-delhi' => [280, '5h 15m'],
                'hyderabad-vijayawada' => [275, '4h 45m'],
                'vijayawada-hyderabad' => [275, '4h 45m'],
                'mumbai-ahmedabad' => [525, '8h 30m'],
                'ahmedabad-mumbai' => [525, '8h 30m'],
                'bangalore-hyderabad' => [570, '9h 00m'],
                'hyderabad-bangalore' => [570, '9h 00m'],
                'pune-nagpur' => [710, '12h 00m'],
                'nagpur-pune' => [710, '12h 00m']
            ];
            $m_key = strtolower(trim($source)) . '-' . strtolower(trim($destination));
            $dist = $matrix[$m_key][0] ?? 600;
            $dur = $matrix[$m_key][1] ?? (ceil($dist / 60) . 'h 00m');

            $ins_r = $pdo->prepare("INSERT INTO routes (source, destination, distance_km, estimated_duration) VALUES (?, ?, ?, ?)");
            $ins_r->execute([$src_c, $dst_c, $dist, $dur]);
            $route_id = $pdo->lastInsertId();
            $target_route = ['id' => $route_id, 'source' => $src_c, 'destination' => $dst_c, 'distance_km' => $dist, 'estimated_duration' => $dur];
        }

        // Check if schedules exist for this route and date
        if ($target_route) {
            $s_count_stmt = $pdo->prepare("SELECT COUNT(*) FROM schedules WHERE route_id = ? AND journey_date = ?");
            $s_count_stmt->execute([$target_route['id'], $journey_date]);
            if ($s_count_stmt->fetchColumn() == 0) {
                // Generate 4 diverse departures across luxury fleet buses
                $avail_buses = $pdo->query("SELECT id, bus_type FROM buses WHERE status = 'Active' ORDER BY id ASC")->fetchAll();
                if ($avail_buses) {
                    $dist = (int)$target_route['distance_km'];
                    $runs = [
                        ['bus_idx' => 0, 'dep' => '06:30:00', 'rate' => 1.45], // Morning Executive
                        ['bus_idx' => 1, 'dep' => '14:15:00', 'rate' => 1.65], // Afternoon Luxury Sleeper
                        ['bus_idx' => 2, 'dep' => '20:30:00', 'rate' => 1.35], // Night Club Class
                        ['bus_idx' => 3, 'dep' => '22:00:00', 'rate' => 1.55], // Late Night Express
                    ];

                    $ins_s = $pdo->prepare("INSERT INTO schedules (bus_id, route_id, journey_date, departure_time, arrival_time, fare, status) VALUES (?, ?, ?, ?, ?, ?, 'Scheduled')");
                    $dur_h = max(2, (int)round($dist / 55));

                    foreach ($runs as $run) {
                        $b_id = $avail_buses[$run['bus_idx'] % count($avail_buses)]['id'];
                        $dep_t = $run['dep'];
                        $arr_ts = strtotime($journey_date . ' ' . $dep_t) + ($dur_h * 3600);
                        $arr_t = date('H:i:s', $arr_ts);
                        $fare = max(350.00, round(($dist * $run['rate']) / 50) * 50);

                        $ins_s->execute([$b_id, $target_route['id'], $journey_date, $dep_t, $arr_t, $fare]);
                    }
                }
            }
        }
    } else {
        // If searching with partial or empty source/destination, ensure popular routes have schedules on that date
        $total_on_date = $pdo->prepare("SELECT COUNT(*) FROM schedules WHERE journey_date = ?");
        $total_on_date->execute([$journey_date]);
        if ($total_on_date->fetchColumn() == 0) {
            $routes_to_seed = $pdo->query("SELECT * FROM routes ORDER BY id ASC LIMIT 10")->fetchAll();
            $avail_buses = $pdo->query("SELECT id FROM buses WHERE status = 'Active'")->fetchAll();
            if ($routes_to_seed && $avail_buses) {
                $ins_s = $pdo->prepare("INSERT INTO schedules (bus_id, route_id, journey_date, departure_time, arrival_time, fare, status) VALUES (?, ?, ?, ?, ?, ?, 'Scheduled')");
                foreach ($routes_to_seed as $rt) {
                    $dist = (int)$rt['distance_km'];
                    $dur_h = max(2, (int)round($dist / 55));

                    $ins_s->execute([$avail_buses[0]['id'], $rt['id'], $journey_date, '06:30:00', date('H:i:s', strtotime('06:30:00') + ($dur_h * 3600)), max(350, round(($dist * 1.4) / 50) * 50)]);
                    $ins_s->execute([$avail_buses[1 % count($avail_buses)]['id'], $rt['id'], $journey_date, '14:00:00', date('H:i:s', strtotime('14:00:00') + ($dur_h * 3600)), max(400, round(($dist * 1.6) / 50) * 50)]);
                    $ins_s->execute([$avail_buses[2 % count($avail_buses)]['id'], $rt['id'], $journey_date, '21:00:00', date('H:i:s', strtotime('21:00:00') + ($dur_h * 3600)), max(450, round(($dist * 1.5) / 50) * 50)]);
                }
            }
        }
    }
}

// Build search query with prepared statements
$sql = "SELECT s.*, b.bus_number, b.bus_name, b.bus_type, b.total_seats, b.amenities, b.rating,
               r.source, r.destination, r.distance_km, r.estimated_duration,
               (b.total_seats - (
                   SELECT COUNT(bs.id) FROM booking_seats bs 
                   JOIN bookings bk ON bs.booking_id = bk.id 
                   WHERE bk.schedule_id = s.id AND bk.booking_status = 'Confirmed'
               )) AS available_seats
        FROM schedules s
        JOIN buses b ON s.bus_id = b.id
        JOIN routes r ON s.route_id = r.id
        WHERE s.status = 'Scheduled'";

$params = [];

if (!empty($source)) {
    $sql .= " AND r.source LIKE ?";
    $params[] = "%$source%";
}
if (!empty($destination)) {
    $sql .= " AND r.destination LIKE ?";
    $params[] = "%$destination%";
}
if (!empty($journey_date)) {
    $sql .= " AND s.journey_date = ?";
    $params[] = $journey_date;
}

$sql .= " ORDER BY s.departure_time ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$buses = $stmt->fetchAll();

// Distinct cities for autocomplete in modify form
$cities_stmt = $pdo->query("SELECT DISTINCT source AS city FROM routes UNION SELECT DISTINCT destination AS city FROM routes ORDER BY city");
$cities = $cities_stmt->fetchAll(PDO::FETCH_COLUMN);
?>

<div class="container py-4">
    <!-- Header Summary Bar -->
    <div class="bg-primary text-white p-4 rounded-4 shadow-sm mb-4">
        <div class="row align-items-center g-3">
            <div class="col-lg-7">
                <div class="d-flex align-items-center gap-2 mb-1">
                    <span class="badge bg-warning text-dark fw-bold">Live Schedules</span>
                    <span class="text-light small"><i class="fa-solid fa-calendar me-1"></i> <?php echo date('D, d M Y', strtotime($journey_date)); ?></span>
                </div>
                <h3 class="fw-extrabold mb-1">
                    <?php echo htmlspecialchars($source ?: 'All Routes'); ?> 
                    <span class="text-warning">&rarr;</span> 
                    <?php echo htmlspecialchars($destination ?: 'All Destinations'); ?>
                </h3>
                <div class="small text-light opacity-90">
                    Showing <strong id="visibleBusCount"><?php echo count($buses); ?></strong> available bus options
                </div>
            </div>

            <!-- Date Switcher and Modify Toggle -->
            <div class="col-lg-5 text-lg-end">
                <div class="btn-group shadow-sm me-2 mb-2 mb-sm-0">
                    <a href="search_bus.php?source=<?php echo urlencode($source); ?>&destination=<?php echo urlencode($destination); ?>&journey_date=<?php echo $prev_date; ?>" 
                       class="btn btn-light btn-sm <?php echo $is_today ? 'disabled opacity-50' : ''; ?>" title="Previous Day">
                        &larr; Prev Day
                    </a>
                    <a href="search_bus.php?source=<?php echo urlencode($source); ?>&destination=<?php echo urlencode($destination); ?>&journey_date=<?php echo $today; ?>" 
                       class="btn btn-light btn-sm <?php echo $is_today ? 'active fw-bold' : ''; ?>">
                        Today
                    </a>
                    <a href="search_bus.php?source=<?php echo urlencode($source); ?>&destination=<?php echo urlencode($destination); ?>&journey_date=<?php echo $next_date; ?>" 
                       class="btn btn-light btn-sm" title="Next Day">
                        Next Day &rarr;
                    </a>
                </div>
                <button class="btn btn-outline-light btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#modifySearchBox" aria-expanded="false" aria-controls="modifySearchBox">
                    <i class="fa-solid fa-pen-to-square me-1"></i> Modify
                </button>
            </div>
        </div>

        <!-- Collapsible Modify Search Box -->
        <div class="collapse mt-3 pt-3 border-top border-white border-opacity-25" id="modifySearchBox">
            <form action="search_bus.php" method="GET" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label text-light small fw-bold mb-1">Origin City</label>
                    <input type="text" name="source" class="form-control form-control-sm" list="modifyCities" value="<?php echo htmlspecialchars($source); ?>" required placeholder="Origin">
                </div>
                <div class="col-md-3">
                    <label class="form-label text-light small fw-bold mb-1">Destination City</label>
                    <input type="text" name="destination" class="form-control form-control-sm" list="modifyCities" value="<?php echo htmlspecialchars($destination); ?>" required placeholder="Destination">
                </div>
                <div class="col-md-3">
                    <label class="form-label text-light small fw-bold mb-1">Date</label>
                    <input type="date" name="journey_date" class="form-control form-control-sm" value="<?php echo htmlspecialchars($journey_date); ?>" min="<?php echo $today; ?>" required>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-warning w-100 btn-sm fw-bold">
                        <i class="fa-solid fa-magnifying-glass me-1"></i> Update Results
                    </button>
                </div>
                <datalist id="modifyCities">
                    <?php foreach ($cities as $c): ?>
                        <option value="<?php echo htmlspecialchars($c); ?>">
                    <?php endforeach; ?>
                </datalist>
            </form>
        </div>
    </div>

    <?php if (empty($buses)): ?>
        <!-- No Buses Found -->
        <div class="card shadow-sm border-0 rounded-4 text-center py-5 px-3">
            <div class="py-4">
                <div class="rounded-circle bg-light d-inline-flex align-items-center justify-content-center p-4 mb-3 text-muted">
                    <i class="fa-solid fa-bus-simple display-3"></i>
                </div>
                <h3 class="fw-bold text-dark mb-2">No Buses Scheduled for this Date</h3>
                <p class="text-muted max-w-500 mx-auto mb-4" style="max-width: 500px;">
                    We couldn't find active buses between <strong><?php echo htmlspecialchars($source ?: 'your source'); ?></strong> and <strong><?php echo htmlspecialchars($destination ?: 'your destination'); ?></strong> on <strong><?php echo htmlspecialchars($journey_date); ?></strong>.
                </p>
                <div class="d-flex justify-content-center gap-2 flex-wrap">
                    <a href="search_bus.php?source=<?php echo urlencode($source); ?>&destination=<?php echo urlencode($destination); ?>&journey_date=<?php echo $next_date; ?>" class="btn btn-primary">
                        <i class="fa-solid fa-calendar-plus me-1"></i> Check Next Day (<?php echo $next_date; ?>)
                    </a>
                    <a href="index.php" class="btn btn-outline-secondary">
                        <i class="fa-solid fa-house me-1"></i> Return to Home
                    </a>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <!-- Filter Sidebar -->
            <div class="col-lg-3">
                <div class="card shadow-sm border-0 rounded-4 p-4 sticky-top" style="top: 85px;">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-filter me-2 text-primary"></i> Filters</h5>
                        <button type="button" id="resetFiltersBtn" class="btn btn-link text-primary p-0 small text-decoration-none fw-semibold">
                            Reset All
                        </button>
                    </div>

                    <!-- Sort Selection -->
                    <div class="mb-4">
                        <label class="form-label small fw-bold text-muted">Sort Buses By</label>
                        <select id="sortBusesSelect" class="form-select form-select-sm">
                            <option value="time_asc">Departure Time (Earliest First)</option>
                            <option value="fare_asc">Price (Lowest to Highest)</option>
                            <option value="fare_desc">Price (Highest to Lowest)</option>
                            <option value="rating_desc">Highest Passenger Rating</option>
                        </select>
                    </div>

                    <hr class="my-3">

                    <!-- Bus Type Filter -->
                    <div class="mb-4">
                        <label class="form-label small fw-bold text-muted mb-2">Bus Type / Class</label>
                        <div class="form-check mb-2">
                            <input class="form-check-input filter-bus-type" type="checkbox" value="sleeper" id="filterSleeper">
                            <label class="form-check-label small" for="filterSleeper">AC Sleeper</label>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input filter-bus-type" type="checkbox" value="seater" id="filterSeater">
                            <label class="form-check-label small" for="filterSeater">AC Seater / Semi-Sleeper</label>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input filter-bus-type" type="checkbox" value="volvo" id="filterVolvo">
                            <label class="form-check-label small" for="filterVolvo">Volvo Multi-Axle</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input filter-bus-type" type="checkbox" value="non-ac" id="filterNonAc">
                            <label class="form-check-label small" for="filterNonAc">Non-AC</label>
                        </div>
                    </div>

                    <hr class="my-3">

                    <!-- Departure Time Filter -->
                    <div class="mb-2">
                        <label class="form-label small fw-bold text-muted mb-2">Departure Time Slot</label>
                        <div class="form-check mb-2">
                            <input class="form-check-input filter-dept-time" type="checkbox" value="morning" id="timeMorning">
                            <label class="form-check-label small" for="timeMorning">
                                <i class="fa-solid fa-sun text-warning me-1"></i> Morning (06:00 - 12:00)
                            </label>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input filter-dept-time" type="checkbox" value="afternoon" id="timeAfternoon">
                            <label class="form-check-label small" for="timeAfternoon">
                                <i class="fa-solid fa-cloud-sun text-info me-1"></i> Afternoon (12:00 - 18:00)
                            </label>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input filter-dept-time" type="checkbox" value="evening" id="timeEvening">
                            <label class="form-check-label small" for="timeEvening">
                                <i class="fa-solid fa-moon text-primary me-1"></i> Evening (18:00 - 24:00)
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input filter-dept-time" type="checkbox" value="night" id="timeNight">
                            <label class="form-check-label small" for="timeNight">
                                <i class="fa-solid fa-bed text-secondary me-1"></i> Late Night (00:00 - 06:00)
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bus Cards List -->
            <div class="col-lg-9">
                <!-- Filter No Results Alert -->
                <div id="filterNoResults" class="card shadow-sm border-0 rounded-4 text-center py-5 px-3 mb-4" style="display: none;">
                    <div class="py-3">
                        <i class="fa-solid fa-filter-circle-xmark fs-1 text-muted mb-3"></i>
                        <h5>No buses match your selected filter criteria</h5>
                        <p class="text-muted small">Try unchecking some filters or click reset.</p>
                    </div>
                </div>

                <div id="busesListContainer" class="d-flex flex-column gap-3">
                    <?php foreach ($buses as $bus): 
                        $dept_hour = (int)substr($bus['departure_time'], 0, 2);
                        $avail = max(0, (int)$bus['available_seats']);
                    ?>
                        <div class="card bus-card bus-result-card shadow-sm p-4"
                             data-bus-type="<?php echo htmlspecialchars($bus['bus_type']); ?>"
                             data-dept-hour="<?php echo $dept_hour; ?>"
                             data-dept-time="<?php echo $bus['departure_time']; ?>"
                             data-fare="<?php echo $bus['fare']; ?>"
                             data-rating="<?php echo $bus['rating']; ?>">
                            <div class="row align-items-center g-3">
                                <!-- Operator & Details -->
                                <div class="col-lg-4">
                                    <div class="d-flex align-items-center gap-2 mb-1">
                                        <h5 class="fw-bold text-dark mb-0"><?php echo htmlspecialchars($bus['bus_name']); ?></h5>
                                    </div>
                                    <span class="badge bg-primary-subtle text-primary mb-2">
                                        <?php echo htmlspecialchars($bus['bus_type']); ?>
                                    </span>
                                    <div class="small text-muted mb-2">
                                        <i class="fa-solid fa-id-card me-1 text-secondary"></i> Bus: <?php echo htmlspecialchars($bus['bus_number']); ?>
                                    </div>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="badge bg-warning text-dark fw-bold">
                                            <i class="fa-solid fa-star me-1 text-dark"></i> <?php echo number_format($bus['rating'], 1); ?>
                                        </span>
                                        <span class="small text-muted">Superb</span>
                                    </div>
                                </div>

                                <!-- Schedule Timeline -->
                                <div class="col-lg-5 text-center">
                                    <div class="d-flex justify-content-between align-items-center px-2">
                                        <div class="text-start">
                                            <div class="time-badge"><?php echo substr($bus['departure_time'], 0, 5); ?></div>
                                            <div class="fw-semibold small text-dark"><?php echo htmlspecialchars($bus['source']); ?></div>
                                        </div>

                                        <div class="text-center px-2 flex-grow-1 mx-2">
                                            <div class="duration-line mb-1">
                                                <span><?php echo htmlspecialchars($bus['estimated_duration']); ?></span>
                                            </div>
                                            <div class="text-muted" style="font-size: 0.7rem;">Direct Highway</div>
                                        </div>

                                        <div class="text-end">
                                            <div class="time-badge"><?php echo substr($bus['arrival_time'], 0, 5); ?></div>
                                            <div class="fw-semibold small text-dark"><?php echo htmlspecialchars($bus['destination']); ?></div>
                                        </div>
                                    </div>

                                    <!-- Amenities Pills -->
                                    <div class="d-flex justify-content-center gap-1 flex-wrap mt-3">
                                        <span class="amenity-pill" title="Live GPS Tracking"><i class="fa-solid fa-location-arrow text-primary"></i> GPS</span>
                                        <span class="amenity-pill" title="Mobile Charging Points"><i class="fa-solid fa-plug text-warning"></i> Charging</span>
                                        <span class="amenity-pill" title="Air Conditioned"><i class="fa-solid fa-snowflake text-info"></i> AC</span>
                                        <span class="amenity-pill" title="Clean Blankets"><i class="fa-solid fa-rug text-success"></i> Blanket</span>
                                    </div>
                                </div>

                                <!-- Pricing & Booking CTA -->
                                <div class="col-lg-3 text-lg-end text-center border-start-lg pt-3 pt-lg-0">
                                    <div class="text-muted small">Starts from</div>
                                    <div class="fs-2 fw-extrabold text-primary mb-1">
                                        ₹<?php echo number_format($bus['fare'], 0); ?>
                                    </div>

                                    <div class="mb-3">
                                        <?php if ($avail > 0): ?>
                                            <span class="badge <?php echo ($avail <= 5) ? 'bg-danger-subtle text-danger' : 'bg-success-subtle text-success'; ?> fw-semibold">
                                                <i class="fa-solid fa-chair me-1"></i> <?php echo $avail; ?> seats available
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-danger text-white fw-bold">SOLD OUT</span>
                                        <?php endif; ?>
                                    </div>

                                    <?php if ($avail > 0): ?>
                                        <a href="select_seat.php?schedule_id=<?php echo $bus['id']; ?>" class="btn btn-primary w-100 fw-bold shadow-sm">
                                            <i class="fa-solid fa-chair me-1"></i> Select Seats
                                        </a>
                                    <?php else: ?>
                                        <button class="btn btn-secondary w-100" disabled>Sold Out</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
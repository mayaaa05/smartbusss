<?php
// FINAL CODE - SAREE DATES
$conn = mysqli_connect("localhost", "root", "", "bus_reservation");
if(!$conn) die("DB Error: ".mysqli_connect_error());

// Pehla bus id nikalo - foreign key error kabhi nahi ayega
$res = mysqli_query($conn, "SELECT id FROM buses LIMIT 1");
$first_bus = mysqli_fetch_assoc($res);
$bus_id = $first_bus['id'];

echo "Bus ID: $bus_id se saree dates generate ho rahi hai...<br>";

mysqli_query($conn, "SET FOREIGN_KEY_CHECKS=0");
mysqli_query($conn, "TRUNCATE TABLE schedules");
mysqli_query($conn, "SET FOREIGN_KEY_CHECKS=1");

$start = new DateTime('2026-09-30');
$end = new DateTime('2027-09-30');

$routes_q = mysqli_query($conn, "SELECT id FROM routes");
$routes = [];
while($r = mysqli_fetch_assoc($routes_q)) $routes[] = $r['id'];

echo "Total Routes: ".count($routes)."<br>";

$count = 0;
$batch = "";

for($d = clone $start; $d <= $end; $d->modify('+1 day')){
    $date = $d->format('Y-m-d');
    foreach($routes as $rid){
        // 3 time ki bus ek hi din - same bus id, alag time
        $times = [
            ['08:00:00','22:00:00', 899],
            ['13:00:00','03:00:00', 1099],
            ['22:00:00','12:00:00', 1299]
        ];
        foreach($times as $t){
            $sql = "INSERT INTO schedules (bus_id, route_id, journey_date, departure_time, arrival_time, fare, status) VALUES ($bus_id, $rid, '$date', '{$t[0]}', '{$t[1]}', {$t[2]}, 'Scheduled');";
            mysqli_query($conn, $sql);
            $count++;
        }
    }
    if($count % 10000 == 0){
        echo "$count done - $date<br>";
        flush(); ob_flush();
    }
}

echo "<h1>DONE! $count buses inserted! SAREE DATES READY! 🎉</h1>";
echo "Test: Chandigarh->Patna 2026-10-25 | 2027-09-30 sab chalega";
?>
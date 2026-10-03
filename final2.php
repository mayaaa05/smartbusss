<?php
set_time_limit(0);
$conn = mysqli_connect("localhost", "root", "", "bus_reservation");

mysqli_query($conn, "SET FOREIGN_KEY_CHECKS=0");
mysqli_query($conn, "TRUNCATE TABLE schedules");
mysqli_query($conn, "SET FOREIGN_KEY_CHECKS=1");

$buses = [1,2,3,4,5,6]; // Hardcoded 6 buses

$routes_res = mysqli_query($conn, "SELECT id FROM routes ORDER BY id");
$routes = [];
while($r = mysqli_fetch_assoc($routes_res)) $routes[] = $r['id'];

$times = [
 ['08:00:00','22:00:00',999],
 ['13:00:00','03:00:00',1099],
 ['22:00:00','12:00:00',1299]
];

$start = new DateTime('2026-09-30');
$end = new DateTime('2027-09-30');
$values = [];
$route_idx = 0;

for($d = clone $start; $d <= $end; $d->modify('+1 day')){
  $date = $d->format('Y-m-d');
  $route_idx = 0;
  foreach($routes as $rid){
    for($ti=0; $ti<3; $ti++){
      $bus_id = $buses[ ($ti + $route_idx) % 6 ]; // Har time alag bus
      $values[] = "($bus_id, $rid, '$date', '{$times[$ti][0]}', '{$times[$ti][1]}', {$times[$ti][2]}, 'Scheduled')";
      if(count($values)>=1000){
        mysqli_query($conn, "INSERT INTO schedules (bus_id, route_id, journey_date, departure_time, arrival_time, fare, status) VALUES ".implode(",", $values));
        $values = [];
      }
    }
    $route_idx++;
  }
}
if($values) mysqli_query($conn, "INSERT INTO schedules (bus_id, route_id, journey_date, departure_time, arrival_time, fare, status) VALUES ".implode(",", $values));

echo "<h1>DONE! Ab alag alag naam aayenge - Orange, Sharma, VRL!</h1>";
?>
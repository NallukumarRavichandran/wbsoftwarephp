<?php
require_once "db.php";

$res = $conn->query("
    SELECT id, slip_no, vehicle_no,
           first_weight, first_date, first_time, gt_type
    FROM weighments
    ORDER BY vehicle_no
");

$data = [];
while ($row = $res->fetch_assoc()) {
    $data[] = $row;
}

echo json_encode($data);

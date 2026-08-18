<?php
require_once "db.php";
require_once "apacs_login.php";

$result = $conn->query("
SELECT
    slip_no,
    request_data,
    retry_count,
    status
FROM apacs_upload_log
WHERE status IN ('FAILED','PENDING')
ORDER BY uploaded_on ASC
");

if ($result->num_rows == 0) {
    echo "No pending uploads.";
    exit;
}

$success = 0;
$failed = 0;

while ($row = $result->fetch_assoc()) {

    $ok = uploadToApacs(
        $row['slip_no'],
        $row['request_data']
    );

    if ($ok) {
        $success++;
    } else {
        $failed++;
    }
}

echo "<h3>APACS Retry Completed</h3>";

echo "Successful Uploads : " . $success . "<br>";

echo "Failed Uploads : " . $failed . "<br>";
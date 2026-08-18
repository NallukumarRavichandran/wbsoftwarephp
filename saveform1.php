<?php
session_start();
require_once "db.php";
require_once "functions.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login_page.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Invalid access");
}

/* ===============================
   FETCH & SANITIZE
   =============================== */
$slip_no    = (int)($_POST['slip_no'] ?? 0);
$vehicle_no = strtoupper(trim($_POST['vehicle_no'] ?? ''));

$gt_type = strtoupper(substr($_POST['gt_type'] ?? 'G', 0, 1));
if (!in_array($gt_type, ['G','T'])) $gt_type = 'G';

$gross_wt   = (int)($_POST['gross_weight'] ?? 0);
$tare_wt    = (int)($_POST['tare_weight'] ?? 0);

$gross_date = $_POST['gross_date'] ?? '';
$gross_time = $_POST['gross_time'] ?? '';
$tare_date  = $_POST['tare_date'] ?? '';
$tare_time  = $_POST['tare_time'] ?? '';

$company    = getCompany($conn);
$company_id = $company['id'];

if (!$slip_no || !$vehicle_no) {
    die("Slip No and Vehicle No required");
}

/* ============================================================
   FIRST WEIGHMENT  (ONLY ONE WEIGHT AVAILABLE)
   ============================================================ */
if ($gross_wt === 0 || $tare_wt === 0) {
	
	$first_image = captureCamera($slip_no, 1);
    $first_weight = ($gross_wt > 0) ? $gross_wt : $tare_wt;
    $first_date   = ($gross_wt > 0) ? $gross_date : $tare_date;
    $first_time   = ($gross_wt > 0) ? $gross_time : $tare_time;
	
/* Vehicle already pending ? */

$checkVehicle = $conn->prepare("
SELECT
    slip_no,
    gt_type,
    first_weight,
    first_date,
    first_time
FROM weighments
WHERE vehicle_no = ?
AND company_id = ?
LIMIT 1
");

$checkVehicle->bind_param(
    "si",
    $vehicle_no,
    $company_id
);

$checkVehicle->execute();

$pending = $checkVehicle
            ->get_result()
            ->fetch_assoc();	
			
$action = "";

if ($pending) {
    // Same type(g/t) entered again
    if ($pending['gt_type'] == $gt_type) {
        $action = "overwrite";
    }
    // Opposite type entered
    else {
        $action = "second";
    }
}			
			
if ($pending)
{
    // Same type already exists (Gross->Gross or Tare->Tare)
    if ($pending['gt_type'] == $gt_type)
    {
        $_SESSION['error'] =
            "Vehicle {$vehicle_no} already has a pending "
            . ($gt_type == "G" ? "GROSS" : "TARE")
            . " weighment.\n\n"
            . "Slip No : {$pending['slip_no']}\n"
            . "Weight  : {$pending['first_weight']}\n\n"
            . "First weighment already found.";

        header("Location: mainform.php");
        exit;
    }

    // Opposite type entered
    $_SESSION['error'] =
        "Vehicle {$vehicle_no} already has a pending first weighment.\n\n"
        . "Please use SECOND WEIGHMENT.";

    header("Location: mainform.php");
    exit;
}
else
{
    $pendingGT = "";
}			
	
	
	// Prevent duplicate save of same slip
$check = $conn->prepare("
    SELECT id
    FROM weighments
    WHERE slip_no = ?
");
$check->bind_param("i", $slip_no);
$check->execute();

if ($check->get_result()->num_rows > 0) {
    $_SESSION['error'] = "Slip Number already saved.";
    header("Location: mainform.php");
    exit;
}

    $stmt = $conn->prepare("
		INSERT INTO weighments
		(slip_no, vehicle_no, first_weight, first_date, first_time, gt_type, first_image_path, company_id, user_id)
		VALUES (?,?,?,?,?,?,?,?,?)
    ");

    $stmt->bind_param(
        "issssssii",
        $slip_no,
        $vehicle_no,
        $first_weight,
        $first_date,
        $first_time,
        $gt_type,
		$first_image,
        $company_id,
        $_SESSION['user_id']
    );

    if (!$stmt->execute()) {
        die("Error saving first weighment");
    }

    /* ---- SAVE DYNAMIC VALUES (PARTIAL) ---- */
    saveDynamicFields($conn, $company_id, $slip_no);

    header("Location: print_slip.php?slip=".$slip_no);
    exit;
}

/* ============================================================
   SECOND WEIGHMENT  (FINAL SAVE)
   ============================================================ */
$conn->begin_transaction();

try {

	$second_image = captureCamera($slip_no, 2);

	$get = $conn->prepare("SELECT first_image_path FROM weighments WHERE slip_no=?");
	$get->bind_param("i", $slip_no);
	$get->execute();
	$old = $get->get_result()->fetch_assoc();

	$first_image = $old['first_image_path'] ?? null;

    if ($gross_wt < $tare_wt) {
        [$gross_wt, $tare_wt] = [$tare_wt, $gross_wt];
        [$gross_date, $tare_date] = [$tare_date, $gross_date];
        [$gross_time, $tare_time] = [$tare_time, $gross_time];
    }
    $net_wt = $gross_wt - $tare_wt;

if ($net_wt <= 0) {
    $_SESSION['error'] = "GROSS and TARE weights are same. NET weight is ZERO.";
    header("Location: mainform.php");
    exit;
}
    /* ---- INSERT FINAL RECORD ---- */
    $stmt = $conn->prepare("
        INSERT INTO sweighment
		(slip_no, vehicle_no,
		gross_weight, gross_date, gross_time,
		tare_weight, tare_date, tare_time,
		net_weight, first_image_path, second_image_path, company_id, user_id)
		VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)
		");

	$stmt->bind_param(
		"issssssssssii",
		$slip_no,
		$vehicle_no,
		$gross_wt,
		$gross_date,
		$gross_time,
		$tare_wt,
		$tare_date,
		$tare_time,
		$net_wt,
		$first_image,
		$second_image,
		$company_id,
		$_SESSION['user_id']
);

    if (!$stmt->execute()) throw new Exception($stmt->error);

	/* ---- CREATE APACS PENDING ENTRY ---- */
$apacs = $conn->prepare("
    INSERT INTO apacs_upload_log
    (slip_no, status)
    VALUES (?, 'PENDING')
");

$apacs->bind_param("s", $slip_no);

if (!$apacs->execute()) {
    throw new Exception($apacs->error);
}

    /* ---- UPSERT DYNAMIC FIELDS ---- */
    upsertDynamicFields($conn, $company_id, $slip_no);
	/* ---- READ DYNAMIC FIELDS FOR APACS ---- */

$apacsFields = [];

$q = $conn->query("
    SELECT wf.field_name,
           wfv.field_value
    FROM weighment_field_values wfv
    INNER JOIN weighment_fields wf
        ON wf.id = wfv.field_id
    WHERE wfv.weighment_id = $slip_no
");

while ($r = $q->fetch_assoc()) {

    $apacsFields[$r['field_name']] = $r['field_value'];

}

$latestDate = ($gross_date >= $tare_date) ? $gross_date : $tare_date;

$dt = DateTime::createFromFormat('d/m/Y', $latestDate);

$weighDate = $dt ? $dt->format('Y-m-d') : '';
/* ---- BUILD APACS PAYLOAD ---- */

$payload = array(

    "weighBridgeName" => $company['company_name'],

    "serialNo"        => (string)$slip_no,

	"weighDate" => $weighDate,
	
    "weighTime"       => ($gross_date >= $tare_date)
                            ? $gross_time
                            : $tare_time,

    "vehicleNumber"   => $vehicle_no,

"movementType" => strtolower($apacsFields['movementType'] ?? ""),
    "cargo"           => $apacsFields['cargo'] ?? "",

    "clientName"      => $apacsFields['clientName'] ?? "",

    "grossWeight"     => $gross_wt,

    "tareWeight"      => $tare_wt,

    "netWeight"       => $net_wt,

"weightUnit" => strtolower($apacsFields['weightUnit'] ?? "")
);

$request_json = json_encode($payload);


/* ---- SAVE REQUEST JSON ---- */

$stmt = $conn->prepare("
    UPDATE apacs_upload_log
    SET request_data = ?
    WHERE slip_no = ?
");

$stmt->bind_param("ss", $request_json, $slip_no);

if (!$stmt->execute()) {
    throw new Exception($stmt->error);
}

    /* ---- REMOVE TEMP RECORD ---- */
    $del = $conn->prepare("DELETE FROM weighments WHERE slip_no = ?");
    $del->bind_param("i", $slip_no);
    $del->execute();

$conn->commit();

require_once "apacs_login.php";

uploadToApacs((string)$slip_no, $request_json);

header("Location: print_slip.php?slip=".$slip_no);
exit;


} catch (Exception $e) {
    $conn->rollback();
    die("Save failed: ".$e->getMessage());
}

//camera image save

function captureCamera($slip_no, $type)
{
    $username = "admin";
    $password = "GiRi_1973";
    $url = "http://192.168.1.11/cgi-bin/snapshot.cgi?channel=1";

    $folder = __DIR__ . "/captures/";
    if (!is_dir($folder)) {
        mkdir($folder, 0777, true);
    }

    $filename = $slip_no . "_" . $type . ".jpg";
    $filepath = $folder . $filename;

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST);
    curl_setopt($ch, CURLOPT_USERPWD, "$username:$password");

    $img = curl_exec($ch);

    curl_close($ch);

if ($img) {
    file_put_contents($filepath, $img);
    return "captures/" . $filename;
}

    return null;
}
/* ============================================================
   FUNCTION : SAVE FIRST-WEIGHMENT DYNAMIC FIELDS
   (INSERT ONLY)
   ============================================================ */
function saveDynamicFields($conn, $company_id, $slip_no)
{
    $fields = $conn->prepare("
    SELECT id, field_name, is_required
    FROM weighment_fields
    WHERE company_id = ?
      AND is_active = 1
");
    $fields->bind_param("i", $company_id);
    $fields->execute();
    $res = $fields->get_result();

    $ins = $conn->prepare("
        INSERT INTO weighment_field_values
        (weighment_id, field_id, field_value)
        VALUES (?,?,?)
    ");

    while ($f = $res->fetch_assoc()) {

        $key   = 'dyn_'.$f['id'];
        $value = strtoupper(trim($_POST[$key] ?? ''));

        if ($f['is_required'] && $value === '') {
            die("Required field missing: ".$f['field_name']);
        }

        if ($value === '') continue;

        $ins->bind_param("iis", $slip_no, $f['id'], $value);
        $ins->execute();
    }
}


/* ============================================================
   FUNCTION : SECOND-WEIGHMENT UPSERT
   UPDATE if exists  |  INSERT if new
   ============================================================ */
function upsertDynamicFields($conn, $company_id, $slip_no)
{
    $fields = $conn->prepare("
    SELECT id, field_name, is_required
    FROM weighment_fields
    WHERE company_id = ?
      AND is_active = 1
");
    $fields->bind_param("i", $company_id);
    $fields->execute();
    $res = $fields->get_result();

    $check = $conn->prepare("
        SELECT id FROM weighment_field_values
        WHERE weighment_id = ? AND field_id = ?
    ");

    $update = $conn->prepare("
        UPDATE weighment_field_values
        SET field_value = ?
        WHERE weighment_id = ? AND field_id = ?
    ");

    $insert = $conn->prepare("
        INSERT INTO weighment_field_values
        (weighment_id, field_id, field_value)
        VALUES (?,?,?)
    ");

    while ($f = $res->fetch_assoc()) {

        $key   = 'dyn_'.$f['id'];
        $value = strtoupper(trim($_POST[$key] ?? ''));

        if ($f['is_required'] && $value === '') {
            throw new Exception("Required field missing: ".$f['field_name']);
        }

        if ($value === '') continue;

        $check->bind_param("ii", $slip_no, $f['id']);
        $check->execute();
        $exists = $check->get_result()->num_rows;

        if ($exists) {
            $update->bind_param("sii", $value, $slip_no, $f['id']);
            $update->execute();
        } else {
            $insert->bind_param("iis", $slip_no, $f['id'], $value);
            $insert->execute();
        }
    }
}
?>
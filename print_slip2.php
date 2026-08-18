<?php
date_default_timezone_set('Asia/Kolkata');

session_start();
require_once "db.php";
require_once "functions.php";

if (!isset($_SESSION['user_id'])) die("Unauthorized");

$slip_no = $_GET['slip'] ?? '';
if ($slip_no == '') die("Slip missing");

$company = getCompany($conn);

/* FINAL WEIGHMENT */
$stmt2 = $conn->prepare("
    SELECT slip_no, vehicle_no,
           gross_weight, gross_date, gross_time,
           tare_weight, tare_date, tare_time,
           net_weight,
           first_image_path,
           second_image_path
    FROM sweighment
    WHERE slip_no = ?
    LIMIT 1
");
$stmt2->bind_param("s", $slip_no);
$stmt2->execute();
$res2 = $stmt2->get_result();

$isFinal = ($res2->num_rows > 0);

if ($isFinal) {
    $row = $res2->fetch_assoc();
}
$stmt2->close();

/* FIRST WEIGHMENT */
if (!$isFinal) {
    $stmt1 = $conn->prepare("
        SELECT slip_no, vehicle_no,
               first_weight, first_date, first_time, gt_type,
               first_image_path
        FROM weighments
        WHERE slip_no = ?
        LIMIT 1
    ");
    $stmt1->bind_param("s", $slip_no);
    $stmt1->execute();
    $res1 = $stmt1->get_result();

    if ($res1->num_rows == 0) {
        echo "<script>alert('Slip not found');window.location='mainform.php';</script>";
        exit;
    }

    $row = $res1->fetch_assoc();
    $stmt1->close();
}

/* DYNAMIC FIELDS */
$dynamic_fields = [];

$stmt = $conn->prepare("
    SELECT f.field_label, f.field_name,
           COALESCE(v.field_value,'') AS field_value
    FROM weighment_fields f
    LEFT JOIN weighment_field_values v
      ON v.field_id = f.id
     AND v.weighment_id = ?
    WHERE f.company_id = ?
      AND f.is_active = 1
    ORDER BY f.field_order
");
$stmt->bind_param("si", $slip_no, $company['id']);
$stmt->execute();
$res = $stmt->get_result();

while ($r = $res->fetch_assoc()) {
    $dynamic_fields[] = $r;
}
$stmt->close();

// Intelligent field mapper to capture Cargo, Client, Vessel, Unit etc.
$cargo         = '';
$client_name   = '';
$vessel_name   = '';
$vt_no         = '';
$movement_type = '';
$weight_unit   = 'Kg'; // Default

foreach ($dynamic_fields as $df) {
    $lbl  = strtoupper(trim($df['field_label']));
    $name = strtoupper(trim($df['field_name'] ?? ''));
    $val  = trim($df['field_value']);

    if (empty($val)) continue;

    if (stripos($lbl, 'CARGO') !== false || stripos($name, 'CARGO') !== false || stripos($lbl, 'MATERIAL') !== false) {
        $cargo = $val;
    } elseif (stripos($lbl, 'CLIENT') !== false || stripos($name, 'CLIENT') !== false || stripos($lbl, 'PARTY') !== false) {
        $client_name = $val;
    } elseif (stripos($lbl, 'VESSEL') !== false || stripos($name, 'VESSEL') !== false) {
        $vessel_name = $val;
    } elseif (stripos($lbl, 'VT') !== false || stripos($name, 'VT') !== false) {
        $vt_no = $val;
    } elseif (stripos($lbl, 'MOVEMENT') !== false || stripos($name, 'MOVEMENT') !== false) {
        $movement_type = $val;
    } elseif (stripos($lbl, 'UNIT') !== false || stripos($name, 'UNIT') !== false) {
        $weight_unit = $val;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Weighment Certificate - Slip #<?= htmlspecialchars($row['slip_no']) ?></title>
<style>
@page {
    margin: 0;
    size: auto;
}

body {
    font-family: "Courier New", Courier, monospace, Arial;
    margin: 0;
    padding: 10px;
    background: #fff;
    color: #000;
}

.slip-container {
    width: 760px;
    margin: 0 auto;
    padding: 12px 18px;
    background: #fff;
}

.header {
    text-align: center;
    margin-bottom: 10px;
}

.company-name {
    font-size: 22px;
    font-weight: bold;
    letter-spacing: 1px;
    text-transform: uppercase;
}

.company-addr {
    font-size: 15px;
    margin-top: 4px;
    line-height: 1.4;
}

.cert-title {
    font-size: 18px;
    font-weight: bold;
    margin-top: 8px;
    letter-spacing: 1.5px;
}

.divider-dashed {
    border-top: 1px dashed #000;
    margin: 10px 0;
    width: 100%;
}

.info-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 15px;
}

.info-table td {
    padding: 5px 0;
    vertical-align: top;
}

.info-table .lbl {
    font-weight: bold;
    width: 130px;
}

.info-table .sep {
    width: 20px;
    text-align: center;
    font-weight: bold;
}

.info-table .val {
    width: 230px;
}

.weights-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 15px;
}

.weights-table td {
    padding: 6px 0;
    vertical-align: middle;
}

.weights-table .w-lbl {
    font-weight: bold;
    width: 110px;
}

.weights-table .w-sep {
    width: 20px;
    text-align: center;
    font-weight: bold;
}

.weights-table .w-val {
    width: 170px;
    font-weight: bold;
}

.weights-table .w-dt-lbl,
.weights-table .w-tm-lbl {
    font-weight: bold;
    width: 65px;
    text-align: right;
    padding-right: 6px;
}

.weights-table .w-dt-val,
.weights-table .w-tm-val {
    width: 110px;
}

.signature-section {
    text-align: right;
    margin-top: 35px;
    padding-right: 35px;
    font-size: 15px;
    font-weight: bold;
}

@media print {
    body {
        margin: 0;
        padding: 10mm 15mm;
    }
    .slip-container {
        width: 100%;
        margin: 0;
        padding: 0;
    }
}
</style>
</head>

<body onload="window.print(); window.onafterprint = () => window.location = 'mainform.php';">

<div class="slip-container">

    <!-- HEADER SECTION -->
    <div class="header">
        <div class="company-name"><?= htmlspecialchars($company['company_name']) ?></div>
        <div class="company-addr"><?= nl2br(htmlspecialchars($company['company_address'])) ?></div>
        <div class="cert-title">WEIGHMENT CERTIFICATE</div>
    </div>

    <div class="divider-dashed"></div>

    <!-- 2-COLUMN VEHICLE & CARGO DETAILS -->
    <table class="info-table">
        <tr>
            <td class="lbl">Slip No</td>
            <td class="sep">:</td>
            <td class="val"><?= htmlspecialchars($row['slip_no']) ?></td>

            <td class="lbl">Vehicle No</td>
            <td class="sep">:</td>
            <td class="val"><?= htmlspecialchars($row['vehicle_no']) ?></td>
        </tr>
        <tr>
            <td class="lbl">Cargo Name</td>
            <td class="sep">:</td>
            <td class="val"><?= htmlspecialchars($cargo) ?></td>

            <td class="lbl">Client Name</td>
            <td class="sep">:</td>
            <td class="val"><?= htmlspecialchars($client_name) ?></td>
        </tr>
        <tr>
            <td class="lbl">Vessel Name</td>
            <td class="sep">:</td>
            <td class="val"><?= htmlspecialchars($vessel_name) ?></td>

            <td class="lbl">Vt No</td>
            <td class="sep">:</td>
            <td class="val"><?= htmlspecialchars($vt_no) ?></td>
        </tr>
        <?php if (!empty($movement_type)): ?>
        <tr>
            <td class="lbl">Movement Type</td>
            <td class="sep">:</td>
            <td class="val" colspan="4"><?= htmlspecialchars($movement_type) ?></td>
        </tr>
        <?php endif; ?>
    </table>

    <div class="divider-dashed"></div>

    <!-- WEIGHTS, DATE & TIME SECTION -->
    <table class="weights-table">
        <?php if ($isFinal): ?>
        <tr>
            <td class="w-lbl">GROSS Wt</td>
            <td class="w-sep">:</td>
            <td class="w-val"><?= htmlspecialchars($row['gross_weight']) ?> <?= htmlspecialchars($weight_unit) ?></td>
            <td class="w-dt-lbl">Date :</td>
            <td class="w-dt-val"><?= htmlspecialchars($row['gross_date']) ?></td>
            <td class="w-tm-lbl">Time :</td>
            <td class="w-tm-val"><?= htmlspecialchars(substr($row['gross_time'], 0, 5)) ?></td>
        </tr>
        <tr>
            <td class="w-lbl">Tare Wt</td>
            <td class="w-sep">:</td>
            <td class="w-val"><?= htmlspecialchars($row['tare_weight']) ?> <?= htmlspecialchars($weight_unit) ?></td>
            <td class="w-dt-lbl">Date :</td>
            <td class="w-dt-val"><?= htmlspecialchars($row['tare_date']) ?></td>
            <td class="w-tm-lbl">Time :</td>
            <td class="w-tm-val"><?= htmlspecialchars(substr($row['tare_time'], 0, 5)) ?></td>
        </tr>
        <tr>
            <td class="w-lbl">Net Wt</td>
            <td class="w-sep">:</td>
            <td class="w-val" colspan="5"><?= htmlspecialchars($row['net_weight']) ?> <?= htmlspecialchars($weight_unit) ?></td>
        </tr>
        <?php else: ?>
        <tr>
            <td class="w-lbl"><?= ($row['gt_type'] == 'G') ? 'GROSS Wt' : 'Tare Wt' ?></td>
            <td class="w-sep">:</td>
            <td class="w-val"><?= htmlspecialchars($row['first_weight']) ?> <?= htmlspecialchars($weight_unit) ?></td>
            <td class="w-dt-lbl">Date :</td>
            <td class="w-dt-val"><?= htmlspecialchars($row['first_date']) ?></td>
            <td class="w-tm-lbl">Time :</td>
            <td class="w-tm-val"><?= htmlspecialchars(substr($row['first_time'], 0, 5)) ?></td>
        </tr>
        <?php endif; ?>
    </table>

    <div class="divider-dashed"></div>

    <!-- FOOTER SIGNATURE -->
    <div class="signature-section">
        Operator's Signature
    </div>

</div>

</body>
</html>
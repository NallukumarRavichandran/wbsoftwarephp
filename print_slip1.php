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
    SELECT f.field_label,
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
?>
<!DOCTYPE html>
<html>
<head>
<title>Weighment Slip</title>
<style>
body{
    font-family:Arial;
    margin:0;
    background:#fff;
}
.slip{
    width:95%;
    margin:10px auto;
    border:2px solid #000;
}
.header{
    background:#0b57d0;
    color:#fff;
    text-align:center;
    padding:12px;
}
.header h2{
    margin:0;
    font-size:28px;
}
.header div{
    margin-top:5px;
    font-size:14px;
}
.section{
    padding:10px 15px;
    border-top:1px solid #000;
}
.row{
    display:flex;
    justify-content:space-between;
    margin:6px 0;
    gap:20px;
}
.box{
    flex:1;
}
.label{
    font-weight:bold;
}
.images{
    display:flex;
    gap:20px;
    justify-content:center;
    align-items:flex-start;
}
.images div{
    flex:1;
    text-align:center;
}
.images img{
    width:100%;
    max-width:320px;
    height:220px;
    object-fit:cover;
    border:1px solid #000;
}
.weights{
    display:flex;
    justify-content:space-around;
    text-align:center;
}
.weights div{
    flex:1;
    padding:10px;
    border:1px solid #000;
    margin:5px;
}
.big{
    font-size:28px;
    font-weight:bold;
    color:#0b57d0;
}
.footer{
    text-align:center;
    padding:10px;
    border-top:1px solid #000;
    font-size:12px;
}
@media print{
    body{ margin:0; }
}
</style>
</head>

<body onload="window.print();window.onafterprint=()=>window.location='mainform.php';">

<div class="slip">

    <div class="header">
        <h2><?= htmlspecialchars($company['company_name']) ?></h2>
        <div><?= nl2br(htmlspecialchars($company['company_address'])) ?></div>
    </div>

    <div class="section">
        <div class="row">
            <div class="box"><span class="label">Slip No:</span> <?= $row['slip_no'] ?></div>
            <div class="box"><span class="label">Vehicle:</span> <?= htmlspecialchars($row['vehicle_no']) ?></div>
        </div>
    </div>

    <?php if ($isFinal): ?>
    <div class="section">
        <div class="images">

            <?php if (!empty($row['first_image_path'])): ?>
            <div>
                <b>First Weighment</b><br><br>
                <img src="<?= $row['first_image_path'] ?>">
            </div>
            <?php endif; ?>

            <?php if (!empty($row['second_image_path'])): ?>
            <div>
                <b>Second Weighment</b><br><br>
                <img src="<?= $row['second_image_path'] ?>">
            </div>
            <?php endif; ?>

        </div>
    </div>
    <?php else: ?>
        <?php if (!empty($row['first_image_path'])): ?>
        <div class="section" style="text-align:center;">
            <img src="<?= $row['first_image_path'] ?>" style="max-width:320px;height:220px;border:1px solid #000;">
        </div>
        <?php endif; ?>
    <?php endif; ?>

    <?php if (!empty($dynamic_fields)): ?>
    <div class="section">
        <?php foreach($dynamic_fields as $df): ?>
            <div class="row">
                <div class="box">
                    <span class="label"><?= htmlspecialchars($df['field_label']) ?>:</span>
                    <?= htmlspecialchars($df['field_value']) ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="section">
        <?php if ($isFinal): ?>
            <div class="weights">
                <div>
                    Gross<br>
                    <div class="big"><?= $row['gross_weight'] ?></div>
                </div>
                <div>
                    Tare<br>
                    <div class="big"><?= $row['tare_weight'] ?></div>
                </div>
                <div>
                    Net<br>
                    <div class="big"><?= $row['net_weight'] ?></div>
                </div>
            </div>
        <?php else: ?>
            <div class="weights">
                <div>
                    <?= ($row['gt_type']=='G') ? 'Gross' : 'Tare' ?><br>
                    <div class="big"><?= $row['first_weight'] ?></div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="footer">
        Computer Generated Weighment Slip
    </div>

</div>

</body>
</html>
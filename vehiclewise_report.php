<?php
session_start();
require_once "db.php";
require_once "functions.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login_page.php");
    exit;
}

require_once "header.php";

$company = getCompany($conn);
$company_id = $company['id'];

/* -----------------------------------
   FETCH VEHICLES FROM COMPLETED WEIGHMENTS
------------------------------------*/
$vehicles = [];
$vstmt = $conn->prepare("
    SELECT DISTINCT vehicle_no
    FROM sweighment
    WHERE company_id = ?
    ORDER BY vehicle_no
");
$vstmt->bind_param("i", $company_id);
$vstmt->execute();
$vresult = $vstmt->get_result();
while ($vrow = $vresult->fetch_assoc()) {
    $vehicles[] = $vrow['vehicle_no'];
}

/* -----------------------------------
   VEHICLE FILTER
------------------------------------*/
$vehicle_no = $_GET['vehicle_no'] ?? '';

/* -----------------------------------
   FETCH DYNAMIC FIELDS (GENERALIZED)
------------------------------------*/
$dyn_fields = [];

$fstmt = $conn->prepare("
    SELECT id, field_label
    FROM weighment_fields
    WHERE company_id = ?
      AND is_active = 1
    ORDER BY field_order
");
$fstmt->bind_param("i", $company_id);
$fstmt->execute();
$fres = $fstmt->get_result();
while ($f = $fres->fetch_assoc()) {
    $dyn_fields[$f['id']] = $f['field_label'];
}
$from_date = $_GET['from_date'] ?? '';
$to_date   = $_GET['to_date'] ?? '';

/* -----------------------------------
   FETCH COMPLETED WEIGHMENTS
------------------------------------*/
$sql = "
    SELECT
        id,
        slip_no,
        vehicle_no,
        gross_weight,
        gross_date,
        gross_time,
        tare_weight,
        tare_date,
        tare_time,
        net_weight
    FROM sweighment
    WHERE company_id = ?
";

$params = [$company_id];
$types  = "i";

if ($vehicle_no !== '') {
    $sql .= " AND vehicle_no = ?";
    $params[] = $vehicle_no;
    $types   .= "s";
}

if ($from_date !== '') {
    $sql .= " AND STR_TO_DATE(gross_date,'%d/%m/%Y') >= ?";
    $params[] = $from_date;   // input type=date gives YYYY-MM-DD (perfect)
    $types   .= "s";
}

if ($to_date !== '') {
    $sql .= " AND STR_TO_DATE(gross_date,'%d/%m/%Y') <= ?";
    $params[] = $to_date;
    $types   .= "s";
}


$sql .= " ORDER BY CAST(slip_no AS UNSIGNED) asc";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

/* -----------------------------------
   FETCH DYNAMIC FIELD VALUES
------------------------------------*/
$dyn_values = [];

$vstmt = $conn->prepare("
    SELECT weighment_id, field_id, field_value
    FROM weighment_field_values
");
$vstmt->execute();
$vresult = $vstmt->get_result();
while ($v = $vresult->fetch_assoc()) {
    $dyn_values[$v['weighment_id']][$v['field_id']] = $v['field_value'];
}
?>

<div class="page-title">VEHICLEWISE REPORT</div>

<div class="container">

<div style="display:flex; justify-content:space-between; margin-bottom:10px;">
    <form method="get" style="display:flex;gap:10px;align-items:center;">

    <!-- VEHICLE -->
    <select name="vehicle_no" style="padding:6px;width:180px;">
        <option value="">-- All Vehicles --</option>
        <?php foreach ($vehicles as $v): ?>
            <option value="<?php echo htmlspecialchars($v); ?>"
                <?php if ($vehicle_no === $v) echo 'selected'; ?>>
                <?php echo htmlspecialchars($v); ?>
            </option>
        <?php endforeach; ?>
    </select>

    <!-- FROM DATE -->
    From:
    <input type="date" name="from_date"
        value="<?= $_GET['from_date'] ?? '' ?>">

    <!-- TO DATE -->
    To:
    <input type="date" name="to_date"
        value="<?= $_GET['to_date'] ?? '' ?>">

    <button type="submit">Show</button>

</form>


    <button onclick="window.print()">Print</button>
</div>

<?php
$veh_label = $vehicle_no ?: "All Vehicles";
?>

<div style="margin-bottom:8px;font-weight:bold;">
    Vehicle : <?= htmlspecialchars($veh_label); ?>

    <?php if ($from_date || $to_date): ?>
        &nbsp;&nbsp;|&nbsp;&nbsp;
        Period :
        <?= $from_date ? date('d-m-Y', strtotime($from_date)) : 'Beginning'; ?>
        →
        <?= $to_date ? date('d-m-Y', strtotime($to_date)) : 'Today'; ?>
    <?php endif; ?>
</div>

<table width="100%" border="1" cellspacing="0" cellpadding="8"
       style="background:#fff; border-collapse:collapse;">

<tr style="background:#e5e7eb; font-weight:bold;">
    <th>Slip No</th>
    <th>Vehicle No</th>
    <th>Gross Weight</th>
    <th>Gross Date</th>
    <th>Gross Time</th>
    <th>Tare Weight</th>
    <th>Tare Date</th>
    <th>Tare Time</th>
    <th>Net Weight</th>
    <?php foreach ($dyn_fields as $label): ?>
        <th><?php echo htmlspecialchars($label); ?></th>
    <?php endforeach; ?>
</tr>

<?php if ($result->num_rows === 0): ?>
<tr>
    <td colspan="<?php echo 9 + count($dyn_fields); ?>" style="text-align:center;">
        No completed weighments found
    </td>
</tr>
<?php else: ?>
<?php while ($row = $result->fetch_assoc()): ?>
<tr>
    <?php $wid = $row['slip_no']; ?>
    <td><?php echo (int)$row['slip_no']; ?></td>
    <td><?php echo htmlspecialchars($row['vehicle_no']); ?></td>
    <td style="text-align:right;"><?php echo number_format($row['gross_weight'], 0); ?></td>
    <td><?php echo htmlspecialchars($row['gross_date']); ?></td>
    <td><?php echo htmlspecialchars($row['gross_time']); ?></td>
    <td style="text-align:right;"><?php echo number_format($row['tare_weight'], 0); ?></td>
    <td><?php echo htmlspecialchars($row['tare_date']); ?></td>
    <td><?php echo htmlspecialchars($row['tare_time']); ?></td>
    <td style="text-align:right;font-weight:bold;">
        <?php echo number_format($row['net_weight'], 0); ?>
    </td>

    <?php foreach ($dyn_fields as $fid => $label): ?>
        <td style="text-align:right;">
            <?php
            echo isset($dyn_values[$wid][$fid])
                ? htmlspecialchars($dyn_values[$wid][$fid])
                : '';
            ?>
        </td>
    <?php endforeach; ?>
</tr>
<?php endwhile; ?>
<?php endif; ?>

</table>

</div>

<style>
@media print {
    .top-menu,
    form,
    button {
        display:none;
    }

    body {
        background:white;
    }

    table {
        font-size:12px;
    }
}

</style>

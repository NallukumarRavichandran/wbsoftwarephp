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
   FIELD FROM HEADER (IDENTITY ONLY)
------------------------------------*/
$field_id = (int)($_GET['field_id'] ?? 0);
if ($field_id <= 0) {
    die("Invalid report");
}

/* -----------------------------------
   VALIDATE FIELD
------------------------------------*/
$stmt = $conn->prepare("
    SELECT field_label,field_options
    FROM weighment_fields
  WHERE id = ?
  AND company_id = ?
  AND field_options LIKE '%report%'
  AND is_active = 1
");
$stmt->bind_param("ii", $field_id, $company_id);
$stmt->execute();
$rs = $stmt->get_result();

if ($rs->num_rows === 0) {
    die("Report not allowed");
}

$row = $rs->fetch_assoc();

$field_label = $row['field_label'];

$is_total_report =
    strpos($row['field_options'],'total') !== false;
	
/* -----------------------------------
   FILTER VALUES FOR DROPDOWN
------------------------------------*/
$values = [];

if(!$is_total_report)
{
	$stmt = $conn->prepare("
    SELECT DISTINCT field_value
    FROM weighment_field_values
    WHERE field_id = ?
    ORDER BY field_value
");

$stmt->bind_param("i", $field_id);
$stmt->execute();
$rs = $stmt->get_result();

while ($r = $rs->fetch_assoc()) {
    $values[] = $r['field_value'];
} }

$value='';
if(!$is_total_report)
{
    $value = $_GET['value'] ?? '';
}
$from_date = $_GET['from_date'] ?? '';
$to_date   = $_GET['to_date'] ?? '';
/* -----------------------------------
   FETCH ALL DYNAMIC FIELD LABELS
------------------------------------*/
$dyn_fields = [];
$total_fields = [];
$totals = [];
$stmt = $conn->prepare("
    SELECT id,
           field_label,
           field_options
    FROM weighment_fields
    WHERE company_id = ?
      AND is_active = 1
    ORDER BY field_order
");
$stmt->bind_param("i", $company_id);
$stmt->execute();
$rs = $stmt->get_result();

while ($r = $rs->fetch_assoc()) {

    $dyn_fields[$r['id']] = $r['field_label'];

    if (strpos($r['field_options'],'total') !== false)
	{
        $total_fields[] = $r['id'];
    }

}
/* -----------------------------------
   FIND MATCHING SLIP NUMBERS
------------------------------------*/
$slips = [];

$sql = "
    SELECT DISTINCT v.weighment_id
    FROM weighment_field_values v
    JOIN sweighment s ON s.slip_no = v.weighment_id
    WHERE s.company_id = ?
      AND v.field_id = ?
";

$params = [$company_id, $field_id];
$types  = "ii";

if ($from_date !== '' && $to_date !== '') {

    $sql .= " AND (
        (
            STR_TO_DATE(s.gross_date, '%d/%m/%Y')
            BETWEEN ? AND ?
        )
        OR
        (
            s.tare_date IS NOT NULL AND
            STR_TO_DATE(s.tare_date, '%d/%m/%Y')
            BETWEEN ? AND ?
        )
    )";

    // we pass normal YYYY-MM-DD from the date picker
    $params[] = $from_date;
    $params[] = $to_date;
    $params[] = $from_date;
    $params[] = $to_date;

    $types .= "ssss";
}

if(!$is_total_report && $value!='')
{

    $sql .= " AND v.field_value = ?";
    $params[] = $value;
    $types .= "s";
}
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$rs = $stmt->get_result();

while ($r = $rs->fetch_assoc()) {
    $slips[] = (int)$r['weighment_id'];
}

/* -----------------------------------
   FETCH SWEIGHMENT DATA
------------------------------------*/
$result = false;

if (!empty($slips)) {

    $placeholders = implode(',', array_fill(0, count($slips), '?'));

    $sql = "
        SELECT *
        FROM sweighment
        WHERE slip_no IN ($placeholders)
          AND company_id = ?
        ORDER BY CAST(slip_no AS UNSIGNED) ASC
    ";

    $bind = array_merge($slips, [$company_id]);
    $types = str_repeat('i', count($slips)) . "i";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$bind);
    $stmt->execute();
    $result = $stmt->get_result();
}

/* -----------------------------------
   FETCH DYNAMIC FIELD VALUES
------------------------------------*/
$dyn_values = [];

if (!empty($slips)) {

    $placeholders = implode(',', array_fill(0, count($slips), '?'));
    $types = str_repeat('i', count($slips));

    $stmt = $conn->prepare("
        SELECT weighment_id, field_id, field_value
        FROM weighment_field_values
        WHERE weighment_id IN ($placeholders)
    ");
    $stmt->bind_param($types, ...$slips);
    $stmt->execute();
    $rs = $stmt->get_result();

    while ($r = $rs->fetch_assoc()) {
        $dyn_values[$r['weighment_id']][$r['field_id']] = $r['field_value'];
    }
}
?>

<div class="page-title">
    <?php echo strtoupper(htmlspecialchars($field_label)); ?> REPORT
</div>

<div class="container">

<div style="display:flex;justify-content:space-between;margin-bottom:10px;">
    <form method="get">
        <input type="hidden" name="field_id" value="<?php echo $field_id; ?>">
		
		
    From:
    <input type="date" name="from_date"
           value="<?php echo htmlspecialchars($from_date); ?>">

    To:
    <input type="date" name="to_date"
           value="<?php echo htmlspecialchars($to_date); ?>">
		
		<?php if(!$is_total_report): ?>
        <select name="value" style="padding:6px;width:220px;">
            <option value="">-- All <?php echo htmlspecialchars($field_label); ?> --</option>
            <?php foreach ($values as $v): ?>
                <option value="<?php echo htmlspecialchars($v); ?>"
                    <?php if ($value === $v) echo 'selected'; ?>>
                    <?php echo htmlspecialchars($v); ?>
                </option>
            <?php endforeach; ?>
        </select>
		<?php endif; ?>
        <button type="submit">Show</button>
    </form>

    <button onclick="window.print()">Print</button>
</div>

<table width="100%" border="1" cellspacing="0" cellpadding="8" style="background:#fff;border-collapse:collapse;">
<tr style="background:#e5e7eb;font-weight:bold;">

<th>Slip No</th>
<th>Vehicle No</th>
<th>Gross</th>
<th>Gross Date</th>
<th>Gross Time</th>
<th>Tare</th>
<th>Tare Date</th>
<th>Tare Time</th>
<th>Net</th>

<?php foreach($dyn_fields as $fid=>$lbl): ?>

    <th><?= htmlspecialchars($lbl) ?></th>

<?php endforeach; ?>

</tr>

<?php if (!$result || $result->num_rows === 0): ?>
<tr>
    <td colspan="<?php echo 9 + count($dyn_fields); ?>" align="center">
        No records found
    </td>
</tr>
<?php else: ?>
<?php while ($row = $result->fetch_assoc()): ?>
<tr>
    <?php $wid = $row['slip_no']; ?>
    <td><?php echo (int)$row['slip_no']; ?></td>
    <td><?php echo htmlspecialchars($row['vehicle_no']); ?></td>
    <td align="right"><?php echo number_format($row['gross_weight'], 0); ?></td>
    <td><?php echo $row['gross_date']; ?></td>
    <td><?php echo $row['gross_time']; ?></td>
    <td align="right"><?php echo number_format($row['tare_weight'], 0); ?></td>
    <td><?php echo $row['tare_date']; ?></td>
    <td><?php echo $row['tare_time']; ?></td>
    <td align="right"><b><?php echo number_format($row['net_weight'], 0); ?></b></td>

<?php foreach ($dyn_fields as $fid => $lbl): ?>

<?php
$value = $dyn_values[$wid][$fid] ?? '';

if (in_array($fid, $total_fields)) {
    $totals[$fid] = ($totals[$fid] ?? 0) + (float)$value;
}
?>

<td>
    <?= htmlspecialchars($value) ?>
</td>

<?php endforeach; ?>
</tr>
<?php endwhile; ?>

<tr style="
background:#dbeafe;
font-weight:bold;
">

<td colspan="9" align="right">
TOTAL
</td>

<?php foreach($dyn_fields as $fid=>$lbl): ?>

<td>

<?php
echo in_array($fid, $total_fields)
        ? number_format($totals[$fid],2)
        : '';
?>

</td>

<?php endforeach; ?>

</tr>

<?php endif; ?>
</table>

</div>

<style>
@media print {
    .top-menu,
    .page-title,
    button,
    form {
        display:none;
    }
}
</style>

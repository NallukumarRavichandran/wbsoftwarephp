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
   FETCH DYNAMIC FIELD DEFINITIONS
------------------------------------*/
$dynFields = [];
$res = $conn->query("
    SELECT id, field_label
    FROM weighment_fields
    WHERE company_id = $company_id
      AND is_active = 1
    ORDER BY field_order
");
while ($row = $res->fetch_assoc()) {
    $dynFields[] = $row;
}

/* -----------------------------------
   FETCH PENDING WEIGHMENTS
------------------------------------*/
$sql = "
    SELECT 
        w.id,
        w.slip_no,
        w.vehicle_no,
        w.gt_type,
        w.first_weight,
        w.first_date,
        w.first_time,
        u.username
    FROM weighments w
    LEFT JOIN users u ON u.id = w.user_id
    WHERE w.company_id = ?
  ORDER BY CAST(w.slip_no AS UNSIGNED) ASC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $company_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<div class="page-title">PENDING WEIGHMENT REPORT</div>

<div class="container">

<div style="text-align:right; margin-bottom:10px;">
    <button onclick="window.print()">Print</button>
</div>

<table width="100%" border="1" cellspacing="0" cellpadding="8" style="background:#fff; border-collapse:collapse;">
    <tr style="background:#e5e7eb; font-weight:bold;">
        <th>Slip No</th>
        <th>Vehicle No</th>

        <?php foreach ($dynFields as $f): ?>
            <th><?php echo htmlspecialchars($f['field_label']); ?></th>
        <?php endforeach; ?>

        <th>G/T</th>
        <th>First Weight</th>
        <th>Date</th>
        <th>Time</th>
    </tr>

<?php
$i = 1;

if ($result->num_rows === 0):
?>
    <tr>
        <td colspan="<?php echo 8 + count($dynFields); ?>" style="text-align:center;">
            No Pending Weighments
        </td>
    </tr>
<?php
else:
    while ($row = $result->fetch_assoc()):

        /* -----------------------------------
           FETCH DYNAMIC FIELD VALUES
        ------------------------------------*/
        $values = [];
        $vres = $conn->query("
            SELECT field_id, field_value
            FROM weighment_field_values
            WHERE weighment_id = ".$row['slip_no']
        );
        while ($v = $vres->fetch_assoc()) {
            $values[$v['field_id']] = $v['field_value'];
        }
?>
    <tr>
<td><?php echo (int)$row['slip_no']; ?></td>
        <td><?php echo htmlspecialchars($row['vehicle_no']); ?></td>

        <?php foreach ($dynFields as $f): ?>
            <td>
                <?php echo htmlspecialchars($values[$f['id']] ?? ''); ?>
            </td>
        <?php endforeach; ?>

        <td><?php echo htmlspecialchars($row['gt_type']); ?></td>
        <td style="text-align:right;"><?php echo number_format($row['first_weight'], 0); ?></td>
        <td><?php echo htmlspecialchars($row['first_date']); ?></td>
        <td><?php echo htmlspecialchars($row['first_time']); ?></td>
    </tr>
<?php
    endwhile;
endif;
?>
</table>

</div>

<style>
@media print {
    .top-menu,
    .page-title,
    button {
        display:none;
    }
    body {
        background:white;
    }
}
</style>

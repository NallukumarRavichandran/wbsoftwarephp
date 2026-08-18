<?php
ob_start();  
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once "db.php";
require_once "functions.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login_php.php");
    exit;
}

if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_unset();
    session_destroy();
    header("Location: login_php.php");
    exit;
}


/* ---------------- BACKUP TRIGGER ---------------- */
if (isset($_GET['action']) && $_GET['action'] === 'backup') {

    if ($_SESSION['role'] !== 'admin') {
        die("Unauthorized Access");
    }

    $backupFile = takeDatabaseBackup($conn);
	
	$_SESSION['msg'] = "Database Backup Created Successfully";
	header("Location: mainform.php");
	exit;
}


/* ---------------- RESET SYSTEM ---------------- */
if (isset($_GET['action']) && $_GET['action'] === 'reset') {

    $force = isset($_GET['force']) && $_GET['force'] == 1;

    $result = resetWeighmentTransactions($conn, $force);

    if ($result['status']) {
        $_SESSION['msg'] = $result['message'];
    } else {
        $_SESSION['error'] = $result['message'];
    }

    header("Location: mainform.php");
    exit;
}

/* ---------------- SHOW RESTORE SCREEN ---------------- */
if (isset($_GET['action']) && $_GET['action'] === 'restore_ui') {

    if ($_SESSION['role'] !== 'admin') {
        die("Unauthorized Access");
    }

    $backupDir = __DIR__ . "/backups";
    $files = glob($backupDir . "/*.sql");
    rsort($files);
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Restore Backup</title>
        <style>
            body { font-family:Segoe UI, Arial; background:#f3f4f6; padding:30px; }
            .box {
                background:#fff;
                padding:20px;
                max-width:600px;
                margin:auto;
                border-radius:8px;
                box-shadow:0 4px 20px rgba(0,0,0,0.1);
            }
            a.restore {
                display:block;
                padding:10px;
                margin:6px 0;
                background:#2563eb;
                color:#fff;
                text-decoration:none;
                border-radius:4px;
            }
            a.restore:hover { background:#1d4ed8; }
        </style>
    </head>
    <body>

    <div class="box">
        <h2>Select Backup to Restore</h2>

        <?php if (empty($files)): ?>
            <p>No backups found.</p>
        <?php else: ?>
            <?php foreach ($files as $f): $name = basename($f); ?>
                <a class="restore"
                   href="?action=restore_exec&file=<?= urlencode($name) ?>"
                   onclick="return confirm('Restore <?= $name ?> ? This will overwrite current data!');">
                   <?= $name ?>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>

        <br>
        <a href="mainform.php">⬅ Back</a>
    </div>

    </body>
    </html>
    <?php
    exit;
}
/* ---------------- EXECUTE RESTORE ---------------- */
if (isset($_GET['action']) && $_GET['action'] === 'restore_exec') {

    if ($_SESSION['role'] !== 'admin') {
        die("Unauthorized Access");
    }

    $file = $_GET['file'] ?? '';
    $result = restoreDatabaseBackup($conn, $file);

    if ($result === true) {
        echo "<script>
            alert('Database Restored Successfully');
            window.location='mainform.php';
        </script>";
    } else {
        echo "<script>alert('$result');window.history.back();</script>";
    }

    exit;
}
$company = getCompany($conn);
if (!$company) {
    header("Location: create_company.php");
    exit;
}
$company_id = $company['id'];

/* -----------------------------------
   FETCH REPORTABLE DYNAMIC FIELDS
------------------------------------*/
$report_fields = [];

$rstmt = $conn->prepare("
    SELECT id, field_label
    FROM weighment_fields
    WHERE company_id = ?
      AND field_options LIKE '%report%'
	  AND is_active = 1	
    ORDER BY field_order
");
$rstmt->bind_param("i", $company_id);
$rstmt->execute();
$rres = $rstmt->get_result();

while ($r = $rres->fetch_assoc()) {
    $report_fields[] = $r;
}



?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title><?php echo htmlspecialchars($company['company_name']); ?> - Weighment System</title>

<style>
body {
    font-family: "Segoe UI", Arial, sans-serif;
    margin:0;
    background:#f5f6fa;
}

/* ---------------- TOP MENU ---------------- */
.top-menu {
    background:#1f2937;   /* dark slate */
    color:#fff;
}
.top-menu ul {
    list-style:none;
    margin:0;
    padding:0 20px;
    display:flex;
}
.top-menu li {
    position:relative;
}
.top-menu a {
    display:block;
    padding:12px 18px;
    color:#fff;
    text-decoration:none;
    font-size:15px;
    font-weight:500;
}
.top-menu a:hover {
    background:#374151;
}

/* Dropdown */
.top-menu .dropdown-menu {
    display:none;
    position:absolute;
    top:100%;
    left:0;
    background:#ffffff;
    min-width:220px;
    border-radius:4px;
    box-shadow:0 8px 16px rgba(0,0,0,0.15);
    z-index:1000;
}
.top-menu .dropdown-menu a {
    color:#111;
    padding:10px 14px;
    font-weight:400;
}
.top-menu .dropdown-menu a:hover {
    background:#f3f4f6;
}
.top-menu li:hover .dropdown-menu {
    display:block;
}
.disabled-link {
    color: #9ca3af !important;
    background: #f3f4f6 !important;
    cursor: not-allowed;
    pointer-events: none;
}


/* ---------------- COMPANY HEADER ---------------- */
.company-banner {
    background:#ffffff;
    border-bottom:1px solid #e5e7eb;
    text-align:center;
    padding:18px 10px;
}
.company-banner h1 {
    margin:0;
    font-size:26px;
    letter-spacing:1px;
    color:#111827;
}
.company-banner .info {
    margin-top:6px;
    font-size:14px;
    color:#4b5563;
    line-height:1.6;
}

/* ---------------- PAGE TITLE ---------------- */
.page-title {
    background:#2563eb; /* blue */
    color:#ffffff;
    text-align:center;
    padding:10px;
    font-size:20px;
    font-weight:600;
    letter-spacing:1px;
}

/* Page content */
.container {
    padding:20px;
}
</style>
</head>

<body>

<!-- MENU FIRST -->
<div class="top-menu">
    <ul>
        <!-- HOME -->
        <li>
            <a href="mainform.php">Home</a>
        </li>
<!-- REPORTS -->
<li>
    <a href="#">Reports ▾</a>
    <div class="dropdown-menu">
        <a href="pending_report.php">Pending Report</a>
        <a href="vehiclewise_report.php">Vehicle-wise Report</a>

        <?php if (!empty($report_fields)): ?>
            <div style="border-top:1px solid #e5e7eb;margin:6px 0;"></div>
            <?php foreach ($report_fields as $rf): ?>
                <a href="dynamic_report.php?field_id=<?= $rf['id'] ?>">
                    <?= htmlspecialchars(ucwords(strtolower($rf['field_label']))) ?> Report
                </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</li>

<!-- SCALE -->
<li>
    <a href="#">Scale ▾</a>
    <div class="dropdown-menu">
		<a href="#" onclick="openScaleDialog(); return false;">🔌 Connect Port</a>
        <a href="#" onclick="autoReconnect(); return false;">🔁 Reconnect</a>
        <a href="#" onclick="disconnectScale(); return false;">🔴 Disconnect</a>

        <div style="border-top:1px solid #e5e7eb;margin:6px 0;"></div>
        <a href="#" style="cursor:default;">
            Status: <span id="connStatus">DISCONNECTED</span>
        </a>
    </div>
</li>

<?php if ($_SESSION['role'] === 'admin'): ?>
<li>
    <a href="#">Settings ▾</a>
    <div class="dropdown-menu">

        <a href="?action=backup"
           onclick="return confirm('Do you want to take database backup now?');">
           📦 Backup Database
        </a>

<a href="#"
   class="disabled-link"
   onclick="return false;">
   ♻ Restore Database (Disabled)
</a>

        <a href="?action=reset"
			onclick="return confirm('Normal Reset? Pending slips must be completed.');">
			🔄 Reset Slip Number
		</a>

		<a href="?action=reset&force=1"
			onclick="return confirm('FORCE RESET will delete even running weighments. Are you sure?');">
			⚠ Force Reset
		</a>

    </div>
</li>
<?php endif; ?>

<?php if ($_SESSION['role'] === 'admin'): ?>
<li>
    <a href="admin_page.php">Dashboard</a>
</li>
<?php endif; ?>

        <li>
	<a href="?action=logout" onclick="return confirm('Are you sure you want to logout?');">
		Logout
	</a>
        </li>
    </ul>
</div>


<!-- COMPANY INFO -->
<div class="company-banner">
    <h1><?php echo strtoupper($company['company_name']); ?></h1>
    <div class="info">
        <?php echo nl2br(htmlspecialchars($company['company_address'])); ?><br>
        <?php
$gst   = trim($company['gst_number']);
$phone = trim($company['phone']);
$email = trim($company['email']);
?>

<?php if ($gst !== '' && $gst !== '-'): ?>
    GST: <?= htmlspecialchars($gst); ?>
<?php endif; ?>

<?php if ($phone !== '' && $phone !== '-'): ?>
    <?php if ($gst !== '' && $gst !== '-'): ?> | <?php endif; ?>
    Phone: <?= htmlspecialchars($phone); ?>
<?php endif; ?>

<?php if ($email !== '' && $email !== '-'): ?>
    <br>
    Email: <?= htmlspecialchars($email); ?>
<?php endif; ?>
    </div>
</div>

<?php if (!empty($_SESSION['msg'])): ?>
<div style="background:#dcfce7;color:#166534;padding:10px;margin:10px;">
    <?= $_SESSION['msg']; unset($_SESSION['msg']); ?>
</div>
<?php endif; ?>

<?php if (!empty($_SESSION['error'])): ?>
<div id="errorBox"
style="background:#fee2e2;color:#991b1b;padding:10px;margin:10px;">    <?= $_SESSION['error']; unset($_SESSION['error']); ?>
</div>
<?php endif; ?>

<script src="scale.js?v=<?= time(); ?>" ></script>

<script>
// AUTO CONNECT WHEN PAGE LOADS (after first permission)
window.addEventListener("load", async () => {
    console.log("Checking for saved scale permission...");

    try {
        await autoReconnect();   // tries silently
    } catch(e) {
        console.log("No previous permission yet.");
    }
});
</script>
<div id="scaleDialog" style="
    display:none;
    position:fixed;
    top:0;left:0;right:0;bottom:0;
    background:rgba(0,0,0,0.4);
    z-index:9999;
">
    <div style="
        background:#fff;
        width:320px;
        padding:20px;
        margin:120px auto;
        text-align:center;
        border-radius:8px;
        box-shadow:0 10px 25px rgba(0,0,0,0.3);
    ">
        <h3>Connect Weighbridge</h3>
        <p>Click below to select COM Port</p>

        <button onclick="connectScaleDirect()"
            style="padding:10px 18px;font-size:16px;cursor:pointer;">
            CONNECT SCALE
        </button>

        <br><br>

        <button onclick="closeScaleDialog()">Cancel</button>
    </div>
</div>

</body>
</html>
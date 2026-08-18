<?php
session_start();
require_once "db.php";
require_once "functions.php";

/* ---------- AUTH CHECK ---------- */
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login_php.php");
    exit;
}
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_unset();
    session_destroy();
    header("Location: login_php.php");
    exit;
}

/* ---------- COMPANY CHECK ---------- */
$company = getCompany($conn);
if (!$company) {
    header("Location: create_company.php");
    exit;
}

$action = $_GET['action'] ?? '';

/* ---------- RENDER COMPANY HEADER ---------- */
function renderCompanyHeader($company) {
?>
    <div class="company-header" style="margin-bottom:25px;">
        <h1><?= strtoupper(htmlspecialchars($company['company_name'])) ?></h1>

        <?php if (!empty($company['company_address'])): ?>
            <div><?= htmlspecialchars($company['company_address']) ?></div>
        <?php endif; ?>

        <div>
            GST: <?= htmlspecialchars($company['gst_number'] ?? '-') ?> |
            Phone: <?= htmlspecialchars($company['phone'] ?? '-') ?>
        </div>

        <div>
            Email: <?= htmlspecialchars($company['email'] ?? '-') ?>
        </div>
    </div>

    <div class="section-title">
        COMPANY DETAILS
    </div>
<?php
}
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Admin Dashboard</title>

<style>
* {
    box-sizing: border-box;
    font-family: "Segoe UI", Poppins, Arial, sans-serif;
}

body {
    margin: 0;
    min-height: 100vh;
    background: linear-gradient(135deg, #667eea, #764ba2, #f7971e);
    display: flex;
    align-items: center;
    justify-content: center;
}

.box {
    width: 360px;
    padding: 28px 24px;
    text-align: center;
    background: rgba(255,255,255,0.18);
    backdrop-filter: blur(14px);
    border-radius: 18px;
    box-shadow: 0 25px 45px rgba(0,0,0,0.25);
    color: #fff;
}
.logo{
    width:85px;
    margin-bottom:10px;
    border-radius:50%;
}

h2 {
    margin-bottom: 20px;
    letter-spacing: 1px;
}

a {
    display: block;
    padding: 14px;
    margin: 12px 0;
    border-radius: 12px;
    text-decoration: none;
    font-size: 15px;
    font-weight: 600;
    color: #fff;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

a:hover {
    transform: translateY(-3px) scale(1.02);
    box-shadow: 0 12px 25px rgba(0,0,0,0.3);
}

.btn-company {
    background: linear-gradient(135deg, #43cea2, #185a9d);
}

.btn-fields {
    background: linear-gradient(135deg, #f7971e, #ffd200);
    color: #2c2c2c;
}

.btn-weigh {
    background: linear-gradient(135deg, #ff6a88, #ff99ac);
}
.btn-user {
    background: linear-gradient(135deg, #36d1dc, #5b86e5);
}

.btn-logout {
    background: linear-gradient(135deg, #ff416c, #ff4b2b);
}

.company-header h1 {
    margin: 0;
    font-size: 26px;
}

.company-header div {
    font-size: 14px;
    opacity: 0.95;
}

.section-title {
    background: linear-gradient(135deg, #00c6ff, #0072ff);
    padding: 10px;
    border-radius: 10px;
    font-weight: bold;
    margin: 18px 0;
}
</style>
</head>

<body>

<div class="box">

<img src="gblogo.jpeg" class="logo">
<?php if ($action === 'company'): ?>

    <h2>Company Details</h2>

    <?php renderCompanyHeader($company); ?>

    <a class="btn-company" href="edit_company.php">✏️ Edit Company</a>
    <a class="btn-fields" href="admin_page.php">⬅ Back</a>

<?php else: ?>

    <h2>Admin Dashboard</h2>

    <a class="btn-company" href="admin_page.php?action=company">🏢 Company Screen</a>
	<a class="btn-fields" href="setup_dynamic_fields.php?company_id=<?= $company['id'] ?>">🧩 Dynamic Fields</a>
	    <a class="btn-user" href="create_user.php">👤 Add User</a>

    <a class="btn-weigh" href="mainform.php">⚖️ Weighment Screen</a>
    <a class="btn-logout" href="?action=logout" onclick="return confirm('Are you sure you want to logout?');">🚪 Logout</a>

<?php endif; ?>

</div>

</body>
</html>
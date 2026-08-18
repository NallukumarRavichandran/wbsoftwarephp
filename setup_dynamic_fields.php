<?php
session_start();

require_once "db.php";
require_once "functions.php";

/* ---------- AUTH CHECK ---------- */
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login_php.php");
    exit;
}

$company = getCompany($conn);
$company_id = $company['id'];

/* ---------- SAVE FIELDS ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    foreach ($_POST['field_name'] as $i => $field_name) {

        $field_name = trim($field_name);

        if ($field_name === '') {
            continue;
        }

        $field_id     = $_POST['field_id'][$i] ?? '';
        $field_label  = $_POST['field_label'][$i];
        $field_type   = $_POST['field_type'][$i];
		$field_values = trim($_POST['field_values'][$i] ?? '');
		if ($field_type != 'dropdown') {
			$field_values = '';	}
        $field_option = $_POST['field_options'][$i] ?? '';
        $is_required  = $_POST['is_required'][$i] ?? 0;
        $field_order  = $i;
        $is_active    = $_POST['is_active'][$i] ?? 1;

        /* ---------- CHECK MAIN TABLE COLUMN NAMES ---------- */

        $reserved_fields = [];

        /* ---------- FETCH weighments COLUMNS ---------- */

        $result1 = $conn->query("SHOW COLUMNS FROM weighments");

        while ($col = $result1->fetch_assoc()) {

            $reserved_fields[] = strtolower($col['Field']);

        }

        /* ---------- FETCH sweighment COLUMNS ---------- */

        $result2 = $conn->query("SHOW COLUMNS FROM sweighment");

        while ($col = $result2->fetch_assoc()) {

            $reserved_fields[] = strtolower($col['Field']);

        }

        /* ---------- CHECK FIELD NAME ---------- */

        if (in_array(strtolower($field_name), $reserved_fields)) {

            echo "
            <script>
                alert('Field Name \"$field_name\" already exists in system tables.');
                window.location.href='setup_dynamic_fields.php';
            </script>
            ";

            exit;
        }

        /* ---------- UNIQUE FIELD NAME CHECK ---------- */

        $check = $conn->prepare("
            SELECT id FROM weighment_fields
            WHERE company_id = ?
            AND field_name = ?
            AND id != ?
        ");

        $current_id = $field_id ? $field_id : 0;

        $check->bind_param(
            "isi",
            $company_id,
            $field_name,
            $current_id
        );

        $check->execute();

        $dup = $check->get_result();

        if ($dup->num_rows > 0) {

            echo "
            <script>
                alert('Field Name \"$field_name\" already exists.');
                window.location.href='setup_dynamic_fields.php';
            </script>
            ";

            exit;
        }
        /* ---------- UPDATE ---------- */
        if ($field_id) {
            $stmt = $conn->prepare("
                UPDATE weighment_fields
                SET field_name=?,
                    field_label=?,
                    field_type=?,
					field_values=?,
					field_options=?,
                    is_required=?,
                    field_order=?,
                    is_active=?
                WHERE id=? AND company_id=?
            ");
					$stmt->bind_param(
					"sssssiiiii",
					$field_name,
					$field_label,
					$field_type,
					$field_values,
					$field_option,
					$is_required,
					$field_order,
					$is_active,
					$field_id,
					$company_id
			);
        } else {
           /* ---------- INSERT ---------- */
            $stmt = $conn->prepare("
				INSERT INTO weighment_fields
			(
				company_id,
				field_name,
				field_label,
				field_type,
				field_values,
				field_options,
				is_required,
				field_order,
				is_active
			)
				VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param(
				"isssssiii",
				$company_id,
				$field_name,
				$field_label,
				$field_type,
				$field_values,
				$field_option,
				$is_required,
				$field_order,
				$is_active
			);
        }
        $stmt->execute();
    }
    header("Location: setup_dynamic_fields.php?success=1");
    exit;
}

/* ---------- FETCH EXISTING FIELDS ---------- */

$fields = [];

$res = $conn->prepare("
    SELECT *
    FROM weighment_fields
    WHERE company_id = ?
    ORDER BY field_order, id
");

$res->bind_param("i", $company_id);

$res->execute();

$result = $res->get_result();

while ($row = $result->fetch_assoc()) {

    $fields[] = $row;

}
?>

<!DOCTYPE html>
<html>

<head>

<meta charset="UTF-8">

<title>Setup Dynamic Fields</title>

<style>

body {
    font-family: Arial, sans-serif;
    margin:0;
    padding:30px;
    min-height:100vh;

    background: linear-gradient(
        135deg,
        #667eea 0%,
        #764ba2 50%,
        #f0932b 100%
    );
}

.wrapper {
    max-width:1200px;
    margin:auto;

    background:rgba(255,255,255,0.92);

    padding:30px;

    border-radius:20px;

    box-shadow:0 10px 30px rgba(0,0,0,.25);
}

h2{
    text-align:center;
    color:#111827;
    margin-bottom:25px;
    font-size:38px;
}

.header-row {
    background:#2563eb;
    color:#fff;
    padding:10px;
    border-radius:10px;
    display:grid;
	grid-template-columns:1fr 1fr 150px 220px 180px 170px;
    gap:10px;
    margin-bottom:8px;
    font-weight:bold;
    font-size:16px;
	font-weight:700;
}

.header-row div {
    padding-left: 4px;
}

.row {
    background:#f9fafb;
    border-radius:8px;
    padding:8px;
    display: grid;
    grid-template-columns: 1fr 1fr 150px 220px 180px 170px;
    gap: 10px;
    margin-bottom: 10px;
    align-items: center;
}

.inactive-row {
    opacity: 0.5;
    background: #f1f1f1;
    padding: 6px;
    border-radius: 6px;
}

input,
select{
    padding:10px;
    border:1px solid #cbd5e1;
    border-radius:8px;
    font-size:14px;
}

.actions {
    margin-top: 20px;
    text-align: center;
}

button{
    padding:12px 22px;
    border:none;
    border-radius:10px;
    cursor:pointer;
    font-size:15px;
    font-weight:bold;
}

</style>

<script>

function addRow() {

    const container = document.getElementById('fields');

    const row = document.createElement('div');

    row.className = 'row';

    row.innerHTML = `
    
        <input type="hidden" name="field_id[]" value="">

        <input type="text" name="field_name[]" placeholder="Field Name">

        <input type="text" name="field_label[]" placeholder="Field Label">

		<select name="field_type[]" onchange="toggleFieldValues(this)">
		    <option value="text">Text</option>
            <option value="number">Number</option>
            <option value="date">Date</option>
		   <option value="dropdown">Dropdown</option>
        </select>
		
	<input
		type="text"
		name="field_values[]"
		value="-"
		readonly
		placeholder="Dropdown values">		

        <select name="field_options[]">
            <option value="report">Show in Report</option>
            <option value="">No Report</option>
			<option value="report,total">Show in Report + Total</option>
			
        </select>

        <select name="is_active[]">
            <option value="1">Active</option>
            <option value="0">Not Needed Now</option>
        </select>
    `;

    container.appendChild(row);
}

</script>

</head>

<body>

<div class="wrapper">

    <div style="margin-bottom:15px;">

<a href="admin_page.php"
   style="
        display:inline-block;
        text-decoration:none;
        background:#1e40af;
        color:#fff;
        padding:10px 18px;
        border-radius:10px;
        font-weight:bold;
        font-size:15px;
        box-shadow:0 3px 8px rgba(0,0,0,.2);
        transition:0.3s;
   "
   onmouseover="this.style.background='#2563eb'"
   onmouseout="this.style.background='#1e40af'">
    ← Back to Dashboard
</a>

    </div>

    <?php if (isset($_GET['success'])): ?>

		<p style="
		color:#065f46;
		background:#d1fae5;
		padding:12px;
		border-radius:10px;
		text-align:center;
		font-weight:bold;
		">
            Fields saved successfully
        </p>

    <?php endif; ?>

    <form method="post">

        <div class="header-row">

            <div>Field Name</div>
            <div>Field Label</div>
            <div>Field Data Type</div>
		    <div>Field Values</div>
            <div>Field Option</div>
            <div>Status</div>

        </div>

        <div id="fields">

            <?php foreach ($fields as $f): ?>

                <div class="row <?= $f['is_active']==0 ? 'inactive-row' : '' ?>">

                    <input type="hidden"
                           name="field_id[]"
                           value="<?= $f['id'] ?>">

                    <input type="text"
                           name="field_name[]"
                           value="<?= htmlspecialchars($f['field_name']) ?>">

                    <input type="text"
                           name="field_label[]"
                           value="<?= htmlspecialchars($f['field_label']) ?>">

					<select name="field_type[]" onchange="toggleFieldValues(this)">
                        <option value="text"
                            <?= $f['field_type']=='text' ? 'selected' : '' ?>>
                            Text
                        </option>

                        <option value="number"
                            <?= $f['field_type']=='number' ? 'selected' : '' ?>>
                            Number
                        </option>

                        <option value="date"
                            <?= $f['field_type']=='date' ? 'selected' : '' ?>>
                            Date
                        </option>
						<option value="dropdown"
							<?= $f['field_type']=='dropdown' ? 'selected' : '' ?>>
							Dropdown
						</option>
                    </select>
					
					<input
						type="text"
						name="field_values[]"
						value="<?= htmlspecialchars($f['field_values'] ?: '-') ?>"	
						readonly>

                    <select name="field_options[]">

                        <option value="report"
                            <?= $f['field_options']=='report' ? 'selected' : '' ?>>
                            Show in Report
                        </option>

                        <option value=""
                            <?= $f['field_options']=='' ? 'selected' : '' ?>>
                            No Report
                        </option>
						
						<option value="report,total"
							<?= $f['field_options']=='report,total' ? 'selected' : '' ?>>
							Show in Report + Total
						</option>						

                    </select>

                    <select name="is_active[]">

                        <option value="1"
                            <?= $f['is_active']==1 ? 'selected' : '' ?>>
                            Active
                        </option>

                        <option value="0"
                            <?= $f['is_active']==0 ? 'selected' : '' ?>>
                            Not Needed Now
                        </option>

                    </select>

                </div>

            <?php endforeach; ?>

        </div>

        <div class="actions">

			<button
				type="button"
				onclick="addRow()"
				style="
				background:#10b981;
				color:white;
				margin-right:10px;
				">
                Add More Field
            </button>

			<button
				type="submit"
				style="
				background:#f59e0b;
				color:white;
				">
                Save Fields
            </button>

        </div>

    </form>

</div>
<script>
function toggleFieldValues(selectObj)
{
    const row = selectObj.closest('.row');
    const txt = row.querySelector('input[name="field_values[]"]');

    if (selectObj.value === "dropdown")
    {
        txt.readOnly = false;

        if (txt.value === "-")
            txt.value = "";
    }
    else
    {
        if (txt.value === "")
            txt.value = "-";

        txt.readOnly = true;
    }
}

/* Initialize when page loads */
window.onload = function()
{
    document.querySelectorAll('select[name="field_type[]"]').forEach(function(sel){
        toggleFieldValues(sel);
    });
};
</script>
</body>
</html>
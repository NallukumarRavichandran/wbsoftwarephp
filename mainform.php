<?php
// CAMERA MODULE DISABLED
// if (isset($_GET['camera'])) {
//     $wbConfig = include __DIR__ . '/config.php';
//     $camNum   = (int)$_GET['camera'];
//     if (!isset($wbConfig['cameras'][$camNum])) { http_response_code(404); exit; }
//     $cam = $wbConfig['cameras'][$camNum];
//     $ch = curl_init($cam['url']);
//     curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
//     curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST);
//     curl_setopt($ch, CURLOPT_USERPWD, $cam['username'] . ":" . $cam['password']);
//     curl_setopt($ch, CURLOPT_TIMEOUT, 5);
//     $img = curl_exec($ch);
//     curl_close($ch);
//     if (!$img) { http_response_code(404); exit; }
//     if (ob_get_length()) ob_clean();
//     header("Content-Type: image/jpeg");
//     header("Content-Length: " . strlen($img));
//     echo $img;
//     exit;
// }

require_once "header.php";

$company = getCompany($conn);
$company_id = $company['id'];
$pending_action = $_SESSION['pending_action'] ?? '';
?>
	
<?php if (!empty($_SESSION['weight_warning'])): ?>
<div style="
    color:#b00000;
    background:#ffe5e5;
    border:2px solid #b00000;
    padding:10px;
    margin:10px auto;
    width:80%;
    text-align:center;
    font-weight:bold;
">
    <?= htmlspecialchars($_SESSION['weight_warning']); ?>
</div>
<?php unset($_SESSION['weight_warning']); endif; ?>

<?php
/* VEHICLES FOR SECOND WEIGHMENT (WITH DYNAMIC FIELD VALUES) */
$vehicleData = [];

$q = $conn->query("
    SELECT id, slip_no, vehicle_no,
           first_weight, first_date, first_time, gt_type,
           company_id, user_id
    FROM weighments
    WHERE company_id = $company_id
    ORDER BY id DESC
");

while ($r = $q->fetch_assoc()) {
    $dyn = [];
    $dq = $conn->query("
        SELECT field_id, field_value
        FROM weighment_field_values
        WHERE weighment_id = {$r['slip_no']}
    ");
    while ($d = $dq->fetch_assoc()) {
        $dyn[$d['field_id']] = $d['field_value'];
    }
    $r['dynamic'] = $dyn;
    $vehicleData[] = $r;
}

/* DYNAMIC FIELDS */
$fields = [];
$res = $conn->query("
    SELECT *
    FROM weighment_fields
    WHERE company_id = $company_id
      AND is_active = 1
    ORDER BY field_order, id
");
while ($row = $res->fetch_assoc()) {
    $fields[] = $row;
}

/* SLIP NUMBER */
$slip_no = getNextSlipNumber($conn);
?>

<script>
const VEHICLE_DATA = <?php echo json_encode($vehicleData); ?>;
let secondMode = false;
let secondType = null;
</script>

<style>
body{font-family:Arial;background:#0033cc;color:white;margin:0}
.title{background:#0000aa;text-align:center;padding:6px;font-size:18px}
.container{display:flex;padding:15px}
.left{width:45%;padding:10px}
.right{width:55%;padding:10px}
label{display:inline-block;width:120px}
input,select{padding:6px;width:280px}
input[type=text]{text-transform:uppercase}
.field-group{margin-bottom:18px}
.readonly{background:#ddd}
.weight-box{background:#111;height:160px;margin-bottom:15px;border:3px inset #aaa;
display:flex;align-items:center;justify-content:center;font-size:42px;font-weight:bold;color:#00ff00}
.weight-panel{border:2px solid #ccc;padding:10px}
.weight-panel input{width:110px}
button{padding:6px 15px;font-weight:bold}
#gross_date_ui,#gross_time_ui,#tare_date_ui,#tare_time_ui{
width:160px;padding:8px;font-weight:bold;text-align:center;background:#eee;color:#000;border:2px inset #666}
.row{
    display:flex;
    align-items:center;
    margin-bottom:12px;
}

.row label{
    width:80px;
    font-weight:bold;
}

.row input{
    margin-right:10px;
    padding:5px;
}

.row button{
    margin-right:10px;
}

/* CUSTOMER ACTION ROW & BUTTONS */
.action-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    width: 100%;
    margin-top: 10px;
}

.left-buttons,
.right-buttons {
    display: flex;
    gap: 10px;
    align-items: center;
}

.right-buttons {
    margin-left: auto;
}

.save-btn {
    background-color: #28a745;
    color: white;
}

.cancel-btn {
    background-color: #dc3545;
    color: white;
}

.reprint-btn {
    background-color: #007bff;
    color: white;
}

.save-btn,
.cancel-btn,
.reprint-btn {
    border: none;
    padding: 10px 20px;
    border-radius: 5px;
    font-weight: bold;
    cursor: pointer;
}

.save-btn:hover {
    background-color: #218838;
}

.cancel-btn:hover {
    background-color: #c82333;
}

.reprint-btn:hover {
    background-color: #0069d9;
}
</style>

<script>
function validateVehicleNumber() {
    const vehicleInput = document.getElementById("vehicle_no");
    const vehicle = vehicleInput ? vehicleInput.value.trim().toUpperCase() : "";

    const pattern = /^[A-Z]{2}[0-9]{2}[A-Z]{2}[0-9]{4}$/;

    if (!pattern.test(vehicle)) {
        alert("Vehicle Number must be in the format TN01AB1234");
        if (vehicleInput) vehicleInput.focus();
        return false;
    }

    return true;
}

/* FULL FORM FIELD-BY-FIELD VALIDATION */
function validateFullForm() {
    // 1. Vehicle Number validation
    const vehicleInput = document.getElementById("vehicle_no");
    if (!secondMode && vehicleInput && vehicleInput.style.display !== "none") {
        const vehicleVal = vehicleInput.value.trim();
        if (!vehicleVal) {
            alert("Please enter Vehicle Number!");
            vehicleInput.focus();
            return false;
        }
        if (!validateVehicleNumber()) {
            return false;
        }
    } else if (secondMode) {
        const vehicleSelect = document.getElementById("vehicle_select");
        if (vehicleSelect && !vehicleSelect.value) {
            alert("Please select a Vehicle for Second Weighment!");
            vehicleSelect.focus();
            return false;
        }
    }

    // 2. Validate Left Panel Fields
    const fieldGroups = document.querySelectorAll(".left .field-group");
    for (let group of fieldGroups) {
        const labelEl = group.querySelector("label");
        const labelText = labelEl ? labelEl.innerText.trim() : "Field";
        
        if (labelText.toUpperCase().includes("SLIP NO")) continue;

        const selectEl = group.querySelector("select");
        const inputEl = group.querySelector("input");

        if (selectEl && selectEl.style.display !== "none") {
            if (!selectEl.value || selectEl.value === "" || selectEl.value === "-- Select --") {
                alert("Please select " + labelText + "!");
                selectEl.focus();
                return false;
            }
        } else if (inputEl && inputEl.style.display !== "none") {
            if (!inputEl.value.trim()) {
                alert("Please enter " + labelText + "!");
                inputEl.focus();
                return false;
            }
        }
    }

    // 3. Weight Validation
    const grossEl = document.getElementById("gross_weight");
    const tareEl = document.getElementById("tare_weight");
    const grossVal = grossEl ? grossEl.value.trim() : "";
    const tareVal = tareEl ? tareEl.value.trim() : "";

    if (secondMode) {
        if (!grossVal || !tareVal) {
            alert("Please record second weighment before saving!");
            return false;
        }
    } else {
        if (!grossVal && !tareVal) {
            alert("Please click 'RECORD WEIGHT' to capture live weight before saving!");
            return false;
        }
    }

    return true;
}

window.weightFrozen = false;
var frozenWeight = "";

document.addEventListener("DOMContentLoaded", function () {

    const vSelect = document.getElementById("vehicle_select");
    if (vSelect) {
        vSelect.addEventListener("change", function () {

            let v = VEHICLE_DATA[this.value];
            if (!v) return;

            secondMode = true;

            // basic fields
            const slipEl = document.querySelector("[name='slip_no']");
            const vehEl = document.querySelector("[name='vehicle_no']");
            if (slipEl) slipEl.value = v.slip_no;
            if (vehEl) vehEl.value = v.vehicle_no;

            // lock GT type
            let gt = document.querySelector("[name='gt_type']");
            if (gt) {
                gt.value = v.gt_type;
                gt.disabled = true;
            }

            // clear weights first
            document.getElementById("gross_weight").value = "";
            document.getElementById("tare_weight").value = "";
            document.getElementById("net_weight").value = "";

            document.getElementById("gross_date_ui").value = "";
            document.getElementById("gross_time_ui").value = "";
            document.getElementById("tare_date_ui").value = "";
            document.getElementById("tare_time_ui").value = "";

            document.getElementById("gross_date").value = "";
            document.getElementById("gross_time").value = "";
            document.getElementById("tare_date").value = "";
            document.getElementById("tare_time").value = "";

            // load FIRST weighment correctly
            if (v.gt_type === "G") {
                document.getElementById("gross_weight").value = v.first_weight;
                document.getElementById("gross_date_ui").value = v.first_date;
                document.getElementById("gross_time_ui").value = v.first_time;
                document.getElementById("gross_date").value = v.first_date;
                document.getElementById("gross_time").value = v.first_time;
                secondType = "tare";
            } else {
                document.getElementById("tare_weight").value = v.first_weight;
                document.getElementById("tare_date_ui").value = v.first_date;
                document.getElementById("tare_time_ui").value = v.first_time;
                document.getElementById("tare_date").value = v.first_date;
                document.getElementById("tare_time").value = v.first_time;
                secondType = "gross";
            }

            // LOAD DYNAMIC FIELD VALUES
            if (v.dynamic) {
                Object.keys(v.dynamic).forEach(fid => {
                    let el = document.querySelector(`[name="dyn_${fid}"]`);
                    if (el) el.value = v.dynamic[fid];
                });
            }
            calculateNet();
        });
    }

});

/* SECOND WEIGHMENT MODE */
function startSecondWeighment() {
	let err = document.getElementById("errorBox");
    if(err) err.style.display = "none";

    const vNo = document.getElementById("vehicle_no");
    const vSel = document.getElementById("vehicle_select");

    if (vNo) vNo.style.display = "none";
    if (vSel) {
        vSel.style.display = "inline-block";
        vSel.innerHTML = "<option value=''>SELECT VEHICLE</option>";

        VEHICLE_DATA.forEach((v, i) => {
            let o = document.createElement("option");
            o.value = i;
            o.text = v.vehicle_no;
            vSel.appendChild(o);
        });

        vSel.focus();
    }
}

/* DATE TIME */
function setDateTime(type) {
    let now = new Date();
    let d = String(now.getDate()).padStart(2,'0') + "/" +
            String(now.getMonth()+1).padStart(2,'0') + "/" +
            now.getFullYear();
    let t = String(now.getHours()).padStart(2,'0') + ":" +
            String(now.getMinutes()).padStart(2,'0') + ":00";

    const dUi = document.getElementById(type+"_date_ui");
    const tUi = document.getElementById(type+"_time_ui");
    const dVal = document.getElementById(type+"_date");
    const tVal = document.getElementById(type+"_time");

    if (dUi) dUi.value = d;
    if (tUi) tUi.value = t;
    if (dVal) dVal.value = d;
    if (tVal) tVal.value = t;
}

/* RECORD WEIGHT */
function recordSelectedWeight() {
    const liveEl = document.getElementById("live_weight");
    let live = liveEl ? liveEl.innerText.trim() : "";

	if (!live || parseFloat(live) === 0 || isNaN(parseFloat(live))) {
        alert("NO WEIGHT DETECTED");
        return;
    }

	// 🔒 FREEZE WEIGHT
    window.weightFrozen = true;
    frozenWeight = live;
	
    if (secondMode) {
        const target = document.getElementById(secondType+"_weight");
        if (target) target.value = live;
        setDateTime(secondType);
    } else {
        let gtSelect = document.querySelector("[name='gt_type']");
        let gt = gtSelect ? gtSelect.value : "G";
        let type = (gt === "G") ? "gross" : "tare";
        const target = document.getElementById(type+"_weight");
        if (target) target.value = live;
        setDateTime(type);
    }
    calculateNet();
}

/* NET CALCULATION (SUPPORTS DECIMALS LIKE 7.5) */
function calculateNet() {
    const grossEl = document.getElementById("gross_weight");
    const tareEl = document.getElementById("tare_weight");
    const netEl = document.getElementById("net_weight");

    if (!grossEl || !tareEl || !netEl) return;

    let g = parseFloat(grossEl.value);
    let t = parseFloat(tareEl.value);

    if (!isNaN(g) && !isNaN(t) && grossEl.value.trim() !== "" && tareEl.value.trim() !== "") {
        let net = Math.abs(g - t);
        netEl.value = (net % 1 !== 0) ? net.toFixed(2) : net;
    }
}

document.addEventListener("keydown", e => {
    if(e.code === "Space" && e.target.tagName !== "INPUT" && e.target.tagName !== "SELECT" && e.target.tagName !== "TEXTAREA"){
        e.preventDefault();
        recordSelectedWeight();
    }
});

function resetWeighment() {
	let err = document.getElementById("errorBox");
    if(err) err.style.display = "none";

	// 🔓 RESUME LIVE WEIGHT
    window.weightFrozen = false;
    frozenWeight = "";
	
    // reset form values
    const form = document.querySelector("form");
    if (form) form.reset();

    // reset JS state
    secondMode = false;
    secondType = null;

    // vehicle field visibility
    const vNo = document.getElementById("vehicle_no");
    const vSel = document.getElementById("vehicle_select");

    if (vNo) {
        vNo.style.display = "inline-block";
        vNo.value = "";
    }

    if (vSel) {
        vSel.style.display = "none";
        vSel.innerHTML = "";
    }

    // enable GT type
    const gt = document.querySelector("[name='gt_type']");
    if (gt) gt.disabled = false;

    // clear weights
    document.getElementById("gross_weight").value = "";
    document.getElementById("tare_weight").value = "";
    document.getElementById("net_weight").value = "";

    // clear date/time UI
    document.getElementById("gross_date_ui").value = "";
    document.getElementById("gross_time_ui").value = "";
    document.getElementById("tare_date_ui").value = "";
    document.getElementById("tare_time_ui").value = "";

    // clear hidden date/time
    document.getElementById("gross_date").value = "";
    document.getElementById("gross_time").value = "";
    document.getElementById("tare_date").value = "";
    document.getElementById("tare_time").value = "";

    if (vNo) vNo.focus();
}

function showReprintBox() {
    let box = document.getElementById("reprintBox");
    if (box) {
        box.style.display = "inline-block";
        const rSlip = document.getElementById("reprint_slip");
        if (rSlip) rSlip.focus();
    }
}

function reprintSlip() {
    let slipBox = document.getElementById("reprintBox");
    let slipInput = document.getElementById("reprint_slip");
    let slip = slipInput ? slipInput.value.trim() : "";

    if (slip === "") {
        alert("Please enter Slip Number");
        return;
    }

    if (slipInput) slipInput.value = "";
    if (slipBox) slipBox.style.display = "none";

    window.location.href = "print_slip.php?slip=" + encodeURIComponent(slip);
}

document.addEventListener("keydown", function(e) {
    if (e.key === "Enter") {
        let box = document.getElementById("reprintBox");
        if (box && box.style.display !== "none") {
            e.preventDefault();
            reprintSlip();
        }
    }
});

document.addEventListener("keydown", function(e) {
    if (e.key === "Escape") {
        let box = document.getElementById("reprintBox");
        if (box) box.style.display = "none";
    }
});

document.addEventListener("DOMContentLoaded", function () {
    const form = document.querySelector("form");
    if (form) {
        form.addEventListener("submit", function(e) {
            if (!validateFullForm()) {
                e.preventDefault();
                return;
            }

            let btn = document.getElementById("saveBtn");
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = "SAVING...";
            }
        });
    }
});
</script>
</head>

<div class="title">WEIGHMENT RECORDING SYSTEM</div>

<form method="post" action="saveform.php">
<input type="hidden" id="overwrite" name="overwrite" value="0">
<div class="container">

<div class="left">
<div class="field-group">
<label>SLIP NO</label>
<input type="text" name="slip_no" value="<?= $slip_no ?>" readonly>
</div>

<div class="field-group">
<label>GROSS / TARE</label>
<select name="gt_type">
    <option value="G">GROSS</option>
    <option value="T">TARE</option>
</select>
</div>

<div class="field-group">
<label>VEHICLE NO</label>

<input
    type="text"
    name="vehicle_no"
    id="vehicle_no"
    maxlength="10"
    style="text-transform:uppercase;"
    oninput="this.value=this.value.toUpperCase();
	        this.value=this.value.replace(/[^A-Z0-9]/g,'');
">

<select id="vehicle_select" style="display:none;width:220px;"></select>
</div>

<?php foreach($fields as $f): ?>
<div class="field-group">

<label><?= htmlspecialchars($f['field_label']) ?></label>

<?php if($f['field_type'] == 'dropdown'): ?>

    <select name="dyn_<?= $f['id'] ?>">
        <option value="">-- Select --</option>
<?php
$values = array_map('trim', explode(',', $f['field_values']));
foreach($values as $v):
    $v = strtoupper(trim($v));
    // DEFAULT SELECTION: Automatically select KG for WEIGHT UNIT
    $isSelected = (stripos($f['field_label'], 'WEIGHT UNIT') !== false && $v === 'KG') ? 'selected' : '';
?>
    <option value="<?= htmlspecialchars($v) ?>" <?= $isSelected ?>>
        <?= htmlspecialchars($v) ?>
    </option>
<?php endforeach; ?>

    </select>

<?php else: ?>

    <input
        type="<?= htmlspecialchars($f['field_type']) ?>"
        name="dyn_<?= $f['id'] ?>">

<?php endif; ?>

</div>
<?php endforeach; ?>
</div>

<div class="right">
<div class="weight-box"><span id="live_weight"></span></div>

<div class="weight-panel">

<div class="row">
    <label>GROSS</label>
    <input id="gross_weight" name="gross_weight" readonly>
    <input id="gross_date_ui" readonly>
    <input id="gross_time_ui" readonly>
    <input type="hidden" id="gross_date" name="gross_date">
    <input type="hidden" id="gross_time" name="gross_time">
</div>

<div class="row">
    <label>TARE</label>
    <input id="tare_weight" name="tare_weight" readonly>
    <input id="tare_date_ui" readonly>
    <input id="tare_time_ui" readonly>
    <input type="hidden" id="tare_date" name="tare_date">
    <input type="hidden" id="tare_time" name="tare_time">
</div>

<div class="row">
    <label>NET</label>
    <input name="net_weight" id="net_weight" readonly>
</div>

<div class="row action-row">

    <div class="left-buttons">
        <button type="button" onclick="recordSelectedWeight()">RECORD WEIGHT</button>
        <button type="button" onclick="startSecondWeighment()">SECOND WEIGHMENT</button>
    </div>

    <div class="right-buttons">
        <button type="submit" id="saveBtn" class="save-btn">SAVE</button>
        <button type="button" onclick="resetWeighment()" class="cancel-btn">CANCEL</button>
        <button type="button" onclick="showReprintBox()" class="reprint-btn">RE-PRINT</button>
    </div>

</div>

</div>

<div style="text-align:center;margin-top:15px;">
    <span id="reprintBox" style="display:none;margin-left:10px;">
        <input type="text"
               id="reprint_slip"
               placeholder="Slip No"
               style="width:120px;padding:6px;font-weight:bold;text-align:center;">

        <button type="button" onclick="reprintSlip()">
            PRINT
        </button>
    </span>
</div>

</div>
</div>

</form>
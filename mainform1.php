<?php
// if (isset($_GET['camera'])) {
//     $username = "admin";
//     $password = "GiRi_1973";
//     $url = "http://192.168.1.11/cgi-bin/snapshot.cgi?channel=1";

//     $ch = curl_init($url);
//     curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
//     curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST);
//     curl_setopt($ch, CURLOPT_USERPWD, "$username:$password");
//     curl_setopt($ch, CURLOPT_TIMEOUT, 5);

//     $img = curl_exec($ch);
//     $err = curl_error($ch);
//     curl_close($ch);

// 	if (!$img) {
// 		http_response_code(404);
// 		exit;
// 	}

//     // clear any unwanted output
//     if (ob_get_length()) ob_clean();

//     header("Content-Type: image/jpeg");
//     header("Content-Length: " . strlen($img));

//     echo $img;
//     exit;
// }

require_once "header.php";
?>
  <?php require_once "header.php"; 
  
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

    // fetch dynamic field values for this weighment
    $dyn = [];
    $dq = $conn->query("
        SELECT field_id, field_value
        FROM weighment_field_values
        WHERE weighment_id = {$r['slip_no']}
    ");
    while ($d = $dq->fetch_assoc()) {
        $dyn[$d['field_id']] = $d['field_value'];
    }

    // attach dynamic data to vehicle record
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

/* .camera-box{
    margin-top:15px;
    background:#111;
    border:3px inset #aaa;
    padding:10px;
    text-align:center;
}
.camera-box img{
    width:100%;
    max-width:420px;
    border:2px solid #666;
} */

	
</style>

<script>

function validateVehicleNumber() {
    const vehicle = document.getElementById("vehicle_no").value.trim().toUpperCase();

    const pattern = /^[A-Z]{2}[0-9]{2}[A-Z]{2}[0-9]{4}$/;

    if (!pattern.test(vehicle)) {
        alert("Vehicle Number must be in the format TN01AB1234");
        document.getElementById("vehicle_no").focus();
        return false;
    }

    return true;
}

/* FULL FORM FIELD-BY-FIELD VALIDATION */
function validateFullForm() {
    // 1. Vehicle Number validation
    const vehicleInput = document.getElementById("vehicle_no");
    if (!secondMode && vehicleInput.style.display !== "none") {
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
        if (!vehicleSelect.value) {
            alert("Please select a Vehicle for Second Weighment!");
            vehicleSelect.focus();
            return false;
        }
    }

    // 2. Validate Left Panel Fields (Movement Type, Cargo, Client Name, Weight Unit, etc.)
    const fieldGroups = document.querySelectorAll(".left .field-group");
    for (let group of fieldGroups) {
        const labelEl = group.querySelector("label");
        const labelText = labelEl ? labelEl.innerText.trim() : "Field";
        
        // Skip SLIP NO
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
    const grossVal = document.getElementById("gross_weight").value.trim();
    const tareVal = document.getElementById("tare_weight").value.trim();

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

let weightFrozen = false;
let frozenWeight = "";

document.addEventListener("DOMContentLoaded", function () {

document.getElementById("vehicle_select").addEventListener("change", function () {

    let v = VEHICLE_DATA[this.value];
    if (!v) return;

    secondMode = true;

    // basic fields
    document.querySelector("[name='slip_no']").value = v.slip_no;
    document.querySelector("[name='vehicle_no']").value = v.vehicle_no;

    // lock GT type
    let gt = document.querySelector("[name='gt_type']");
    gt.value = v.gt_type;
    gt.disabled = true;

    // clear weights first
    gross_weight.value = "";
    tare_weight.value = "";
    net_weight.value = "";

    gross_date_ui.value = "";
    gross_time_ui.value = "";
    tare_date_ui.value = "";
    tare_time_ui.value = "";

    gross_date.value = "";
    gross_time.value = "";
    tare_date.value = "";
    tare_time.value = "";

    // load FIRST weighment correctly
    if (v.gt_type === "G") {
        gross_weight.value = v.first_weight;
        gross_date_ui.value = v.first_date;
        gross_time_ui.value = v.first_time;
        gross_date.value = v.first_date;
        gross_time.value = v.first_time;
        secondType = "tare";
    } else {
        tare_weight.value = v.first_weight;
        tare_date_ui.value = v.first_date;
        tare_time_ui.value = v.first_time;
        tare_date.value = v.first_date;
        tare_time.value = v.first_time;
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

});

/* SECOND WEIGHMENT MODE */
function startSecondWeighment() {
	let err = document.getElementById("errorBox");
if(err) err.style.display="none";
    vehicle_no.style.display = "none";
    vehicle_select.style.display = "inline-block";
    vehicle_select.innerHTML = "<option value=''>SELECT VEHICLE</option>";

    VEHICLE_DATA.forEach((v, i) => {
        let o = document.createElement("option");
        o.value = i;
        o.text = v.vehicle_no;
        vehicle_select.appendChild(o);
    });

    vehicle_select.focus();
}


/* DATE TIME */
function setDateTime(type) {
    let now = new Date();
    let d = String(now.getDate()).padStart(2,'0') + "/" +
            String(now.getMonth()+1).padStart(2,'0') + "/" +
            now.getFullYear();
    let t = String(now.getHours()).padStart(2,'0') + ":" +
            String(now.getMinutes()).padStart(2,'0') + ":00";

    document.getElementById(type+"_date_ui").value = d;
    document.getElementById(type+"_time_ui").value = t;
    document.getElementById(type+"_date").value = d;
    document.getElementById(type+"_time").value = t;
}

/* RECORD */
function recordSelectedWeight() {
    let live = live_weight.innerHTML.trim();
	if (!live || live === "0") {
        alert("NO WEIGHT");
        return;
    }
	    // 🔒 FREEZE WEIGHT
    weightFrozen = true;
    frozenWeight = live;
	
    if (secondMode) {
        document.getElementById(secondType+"_weight").value = live;
        setDateTime(secondType);
    } else {
        let gt = document.querySelector("[name='gt_type']").value;
        let type = gt === "G" ? "gross" : "tare";
        document.getElementById(type+"_weight").value = live;
        setDateTime(type);
    }
    calculateNet();
}

/* NET */
function calculateNet() {
    let g = parseInt(gross_weight.value)||0;
    let t = parseInt(tare_weight.value)||0;
    if (g && t) net_weight.value = g - t;
}

document.addEventListener("keydown", e=>{
    if(e.code==="Space" && e.target.tagName!=="INPUT"){
        e.preventDefault();
        recordSelectedWeight();
    }
});

function resetWeighment() {
	let err = document.getElementById("errorBox");
if(err) err.style.display="none";
	    // 🔓 RESUME LIVE WEIGHT
    weightFrozen = false;
    frozenWeight = "";
	
    // reset form values
    document.querySelector("form").reset();

    // reset JS state
    secondMode = false;
    secondType = null;

    // vehicle field visibility
    vehicle_no.style.display = "inline-block";
    vehicle_no.value = "";

    vehicle_select.style.display = "none";
    vehicle_select.innerHTML = "";

    // enable GT type
    document.querySelector("[name='gt_type']").disabled = false;

    // clear weights
    gross_weight.value = "";
    tare_weight.value = "";
    net_weight.value = "";

    // clear date/time UI
    gross_date_ui.value = "";
    gross_time_ui.value = "";
    tare_date_ui.value = "";
    tare_time_ui.value = "";

    // clear hidden date/time
    gross_date.value = "";
    gross_time.value = "";
    tare_date.value = "";
    tare_time.value = "";

    // focus vehicle number for fresh entry
    vehicle_no.focus();
}

function showReprintBox() {
    let box = document.getElementById("reprintBox");
    box.style.display = "inline-block";
    document.getElementById("reprint_slip").focus();
}

function reprintSlip() {
    let slipBox = document.getElementById("reprintBox");
    let slipInput = document.getElementById("reprint_slip");
    let slip = slipInput.value.trim();

    if (slip === "") {
        alert("Please enter Slip Number");
        return;
    }

    // Immediately exit re-print mode
    slipInput.value = "";
    slipBox.style.display = "none";

    // Open print page
    window.location.href = "print_slip.php?slip=" + encodeURIComponent(slip);
}

// Allow Enter key to print
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

    document.querySelector("form").addEventListener("submit", function(e) {

        if (!validateFullForm()) {
            e.preventDefault();
            return;
        }

        let btn = document.getElementById("saveBtn");
        btn.disabled = true;
        btn.innerHTML = "SAVING...";
    });

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

<!-- <div class="camera-box">
    <div style="margin-bottom:8px;font-weight:bold;color:#fff;">
        LIVE CAMERA
    </div>

    <div id="camera_status"
         style="
            display:none;
            color:red;
            font-weight:bold;
            font-size:18px;
            padding:50px 0;
         ">
         CAMERA NOT CONNECTED
    </div>

    <img id="cam_live" src="mainform.php?camera=1">
</div> -->

<div class="weight-panel">

<div class="row">
    <label>GROSS</label>
    <input id="gross_weight" name="gross_weight" >
    <input id="gross_date_ui" readonly>
    <input id="gross_time_ui" readonly>
    <input type="hidden" id="gross_date" name="gross_date">
    <input type="hidden" id="gross_time" name="gross_time">
</div>

<div class="row">
    <label>TARE</label>
    <input id="tare_weight" name="tare_weight" >
    <input id="tare_date_ui" readonly>
    <input id="tare_time_ui" readonly>
    <input type="hidden" id="tare_date" name="tare_date">
    <input type="hidden" id="tare_time" name="tare_time">
</div>

<div class="row">
    <label>NET</label>
    <input name="net_weight" id="net_weight" readonly>
</div>

<div class="row">
    <button type="button" onclick="recordSelectedWeight()">RECORD WEIGHT</button>
    <button type="button" onclick="startSecondWeighment()">SECOND WEIGHMENT</button>
</div>

</div>
</div>
</div>

<div style="text-align:center">
<button type="submit" id="saveBtn">SAVE</button>
<button type="button" onclick="resetWeighment()">CANCEL</button>
</div>
<div style="text-align:center;margin-top:15px;">

    <button type="button" onclick="showReprintBox()">
        RE-PRINT
    </button>

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


</form>
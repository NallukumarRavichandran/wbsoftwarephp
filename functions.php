<?php

/* ============================================================
   COMPANY FUNCTIONS
   (Matches your simplified company table)
   id, company_name, company_address, gst_number, phone, email
   ============================================================ */

function getCompany($conn) {
    $q = $conn->query("SELECT * FROM company LIMIT 1");
    return $q->fetch_assoc();
}

function saveCompany($conn, $data) {

    $company_name    = strtoupper(trim($data['company_name'] ?? ''));
    $company_address = strtoupper(trim($data['company_address'] ?? ''));
    $gst_number      = strtoupper(trim($data['gst_number'] ?? ''));
    $phone           = trim($data['phone'] ?? '');
    $email           = trim($data['email'] ?? '');

    $stmt = $conn->prepare("
        INSERT INTO company
        (company_name, company_address, gst_number, phone, email)
        VALUES (?, ?, ?, ?, ?)
    ");

    $stmt->bind_param(
        "sssss",
        $company_name,
        $company_address,
        $gst_number,
        $phone,
        $email
    );

    return $stmt->execute();
}

function updateCompany($conn, $data, $company_id) {

    $company_name    = strtoupper(trim($data['company_name'] ?? ''));
    $company_address = strtoupper(trim($data['company_address'] ?? ''));
    $gst_number      = strtoupper(trim($data['gst_number'] ?? ''));
    $phone           = trim($data['phone'] ?? '');
    $email           = trim($data['email'] ?? '');

    $stmt = $conn->prepare("
        UPDATE company
        SET company_name = ?,
            company_address = ?,
            gst_number = ?,
            phone = ?,
            email = ?
        WHERE id = ?
    ");

    $stmt->bind_param(
        "sssssi",
        $company_name,
        $company_address,
        $gst_number,
        $phone,
        $email,
        $company_id
    );

    return $stmt->execute();
}


// Generate next slip number

function getNextSlipNumber($conn) {

    $sql = "
        SELECT MAX(slip_no) AS max_slip FROM (
            SELECT slip_no FROM weighments
            UNION ALL
            SELECT slip_no FROM sweighment
        ) t
    ";

    $res = $conn->query($sql);
    $row = $res->fetch_assoc();

    return ($row['max_slip'] ?? 0) + 1;
}



// Always show all fields (simple version)
function showField($field_name) {
    return true;
}

// Stub for indicator reading
function readWeight() {
    return 0;
}

function formatDate($db_date) {
    global $config;
    return date($config['date_format'], strtotime($db_date));
}

function formatTime($db_time) {
    global $config;
    return $db_time;
}

function takeDatabaseBackup($conn)
{
    /* Get current database name */
    $dbname = $conn->query("SELECT DATABASE()")->fetch_row()[0];

    /* Format date */
    $date = date("d-m-Y");
    $time = date("H-i-s");

    $filename = "weighbridge_backup_{$date}_{$time}.sql";

    /* Backup folder */
    $backupDir = __DIR__ . "/backups";
    if (!is_dir($backupDir)) {
        mkdir($backupDir, 0777, true);
    }

    $filepath = $backupDir . "/" . $filename;

    /* Path to mysqldump */
    $mysqldump = "C:\\xampp\\mysql\\bin\\mysqldump.exe";

    /* ---- MySQL Login ---- */
    $mysqlUser = "root";
    $mysqlPassword = "";   // keep empty if no password

    /* ---- Build command safely ---- */
    $command = "\"$mysqldump\" --user=$mysqlUser ";

    if ($mysqlPassword !== "") {
        $command .= "--password=$mysqlPassword ";
    }

    $command .= "$dbname > \"$filepath\"";

    system($command);

    return $filepath;
}

function resetWeighmentTransactions(mysqli $conn, $force = false)
{
    try {
		    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $conn->set_charset("utf8");

        /* STEP 1 — TAKE BACKUP FIRST */
        $backupFile = takeDatabaseBackup($conn);

        if ($backupFile === false) {
            throw new Exception("Backup failed. Reset cancelled.");
        }

        $conn->begin_transaction();

        /* STEP 2 — CHECK FOR PENDING WEIGHMENT (ONLY IF NOT FORCE) */
        if (!$force) {

            $check = $conn->query("
                SELECT COUNT(*) AS cnt
                FROM weighments w
                LEFT JOIN sweighment s ON w.slip_no = s.slip_no
                WHERE s.slip_no IS NULL
            ");

            $row = $check->fetch_assoc();

            if ($row['cnt'] > 0) {
                throw new Exception("Pending weighments exist. Use FORCE RESET if required.");
            }
        }
$conn->query("SET FOREIGN_KEY_CHECKS=0");
        /* STEP 3 — CLEAR TRANSACTION TABLES */
        $conn->query("DELETE FROM weighment_field_values");
        $conn->query("DELETE FROM sweighment");
        $conn->query("DELETE FROM weighments");
$conn->query("SET FOREIGN_KEY_CHECKS=1");
        /* STEP 4 — RESET AUTO INCREMENT */
        $conn->query("ALTER TABLE weighments AUTO_INCREMENT = 1");
        $conn->query("ALTER TABLE sweighment AUTO_INCREMENT = 1");
        $conn->query("ALTER TABLE weighment_field_values AUTO_INCREMENT = 1");

        $conn->commit();

        return [
            'status'  => true,
            'message' => "System reset successful. Backup created: " . basename($backupFile)
        ];

    } catch (Exception $e) {

        $conn->rollback();

        return [
            'status'  => false,
            'message' => $e->getMessage()
        ];
    }
}

function restoreDatabaseBackup(mysqli $conn, $filename)
{
    $backupDir = __DIR__ . "/backups";
    $filepath  = $backupDir . "/" . basename($filename);

    if (!file_exists($filepath)) {
        return "Backup file not found.";
    }

    /* Path to mysql.exe */
    $mysql = "C:\\xampp\\mysql\\bin\\mysql.exe";

    /* Get DB name */
    $dbname = $conn->query("SELECT DATABASE()")->fetch_row()[0];

    /* MySQL login */
    $mysqlUser = "root";
    $mysqlPassword = ""; // add if needed

    /* Build command */
    $command = "\"$mysql\" --user=$mysqlUser ";

    if ($mysqlPassword !== "") {
        $command .= "--password=$mysqlPassword ";
    }

    $command .= "$dbname < \"$filepath\"";

    /* Execute silently */
    exec($command . " 2>&1", $output, $result);

    if ($result !== 0) {
        return "Restore failed:\n" . implode("\n", $output);
    }

    return true;
}
?>
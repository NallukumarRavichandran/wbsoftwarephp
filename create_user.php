<?php
session_start();
require_once "db.php";

/* ADMIN CHECK – keep as-is */
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login_php.php");
    exit;
}

$error = "";
$success = "";
$pwd_error = "";
$pwd_success = "";
$users = [];
$res = $conn->query("SELECT id, username FROM users WHERE role = 'user'");
while ($row = $res->fetch_assoc()) {
    $users[] = $row;
}

function strongPassword($pwd) {
    return strlen($pwd) >= 8 &&
           preg_match('/[A-Z]/', $pwd) &&
           preg_match('/[a-z]/', $pwd) &&
           preg_match('/[0-9]/', $pwd) &&
           preg_match('/[^a-zA-Z0-9]/', $pwd);
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? "");
    $password = trim($_POST['password'] ?? "");

    if ($username === "" || $password === "") {
        $error = "All fields are required.";
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $conn->prepare(
            "INSERT INTO users (username, password, role)
             VALUES (?, ?, 'user')"
        );
        $stmt->bind_param("ss", $username, $hash);

        if ($stmt->execute()) {
            $success = "User created successfully.";
        } else {
            $error = "Username already exists.";
        }
    }
}
/* ---------- CHANGE PASSWORD ---------- */
if (isset($_POST['change_password'])) {

    $targetUser = (int)($_POST['target_user'] ?? $_SESSION['user_id']);
    $current    = $_POST['current_password'] ?? '';
    $new        = $_POST['new_password'] ?? '';
    $confirm    = $_POST['confirm_password'] ?? '';

    if ($new === "" || $confirm === "") {
        $pwd_error = "All password fields are required.";
    }
    elseif ($new !== $confirm) {
        $pwd_error = "New passwords do not match.";
    }
    elseif (!strongPassword($new)) {
        $pwd_error = "Password must be 8+ chars with upper, lower, number & symbol.";
    }
    else {

        /* If admin changing OWN password → verify current */
        if ($targetUser === $_SESSION['user_id']) {

            $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->bind_param("i", $_SESSION['user_id']);
            $stmt->execute();
            $stmt->bind_result($dbHash);
            $stmt->fetch();
            $stmt->close();

            if (!password_verify($current, $dbHash)) {
                $pwd_error = "Current password is incorrect.";
                goto end_pwd;
            }
        }

        /* Update password */
        $newHash = password_hash($new, PASSWORD_DEFAULT);
        $upd = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $upd->bind_param("si", $newHash, $targetUser);
        $upd->execute();
        $upd->close();

        /* Force logout if admin changed OWN password */
        if ($targetUser === $_SESSION['user_id']) {
            session_unset();
            session_destroy();
            header("Location: login_php.php?msg=pwd_changed");
            exit;
        }

        $pwd_success = "Password updated successfully.";
    }
}
end_pwd:


?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Create User</title>

<style>
* {
    box-sizing: border-box;
    font-family: "Segoe UI", Arial, sans-serif;
}

body {
    margin: 0;
    min-height: 100vh;
    background: linear-gradient(135deg, #667eea, #764ba2);
    display: flex;
    align-items: center;
    justify-content: center;
}

.card {
    width: 360px;
    background: rgba(255,255,255,0.18);
    backdrop-filter: blur(14px);
    border-radius: 18px;
    padding: 26px 22px;
    box-shadow: 0 25px 45px rgba(0,0,0,0.25);
    color: #fff;
}

h2 {
    text-align: center;
    margin-bottom: 18px;
    letter-spacing: 1px;
}

label {
    font-size: 14px;
    opacity: 0.9;
}

input {
    width: 100%;
    padding: 12px;
    margin-top: 6px;
    margin-bottom: 16px;
    border-radius: 10px;
    border: none;
    outline: none;
    font-size: 14px;
}

button {
    width: 100%;
    padding: 12px;
    border-radius: 12px;
    border: none;
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
    background: linear-gradient(135deg, #36d1dc, #5b86e5);
    color: #fff;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

button:hover {
    transform: translateY(-2px);
    box-shadow: 0 12px 25px rgba(0,0,0,0.3);
}

.msg {
    padding: 10px;
    border-radius: 8px;
    margin-bottom: 14px;
    font-size: 14px;
    text-align: center;
}

.error {
    background: rgba(239,68,68,0.25);
}

.success {
    background: rgba(34,197,94,0.25);
}

.back {
    display: block;
    text-align: center;
    margin-top: 16px;
    color: #fff;
    text-decoration: none;
    opacity: 0.9;
}

.back:hover {
    text-decoration: underline;
}
</style>
</head>

<body>

<div class="card">
    <h2>👤 Create User</h2>

    <?php if ($error): ?>
        <div class="msg error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="msg success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <form method="POST">
        <label>Username</label>
        <input type="text" name="username" required>

        <label>Password</label>
        <input type="password" name="password" required>

        <button type="submit">Create User</button>
    </form>

    <a class="back" href="admin_page.php">⬅ Back to Admin</a>
	<hr style="margin:22px 0; border:none; border-top:1px solid rgba(255,255,255,0.3);">

<h2>🔐 Change Password</h2>

<?php if ($pwd_error): ?>
    <div class="msg error"><?= htmlspecialchars($pwd_error) ?></div>
<?php endif; ?>

<?php if ($pwd_success): ?>
    <div class="msg success"><?= htmlspecialchars($pwd_success) ?></div>
<?php endif; ?>

<form method="POST">

    <label>Select User</label>
    <select name="target_user" style="width:100%;padding:12px;border-radius:10px;">
        <option value="<?= $_SESSION['user_id'] ?>">
            -- My Password --
        </option>
        <?php foreach ($users as $u): ?>
            <option value="<?= $u['id'] ?>">
                <?= htmlspecialchars($u['username']) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <label>Current Password (only for your own)</label>
    <input type="password" name="current_password">

    <label>New Password</label>
    <input type="password" name="new_password" required>

    <label>Confirm New Password</label>
    <input type="password" name="confirm_password" required>

    <button type="submit" name="change_password">
        Update Password
    </button>
</form>


</div>

</body>
</html>

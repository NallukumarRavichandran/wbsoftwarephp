<?php
session_start();
require_once "db.php";

$error = "";
$success = "";

/* CHECK IF ANY USER EXISTS */
$userCount = 0;
$res = $conn->query("SELECT COUNT(*) AS cnt FROM users");
if ($res) {
    $row = $res->fetch_assoc();
    $userCount = (int)$row['cnt'];
}

/* ===== CREATE FIRST ADMIN ===== */
if ($userCount === 0 && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_admin'])) {

    $username = trim($_POST['username'] ?? "");
    $password = trim($_POST['password'] ?? "");
    $confirm  = trim($_POST['confirm_password'] ?? "");

    if ($username === "" || $password === "" || $confirm === "") {
        $error = "All fields are required.";
    } elseif ($password !== $confirm) {
        $error = "Passwords do not match.";
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);

        // 🔒 FORCE FIRST USER AS ADMIN
        $stmt = $conn->prepare(
            "INSERT INTO users (username, password, role) VALUES (?, ?, 'admin')"
        );
        $stmt->bind_param("ss", $username, $hash);

        if ($stmt->execute()) {
            $success = "Admin user created successfully. Please login.";
        } else {
            $error = "Failed to create admin user.";
        }
    }
}

/* ===== NORMAL LOGIN ===== */
if ($userCount > 0 && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {

    $username = trim($_POST['username'] ?? "");
    $password = trim($_POST['password'] ?? "");

    if ($username === "" || $password === "") {
        $error = "Enter both username and password.";
    } else {
        $stmt = $conn->prepare(
            "SELECT id, username, password, role FROM users WHERE username=? LIMIT 1"
        );
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($res && $res->num_rows === 1) {
            $user = $res->fetch_assoc();

            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id']  = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role']     = $user['role'];

                if ($user['role'] === 'admin') {
                    header("Location: admin_page.php");
                } else {
                    header("Location: mainform.php");
                }
                exit;
            } else {
                $error = "Invalid credentials.";
            }
        } else {
            $error = "Invalid credentials.";
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title><?php echo ($userCount === 0) ? "Create Admin" : "Login"; ?></title>

<style>
body{
    margin:0;
    font-family:Arial;
    height:100vh;

    /* softer gradient */
    background: linear-gradient(135deg, #fbc2eb, #a6c1ee);

    display:flex;
    justify-content:center;
    align-items:center;
}

.login-box{
    background: linear-gradient(
        135deg,
        rgba(214,20,142,0.2),
        rgba(255,255,255,0.1)
    );
    backdrop-filter: blur(12px);

    padding:30px;
    border-radius:18px;

    width:300px;
    text-align:center;

    box-shadow:0 10px 25px rgba(0,0,0,0.25);
}
.logo{
    width:85px;
    margin-bottom:10px;
    border-radius:50%;
}

h2{
    color:#333;
    margin-bottom:20px;
}

/* INPUT */
 input{
    width:80%;
    padding:12px 14px;
    margin:12px auto;

    display:block;

    border:none;
    border-radius:12px;

    background: rgba(255,255,255,0.9);

    font-size:20px;   /* 👈 THIS ONLY ADDED */

    outline:none;
}


/* FOCUS */
input:focus{
    border-color:#d4148e;
    box-shadow:0 0 6px rgba(212,20,142,0.4);
}

/* BUTTON */
button{
    width:75%;
    padding:10px;

    border:none;
    border-radius:10px;

    background: linear-gradient(135deg, #d4148e, #ff6ec4);
    color:white;
    font-weight:bold;
    cursor:pointer;

    transition:0.3s;
}

/* HOVER */
button:hover{
    transform: translateY(-2px);
    box-shadow:0 6px 15px rgba(212,20,142,0.4);
}

.error{
    color:#cc0033;
}

.success{
    color:#2e7d32;
}
</style>
</head>

<body>
<div class="login-box">

<img src="gblogo.jpeg" class="logo">

<h3 style="color:#444; margin:5px 0;">
    Giri Brothers
</h3>

<h2><?php echo ($userCount === 0) ? "Create Admin" : "Login"; ?></h2>
<?php if ($userCount === 0): ?>

    <?php if ($error): ?>
       <p class="error"><?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>

    <?php if ($success): ?>
        <p class="success"><?php echo htmlspecialchars($success); ?></p>
        <a href="login_page.php">Go to Login</a>
    <?php else: ?>
        <form method="POST">
            <input type="text" name="username" placeholder="Admin Username" required><br><br>
            <input type="password" name="password" placeholder="Password" required><br><br>
            <input type="password" name="confirm_password" placeholder="Confirm Password" required><br><br>
            <button type="submit" name="create_admin">Create Admin</button>
        </form>
    <?php endif; ?>

<?php else: ?>

    <?php if ($error): ?>
       <p class="error"><?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>

    <form method="POST">
        <input type="text" name="username" placeholder="Username" required>
        <input type="password" name="password" placeholder="Password" required><br><br>
        <button type="submit" name="login">Login</button>
    </form>

<?php endif; ?>

</div>
</body>
</html>

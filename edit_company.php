<?php
session_start();
require_once "db.php";
require_once "functions.php";
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: admin_page.php");
    exit;
}


$company = getCompany($conn);

if (!$company) {
    die("No company found. <a href='create_company.php'>Create one</a>.");
}

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (updateCompany($conn, $_POST, $company['id'])) {
        echo "<script>alert('Company updated!'); window.location='edit_company.php';</script>";
        exit;
    } else {
        $error = "Failed to update!";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<title>Edit Company</title>
<style>
    body {
        font-family: Arial, sans-serif;
        background: linear-gradient(135deg, #89f7fe, #66a6ff);
        margin: 0;
        padding: 0;
        height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .container {
        background: white;
        width: 380px;
        padding: 25px;
        border-radius: 20px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        text-align: center;
        animation: fadeIn 0.5s ease;
    }

    h2 {
        margin-bottom: 20px;
        color: #333;
        text-transform: uppercase;
    }

    label {
        font-weight: bold;
        float: left;
        margin-bottom: 5px;
        color: #444;
    }

    input, textarea {
        width: 100%;
        padding: 12px 15px;
        margin-bottom: 15px;
        border: 1px solid #ccc;
        border-radius: 30px;
        font-size: 14px;
        text-transform: uppercase;
        outline: none;
        transition: 0.2s;
        box-sizing: border-box;
    }

    input:focus, textarea:focus {
        border-color: #66a6ff;
        box-shadow: 0 0 6px rgba(102, 166, 255, 0.5);
    }

    button {
        background: linear-gradient(135deg, #ff6f91, #845ec2);
        border: none;
        width: 100%;
        padding: 12px;
        color: white;
        font-size: 16px;
        border-radius: 30px;
        cursor: pointer;
        transition: 0.3s;
        font-weight: bold;
        letter-spacing: 1px;
        text-transform: uppercase;
    }

    button:hover {
        background: linear-gradient(135deg, #e9587d, #6a45b2);
    }

    .error {
        color: red;
        margin-bottom: 10px;
    }

    @keyframes fadeIn {
        from {opacity: 0; transform: translateY(20px);}
        to   {opacity: 1; transform: translateY(0);}
    }
</style>
</head>

<body>

<div class="container">
    <h2>Edit Company</h2>

    <?php if (!empty($error)) echo "<p class='error'>$error</p>"; ?>

    <form method="POST">

        <label>Company Name:</label>
        <input type="text" name="company_name" value="<?= $company['company_name'] ?>" required>

        <label>Address:</label>
        <textarea name="company_address" rows="3"><?= $company['company_address'] ?></textarea>

        <label>GST Number:</label>
        <input type="text" name="gst_number" value="<?= $company['gst_number'] ?>">

        <label>Phone:</label>
        <input type="text" name="phone" value="<?= $company['phone'] ?>">

        <label>Email:</label>
        <input type="text" name="email" 
               value="<?= $company['email'] ?>"
               placeholder="Enter email or '-'"
               pattern="^[-]|[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$">

        <button type="submit">Update Company</button>
    </form>
	<a href="admin_page.php" 
   style="
       display:inline-block;
       padding:8px 16px;
       background:#444;
       color:#fff;
       text-decoration:none;
       border-radius:6px;
       font-weight:bold;
       margin-top:15px;
   ">
   ⬅ BACK
</a>
</div>

</body>
</html>

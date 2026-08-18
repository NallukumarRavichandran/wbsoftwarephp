<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: mainform.php");
    exit;
}

require_once "db.php";
require_once "functions.php";

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (saveCompany($conn, $_POST)) {
		$company_id = $conn->insert_id; // get the newly created company ID
echo "<script>
    alert('Company created successfully!');
    window.location='setup_dynamic_fields.php?company_id=$company_id';
</script>";
exit;
    } else {
        $error = "Failed to create company!";
    }
}

?>
<!DOCTYPE html>
<html>
<head>
<title>Create Company</title>
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
        background: linear-gradient(135deg, #2ec1ac, #b5179e);
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
        background: linear-gradient(135deg, #1ba890, #8f0d7e);
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
    <h2>Create Company</h2>

    <?php if (!empty($error)) echo "<p class='error'>$error</p>"; ?>

    <form method="POST">

        <label>Company Name:</label>
        <input type="text" name="company_name" required>

        <label>Address:</label>
        <textarea name="company_address" rows="3"></textarea>

        <label>GST Number:</label>
        <input type="text" name="gst_number">

        <label>Phone:</label>
        <input type="text" name="phone">

        <label>Email:</label>
        <input type="text" name="email" 
        placeholder="Enter email or '-' if not known"
        pattern="^[-]|[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$">

        <button type="submit">Save Company</button>
    </form>
</div>

</body>
</html>

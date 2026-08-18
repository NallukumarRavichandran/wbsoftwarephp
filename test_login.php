<?php
require_once "apacs_login.php";

$result = getApacsToken();

if ($result['status']) {

    echo "<h2 style='color:green;font-weight:bold;'>✅ APACS LOGIN SUCCESSFUL</h2>";

    echo "<p><b>HTTP Code :</b> ".$result['http_code']."</p>";
    echo "<p><b>Message :</b> ".$result['response']['message']."</p>";
    echo "<p><b>Login ID :</b> ".$result['response']['data']['loginId']."</p>";
    echo "<p><b>Weigh Bridge :</b> ".$result['response']['data']['weighBridgeName']."</p>";

    echo "<hr>";

    echo "<details>";
    echo "<summary><b>View Complete Response</b></summary>";
    echo "<pre>";
    print_r($result);
    echo "</pre>";
    echo "</details>";

} else {

    echo "<h2 style='color:red;font-weight:bold;'>❌ APACS LOGIN FAILED</h2>";

    echo "<pre>";
    print_r($result);
    echo "</pre>";
}
?>
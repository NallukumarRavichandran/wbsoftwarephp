<?php

require_once "apacs_login.php";

$result = getApacsToken();

$payload = decodeJwtPayload($result['response']['token']);

echo "<pre>";
print_r($payload);
echo "</pre>";
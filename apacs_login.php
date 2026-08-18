<?php
require_once "db.php";

$config = require 'config.php';

function getApacsToken()
{
	    global $config;
		$url = $config['apacs']['login_url'];
    $data = [
        "loginId" => $config['apacs']['login_id'],
        "password" => $config['apacs']['password']
    ];
    $ch = curl_init($url);

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json",
        "Accept: application/json"
    ]);
	
	curl_setopt($ch, CURLOPT_USERAGENT, "curl/8.0");

    // Same behaviour as curl -k
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        return [
            "status" => false,
            "error" => curl_error($ch)
        ];
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);
	if ($httpCode == 200) {
		$token = json_decode($response, true)['token'];
		$payload = decodeJwtPayload($token);
		$expiry = date("Y-m-d H:i:s", $payload['exp']);
		saveApacsToken($token, $expiry);
	}
    return [
        "status" => ($httpCode == 200),
        "http_code" => $httpCode,
        "response" => json_decode($response, true),
        "raw" => $response
    ];
}
function decodeJwtPayload($jwt)
{
    $parts = explode('.', $jwt);

    if (count($parts) != 3) {
        return false;
    }

    $payload = $parts[1];

    $payload = str_replace(['-', '_'], ['+', '/'], $payload);

    while (strlen($payload) % 4) {
        $payload .= '=';
    }

    return json_decode(base64_decode($payload), true);
}
function saveApacsToken($token, $expiry)
{
    global $conn;

    $sql = "INSERT INTO apacs_token
            (id, token, expiry, updated_on)
            VALUES
            (1, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
                token = VALUES(token),
                expiry = VALUES(expiry),
                updated_on = NOW()";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $token, $expiry);
    $stmt->execute();
    $stmt->close();
}
function getValidApacsToken()
{
    global $conn;

    $sql = "SELECT token, expiry
            FROM apacs_token
            WHERE id = 1";

    $result = $conn->query($sql);

    if ($result && $row = $result->fetch_assoc()) {

        if (strtotime($row['expiry']) > time()) {
            return $row['token'];

        }

    }

    // Token expired or doesn't exist
    $login = getApacsToken();

    if ($login['status']) {

        return $login['response']['token'];

    }

    return false;
}

function uploadToApacs($slip_no, $request_json)
{
    global $conn;

    $token = getValidApacsToken();

    if (!$token) {
        return false;
    }

    $config = require "config.php";

    $url = $config['apacs']['post_url'];

    $ch = curl_init($url);

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $request_json);

    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer ".$token,
        "Content-Type: application/json",
        "Accept: application/json"
    ]);

    curl_setopt($ch, CURLOPT_USERAGENT, "curl/8.0");

    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    $response = curl_exec($ch);

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if (curl_errno($ch)) {
        $response = curl_error($ch);
    }

    curl_close($ch);

    $responseObj = json_decode($response, true);

    $status = "FAILED";
    $apacs_id = null;

    if (
        $httpCode == 201 &&
        isset($responseObj['success']) &&
        $responseObj['success'] === true
    ) {
        $status = "SUCCESS";
        $apacs_id = $responseObj['data']['id'] ?? null;
    }

    $stmt = $conn->prepare("
        UPDATE apacs_upload_log
        SET
            status = ?,
            response_data = ?,
            uploaded_on = NOW(),
            apacs_id = ?
        WHERE slip_no = ?
    ");

    $stmt->bind_param(
        "ssis",
        $status,
        $response,
        $apacs_id,
        $slip_no
    );

    $stmt->execute();

    if ($status == "FAILED") {

        $stmt = $conn->prepare("
            UPDATE apacs_upload_log
            SET retry_count = retry_count + 1
            WHERE slip_no = ?
        ");

        $stmt->bind_param("s", $slip_no);
        $stmt->execute();
    }

    return ($status == "SUCCESS");
}
?>
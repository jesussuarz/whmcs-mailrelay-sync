<?php

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

function mailrelay_api_request($method, $endpoint, $args = [], $params)
{
    $url = 'https://' . $params['Mailrelay Host'] . '.ipzmarketing.com/api/v1/' . $endpoint;

    $headers = [
        'x-auth-token: ' . $params['Mailrelay API Key'],
        'x-request-origin: WHMCS|MailrelayModule|1.0'
    ];

    if (!empty($args)) {
        $headers[] = 'Content-Type: application/json';
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    if (!empty($args)) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($args));
    }

    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if (curl_errno($ch)) {
        throw new Exception(curl_error($ch));
    }

    curl_close($ch);

    return [
        'code' => $httpCode,
        'body' => json_decode($result, true),
    ];
}

?>

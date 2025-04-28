<?php

if (!isset($_GET['key']) || $_GET['key'] !== 'your_key') {
    http_response_code(403);
    die('Access denied');
}

require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../includes/modulefunctions.php';

use WHMCS\Database\Capsule;

// Función de request a Mailrelay
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
    return ['code' => $httpCode, 'body' => json_decode($result, true)];
}

// Obtener configuración del addon
$addonConfig = Capsule::table('tbladdonmodules')->where('module', 'mailrelay')->pluck('value', 'setting')->toArray();

$apiParams = [
    'Mailrelay Host' => $addonConfig['Mailrelay Host'],
    'Mailrelay API Key' => $addonConfig['Mailrelay API Key']
];
$groupIds = array_filter(array_map('trim', explode(',', $addonConfig['Groups to Sync'])));

$limit = 250;

// Leer offset actual
$offsetRow = Capsule::table('mod_mailrelay_sync')->first();
$offset = $offsetRow ? $offsetRow->last_offset : 0;

// Obtener clientes
$clientsResponse = localAPI('GetClients', ['limitnum' => $limit, 'limitstart' => $offset]);

if (isset($clientsResponse['clients']['client']) && !empty($clientsResponse['clients']['client'])) {
    foreach ($clientsResponse['clients']['client'] as $client) {
        sleep(1); // Control rate limit
        $data = [
            'email' => $client['email'],
            'name' => $client['firstname'] . ' ' . $client['lastname'],
            'group_ids' => $groupIds,
            'status' => 'active',
            'restore_if_deleted' => true
        ];
        $response = mailrelay_api_request('POST', 'subscribers/sync', $data, $apiParams);
        echo $client['email'] . ': ' . $response['code'] . PHP_EOL;
    }

    // Actualizar offset y fecha
    $newOffset = $offset + $limit;
    if (count($clientsResponse['clients']['client']) < $limit) {
        $newOffset = 0; // Reset si ya terminó
    }

    if ($offsetRow) {
        Capsule::table('mod_mailrelay_sync')->update(['last_offset' => $newOffset, 'updated_at' => date('Y-m-d H:i:s')]);
    } else {
        Capsule::table('mod_mailrelay_sync')->insert(['last_offset' => $newOffset, 'updated_at' => date('Y-m-d H:i:s')]);
    }
} else {
    echo "No clients to sync." . PHP_EOL;
}

?>


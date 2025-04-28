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
require_once __DIR__ . '/functions.php';

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


<?php

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use WHMCS\Database\Capsule;

// Incluir la lógica principal del módulo (donde está mailrelay_api_request)
require_once __DIR__ . '/functions.php';

// Hook para sincronización automática al crear un cliente
add_hook('ClientAdd', 1, function($vars) {
    mailrelay_sync_single_client($vars['userid']);
});

// Hook para sincronización automática al editar un cliente
add_hook('ClientEdit', 1, function($vars) {
    mailrelay_sync_single_client($vars['userid']);
});

// Función para sincronizar un solo cliente
function mailrelay_sync_single_client($userId)
{
    $addonConfig = Capsule::table('tbladdonmodules')->where('module', 'mailrelay')->pluck('value', 'setting')->toArray();

    // Verificar si Auto Sync está activado
    if (isset($addonConfig['Auto Sync Clients']) && $addonConfig['Auto Sync Clients'] != 'on') {
        return;
    }

    $apiParams = [
        'Mailrelay Host' => $addonConfig['Mailrelay Host'] ?? '',
        'Mailrelay API Key' => $addonConfig['Mailrelay API Key'] ?? ''
    ];

    $groupIds = array_filter(array_map('trim', explode(',', $addonConfig['Groups to Sync'] ?? '')));

    // Obtener detalles del cliente
    $clientData = localAPI('GetClientsDetails', ['clientid' => $userId]);
    if (isset($clientData['email'])) {
        $data = [
            'email' => $clientData['email'],
            'name' => $clientData['firstname'] . ' ' . $clientData['lastname'],
            'group_ids' => $groupIds,
            'status' => 'active',
            'restore_if_deleted' => true
        ];

        mailrelay_api_request('POST', 'subscribers/sync', $data, $apiParams);
    }
}

?>



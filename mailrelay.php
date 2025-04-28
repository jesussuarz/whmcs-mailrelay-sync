<?php

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use Illuminate\Database\Capsule\Manager as Capsule;

function mailrelay_config()
{
    return [
        'name' => 'Mailrelay Sync',
        'description' => 'Synchronize WHMCS clients with Mailrelay groups.',
        'author' => 'soporteserver.com',
        'language' => 'english',
        'version' => '1.0',
        'fields' => [
            'Mailrelay Host' => [
                'FriendlyName' => 'Mailrelay Host',
                'Type' => 'text',
                'Size' => '50',
                'Description' => 'Your Mailrelay account hostname (without .ipzmarketing.com)',
            ],
            'Mailrelay API Key' => [
                'FriendlyName' => 'Mailrelay API Key',
                'Type' => 'password',
                'Size' => '50',
                'Description' => 'Your Mailrelay API Key',
            ],
            'Groups to Sync' => [
                'FriendlyName' => 'Groups to Sync',
                'Type' => 'text',
                'Size' => '50',
                'Description' => 'Comma-separated list of Mailrelay group IDs',
            ],
        ]
    ];
}

function mailrelay_activate()
{
    try {
        if (!Capsule::schema()->hasTable('mod_mailrelay_sync')) {
            Capsule::schema()->create('mod_mailrelay_sync', function ($table) {
                $table->increments('id');
                $table->integer('last_offset')->default(0);
                $table->timestamps();
            });
        }
        return ['status' => 'success', 'description' => 'Mailrelay Sync module activated.'];
    } catch (Exception $e) {
        return ['status' => 'error', 'description' => $e->getMessage()];
    }
}

function mailrelay_deactivate()
{
    try {
        Capsule::schema()->dropIfExists('mod_mailrelay_sync');
        return ['status' => 'success', 'description' => 'Mailrelay Sync module deactivated and table removed.'];
    } catch (Exception $e) {
        return ['status' => 'error', 'description' => $e->getMessage()];
    }
}
require_once __DIR__ . '/functions.php';

function mailrelay_output($vars)
{
    $offsetRow = Capsule::table('mod_mailrelay_sync')->first();
    $currentOffset = $offsetRow ? $offsetRow->last_offset : 0;
    $lastSyncTime = $offsetRow ? $offsetRow->updated_at : 'Never';
    $modulelink = $vars['modulelink'];
    $version = $vars['version'];
    $limit = 250;

    // Obtener cantidad total de clientes
    $totalClientsResponse = localAPI('GetClients', ['limitnum' => 1]);
    $totalClients = $totalClientsResponse['totalresults'] ?? 0;

    echo "<img src='../modules/addons/mailrelay/mailrelay.png' style='max-width:150px;'> ";
    echo "<h2>Mailrelay Sync Module (v{$version})</h2>";
    echo "<p>This module synchronizes WHMCS clients with Mailrelay groups.</p>";
    echo "<p id='syncInfo'><strong>Last Saved Offset (from DB):</strong> {$currentOffset} / {$totalClients}<br>";
    echo "<strong>Last Sync Time:</strong> {$lastSyncTime}</p>";
    echo "<div class='alert alert-info'>
        <strong>How Synchronization Works:</strong><br>
        Each synchronization run processes a maximum of <strong>{$limit} clients per run</strong> due to hosting limitations.<br><br>
        The system saves an <strong>offset</strong> (last client processed) to ensure the next sync resumes from where it left off.<br>
        The offset <strong>resets to 0</strong> automatically when all clients have been synchronized.<br><br>
        <strong>Automated Sync:</strong> To automate synchronization every 24 hours, configure this cron job:<br>
        <code>php /path/to/whmcs/modules/addons/mailrelay/cron_sync.php?key=your_key</code><br>
        Replace <code>/path/to/whmcs</code> with your actual WHMCS installation path.
    </div>";
    echo "<p><a href='{$modulelink}&action=sync' class='btn btn-primary' id='syncButton'>Manual Sync Now</a></p>";
    echo "Developed by <a href='https://soporteserver.com' target='_blank'>Soporte Server</a>";

    // Overlay + JS
    echo "<style>#loadingOverlay {position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:9999;display:none;}
    #loadingOverlay div {position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);color:white;font-size:20px;}
    </style><div id='loadingOverlay'><div>Loading... Please wait.</div></div>";
    
    echo "<script>
        document.getElementById('syncButton').addEventListener('click', function(event) {
            document.getElementById('loadingOverlay').style.display = 'block';
        });
    </script>";

    // Proceso de sincronización
    if (isset($_GET['action']) && $_GET['action'] == 'sync') {
        $results = mailrelay_manual_sync($vars);
        $newOffsetRow = Capsule::table('mod_mailrelay_sync')->first();
        $newOffset = $newOffsetRow ? $newOffsetRow->last_offset : 0;
        $newSyncTime = $newOffsetRow ? $newOffsetRow->updated_at : 'Never';

        echo "<div class='alert alert-info'><p><strong>Total Synced This Run:</strong> " . count($results) . " clients</p><ul>";
        foreach ($results as $result) {
            $client = $result['client'];
            $response = $result['response'];
            $status = ($response['code'] == 200 || $response['code'] == 201) ? 'Synced' : 'Error - ' . $response['code'] . ' - ' . json_encode($response['body']['errors'] ?? 'Unknown error');
            echo "<li><strong>{$client}</strong>: {$status}</li>";
        }
        echo "</ul></div>";

        echo "<script>
            document.getElementById('syncInfo').innerHTML = '<strong>Last Saved Offset (from DB):</strong> {$newOffset} / {$totalClients}<br><strong>Last Sync Time:</strong> {$newSyncTime}';
            document.getElementById('loadingOverlay').style.display = 'none';
        </script>";
    }
}

function mailrelay_manual_sync($vars)
{
    $apiParams = [
        'Mailrelay Host' => $vars['Mailrelay Host'],
        'Mailrelay API Key' => $vars['Mailrelay API Key']
    ];
    $groupIds = array_filter(array_map('trim', explode(',', $vars['Groups to Sync'])));
    $synced = [];
    $limit = 250;

    $offsetRow = Capsule::table('mod_mailrelay_sync')->first();
    $offset = $offsetRow ? $offsetRow->last_offset : 0;

    $clientsResponse = localAPI('GetClients', ['limitnum' => $limit, 'limitstart' => $offset]);
    if (isset($clientsResponse['clients']['client']) && !empty($clientsResponse['clients']['client'])) {
        foreach ($clientsResponse['clients']['client'] as $client) {
            sleep(0);  // Control de rate limit
            $data = [
                'email' => $client['email'],
                'name' => $client['firstname'] . ' ' . $client['lastname'],
                'group_ids' => $groupIds,
                'status' => 'active',
                'restore_if_deleted' => true
            ];
            $response = mailrelay_api_request('POST', 'subscribers/sync', $data, $apiParams);
            $synced[] = ['client' => $client['email'], 'response' => $response];
        }

        $newOffset = $offset + $limit;
        if (count($clientsResponse['clients']['client']) < $limit) {
            $newOffset = 0;  // Reset
        }

        if ($offsetRow) {
            Capsule::table('mod_mailrelay_sync')->update(['last_offset' => $newOffset, 'updated_at' => date('Y-m-d H:i:s')]);
        } else {
            Capsule::table('mod_mailrelay_sync')->insert(['last_offset' => $newOffset, 'updated_at' => date('Y-m-d H:i:s')]);
        }
    }

    return $synced;
}

?>


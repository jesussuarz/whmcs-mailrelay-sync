# WHMCS Mailrelay Sync Module

This WHMCS addon synchronizes your WHMCS client list with your Mailrelay account. It supports **manual synchronization**, **automatic synchronization via cron**, and **auto-sync when adding or editing clients**.

## Features

- Sync WHMCS clients with specific Mailrelay groups.
- Synchronize in **batches of 250 clients** (adjustable).
- Avoids API rate limits with automatic delays.
- Tracks synchronization progress using an **offset** saved in the database.
- Resumes sync from the last offset (avoiding reprocessing).
- Optionally sync new or updated clients automatically via WHMCS hooks.

---

## Installation

1. Upload the module to your WHMCS addons directory: /modules/addons/mailrelay/
2. Activate the addon in **WHMCS Admin > System Settings > Addon Modules**.
3. Configure:

- **Mailrelay Host**: Your Mailrelay subdomain (without `.ipzmarketing.com`).
- **Mailrelay API Key**: Your Mailrelay API key.
- **Groups to Sync**: Comma-separated list of Mailrelay group IDs.
- **Auto Sync Clients**: Enable or disable auto-sync on client creation or updates.

---

## Manual Synchronization

Go to **WHMCS Admin > Addon Modules > Mailrelay Sync**, and click **Manual Sync Now**.

- The module syncs up to **250 clients per run** (limit configurable).
- The **offset** tracks the last synchronized client to continue from there.
- The offset resets to **0** once all clients are synchronized.

---

## Cron Synchronization
You can configure a **cron job** to run the sync automatically every 24 hours.

### Example Cron Command:

```bash
curl -s your_whmcs/modules/addons/mailrelay/cron_sync.php?key=YOUR_KEY
```

## Replace:
* /opt/plesk/php/8.1/bin/php: Path to your PHP binary (adjust for your environment).
* YOUR_WHMCS: Your WHMCS installation.
* YOUR_KEY: Replace with your configured GET key.

## Secure Access:
* The cron script requires a GET parameter (key) to prevent unauthorized execution. Set your preferred key in the cron command (key=YOUR_KEY).
* Recommended schedule: Run once per day (every 24 hours).

## Offset Mechanism
* The offset keeps track of the last batch of clients synchronized.
* The sync resumes from the last offset to avoid duplicate syncing.
* When all clients are synced, the offset resets to 0.

This ensures that each run only processes new or unsynced clients.

## License
MIT License.

## Developed by
Jesus Suarez - Soporte Server
https://soporteserver.com





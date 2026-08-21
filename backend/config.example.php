<?php
return [
    // Optional: only required if you want to call notify.php over HTTP.
    // Direct integration.php does not need a webhook secret.
    'webhook_secret' => getenv('HARDWIRE_WEBHOOK_SECRET') ?: '',

    // Optional when the Firebase private JSON is placed in this same folder.
    // bootstrap.php auto-detects valid Service Account JSON files.
    'service_account_file' => getenv('FIREBASE_SERVICE_ACCOUNT') ?: '',

    // Must match HardwireApplication.PUSH_TOPIC in the Android app.
    'topic' => getenv('HARDWIRE_FCM_TOPIC') ?: 'hardwire-events',
];

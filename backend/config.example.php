<?php
return [
    'webhook_secret' => getenv('HARDWIRE_WEBHOOK_SECRET') ?: 'CHANGE_ME',
    'service_account_file' => getenv('FIREBASE_SERVICE_ACCOUNT') ?: __DIR__ . '/firebase-service-account.json',
    'topic' => getenv('HARDWIRE_FCM_TOPIC') ?: 'hardwire-events',
];

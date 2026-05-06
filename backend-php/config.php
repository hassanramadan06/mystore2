<?php
// Copy this file to `config.php` and fill in your real values.
// On cPanel, get DB credentials from "MySQL® Databases" in cPanel.
return [
    // ---- Database ----
    'db' => [
        'host'    => 'localhost',
        'port'    => 3307,
        'name'    => 'mystore_php',  // e.g. mystore2_mystore
        'user'    => 'root',   // e.g. mystore2_dbuser
        'pass'    => '',
        'charset' => 'utf8mb4',
    ],

    // ---- JWT ----
    // CHANGE THIS to a long random string (at least 32 chars).
    // Run:  php -r "echo bin2hex(random_bytes(32)).PHP_EOL;"
    'jwt' => [
        'secret'      => 'CHANGE_ME_TO_A_LONG_RANDOM_STRING',
        'issuer'      => 'mystore',
        'audience'    => 'mystore-frontend',
        'ttl_minutes' => 720,  // 12 hours
    ],

    // ---- CORS ----
    // List the origins allowed to call the API.
    // For production, set this to your real frontend domain (e.g. https://mystore2.com).
    // Add 'http://127.0.0.1:5500' if you also use Live Server during development.
    'cors_origins' => [
        'http://127.0.0.1:5500',
        'http://localhost:5500',
        'https://mystore2.com',
        'https://www.mystore2.com',
    ],

    // ---- Stub payment ----
    // Returned in the checkout response just like a real Stripe PaymentIntent.
    'payment' => [
        'publishable_key' => 'pk_stub_local',
    ],
];

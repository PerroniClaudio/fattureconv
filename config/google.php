<?php

// Config helper to assemble Google Cloud credentials from environment variables.
// This returns an array with 'key_file' (array) and other optional defaults.

return [
    // Build the key_file array expected by the Google client libraries / spatie adapter.
    'key_file' => [
        'type' => env('GOOGLE_CLOUD_KEY_FILE_TYPE', 'service_account'),
        'project_id' => env('GOOGLE_CLOUD_PROJECT_ID'),
        'private_key_id' => env('GOOGLE_CLOUD_PRIVATE_KEY_ID'),
        'private_key' => env('GOOGLE_CLOUD_PRIVATE_KEY'),
        'client_email' => env('GOOGLE_CLOUD_CLIENT_EMAIL'),
        'client_id' => env('GOOGLE_CLOUD_CLIENT_ID'),
        'auth_uri' => env('GOOGLE_CLOUD_AUTH_URI', 'https://accounts.google.com/o/oauth2/auth'),
        'token_uri' => env('GOOGLE_CLOUD_TOKEN_URI', 'https://oauth2.googleapis.com/token'),
        'auth_provider_x509_cert_url' => env('GOOGLE_CLOUD_AUTH_PROVIDER_X509_CERT_URL', 'https://www.googleapis.com/oauth2/v1/certs'),
        'client_x509_cert_url' => env('GOOGLE_CLOUD_CLIENT_X509_CERT_URL'),
        'universe_domain' => env('GOOGLE_CLOUD_UNIVERSE_DOMAIN', 'googleapis.com'),
    ],
];

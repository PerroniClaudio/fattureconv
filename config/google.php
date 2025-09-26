<?php

// Config helper to assemble Google Cloud credentials from environment variables.
// This returns an array with 'key_file' (array) and other optional defaults.

return [
    // Build the key_file array expected by the Google client libraries / spatie adapter.
    'key_file' => function () {
        // The private key in env may contain literal '\n' sequences; convert them to real newlines.
        $privateKey = env('GOOGLE_CLOUD_PRIVATE_KEY');
        if ($privateKey && strpos($privateKey, '\\n') !== false) {
            $privateKey = str_replace('\\n', "\n", $privateKey);
        }

        $keyFile = [
            'type' => env('GOOGLE_CLOUD_KEY_FILE_TYPE', 'service_account'),
            'project_id' => env('GOOGLE_CLOUD_PROJECT_ID'),
            'private_key_id' => env('GOOGLE_CLOUD_PRIVATE_KEY_ID'),
            'private_key' => $privateKey,
            'client_email' => env('GOOGLE_CLOUD_CLIENT_EMAIL'),
            'client_id' => env('GOOGLE_CLOUD_CLIENT_ID'),
            'auth_uri' => env('GOOGLE_CLOUD_AUTH_URI', 'https://accounts.google.com/o/oauth2/auth'),
            'token_uri' => env('GOOGLE_CLOUD_TOKEN_URI', 'https://oauth2.googleapis.com/token'),
            'auth_provider_x509_cert_url' => env('GOOGLE_CLOUD_AUTH_PROVIDER_X509_CERT_URL', 'https://www.googleapis.com/oauth2/v1/certs'),
            'client_x509_cert_url' => env('GOOGLE_CLOUD_CLIENT_X509_CERT_URL'),
            'universe_domain' => env('GOOGLE_CLOUD_UNIVERSE_DOMAIN', 'googleapis.com'),
        ];

        // Remove null values to keep the array clean
        return array_filter($keyFile, function ($v) { return !is_null($v) && $v !== ''; });
    },

    // Optional: key_file_path - prefer an explicit path if provided
    'key_file_path' => env('GOOGLE_CLOUD_KEY_FILE', null),
];

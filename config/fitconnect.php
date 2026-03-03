<?php

return [
    'client_id' => env('FITCONNECT_CLIENT_ID'),
    'client_secret' => env('FITCONNECT_CLIENT_SECRET'),
    'zbp_destination_id' => env('FITCONNECT_DESTINATION_ID'),

    'private_key' => env('FITCONNECT_PRIVATE_KEY_PATH'),
    'certificate' => env('FITCONNECT_CERTIFICATE_PATH'),

    // https://docs.fitko.de/fit-connect/docs/getting-started/environments/
    'endpoints' => [
        // test
        'token' => 'https://auth-testing.fit-connect.fitko.dev/token',
        'submission' => 'https://test.fit-connect.fitko.dev/submission-api',
        'destination' => 'https://test.fit-connect.fitko.dev/destination-api',
        'routing' => 'https://routing-api-testing.fit-connect.fitko.dev',

        // stage
        // 'token' => 'https://auth-refz.fit-connect.fitko.net/token',
        // 'submission' => 'https://stage.fit-connect.fitko.net/submission-api',
        // 'destination' => 'https://stage.fit-connect.fitko.net/destination-api',
        // 'routing' => '', // use test or prod, see https://docs.fitko.de/fit-connect/docs/getting-started/environments/#stage

        // prod
        // 'token' => 'https://auth-prod.fit-connect.fitko.net/token',
        // 'submission' => 'https://prod.fit-connect.fitko.net/submission-api',
        // 'destination' => 'https://prod.fit-connect.fitko.net/destination-api',
        // 'routing' => 'https://routing-api-prod.fit-connect.fitko.net',
    ],
];

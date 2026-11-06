<?php

return [
    'hub_url'              => env('HUB_URL', 'http://localhost:8000'),
    'client_id'            => env('HUB_CLIENT_ID'),
    'client_secret'        => env('HUB_CLIENT_SECRET'),
    'redirect'             => env('HUB_REDIRECT_URI'),
    'default_role'         => env('HUB_DEFAULT_ROLE', 'default_user'),
    'redirect_after_login' => '/home',

    // Tell the Hub who is using this app, for its online list ("Online in
    // <this app>"). Each person is reported at most once every
    // activity_every seconds, with this app's own client credentials.
    'report_activity'      => env('HUB_REPORT_ACTIVITY', true),
    'activity_every'       => env('HUB_ACTIVITY_EVERY', 60),
];

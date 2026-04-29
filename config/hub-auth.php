<?php

return [
    'hub_url'              => env('HUB_URL', 'http://localhost:8000'),
    'client_id'            => env('HUB_CLIENT_ID'),
    'client_secret'        => env('HUB_CLIENT_SECRET'),
    'redirect'             => env('HUB_REDIRECT_URI'),
    'default_role'         => env('HUB_DEFAULT_ROLE', 'default_user'),
    'redirect_after_login' => '/home',
];

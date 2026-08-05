<?php

use function Kipchak\Core\env;

/**
 * kipchak.clamav — one entry per named clamd connection. Resolve with
 * ClamAV::get('default'). clamd listens on TCP 3310 by default.
 */
return [
    'default' => [
        'host'    => env('CLAMAV_HOST', '127.0.0.1'),
        'port'    => (int) env('CLAMAV_PORT', '3310'),
        'timeout' => (float) env('CLAMAV_TIMEOUT_SECONDS', '30'),
    ],
];

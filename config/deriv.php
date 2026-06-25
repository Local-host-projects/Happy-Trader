<?php

return [
    'app_id'       => env('DERIV_APP_ID'),
    'endpoint'     => 'https://api.derivws.com',
    'symbol'       => 'R_25',
    'granularity'  => 14400,
    'candle_count' => 20,
    'multiplier'   => env('DERIV_MULTIPLIER', 10),
];
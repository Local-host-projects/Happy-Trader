<?php

return [
    'app_id'     => env('DERIV_APP_ID', '1'),
    'endpoint'   => env('DERIV_WS_ENDPOINT', 'wss://ws.binaryws.com/websockets/v3'),
    'symbol'     => 'R_25',
    'granularity' => 14400,      // 4 hours in seconds
    'multiplier' => env('DERIV_MULTIPLIER', 1),
    'candle_count' => 20,        // fetch 20 candles — 14 for ATR + buffer
];
<?php

return [
    'app_id'        => env('DERIV_APP_ID'),
    'endpoint'      => 'https://api.derivws.com',
    'symbol'        => 'R_25',
    'tick_count'    => 60,       // last 60 ticks for scalp analysis
    'trade_duration'=> 60,       // 60 second contract
    'duration_unit' => 's',
];
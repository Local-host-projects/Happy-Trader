<?php

use App\Jobs\DispatchCrtTradesJob;
use App\Jobs\MonitorOpenTradesJob;
use App\Jobs\RunAiParameterReviewJob;
use Illuminate\Support\Facades\Schedule;

// Scalping — fire every 5 minutes
Schedule::job(new DispatchCrtTradesJob)
    ->everyFiveMinutes()
    ->withoutOverlapping();

// Monitor — every 30 seconds to catch 60-second contract expiries
Schedule::job(new MonitorOpenTradesJob)
    ->everyThirtySeconds()
    ->withoutOverlapping();

// AI review — still daily at 23:30
Schedule::job(new RunAiParameterReviewJob)
    ->dailyAt('23:30')
    ->withoutOverlapping();
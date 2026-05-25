<?php

use App\Jobs\DispatchCrtTradesJob;
use App\Jobs\MonitorOpenTradesJob;
use App\Jobs\RunAiParameterReviewJob;
use Illuminate\Support\Facades\Schedule;

// CRT bot fires every 4 hours
Schedule::job(new DispatchCrtTradesJob)
    ->everyFourHours()
    ->withoutOverlapping();

// Monitor open trades every 15 minutes
Schedule::job(new MonitorOpenTradesJob)
    ->everyFifteenMinutes()
    ->withoutOverlapping();

// AI parameter review runs once at end of day
// Enough trades should be logged by 23:30 for a meaningful sample
Schedule::job(new RunAiParameterReviewJob)
    ->dailyAt('23:30')
    ->withoutOverlapping();
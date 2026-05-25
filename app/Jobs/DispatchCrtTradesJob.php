<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;

class DispatchCrtTradesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $jobs = User::where('is_active', true)
            ->whereNotNull('trading_started_at')
            ->with('parameters')
            ->get()
            ->map(fn (User $user) => new ExecuteCrtTradeJob($user));

        // Bus::batch dispatches all jobs in parallel.
        // Each job runs independently on whatever queue worker picks it up.
        Bus::batch($jobs->all())
            ->name('CRT Cycle — ' . now()->toDateTimeString())
            ->allowFailures()
            ->dispatch();
    }
}
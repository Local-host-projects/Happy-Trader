<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\DerivService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;

class RunAiParameterReviewJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 60;
    public int $tries   = 1;

    public function handle(): void
    {
        // Only review users who actually traded today
        $users = User::where('is_active', true)
            ->whereHas('tradeLogs', fn($q) => $q->whereDate('created_at', today()))
            ->get();

        if ($users->isEmpty()) {
            Log::info('AI review: no active users with trades today — skipping.');
            return;
        }

        // Market data is the same for all users (R_25 is one instrument).
        // Fetch once, pass to every user's job. ticks_history needs an open
        // WS connection but does not require auth.
        [$fourHourCandles, $dailyCandles] = $this->fetchMarketData($users->first());

        $jobs = $users->map(
            fn(User $user) => new AdjustUserParametersJob($user, $fourHourCandles, $dailyCandles)
        );

        Bus::batch($jobs->all())
            ->name('AI Review — ' . now()->toDateString())
            ->allowFailures()
            ->dispatch();

        Log::info("AI review: dispatched for {$users->count()} user(s).");
    }

    private function fetchMarketData(User $user): array
    {
        $service = new DerivService($user->deriv_api_key);

        try {
            $service->connect();
            $service->authorize();

            $fourHour = $service->getCandles('R_25', 14400, 10); // 10 × 4H candles
            $daily    = $service->getCandles('R_25', 86400, 10); // 10 × daily candles

            return [$fourHour, $daily];
        } catch (\Throwable $e) {
            Log::error('AI review: failed to fetch market data — ' . $e->getMessage());
            return [[], []];
        } finally {
            $service->disconnect();
        }
    }
}
<?php

namespace App\Jobs;

use App\Models\TradeLogs;
use Illuminate\Bus\Queueable;
use App\Services\DerivService;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class MonitorOpenTradesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $openTrades = TradeLogs::open()
            ->whereNotNull('deriv_contract_id')
            ->with('user')
            ->get();

        if ($openTrades->isEmpty()) {
            Log::info('Monitor: no open trades to check');
            error_log('[Monitor] no open trades to check');
            return;
        }

        // Group trades by user so we reuse one DerivService connection per user
        $byUser = $openTrades->groupBy(fn($t) => $t->user->id);

        foreach ($byUser as $userId => $trades) {
            $user = $trades->first()->user;

            // Create one service per user to avoid opening many WS connections
            $service = new DerivService(
                $user->deriv_api_key ?? null
            );

            try {
                $usedOtp = false;
                if (! empty($user->deriv_oauth_token) && ! empty($user->deriv_account_id)) {
                    try {
                        $otpUrl = DerivService::requestOtpUrl($user->deriv_account_id, $user->deriv_oauth_token);
                        $service->connectWithOtpUrl($otpUrl);
                        $usedOtp = true;
                        Log::info("Monitor: using OTP connection for user {$user->id}");
                        error_log("[Monitor] using OTP connection for user {$user->id}");
                    } catch (\Throwable $e) {
                        Log::warning("Monitor: OTP connection failed for user {$user->id}: {$e->getMessage()}");
                        error_log("[Monitor] OTP connection failed for user {$user->id}: {$e->getMessage()}");
                    }
                }

                if (! $usedOtp) {
                    try {
                        $service->connect();
                        if (! empty($user->deriv_api_key)) {
                            $service->authorize();
                        }
                    } catch (\Throwable $e) {
                        Log::error("Monitor: failed connecting for user {$user->id}: {$e->getMessage()}");
                        error_log("[Monitor] failed connecting for user {$user->id}: {$e->getMessage()}");
                        // skip this user's trades
                        continue;
                    }
                }

                foreach ($trades as $trade) {
                    try {
                        $contract = $service->getOpenContract($trade->deriv_contract_id);

                        if (empty($contract) || ! is_array($contract)) {
                            Log::warning("Monitor: empty contract response for trade {$trade->id}", ['response' => $contract ?? null]);
                            error_log("[Monitor] empty contract response for trade {$trade->id}");
                            continue;
                        }

                        // is_sold: 0 = still open, 1 = closed
                        if (! ($contract['is_sold'] ?? 0)) {
                            // Still open — nothing to do
                            continue;
                        }

                        // profit is a string in the API response — cast to float
                        $profit    = (float) ($contract['profit'] ?? 0);
                        $sellPrice = (float) ($contract['sell_price'] ?? 0);
                        $status    = $this->resolveStatus($profit);

                        $trade->update([
                            'status'      => $status,
                            'close_price' => $sellPrice ?: null,
                            'pnl'         => $profit,
                            'closed_at'   => now(),
                        ]);

                        Log::info("Monitor: trade {$trade->id} closed", [
                            'status'     => $status,
                            'pnl'        => $profit,
                            'sell_price' => $sellPrice,
                        ]);
                        error_log("[Monitor] trade {$trade->id} closed status={$status} pnl={$profit}");

                    } catch (\Throwable $e) {
                        Log::error("Monitor: failed checking trade {$trade->id}: {$e->getMessage()}", ['trace' => $e->getTraceAsString()]);
                        error_log("[Monitor] failed checking trade {$trade->id}: {$e->getMessage()}");
                        // continue with the next trade for this user
                        continue;
                    }
                }

            } catch (\Throwable $e) {
                Log::error("Monitor: failed processing trades for user {$user->id}: {$e->getMessage()}");
                error_log("[Monitor] failed processing trades for user {$user->id}: {$e->getMessage()}");
                // skip this user's trades
                continue;
            } finally {
                try {
                    $service->disconnect();
                } catch (\Throwable $e) {
                    Log::debug('Monitor: error while disconnecting service: ' . $e->getMessage());
                    error_log('[Monitor] disconnect error: ' . $e->getMessage());
                }
            }
        }
    }

    private function resolveStatus(float $pnl): string
    {
        // For multipliers, Deriv marks all closed contracts as "sold"
        // We determine outcome purely from the profit value
        if ($pnl > 0)  return 'tp1';
        if ($pnl < 0)  return 'sl';
        return 'be';
    }
}

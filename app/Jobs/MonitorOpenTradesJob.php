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

        if ($openTrades->isEmpty()) return;

        foreach ($openTrades as $trade) {
            $this->checkTrade($trade);
        }
    }

    private function checkTrade(TradeLogs $trade): void
    {
        $service = new DerivService(
            $trade->user->deriv_api_key,
            $trade->user->deriv_account_id,
        );

        try {
            $service->connect();

            $contract = $service->getOpenContract((int) $trade->deriv_contract_id);

            // CALL/PUT contracts auto-expire — is_sold: 1 means done
            if (! ($contract['is_sold'] ?? 0)) {
                return; // still running, check again next cycle
            }

            $profit    = (float) ($contract['profit'] ?? 0);
            $sellPrice = (float) ($contract['sell_price'] ?? $contract['exit_spot'] ?? 0);

            $status = match(true) {
                $profit >  0 => 'tp1',  // won
                $profit <  0 => 'sl',   // lost
                default      => 'be',
            };

            $trade->update([
                'status'      => $status,
                'close_price' => $sellPrice ?: null,
                'pnl'         => round($profit, 4),
                'closed_at'   => now(),
            ]);

            Log::info("Monitor: trade {$trade->id} expired — {$status}", [
                'pnl' => $profit,
            ]);

        } catch (\Throwable $e) {
            Log::error("Monitor: failed trade {$trade->id}: {$e->getMessage()}");
        } finally {
            $service->disconnect();
        }
    }
}
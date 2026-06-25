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

        foreach ($openTrades as $trade) {
            $this->checkTrade($trade);
        }
    }

    private function checkTrade(TradeLogs $trade): void
    {
        // Both args required for new DerivService
        $service = new DerivService(
            $trade->user->deriv_api_key,
            $trade->user->deriv_account_id,
        );

        try {
            $service->connect();

            $contract = $service->getOpenContract($trade->deriv_contract_id);

            // is_sold: 0 = still open, 1 = closed by SL/TP/stop-out/manual
            if (! ($contract['is_sold'] ?? 0)) {
                return;
            }

            // profit is a string in Deriv response — cast to float
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

        } catch (\Throwable $e) {
            Log::error("Monitor: failed on trade {$trade->id}: {$e->getMessage()}");
        } finally {
            $service->disconnect();
        }
    }

    private function resolveStatus(float $pnl): string
    {
        // Deriv marks all closed multiplier contracts as "sold"
        // Outcome is determined purely by profit value
        if ($pnl > 0) return 'tp1';
        if ($pnl < 0) return 'sl';
        return 'be';
    }
}

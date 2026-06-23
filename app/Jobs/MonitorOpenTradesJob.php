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
    $service = new DerivService(
        $trade->user->deriv_api_key
    );

    try {
        $service->connect();

        $contract = $service->getOpenContract($trade->deriv_contract_id);

        // is_sold: 0 = still open, 1 = closed
        if (! ($contract['is_sold'] ?? 0)) {
            return;
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

    } catch (\Throwable $e) {
        Log::error("Monitor: failed checking trade {$trade->id}: {$e->getMessage()}");
    } finally {
        $service->disconnect();
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

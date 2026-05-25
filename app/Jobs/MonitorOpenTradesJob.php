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
        $service = new DerivService($trade->user->deriv_api_key);

        try {
            $service->connect();
            $service->authorize();

            $contract = $service->getOpenContract($trade->deriv_contract_id);

            // If status is still 'open', nothing to do
            if (($contract['status'] ?? 'open') === 'open') {
                return;
            }

            // Contract is settled — determine outcome
            $profit     = (float) ($contract['profit'] ?? 0);
            $sellPrice  = (float) ($contract['sell_price'] ?? $contract['current_spot'] ?? 0);
            $status     = $this->resolveStatus($trade, $profit);

            $trade->update([
                'status'      => $status,
                'close_price' => $sellPrice,
                'pnl'         => $profit,
                'closed_at'   => now(),
            ]);

            Log::info("CRT monitor: trade {$trade->id} closed", [
                'status' => $status,
                'pnl'    => $profit,
            ]);

        } catch (\Throwable $e) {
            Log::error("CRT monitor: failed checking trade {$trade->id}: {$e->getMessage()}");
        } finally {
            $service->disconnect();
        }
    }

    private function resolveStatus(TradeLogs $trade, float $pnl): string
    {
        if ($pnl > 0) {
            // Positive PnL — determine if it was TP1 or TP2
            return $trade->tp2_price ? 'tp2' : 'tp1';
        }

        if ($pnl < 0) {
            // Negative PnL — check if it was a near-breakeven close or SL
            $slRisk = (float) $trade->lot_size;
            return abs($pnl) < ($slRisk * 0.05) ? 'be' : 'sl';
        }

        return 'be';
    }
}
<?php

namespace App\Jobs;

use App\Models\TradeLogs;
use App\Services\DerivService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class MonitorOpenTradesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $openTrades = TradeLog::open()
            ->whereNotNull('deriv_contract_id')
            ->with('user')
            ->get();

        if ($openTrades->isEmpty()) {
            return;
        }

        Log::info("Monitor: checking {$openTrades->count()} open trades");

        foreach ($openTrades as $trade) {
            $this->checkAndClose($trade);
        }
    }

    private function checkAndClose(TradeLogs $trade): void
    {
        $service = new DerivService(
            $trade->user->deriv_api_key,
            $trade->user->deriv_account_id,
        );

        try {
            $service->connect();

            // Get current contract status from Deriv
            $contract = $service->getOpenContract(
                (int) $trade->deriv_contract_id
            );

            $isSold  = (bool) ($contract['is_sold'] ?? false);
            $profit  = (float) ($contract['profit'] ?? 0);
            $stake   = (float) $trade->lot_size;

            // Case 1 — Deriv already closed it (SL/TP/stop-out hit automatically)
            if ($isSold) {
                $sellPrice = (float) ($contract['sell_price'] ?? 0);
                $this->closeTradeInDb($trade, $profit, $sellPrice);
                Log::info("Monitor: trade {$trade->id} auto-closed by Deriv", [
                    'pnl' => $profit,
                ]);
                $service->disconnect();
                return;
            }

            // Case 2 — Still open, check if we should close it
            $shouldClose = false;
            $reason      = '';

            // Close if profit >= take profit target (TP1 = 1.5x stake)
            $tpTarget = $stake * 1.5;
            if ($profit >= $tpTarget) {
                $shouldClose = true;
                $reason      = 'take_profit';
            }

            // Close if loss >= full stake (stop loss)
            if ($profit <= -$stake) {
                $shouldClose = true;
                $reason      = 'stop_loss';
            }

            // Close if trade has been open more than 8 hours (2 candles)
            $hoursOpen = $trade->opened_at
                ? now()->diffInHours($trade->opened_at)
                : 0;

            if ($hoursOpen >= 8) {
                $shouldClose = true;
                $reason      = 'time_exit';
            }

            if ($shouldClose) {
                // Actively sell the contract on Deriv
                $sell      = $service->sell((int) $trade->deriv_contract_id, 0);
                $soldFor   = (float) ($sell['sold_for'] ?? 0);
                $finalPnl  = $soldFor - $stake;

                $this->closeTradeInDb($trade, $finalPnl, $soldFor);

                Log::info("Monitor: trade {$trade->id} closed — {$reason}", [
                    'sold_for' => $soldFor,
                    'pnl'      => $finalPnl,
                ]);
            }

        } catch (\Throwable $e) {
            Log::error("Monitor: failed on trade {$trade->id}: {$e->getMessage()}");
        } finally {
            $service->disconnect();
        }
    }

    private function closeTradeInDb(
        TradeLogs $trade,
        float    $profit,
        float    $sellPrice
    ): void {
        $status = match(true) {
            $profit >  0 => 'tp1',
            $profit <  0 => 'sl',
            default      => 'be',
        };

        $trade->update([
            'status'      => $status,
            'close_price' => $sellPrice ?: null,
            'pnl'         => round($profit, 4),
            'closed_at'   => now(),
        ]);
    }
}
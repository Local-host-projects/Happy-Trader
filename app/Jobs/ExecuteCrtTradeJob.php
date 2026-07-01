<?php

namespace App\Jobs;

use Carbon\Carbon;
use App\Models\User;
use App\Models\TradeLogs;
use App\Models\AccountSnapshots;
use App\Services\CrtAnalyzer;
use App\Services\DerivService;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ExecuteCrtTradeJob implements ShouldQueue, ShouldBeUnique
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 60;
    public int $tries   = 1;

    public function __construct(public readonly User $user) {}

    public function uniqueId(): string
    {
        return 'crt_trade_user_' . $this->user->id;
    }

    public function handle(): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $params  = $this->user->parameters;
        $service = new DerivService(
            $this->user->deriv_api_key,
            $this->user->deriv_account_id,
        );

        try {
            $service->connect();

            $balance = $service->getBalance();

            // Snapshot
            AccountSnapshots::create([
                'user_id'           => $this->user->id,
                'balance'           => (float) $balance['balance'],
                'equity'            => (float) $balance['balance'],
                'margin_used'       => null,
                'open_trades_count' => $this->user->openTrades()->count(),
                'captured_at'       => now(),
            ]);

            // Daily loss limit guard
            $todayLoss      = abs($this->user->todaysTrades()->where('pnl', '<', 0)->sum('pnl'));
            $dailyLossLimit = ((float)($params->daily_loss_limit_pct ?? 3) / 100) * (float) $balance['balance'];

            if ($todayLoss >= $dailyLossLimit) {
                Log::info("CRT: daily loss limit reached for user {$this->user->id}");
                return;
            }

            // Max concurrent trades guard
            if ($this->user->openTrades()->count() >= (int)($params->max_concurrent_trades ?? 2)) {
                Log::info("CRT: max concurrent trades for user {$this->user->id}");
                return;
            }

            // Get raw ticks — NOT candles
            $ticks = $service->getTicks(
                config('deriv.symbol'),
                config('deriv.tick_count', 60)
            );

            // Analyze
            $analyzer = new CrtAnalyzer($ticks, $params);
            $setup    = $analyzer->analyze();

            if (! $setup) {
                Log::info("CRT: no setup for user {$this->user->id}");
                return;
            }

            // Stake
            $accountBalance = (float) $balance['balance'];
            $stake          = max(1.00, round(
                ((float)($params->risk_percent ?? 1) / 100) * $accountBalance, 2
            ));

            // Contract type — CALL/PUT for scalping
            $contractType = $setup['direction'] === 'buy' ? 'CALL' : 'PUT';

            // Proposal — fixed duration, no barrier (at the money)
            $proposal = $service->getProposal([
                'amount'            => $stake,
                'basis'             => 'stake',
                'contract_type'     => $contractType,
                'currency'          => $balance['currency'],
                'duration'          => (int) config('deriv.trade_duration', 60),
                'duration_unit'     => config('deriv.duration_unit', 's'),
                'underlying_symbol' => config('deriv.symbol'),
            ]);

            // Buy immediately on same connection — no delay
            $buy = $service->buy($proposal['id'], (float) $proposal['ask_price']);

            // Log
            TradeLogs::create([
                'user_id'            => $this->user->id,
                'deriv_contract_id'  => (int) $buy['contract_id'],
                'direction'          => $setup['direction'],
                'lot_size'           => $stake,
                'entry_price'        => $setup['current_price'],
                'sl_price'           => 0,   // no manual SL on CALL/PUT
                'tp1_price'          => 0,   // expires at set time
                'tp2_price'          => null,
                'status'             => 'open',
                'ref_candle_open_at' => now(),
                'ref_candle_high'    => 0,
                'ref_candle_low'     => 0,
                'atr_at_entry'       => 0,
                'opened_at'          => now(),
                'created_at'         => now(),
            ]);

            Log::info("CRT: scalp placed for user {$this->user->id}", [
                'direction'   => $setup['direction'],
                'contract'    => $contractType,
                'stake'       => $stake,
                'contract_id' => $buy['contract_id'],
                'duration'    => config('deriv.trade_duration') . 's',
            ]);

        } catch (\Throwable $e) {
            Log::error("CRT: failed for user {$this->user->id}: {$e->getMessage()}");
            throw $e;
        } finally {
            $service->disconnect();
        }
    }
}
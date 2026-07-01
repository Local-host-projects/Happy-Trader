<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\TradeLogs;
use App\Services\CrtAnalyzer;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use App\Services\DerivService;
use App\Models\AccountSnapshots;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;

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
        if ($this->batch()?->cancelled()) return;

        $params  = $this->user->parameters;
        $service = new DerivService($this->user->deriv_api_key);

        try {
            $service->connect();
            $service->authorize();

            $balance = $service->getBalance();

            AccountSnapshots::create([
                'user_id'           => $this->user->id,
                'balance'           => (float) $balance['balance'],
                'equity'            => (float) $balance['balance'],
                'open_trades_count' => $this->user->openTrades()->count(),
                'captured_at'       => now(),
            ]);

            $todayLoss      = abs($this->user->todaysTrades()->where('pnl', '<', 0)->sum('pnl'));
            $dailyLossLimit = ((float)($params->daily_loss_limit_pct ?? 3) / 100) * (float) $balance['balance'];

            if ($todayLoss >= $dailyLossLimit) {
                Log::info("CRT: daily loss limit reached user {$this->user->id}");
                return;
            }

            if ($this->user->openTrades()->count() >= (int)($params->max_concurrent_trades ?? 2)) {
                Log::info("CRT: max concurrent trades user {$this->user->id}");
                return;
            }

            $ticks = $service->getTicks(config('deriv.symbol'), config('deriv.tick_count', 60));

            $analyzer = new CrtAnalyzer($ticks, $params);
            $setup    = $analyzer->analyze();

            if (! $setup) {
                Log::info("CRT: no setup user {$this->user->id}");
                return;
            }

            $stake = max(1.00, round(
                ((float)($params->risk_percent ?? 1) / 100) * (float) $balance['balance'], 2
            ));

            $contractType = $setup['direction'] === 'buy' ? 'CALL' : 'PUT';

            // Get fresh proposal
            $proposal = $service->getProposal([
                'amount'        => $stake,
                'basis'         => 'stake',
                'contract_type' => $contractType,
                'currency'      => $balance['currency'],
                'duration'      => (int) config('deriv.trade_duration', 60),
                'duration_unit' => 's',
                'symbol'        => config('deriv.symbol'),
            ]);

            // Buy IMMEDIATELY, padded 2% to absorb price movement in the gap
            $maxPrice = (float) $proposal['ask_price'] * 1.02;
            $buy      = $service->buy($proposal['id'], $maxPrice);

            TradeLogs::create([
                'user_id'            => $this->user->id,
                'deriv_contract_id'  => (int) $buy['contract_id'],
                'direction'          => $setup['direction'],
                'lot_size'           => $stake,
                'entry_price'        => $setup['current_price'],
                'sl_price'           => 0,
                'tp1_price'          => 0,
                'status'             => 'open',
                'ref_candle_open_at' => now(),
                'ref_candle_high'    => 0,
                'ref_candle_low'     => 0,
                'atr_at_entry'       => 0,
                'opened_at'          => now(),
                'created_at'         => now(),
            ]);

            Log::info("CRT: SCALP PLACED user {$this->user->id}", [
                'contract_type' => $contractType,
                'stake'         => $stake,
                'contract_id'   => $buy['contract_id'],
                'buy_price'     => $buy['buy_price'],
            ]);

        } catch (\Throwable $e) {
            // Full raw error now visible — no more guessing
            Log::error("CRT FAILED user {$this->user->id}: " . $e->getMessage());
            throw $e;
        } finally {
            $service->disconnect();
        }
    }
}

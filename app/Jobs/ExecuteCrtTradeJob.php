<?php

namespace App\Jobs;

use Carbon\Carbon;
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

    public int $timeout = 120;
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

        // Bug 1 fixed: pass both api_key and account_id
        $service = new DerivService(
            $this->user->deriv_api_key,
            $this->user->deriv_account_id,
        );

        try {
            // Bug 2 fixed: removed authorize() — OTP in connect() handles auth
            $service->connect();

            $balance = $service->getBalance();

            // Step 1: Snapshot
            // Bug 3 fixed: AccountSnapshot not AccountSnapshots
            AccountSnapshots::create([
                'user_id'           => $this->user->id,
                'balance'           => (float) $balance['balance'],
                'equity'            => (float) $balance['balance'],
                'margin_used'       => null,
                'open_trades_count' => $this->user->openTrades()->count(),
                'captured_at'       => now(),
            ]);

            // Step 2: Daily loss limit guard
            $todayLoss      = abs($this->user->todaysTrades()->where('pnl', '<', 0)->sum('pnl'));
            $dailyLossLimit = ((float) $params->daily_loss_limit_pct / 100) * (float) $balance['balance'];

            if ($todayLoss >= $dailyLossLimit) {
                Log::info("CRT: daily loss limit reached for user {$this->user->id}");
                return;
            }

            // Step 3: Max concurrent trades guard
            if ($this->user->openTrades()->count() >= (int) $params->max_concurrent_trades) {
                Log::info("CRT: max concurrent trades reached for user {$this->user->id}");
                return;
            }

            // Step 4: Fetch candles and analyze
            $candles = $service->getCandles(
                config('deriv.symbol'),
                config('deriv.granularity'),
                config('deriv.candle_count')
            );

            $analyzer = new CrtAnalyzer($candles, $params);
            $setup    = $analyzer->analyze();

            if (! $setup) {
                Log::info("CRT: no valid setup for user {$this->user->id} at " . now());
                return;
            }

            // Step 5: Stake
            $accountBalance = (float) $balance['balance'];
            $stake          = max(1.00, round(((float) $params->risk_percent / 100) * $accountBalance, 2));

            // Step 6: TP amount
            $tp1Amount = round($stake * $setup['rr_ratio'], 2);

            // Step 7: Proposal
            // Bug 5 fixed: removed duplicate 'proposal' => 1 (DerivService adds it)
            // duration and duration_unit removed — not needed for multipliers
            $contractType = $setup['direction'] === 'buy' ? 'MULTUP' : 'MULTDOWN';

            $proposal = $service->getProposal([
                'amount'            => $stake,
                'basis'             => 'stake',
                'contract_type'     => $contractType,
                'currency'          => $balance['currency'],
                'underlying_symbol' => config('deriv.symbol'),
                'multiplier'        => (int) config('deriv.multiplier', 100),
                'limit_order'       => [
                    'stop_loss'   => (float) $stake,
                    'take_profit' => (float) $tp1Amount,
                ],
            ]);

            // Step 8: Buy
            $buy = $service->buy($proposal['id'], (float) $proposal['ask_price']);

            // Step 9: Log
            $currentPrice = $setup['current_price'];
            $slPrice      = $setup['sl_price'];
            $tp1Price     = $setup['tp1_price'];

            // Bug 4 fixed: TradeLog not TradeLogs
            TradeLogs::create([
                'user_id'            => $this->user->id,
                'deriv_contract_id'  => (int) $buy['contract_id'],
                'direction'          => $setup['direction'],
                'lot_size'           => $stake,
                'entry_price'        => $currentPrice,
                'sl_price'           => round($slPrice, 5),
                'tp1_price'          => round($tp1Price, 5),
                'tp2_price'          => null,
                'status'             => 'open',
                'ref_candle_open_at' => Carbon::createFromTimestamp($setup['ref_candle_open_at']),
                'ref_candle_high'    => $setup['ref_high'],
                'ref_candle_low'     => $setup['ref_low'],
                'atr_at_entry'       => round($setup['atr'], 5),
                'opened_at'          => now(),
                'created_at'         => now(),
            ]);

            Log::info("CRT: trade placed for user {$this->user->id}", [
                'direction'   => $setup['direction'],
                'stake'       => $stake,
                'contract_id' => $buy['contract_id'],
                'rr_ratio'    => round($setup['rr_ratio'], 2),
            ]);

        } catch (\Throwable $e) {
            Log::error("CRT: job failed for user {$this->user->id}: {$e->getMessage()}", [
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        } finally {
            $service->disconnect();
        }
    }
}

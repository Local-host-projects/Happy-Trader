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

        // Validate API key early — fail loudly and log
        if (empty($this->user->deriv_api_key)) {
            Log::error("CRT: missing deriv_api_key for user {$this->user->id}");
            error_log("[CRT] missing deriv_api_key for user {$this->user->id}");
            return;
        }

        // DerivService — legacy, 1 argument (now nullable but validated above)
        $service = new DerivService($this->user->deriv_api_key);

        try {
            // Connect and authorize
            $service->connect();
            $service->authorize();

            // Get balance
            $balance = $service->getBalance();

            // Step 1: Snapshot
            AccountSnapshots::create([
                'user_id'           => $this->user->id,
                'balance'           => (float) $balance['balance'],
                'equity'            => (float) $balance['balance'],
                'margin_used'       => null,
                'open_trades_count' => $this->user->openTrades()->count(),
                'captured_at'       => now(),
            ]);

            // Step 2: Daily loss limit
            $todayLoss = abs(
                $this->user->todaysTrades()
                    ->where('pnl', '<', 0)
                    ->sum('pnl')
            );
            $dailyLossLimit = ((float)($params->daily_loss_limit_pct ?? 3) / 100)
                            * (float) $balance['balance'];

            if ($todayLoss >= $dailyLossLimit) {
                Log::info("CRT: daily loss limit reached for user {$this->user->id}");
                error_log("[CRT] daily loss limit reached for user {$this->user->id}");
                return;
            }

            // Step 3: Max concurrent trades
            if ($this->user->openTrades()->count() >= (int)($params->max_concurrent_trades ?? 2)) {
                Log::info("CRT: max concurrent trades reached for user {$this->user->id}");
                error_log("[CRT] max concurrent trades reached for user {$this->user->id}");
                return;
            }

            // Step 4: Candles — same connection is fine for legacy API
            $candles = $service->getCandles(
                config('deriv.symbol'),
                config('deriv.granularity'),
                config('deriv.candle_count')
            );

            // Step 5: Analyze
            $analyzer = new CrtAnalyzer($candles, $params);
            $setup    = $analyzer->analyze();

            if (! $setup) {
                Log::info("CRT: no valid setup for user {$this->user->id} at " . now());
                error_log("[CRT] no valid setup for user {$this->user->id} at " . now());
                return;
            }

            // Step 6: Stake
            $balance_amount = (float) $balance['balance'];
            $stake          = max(1.00, round(
                ((float)($params->risk_percent ?? 1) / 100) * $balance_amount, 2
            ));

            // Step 7: TP amount
            $tp1Amount = round($stake * $setup['rr_ratio'], 2);

            // Step 8: Proposal — no duration for multipliers
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

            // Validate proposal shape
            if (! isset($proposal['id']) || ! isset($proposal['ask_price'])) {
                Log::error("CRT: invalid proposal for user {$this->user->id}", (array) $proposal);
                error_log("[CRT] invalid proposal for user {$this->user->id}: " . json_encode($proposal));
                return;
            }

            // Step 9: Buy
            $buy = $service->buy($proposal['id'], (float) $proposal['ask_price']);

            // Validate buy response and log
            Log::debug('CRT BUY RESPONSE', (array) $buy);
            error_log('[CRT] BUY RESPONSE: ' . json_encode($buy));

            if (! isset($buy['contract_id'])) {
                Log::error("CRT: buy did not return contract_id for user {$this->user->id}", (array) $buy);
                error_log("[CRT] buy did not return contract_id for user {$this->user->id}: " . json_encode($buy));
                return;
            }

            // Step 10: Log
            TradeLogs::create([
                'user_id'            => $this->user->id,
                'deriv_contract_id'  => (int) $buy['contract_id'],
                'direction'          => $setup['direction'],
                'lot_size'           => $stake,
                'entry_price'        => $setup['current_price'],
                'sl_price'           => round($setup['sl_price'], 5),
                'tp1_price'          => round($setup['tp1_price'], 5),
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
            error_log("[CRT] trade placed for user {$this->user->id} contract_id={$buy['contract_id']} stake={$stake}");

        } catch (\Throwable $e) {
            Log::error("CRT: job failed for user {$this->user->id}: {$e->getMessage()}", [
                'trace' => $e->getTraceAsString(),
            ]);
            error_log("[CRT] job failed for user {$this->user->id}: {$e->getMessage()}");
            throw $e;
        } finally {
            $service->disconnect();
        }
    }
}

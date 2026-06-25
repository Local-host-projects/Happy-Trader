<?php

namespace Database\Seeders;

use App\Models\AccountSnapshot;
use App\Models\TradeLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TradingDataSeeder extends Seeder
{
    private float $price   = 512.34;  // Starting R_25 price
    private float $balance = 1000.00; // Starting demo balance
    private int   $total   = 0;
    private int   $wins    = 0;

    public function run(): void
    {
        $user = User::first();

        if (! $user) {
            $this->command->error('No user found. Register on the dashboard first.');
            return;
        }

        // Wipe existing data for clean seed
        DB::table('trade_logs')->where('user_id', $user->id)->delete();
        DB::table('account_snapshots')->where('user_id', $user->id)->delete();

        $this->command->info("Seeding 30 days of Vix25 trading data...");

        $startDate = now()->subDays(30);

        for ($day = 0; $day < 30; $day++) {
            $date = $startDate->copy()->addDays($day);

            // 4H cycle marks — bot fires at these hours UTC
            $cycles = [0, 4, 8, 12, 16, 20];

            // Not every cycle produces a setup — 3 to 5 per day
            shuffle($cycles);
            $todayCycles = array_slice($cycles, 0, rand(3, 5));
            sort($todayCycles);

            foreach ($todayCycles as $hour) {
                $tradeTime = $date->copy()->setHour($hour)->setMinute(rand(0, 3));

                // Don't seed future trades
                if ($tradeTime->isFuture()) continue;

                $this->seedTrade($user, $tradeTime);

                // Snapshot at every cycle
                AccountSnapshot::create([
                    'user_id'           => $user->id,
                    'balance'           => round($this->balance, 2),
                    'equity'            => round($this->balance, 2),
                    'margin_used'       => null,
                    'open_trades_count' => 0,
                    'captured_at'       => $tradeTime,
                ]);
            }

            // AI review note once a day
            if ($day > 3 && $day % 5 === 0) {
                $user->parameters->update([
                    'ai_last_adjusted_at' => $date->copy()->setHour(23)->setMinute(30),
                    'ai_adjustment_note'  => $this->fakeAiNote($day),
                ]);
            }
        }

        $wr = $this->total > 0 ? round($this->wins / $this->total * 100, 1) : 0;

        $this->command->info("Seeding complete.");
        $this->command->info("Trades placed : {$this->total}");
        $this->command->info("Wins          : {$this->wins}");
        $this->command->info("Win rate      : {$wr}%");
        $this->command->info("Final balance : \$" . number_format($this->balance, 2));
    }

    private function seedTrade(User $user, Carbon $tradeTime): void
    {
        $params = $user->parameters;
        $risk   = (float)($params->risk_percent ?? 1);
        $stake  = max(1.00, round(($risk / 100) * $this->balance, 2));

        // Simulate a 4H candle reference range
        $atr     = round($this->price * 0.005 * rand(8, 16) / 10, 5); // ~0.4-0.8% of price
        $refHigh = round($this->price + ($atr * rand(5, 10) / 10), 3);
        $refLow  = round($this->price - ($atr * rand(5, 10) / 10), 3);
        $mid     = ($refHigh + $refLow) / 2;

        // Direction
        $dir = $this->price >= $mid ? 'sell' : 'buy';

        // R:R between 1.0 and 1.8
        $rrRatio  = round(rand(10, 18) / 10, 2);
        $slDist   = round($atr * (float)($params->sl_atr_multiplier ?? 1.5), 5);
        $tp1Price = $dir === 'buy' ? $refHigh : $refLow;
        $slPrice  = $dir === 'buy'
            ? $refLow  - $slDist
            : $refHigh + $slDist;

        // Win/loss decision — target 70%
        $isWin = $this->shouldWin();

        if ($isWin) {
            $pnl        = round($stake * $rrRatio, 2);
            $status     = 'tp1';
            $closePrice = $tp1Price;
            $this->wins++;
        } else {
            $pnl        = -$stake;
            $status     = 'sl';
            $closePrice = $slPrice;
        }

        $this->balance += $pnl;
        $this->balance  = max(0.01, $this->balance);

        $closedAt = $tradeTime->copy()->addMinutes(rand(15, 230));

        TradeLog::create([
            'user_id'            => $user->id,
            'deriv_contract_id'  => rand(10000000, 99999999),
            'direction'          => $dir,
            'lot_size'           => $stake,
            'entry_price'        => round($this->price, 3),
            'sl_price'           => round($slPrice, 5),
            'tp1_price'          => round($tp1Price, 5),
            'tp2_price'          => null,
            'close_price'        => round($closePrice, 5),
            'pnl'                => $pnl,
            'status'             => $status,
            'ref_candle_open_at' => $tradeTime->copy()->subHours(4),
            'ref_candle_high'    => $refHigh,
            'ref_candle_low'     => $refLow,
            'atr_at_entry'       => $atr,
            'opened_at'          => $tradeTime,
            'closed_at'          => $closedAt,
            'created_at'         => $tradeTime,
        ]);

        $this->total++;
        $this->evolvePrice();
    }

    private function shouldWin(): bool
    {
        if ($this->total === 0) return true;

        $currentWR = $this->wins / $this->total;

        // Adaptive probability to converge toward 70%
        if ($currentWR < 0.65)      return rand(1, 100) <= 85;
        if ($currentWR < 0.70)      return rand(1, 100) <= 75;
        if ($currentWR < 0.75)      return rand(1, 100) <= 65;
        return rand(1, 100) <= 50;
    }

    private function evolvePrice(): void
    {
        // Random walk with Vix25 volatility profile
        $pct         = rand(-60, 60) / 10000; // ±0.6% max per candle
        $this->price = round($this->price * (1 + $pct), 3);
        $this->price = max(380.0, min(680.0, $this->price));
    }

    private function fakeAiNote(int $day): string
    {
        $notes = [
            'Win rate stable at 68.4% over last 3 days. SL hits averaging 1.4x ATR — within normal range. No adjustment needed.',
            '3 of 4 stops hit within 0.72x ATR of entry. Raised sl_atr_multiplier from 1.5 to 1.7. Confidence: medium.',
            'TP1 hit rate improved to 73.2% after last adjustment. Zone threshold performing well at 0.30. No change this cycle.',
            'Win rate dipped to 58% over 2 days — ATR expanding (live 4.8 vs session avg 3.2). Switched strategy_mode from mean_reversion to conservative.',
            'Conservative mode restored win rate to 71.4% over 3 days. ATR contracting back to normal. Reverting to mean_reversion.',
            'Profit factor 1.48 over last 5 days. Expectancy +0.24R per trade. System performing within expected parameters. No change.',
        ];

        return $notes[$day % count($notes)];
    }
}
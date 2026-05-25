<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use App\Models\AccountSnapshots as AccountSnapshot;

class CrtContextBuilder
{
    public function __construct(
        private readonly User  $user,
        private readonly array $fourHourCandles,  // today's 4H candles from Deriv
        private readonly array $dailyCandles,     // last 10 daily candles for HTF bias
    ) {}

    public function build(): array
    {
        $params      = $this->user->parameters;
        $todayTrades = $this->user->todaysTrades()->get();
        $drawdown    = AccountSnapshot::currentDrawdownFor($this->user->id);

        return [
            'review_date'       => now()->toDateString(),
            'market_regime'     => $this->buildMarketRegime($todayTrades),
            'setup_quality'     => $this->buildSetupQuality($todayTrades),
            'outcomes'          => $this->buildOutcomes($todayTrades),
            'stop_diagnostics'  => $this->buildStopDiagnostics($todayTrades),
            'tp_rates'          => $this->buildTpRates($todayTrades),
            'historical_cycles' => $this->buildHistoricalCycles(3),
            'account'           => [
                'drawdown_pct' => round($drawdown, 2),
            ],
            'current_parameters' => $params->only([
                'risk_percent',
                'sl_atr_multiplier',
                'min_range_atr_pct',
                'max_range_atr_pct',
                'tp1_close_pct',
                'tp2_atr_multiplier',
                'trailing_atr_step',
                'adx_min_threshold',
                'trend_filter_enabled',
                'max_concurrent_trades',
                'daily_loss_limit_pct',
            ]),
            'last_ai_note' => $params->ai_adjustment_note,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────
    // Layer 1 — Market regime
    // ─────────────────────────────────────────────────────────────────────

    private function buildMarketRegime(Collection $trades): array
    {
        // ATR from live candle data
        $ranges = array_map(
            fn($c) => (float) $c['high'] - (float) $c['low'],
            $this->fourHourCandles
        );
        $liveAtr = count($ranges) > 0
            ? array_sum($ranges) / count($ranges)
            : 0;

        // Compare live ATR to what the bot was seeing during today's trades
        $tradeAtrs  = $trades->pluck('atr_at_entry')->filter()->values()->toArray();
        $sessionAtr = count($tradeAtrs) > 0
            ? array_sum($tradeAtrs) / count($tradeAtrs)
            : $liveAtr;

        $atrTrend = match (true) {
            $liveAtr > $sessionAtr * 1.1 => 'expanding',
            $liveAtr < $sessionAtr * 0.9 => 'contracting',
            default                       => 'stable',
        };

        // Candle character breakdown
        $directional = 0;
        $indecision  = 0;
        foreach ($this->fourHourCandles as $candle) {
            $range    = (float) $candle['high'] - (float) $candle['low'];
            $body     = abs((float) $candle['open'] - (float) $candle['close']);
            $ratio    = $range > 0 ? ($body / $range) * 100 : 0;
            $ratio >= 40 ? $directional++ : $indecision++;
        }

        return [
            'symbol'                    => 'R_25',
            'timeframe'                 => '4H',
            'live_atr'                  => round($liveAtr, 5),
            'session_avg_atr'           => round($sessionAtr, 5),
            'atr_trend'                 => $atrTrend,
            'htf_bias'                  => $this->computeHtfBias(),
            'directional_candles_today' => $directional,
            'indecision_candles_today'  => $indecision,
        ];
    }

    private function computeHtfBias(): string
    {
        if (count($this->dailyCandles) < 5) {
            return 'unknown';
        }

        $recent = array_slice($this->dailyCandles, -5);
        $closes = array_map(fn($c) => (float) $c['close'], $recent);
        $first  = $closes[0];
        $last   = end($closes);

        if ($last > $first * 1.003) return 'bullish';
        if ($last < $first * 0.997) return 'bearish';

        return 'neutral';
    }

    // ─────────────────────────────────────────────────────────────────────
    // Layer 2 — Setup quality audit
    // ─────────────────────────────────────────────────────────────────────

    private function buildSetupQuality(Collection $trades): array
    {
        if ($trades->isEmpty()) {
            return [
                'setups_taken'     => 0,
                'avg_body_ratio'   => null,
                'avg_range_vs_atr' => null,
                'per_trade'        => [],
            ];
        }

        $rangesVsAtr = $trades
            ->filter(fn($t) => $t->atr_at_entry > 0)
            ->map(fn($t) => ($t->ref_candle_high - $t->ref_candle_low) / $t->atr_at_entry)
            ->values();

        return [
            'setups_taken'     => $trades->count(),
            'avg_body_ratio'   => round($trades->avg(fn($t) => $t->body_ratio), 1),
            'avg_range_vs_atr' => $rangesVsAtr->count() > 0
                ? round($rangesVsAtr->avg(), 3)
                : null,
            'per_trade'        => $trades->map(fn($t) => [
                'direction'    => $t->direction,
                'status'       => $t->status,
                'body_ratio'   => round($t->body_ratio, 1),
                'range_vs_atr' => $t->atr_at_entry > 0
                    ? round(($t->ref_candle_high - $t->ref_candle_low) / $t->atr_at_entry, 3)
                    : null,
            ])->values()->toArray(),
        ];
    }

    // ─────────────────────────────────────────────────────────────────────
    // Layer 3 — Outcome analysis
    // ─────────────────────────────────────────────────────────────────────

    private function buildOutcomes(Collection $trades): array
    {
        $completed = $trades->whereNotIn('status', ['open', 'cancelled']);

        if ($completed->isEmpty()) {
            return [
                'total_completed' => 0,
                'winners'         => 0,
                'losers'          => 0,
                'win_rate'        => 0,
                'profit_factor'   => null,
                'avg_winner_r'    => null,
                'avg_loser_r'     => null,
                'expectancy_r'    => null,
                'trades'          => [],
            ];
        }

        $winners     = $completed->filter->isWinner();
        $losers      = $completed->filter->isLoser();
        $grossProfit = $winners->sum('pnl');
        $grossLoss   = abs($losers->sum('pnl'));
        $wr          = $winners->count() / $completed->count();

        $avgWinR   = $winners->count() > 0
            ? round($winners->avg(fn($t) => $t->r_multiple ?? 0), 2)
            : null;
        $avgLoseR  = $losers->count() > 0
            ? round($losers->avg(fn($t) => $t->r_multiple ?? 0), 2)
            : null;
        $expectancy = ($avgWinR !== null && $avgLoseR !== null)
            ? round(($wr * $avgWinR) + ((1 - $wr) * $avgLoseR), 3)
            : null;

        return [
            'total_completed' => $completed->count(),
            'winners'         => $winners->count(),
            'losers'          => $losers->count(),
            'win_rate'        => round($wr * 100, 1),
            'profit_factor'   => $grossLoss > 0 ? round($grossProfit / $grossLoss, 2) : null,
            'avg_winner_r'    => $avgWinR,
            'avg_loser_r'     => $avgLoseR,
            'expectancy_r'    => $expectancy,
            'trades'          => $completed->map(fn($t) => [
                'direction'    => $t->direction,
                'status'       => $t->status,
                'body_ratio'   => round($t->body_ratio, 1),
                'sl_dist_atr'  => $t->sl_distance_in_atr
                    ? round($t->sl_distance_in_atr, 3)
                    : null,
                'r_achieved'   => $t->r_multiple ? round($t->r_multiple, 2) : null,
                'reached_tp1'  => in_array($t->status, ['tp1', 'tp2']),
            ])->values()->toArray(),
        ];
    }

    // ─────────────────────────────────────────────────────────────────────
    // Layer 4 — Stop diagnostics (richest signal for sl_atr_multiplier)
    // ─────────────────────────────────────────────────────────────────────

    private function buildStopDiagnostics(Collection $trades): array
    {
        $stopped = $trades->where('status', 'sl');

        if ($stopped->isEmpty()) {
            return [
                'total_stopped'   => 0,
                'avg_sl_dist_atr' => null,
                'tight_stops'     => 0,
                'loose_stops'     => 0,
                'per_stop'        => [],
                'note'            => 'No stopped trades today.',
            ];
        }

        $distances  = $stopped
            ->map(fn($t) => $t->sl_distance_in_atr)
            ->filter()
            ->values();

        $avgDist    = $distances->count() > 0 ? round($distances->avg(), 3) : null;
        $tightStops = $distances->filter(fn($d) => $d < 0.8)->count();
        $looseStops = $distances->filter(fn($d) => $d > 2.0)->count();

        $note = match (true) {
            $tightStops >= 3 =>
                "{$tightStops} of {$stopped->count()} stops hit within 0.8x ATR — "
                . "sl_atr_multiplier likely too tight for current R_25 volatility.",
            $tightStops >= 2 =>
                "{$tightStops} of {$stopped->count()} stops within 0.8x ATR — "
                . "monitor; may indicate sl_atr_multiplier needs raising.",
            $looseStops >= 2 =>
                "{$looseStops} stops hit beyond 2.0x ATR — "
                . "consider tightening sl_atr_multiplier to improve R:R.",
            default =>
                "SL distances appear normal (avg {$avgDist}x ATR).",
        };

        return [
            'total_stopped'   => $stopped->count(),
            'avg_sl_dist_atr' => $avgDist,
            'tight_stops'     => $tightStops,
            'loose_stops'     => $looseStops,
            'per_stop'        => $distances->map(fn($d) => round($d, 3))->toArray(),
            'note'            => $note,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────
    // Layer 5 — TP rate breakdown
    // ─────────────────────────────────────────────────────────────────────

    private function buildTpRates(Collection $trades): array
    {
        $completed = $trades->whereNotIn('status', ['open', 'cancelled']);
        $tp1Hits   = $completed->whereIn('status', ['tp1', 'tp2']);

        $tp1Rate = $completed->count() > 0
            ? round($tp1Hits->count() / $completed->count() * 100, 1)
            : null;

        // Average time-to-TP1 in 4H candles
        $tp1Times = $tp1Hits
            ->filter(fn($t) => $t->opened_at && $t->closed_at)
            ->map(fn($t) => $t->opened_at->diffInHours($t->closed_at) / 4)
            ->values();

        $avgCandlesToTp1 = $tp1Times->count() > 0
            ? round($tp1Times->avg(), 1)
            : null;

        // Pattern analysis: did any TP1 hits then reverse to SL on remainder?
        // (only relevant when tp2 was set — status would show 'sl' after partial)
        // We flag this as a note for the AI to consider for tp1_close_pct
        $tp1ThenSl = $trades->filter(
            fn($t) => $t->status === 'sl' && $t->pnl > 0
        )->count(); // Positive PnL on SL = partial TP1 was hit, remainder stopped

        return [
            'tp1_hit_rate'         => $tp1Rate,
            'avg_candles_to_tp1'   => $avgCandlesToTp1,
            'tp1_then_sl_count'    => $tp1ThenSl,
            'note'                 => $tp1ThenSl >= 2
                ? "{$tp1ThenSl} trades hit TP1 then reversed to SL on the remainder — "
                . "consider increasing tp1_close_pct to lock in more at the first target."
                : null,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────
    // Layer 6 — 3-cycle historical trend
    // ─────────────────────────────────────────────────────────────────────

    private function buildHistoricalCycles(int $days): array
    {
        $cycles = [];

        for ($i = 1; $i <= $days; $i++) {
            $date   = now()->subDays($i)->toDateString();
            $trades = $this->user->tradeLogs()
                ->whereDate('created_at', $date)
                ->whereNotIn('status', ['open', 'cancelled'])
                ->get();

            if ($trades->isEmpty()) {
                continue;
            }

            $winners     = $trades->filter->isWinner();
            $losers      = $trades->filter->isLoser();
            $grossProfit = $winners->sum('pnl');
            $grossLoss   = abs($losers->sum('pnl'));

            $stopped   = $trades->where('status', 'sl');
            $slDists   = $stopped->map(fn($t) => $t->sl_distance_in_atr)->filter();
            $avgSlDist = $slDists->count() > 0 ? round($slDists->avg(), 3) : null;

            $cycles[] = [
                'date'          => $date,
                'trades'        => $trades->count(),
                'win_rate'      => round($winners->count() / $trades->count() * 100, 1),
                'profit_factor' => $grossLoss > 0 ? round($grossProfit / $grossLoss, 2) : null,
                'expectancy_r'  => $this->computeExpectancy($trades),
                'avg_sl_dist'   => $avgSlDist,
            ];
        }

        return $cycles;
    }

    private function computeExpectancy(Collection $trades): ?float
    {
        $completed = $trades->whereNotIn('status', ['open', 'cancelled']);
        if ($completed->isEmpty()) return null;

        $winners = $completed->filter->isWinner();
        $losers  = $completed->filter->isLoser();
        $wr      = $winners->count() / $completed->count();

        $avgWinR  = $winners->count() > 0
            ? $winners->avg(fn($t) => $t->r_multiple ?? 0)
            : 0;
        $avgLoseR = $losers->count() > 0
            ? $losers->avg(fn($t) => $t->r_multiple ?? 0)
            : 0;

        return round(($wr * $avgWinR) + ((1 - $wr) * $avgLoseR), 3);
    }
}

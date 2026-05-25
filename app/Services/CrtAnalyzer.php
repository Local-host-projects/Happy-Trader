<?php

namespace App\Services;

use Carbon\Carbon;
use App\Models\Parameters;

class CrtAnalyzer
{
    // How close to the extreme price must be to qualify as a setup.
    // 0.30 means price must be in the top or bottom 30% of the range.
    private const ZONE_THRESHOLD = 0.30;

    // 4H candle open hours (UTC) that fall in active sessions for R_25.
    // Based on session data: Asia peak ~05:00 UTC, London 06:00-14:00 UTC,
    // US overlap 14:00-22:00 UTC. We skip 00:00 UTC (dead window).
    private const ACTIVE_HOURS_UTC = [4, 8, 12, 16, 20];

    private float $atr;

    public function __construct(
        private readonly array     $candles,
        private readonly Parameters $params,
    ) {}

    public function analyze(): ?array
    {
        // Need at least 16 candles: 14 for ATR + 1 reference + 1 forming
        if (count($this->candles) < 16) {
            return null;
        }

        // Session filter — only trade active windows
        if (! $this->isActiveSession()) {
            return null;
        }

        $this->atr = $this->calculateAtr(14);

        if ($this->atr == 0) {
            return null;
        }

        $count   = count($this->candles);
        $ref     = $this->candles[$count - 2]; // last completed candle
        $current = $this->candles[$count - 1]; // currently forming candle

        $refHigh      = (float) $ref['high'];
        $refLow       = (float) $ref['low'];
        $range        = $refHigh - $refLow;
        $bodySize     = abs((float) $ref['open'] - (float) $ref['close']);
        $bodyRatio    = $range > 0 ? ($bodySize / $range) * 100 : 0;
        $currentPrice = (float) $current['close'];

        // ── Range quality filters ──────────────────────────────────────────

        $minRange = ($this->params->min_range_atr_pct / 100) * $this->atr;
        $maxRange = ($this->params->max_range_atr_pct / 100) * $this->atr;

        if ($range < $minRange || $range > $maxRange) {
            return null;
        }

        // Reject doji / indecision candles — no clear structure to fade
        if ($bodyRatio < 30) {
            return null;
        }

        // ADX filter (optional)
        if ($this->params->adx_min_threshold !== null) {
            if ($this->calculateAdx(14) < (float) $this->params->adx_min_threshold) {
                return null;
            }
        }

        // ── Zone detection — the core CRT mean-reversion logic ────────────
        //
        // Upper zone: price in the top ZONE_THRESHOLD of the range → SELL
        //   Price has tagged the peak, expect reversion down to the low
        //
        // Lower zone: price in the bottom ZONE_THRESHOLD of the range → BUY
        //   Price has tagged the dip, expect reversion up to the high
        //
        // Dead zone (middle): no setup, price has no clear directional bias

        $upperZoneLine = $refLow + ($range * (1 - self::ZONE_THRESHOLD)); // e.g. 70% level
        $lowerZoneLine = $refLow + ($range * self::ZONE_THRESHOLD);        // e.g. 30% level

        if ($currentPrice >= $upperZoneLine) {
            $direction   = 'sell';
            $slPrice     = $refHigh + ($this->atr * (float) $this->params->sl_atr_multiplier);
            $tpPrice     = $refLow;  // target: opposite extreme (the dip)
        } elseif ($currentPrice <= $lowerZoneLine) {
            $direction   = 'buy';
            $slPrice     = $refLow - ($this->atr * (float) $this->params->sl_atr_multiplier);
            $tpPrice     = $refHigh; // target: opposite extreme (the peak)
        } else {
            return null; // price in dead zone — no setup
        }

        // ── R:R check ─────────────────────────────────────────────────────

        $tpDistance = abs($currentPrice - $tpPrice);
        $slDistance = abs($currentPrice - $slPrice);

        if ($slDistance == 0) {
            return null;
        }

        $rrRatio = $tpDistance / $slDistance;

        // Require at least 1:1 before entering
        if ($rrRatio < 1.0) {
            return null;
        }

        return [
            'direction'          => $direction,
            'atr'                => $this->atr,
            'range'              => $range,
            'body_ratio'         => $bodyRatio,
            'ref_high'           => $refHigh,
            'ref_low'            => $refLow,
            'ref_candle_open_at' => (int) $ref['epoch'],
            'current_price'      => $currentPrice,
            'sl_price'           => $slPrice,
            'tp1_price'          => $tpPrice,
            'sl_distance'        => $slDistance,
            'tp1_distance'       => $tpDistance,
            'rr_ratio'           => $rrRatio,
        ];
    }

    // ── Session filter ────────────────────────────────────────────────────

    private function isActiveSession(): bool
    {
        $hourUtc = Carbon::now('UTC')->hour;
        return in_array($hourUtc, self::ACTIVE_HOURS_UTC);
    }

    // ── ATR (simple average of last N candle ranges) ───────────────────────

    private function calculateAtr(int $period): float
    {
        $count  = count($this->candles);
        $start  = max(0, $count - $period - 1);
        $ranges = [];

        for ($i = $start; $i < $count - 1; $i++) {
            $ranges[] = (float) $this->candles[$i]['high']
                      - (float) $this->candles[$i]['low'];
        }

        return count($ranges) > 0
            ? array_sum($ranges) / count($ranges)
            : 0;
    }

    // ── Simplified ADX ────────────────────────────────────────────────────

    private function calculateAdx(int $period): float
    {
        $count = count($this->candles);

        if ($count < $period + 2) {
            return 0;
        }

        $trValues = [];
        $plusDm   = [];
        $minusDm  = [];

        for ($i = $count - $period - 1; $i < $count - 1; $i++) {
            $high      = (float) $this->candles[$i]['high'];
            $low       = (float) $this->candles[$i]['low'];
            $prevHigh  = (float) $this->candles[$i - 1]['high'];
            $prevLow   = (float) $this->candles[$i - 1]['low'];
            $prevClose = (float) $this->candles[$i - 1]['close'];

            $trValues[] = max(
                $high - $low,
                abs($high - $prevClose),
                abs($low - $prevClose)
            );

            $upMove   = $high - $prevHigh;
            $downMove = $prevLow - $low;

            $plusDm[]  = ($upMove > $downMove && $upMove > 0) ? $upMove : 0;
            $minusDm[] = ($downMove > $upMove && $downMove > 0) ? $downMove : 0;
        }

        $atr   = array_sum($trValues) / count($trValues);
        $pdi   = $atr > 0 ? (array_sum($plusDm) / count($plusDm)) / $atr * 100 : 0;
        $mdi   = $atr > 0 ? (array_sum($minusDm) / count($minusDm)) / $atr * 100 : 0;
        $diSum = $pdi + $mdi;

        return $diSum > 0 ? (abs($pdi - $mdi) / $diSum) * 100 : 0;
    }
}
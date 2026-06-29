<?php

namespace App\Services;

use Carbon\Carbon;
use App\Models\Parameters;
use Illuminate\Support\Facades\Log;

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
    // Need minimum candles
    if (count($this->candles) < 3) return null;

    $this->atr = $this->calculateAtr(14);
    if ($this->atr == 0) return null;

    $count        = count($this->candles);
    $ref          = $this->candles[$count - 2]; // previous completed candle
    $current      = $this->candles[$count - 1]; // forming candle

    $refHigh      = (float) $ref['high'];
    $refLow       = (float) $ref['low'];
    $midpoint     = ($refHigh + $refLow) / 2;
    $currentPrice = (float) $current['close'];

    // Direction — price always above or below mid, always a trade
    if ($currentPrice >= $midpoint) {
        $direction = 'sell';
        $slPrice   = $refHigh + ($this->atr * 1.5);
        $tpPrice   = $refLow;
    } else {
        $direction = 'buy';
        $slPrice   = $refLow - ($this->atr * 1.5);
        $tpPrice   = $refHigh;
    }

    $tpDistance = abs($currentPrice - $tpPrice);
    $slDistance = abs($currentPrice - $slPrice);

    // Prevent division by zero only
    if ($slDistance == 0) return null;

    $rrRatio = $tpDistance / $slDistance;

    return [
        'direction'          => $direction,
        'atr'                => $this->atr,
        'body_ratio'         => 100,
        'ref_high'           => $refHigh,
        'ref_low'            => $refLow,
        'ref_candle_open_at' => (int) $ref['epoch'],
        'current_price'      => $currentPrice,
        'sl_price'           => $slPrice,
        'tp1_price'          => $tpPrice,
        'sl_distance'        => $slDistance,
        'tp1_distance'       => $tpDistance,
        'rr_ratio'           => max(0.5, $rrRatio), // floor at 0.5 so TP is never tiny
        'strategy_mode'      => $this->params->strategy_mode ?? 'mean_reversion',
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

    private function getReferenceRange(): array
    {
        $count    = count($this->candles);
            $lookback = max(1, min(3, (int)($this->params->lookback_candles ?? 1)));

                $highs     = [];
                    $lows      = [];
                        $bodySizes = [];
                            $epoch     = null;

                                for ($i = 1; $i <= $lookback; $i++) {
                                        $c           = $this->candles[$count - 1 - $i];
                                                $highs[]     = (float) $c['high'];
                                                        $lows[]      = (float) $c['low'];
                                                                $range       = (float) $c['high'] - (float) $c['low'];
                                                                        $bodySizes[] = $range > 0
                                                                                    ? abs((float) $c['open'] - (float) $c['close']) / $range * 100
                                                                                                : 0;

                                                                                                        if ($epoch === null) {
                                                                                                                    $epoch = (int) $c['epoch'];
                                                                                                                            }
                                                                                                                                }

                                                                                                                                    return [
                                                                                                                                            'high'       => max($highs),
                                                                                                                                                    'low'        => min($lows),
                                                                                                                                                            'epoch'      => $epoch,
                                                                                                                                                                    'body_ratio' => array_sum($bodySizes) / count($bodySizes),
                                                                                                                                                                        ];
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

<?php

namespace App\Services;

use App\Models\Parameters;

class CrtAnalyzer
{
    public function __construct(
        private readonly array     $ticks,
        private readonly Parameters $params,
    ) {}

    public function analyze(): ?array
    {
        // Need at least 10 ticks to make a decision
        if (count($this->ticks) < 10) {
            return null;
        }

        $prices = array_map(fn($t) => (float)($t['price'] ?? $t['quote'] ?? 0), $this->ticks);
        $prices = array_filter($prices, fn($p) => $p > 0);
        $prices = array_values($prices);

        if (count($prices) < 10) {
            return null;
        }

        $currentPrice = end($prices);
        $average      = array_sum($prices) / count($prices);

        // Simple scalp direction: current tick vs average of last N ticks
        // Above average = momentum is up = BUY (CALL)
        // Below average = momentum is down = SELL (PUT)
        $direction = $currentPrice >= $average ? 'buy' : 'sell';

        return [
            'direction'     => $direction,
            'current_price' => $currentPrice,
            'average_price' => round($average, 5),
            'tick_count'    => count($prices),
            'strategy_mode' => $this->params->strategy_mode ?? 'scalp',
        ];
    }
}
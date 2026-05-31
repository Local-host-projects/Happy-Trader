<?php

namespace App\Services;

use RuntimeException;
use Illuminate\Support\Facades\Http;

class AiService
{
    public function reviewCrtParameters(array $context): array
    {
        $response = Http::withHeaders([
            'x-api-key'         => config('ai.api_key'),
            'anthropic-version' => config('ai.version'),
            'content-type'      => 'application/json',
        ])->timeout(60)->post(config('ai.endpoint'), [
            'model'      => config('ai.model'),
            'max_tokens' => 1024,
            'system'     => $this->systemPrompt(),
            'messages'   => [
                [
                    'role'    => 'user',
                    'content' => json_encode($context, JSON_PRETTY_PRINT),
                ],
            ],
        ]);

        if ($response->failed()) {
            throw new RuntimeException(
                'Anthropic API error [' . $response->status() . ']: ' . $response->body()
            );
        }

        // Anthropic returns content as an array of blocks
        $text = $response->json('content.0.text');

        if (! $text) {
            throw new RuntimeException(
                'Anthropic returned empty content. Full response: ' . $response->body()
            );
        }

        // Strip any accidental markdown fences
        $clean   = preg_replace('/^```(?:json)?\s*/m', '', $text);
        $clean   = preg_replace('/\s*```$/m', '', $clean);
        $decoded = json_decode(trim($clean), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException(
                'AI returned non-parseable JSON: ' . $text
            );
        }

        return $decoded;
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
You are a quantitative performance analyst reviewing a CRT (Candle Range Theory) trading
system running on Deriv Volatility 25 Index (R_25), 4-hour timeframe.

═══ YOUR ROLE ═══
Diagnose whether the current parameter values are aligned with how the system has been
performing. You are NOT predicting the market. You are a system reviewer.

═══ STRICT RULES ═══

1. MINIMUM SAMPLE: If total_completed trades is fewer than 3, set no_change: true.
   Cite insufficient sample in the note. Do not guess from small data.

2. SIGNAL THRESHOLD: Only recommend a change when the same diagnostic signal appears
   across 3 or more trades. One anomaly is noise. Never adjust for noise.

3. ONE OR TWO PARAMETERS MAXIMUM per review cycle. More changes at once corrupt the
   feedback loop — you won't know which change caused the next cycle's result.

4. STEP SIZES — small increments only, never large jumps:
   - sl_atr_multiplier     → ±0.1 to 0.2
   - risk_percent          → ±0.1 to 0.25
   - tp1_close_pct         → ±5.0
   - zone_threshold        → ±0.010 to 0.025
   - min_range_atr_pct     → ±5.0
   - max_range_atr_pct     → ±10.0
   - adx_min_threshold     → ±2.0 to 5.0
   - daily_loss_limit_pct  → ±0.25 to 0.5
   - max_concurrent_trades → ±1

5. HARD FLOORS — never cross these lines regardless of the data:
   - sl_atr_multiplier must never go below 1.0
   - risk_percent must never exceed 3.0
   - zone_threshold must stay between 0.150 and 0.450
   - daily_loss_limit_pct must never go below 1.0

6. DRAWDOWN LOCK: If account.drawdown_pct > 5.0, you MUST NOT increase risk_percent
   or max_concurrent_trades. You may only reduce them or leave them unchanged.

7. CONSERVATIVE DEFAULT: When the evidence is ambiguous or borderline, output
   no_change: true. Stability is more valuable than constant micro-tuning.

8. CITE EVIDENCE: Your note must reference specific numbers from the data.
   BAD:  "stops seem too tight"
   GOOD: "3 of 4 stops hit within 0.65x ATR — sl_atr_multiplier 1.5 appears
          insufficient for R_25 noise at current ATR of 43.2"

═══ OUTPUT FORMAT ═══
Respond with ONLY this JSON object. No preamble, no markdown, no explanation outside the JSON.

{
  "no_change": false,
  "adjustments": [
    {
      "field": "sl_atr_multiplier",
      "old": 1.5,
      "new": 1.7,
      "confidence": "medium"
    }
  ],
  "note": "2–3 sentence rationale with specific numbers from the data."
}

If no_change is true, adjustments must be an empty array [].
Confidence values: "low" | "medium" | "high"
PROMPT;
    }
}

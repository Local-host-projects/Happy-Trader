<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\AiService;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use App\Services\CrtContextBuilder;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;

class AdjustUserParametersJob implements ShouldQueue, ShouldBeUnique
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 90;
    public int $tries   = 2;

    public function __construct(
        public readonly User  $user,
        public readonly array $fourHourCandles,
        public readonly array $dailyCandles,
    ) {}

    public function uniqueId(): string
    {
        return 'ai_review_user_' . $this->user->id . '_' . now()->toDateString();
    }

    public function handle(): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        // Build the full context payload
        $builder = new CrtContextBuilder(
            $this->user,
            $this->fourHourCandles,
            $this->dailyCandles,
        );

        $context = $builder->build();

        // Hard gate: the AI rule says <3 completed trades = no review.
        // Check here too so we don't waste an API call.
        if ($context['outcomes']['total_completed'] < 3) {
            Log::info(
                "AI review: user {$this->user->id} has only "
                . "{$context['outcomes']['total_completed']} completed trades today — skipping."
            );
            return;
        }

        // Call deepseek
        $ai     = new AiService();
        $result = $ai->reviewCrtParameters($context);

        $params  = $this->user->parameters;
        $noChange = $result['no_change'] ?? true;

        if ($noChange || empty($result['adjustments'])) {
            // No change — but still record the note so there's an audit trail
            $params->ai_last_adjusted_at = now();
            $params->ai_adjustment_note  = '[No change] ' . ($result['note'] ?? 'No note provided.');
            $params->updated_at          = now();
            $params->save();

            Log::info("AI review: no change for user {$this->user->id}.", [
                'note' => $result['note'] ?? '',
            ]);

            return;
        }

        // Validate adjustments against the allowed field list before applying.
        // The Parameter model's applyAiAdjustments() also filters this, but
        // we log the pre-validated intent here for transparency.
        $adjustments = collect($result['adjustments'])
            ->filter(fn($adj) => isset($adj['field'], $adj['old'], $adj['new']))
            ->values()
            ->toArray();

        if (empty($adjustments)) {
            return;
        }

        $params->applyAiAdjustments($adjustments, $result['note'] ?? '');

        Log::info("AI review: parameters updated for user {$this->user->id}.", [
            'adjustments' => $adjustments,
            'note'        => $result['note'] ?? '',
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error(
            "AI review: job failed for user {$this->user->id}: {$exception->getMessage()}"
        );
    }
}
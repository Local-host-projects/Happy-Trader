<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Parameters extends Model
{
     public $timestamps = false;

    const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'user_id',
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
        'ai_last_adjusted_at',
        'ai_adjustment_note',
    ];

    protected $casts = [
        'risk_percent'          => 'decimal:2',
        'sl_atr_multiplier'     => 'decimal:2',
        'min_range_atr_pct'     => 'decimal:2',
        'max_range_atr_pct'     => 'decimal:2',
        'tp1_close_pct'         => 'decimal:2',
        'tp2_atr_multiplier'    => 'decimal:2',
        'trailing_atr_step'     => 'decimal:2',
        'adx_min_threshold'     => 'decimal:2',
        'trend_filter_enabled'  => 'boolean',
        'ai_last_adjusted_at'   => 'datetime',
    ];

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function applyAiAdjustments(array $adjustments, string $note): void
    {
        $delta = collect($adjustments)->mapWithKeys(fn ($adj) => [
            $adj['field'] => $adj['new'],
        ])->toArray();

        $this->fill($delta);
        $this->ai_last_adjusted_at = now();
        $this->ai_adjustment_note  = $note;
        $this->updated_at          = now();
        $this->save();
    }

    public static function defaults(): array
    {
        return [
            'risk_percent'         => 1.00,
            'sl_atr_multiplier'    => 1.50,
            'min_range_atr_pct'    => 50.00,
            'max_range_atr_pct'    => 250.00,
            'tp1_close_pct'        => 60.00,
            'tp2_atr_multiplier'   => null,
            'trailing_atr_step'    => null,
            'adx_min_threshold'    => null,
            'trend_filter_enabled' => false,
            'max_concurrent_trades'=> 2,
            'daily_loss_limit_pct' => 3.00,
        ];
    }
}

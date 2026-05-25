<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountSnapshots extends Model
{
      public $timestamps = false;

    protected $fillable = [
        'user_id',
        'balance',
        'equity',
        'margin_used',
        'open_trades_count',
        'captured_at',
    ];

    protected $casts = [
        'balance'           => 'decimal:2',
        'equity'            => 'decimal:2',
        'margin_used'       => 'decimal:2',
        'open_trades_count' => 'integer',
        'captured_at'       => 'datetime',
    ];

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function peakEquityFor(int $userId): float
    {
        return static::where('user_id', $userId)->max('equity') ?? 0;
    }

    public static function currentDrawdownFor(int $userId): float
    {
        $peak   = static::peakEquityFor($userId);
        $latest = static::where('user_id', $userId)
                        ->latest('captured_at')
                        ->value('equity');

        if (! $peak || ! $latest) return 0;

        return (($peak - $latest) / $peak) * 100;
    }
}

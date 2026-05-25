<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    { 
        Schema::create('parameters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();

            $table->decimal('risk_percent', 4, 2)->default(1.00);
            $table->decimal('sl_atr_multiplier', 4, 2)->default(1.50);
            $table->decimal('min_range_atr_pct', 5, 2)->default(50.00);
            $table->decimal('max_range_atr_pct', 5, 2)->default(250.00);
            $table->decimal('tp1_close_pct', 5, 2)->default(60.00);
            $table->decimal('tp2_atr_multiplier', 4, 2)->nullable();
            $table->decimal('trailing_atr_step', 4, 2)->nullable();
            $table->decimal('adx_min_threshold', 5, 2)->nullable();
            $table->boolean('trend_filter_enabled')->default(false);
            $table->tinyInteger('max_concurrent_trades')->default(2);
            $table->decimal('daily_loss_limit_pct', 4, 2)->default(3.00);

            $table->timestamp('ai_last_adjusted_at')->nullable();
            $table->text('ai_adjustment_note')->nullable();

            $table->timestamp('updated_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('parameters');
    }
};

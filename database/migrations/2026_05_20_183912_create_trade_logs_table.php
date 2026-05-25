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
        Schema::create('trade_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->enum('direction', ['buy', 'sell']);
            $table->decimal('lot_size', 10, 4);
            $table->decimal('entry_price', 12, 5);
            $table->decimal('sl_price', 12, 5);
            $table->decimal('tp1_price', 12, 5);
            $table->decimal('tp2_price', 12, 5)->nullable();
            $table->decimal('close_price', 12, 5)->nullable();
            $table->decimal('pnl', 12, 4)->nullable();
            $table->enum('status', ['open', 'tp1', 'tp2', 'sl', 'be', 'cancelled'])->default('open');

            $table->timestamp('ref_candle_open_at');
            $table->decimal('ref_candle_high', 12, 5);
            $table->decimal('ref_candle_low', 12, 5);
            $table->decimal('atr_at_entry', 10, 5);

            $table->timestamp('opened_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trade_logs');
    }
};

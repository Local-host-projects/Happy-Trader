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
       Schema::create('account_snapshots', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();

    $table->decimal('balance', 14, 2);
    $table->decimal('equity', 14, 2);
    $table->decimal('margin_used', 14, 2)->nullable();
    $table->integer('open_trades_count')->default(0);

    $table->timestamp('captured_at')->useCurrent();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('account_snapshots');
    }
};

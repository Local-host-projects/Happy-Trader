<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trade_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('deriv_contract_id')->nullable()->after('user_id');
            $table->index('deriv_contract_id');
        });
    }

    public function down(): void
    {
        Schema::table('trade_logs', function (Blueprint $table) {
            $table->dropColumn('deriv_contract_id');
        });
    }
};
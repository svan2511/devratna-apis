<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Payment audit trail — every failed payment keeps its reason,
     * every paid order keeps its confirmation time. No silent money.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->text('failure_reason')->nullable()->after('status');
            $table->timestamp('paid_at')->nullable()->after('failure_reason');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['failure_reason', 'paid_at']);
        });
    }
};

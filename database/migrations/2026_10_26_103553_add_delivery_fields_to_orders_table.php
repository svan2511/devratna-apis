<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Assignment + rider-owned timestamps. Fulfillment status remains the
     * single customer-facing journey (ready -> out_for_delivery -> delivered).
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('delivery_partner_id')
                ->nullable()
                ->after('user_id')
                ->constrained('delivery_partners')
                ->nullOnDelete();
            $table->timestamp('rider_assigned_at')->nullable()->after('ready_at');
            $table->timestamp('rider_accepted_at')->nullable()->after('rider_assigned_at');
            $table->timestamp('picked_up_at')->nullable()->after('rider_accepted_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('delivery_partner_id');
            $table->dropColumn(['rider_assigned_at', 'rider_accepted_at', 'picked_up_at']);
        });
    }
};

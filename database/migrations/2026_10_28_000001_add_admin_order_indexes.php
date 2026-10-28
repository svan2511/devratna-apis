<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Admin kitchen list paid orders + fulfillment status par filter karke
     * latest-first padhta hai; 8s polling par ye index DB load kam rakhta hai.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->index(
                ['status', 'fulfillment_status', 'created_at'],
                'orders_admin_queue_index',
            );
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_admin_queue_index');
        });
    }
};

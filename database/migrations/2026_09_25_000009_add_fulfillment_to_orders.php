<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kitchen workflow is separate from payment status.
     * payment status: pending → paid | failed (Razorpay, existing)
     * fulfillment: new → preparing → ready → delivered | cancelled (admin)
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('fulfillment_status', 20)->default('new')->after('status');
            $table->text('kitchen_note')->nullable()->after('fulfillment_status');
            $table->timestamp('ready_at')->nullable()->after('paid_at');
            $table->timestamp('delivered_at')->nullable()->after('ready_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['fulfillment_status', 'kitchen_note', 'ready_at', 'delivered_at']);
        });
    }
};

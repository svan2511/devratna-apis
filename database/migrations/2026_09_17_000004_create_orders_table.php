<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Customer orders placed from the mobile app (Razorpay prepaid).
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Snapshot of lines: [{id, name, portion, qty, unit}]
            $table->json('items');
            $table->unsignedInteger('subtotal')->default(0);
            // total = subtotal + flat delivery charge.
            $table->unsignedInteger('total')->default(0);
            // pending → paid | failed
            $table->string('status', 20)->default('pending');
            $table->string('razorpay_order_id')->nullable()->unique();
            $table->string('razorpay_payment_id')->nullable();
            // Customer location at order time (geofence check).
            $table->decimal('customer_lat', 10, 7)->nullable();
            $table->decimal('customer_lng', 10, 7)->nullable();
            $table->unsignedInteger('distance_m')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};

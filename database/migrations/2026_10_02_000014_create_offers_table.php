<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Time-bound offers — discount and/or free item, auto-applied at billing.
     * Ek order pe best ek offer lagti hai (sabse bada fayda).
     */
    public function up(): void
    {
        Schema::create('offers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('description', 200)->nullable();
            // all = poora menu | category = ek category (slug) | item = ek dish (id as string)
            $table->string('target_type', 20)->default('all');
            $table->string('target_value', 50)->nullable();
            // none = sirf free item | percent = % off | flat = ₹ off (eligible lines pe)
            $table->string('discount_type', 20)->default('none');
            $table->unsignedInteger('discount_value')->default(0);
            // Free item — menu_items.id (available hona chahiye, warna offer skip).
            $table->foreignId('free_item_id')->nullable()->constrained('menu_items')->nullOnDelete();
            // Is food subtotal se kam pe offer nahi lagegi (0 = koi shart nahi).
            $table->unsignedInteger('min_order')->default(0);
            // Null = us taraf se khula (hamesha se / hamesha tak).
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offers');
    }
};

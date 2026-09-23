<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menu catalogue — plain Eloquent tables, no engine-specific features,
     * so SQLite / MySQL / Postgres all work unchanged.
     */
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            // Printed label as-is ("₹40 / 70", "MRP").
            $table->string('price_label');
            // First numeric price in rupees (0 for MRP).
            $table->unsignedInteger('price_value')->default(0);
            $table->boolean('is_veg')->default(true);
            $table->boolean('is_bestseller')->default(false);
            // Local asset key for the app (e.g. "momos"), null = monogram tile.
            $table->string('image_key')->nullable();
            $table->boolean('is_available')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['category_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_items');
        Schema::dropIfExists('categories');
    }
};

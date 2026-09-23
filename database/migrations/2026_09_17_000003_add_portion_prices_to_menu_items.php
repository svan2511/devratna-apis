<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Half / Full portion prices — parsed from the printed price label
     * ("₹210 / 320" → half 210, full 320; single price → half only).
     */
    public function up(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->unsignedInteger('half_price')->nullable()->after('price_value');
            $table->unsignedInteger('full_price')->nullable()->after('half_price');
        });
    }

    public function down(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->dropColumn(['half_price', 'full_price']);
        });
    }
};

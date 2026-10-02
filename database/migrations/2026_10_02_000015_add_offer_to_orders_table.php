<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Applied offer ka hisaab order pe — bill hamesha audit-ready rahe.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedInteger('discount')->default(0)->after('subtotal');
            $table->foreignId('offer_id')->nullable()->after('discount')->constrained('offers')->nullOnDelete();
            $table->string('offer_name', 100)->nullable()->after('offer_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('offer_id');
            $table->dropColumn(['discount', 'offer_name']);
        });
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Flat discount ka scope — poore order pe ek baar, ya har eligible dish pe.
     */
    public function up(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            $table->string('discount_scope', 10)->default('order')->after('discount_value');
        });
    }

    public function down(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            $table->dropColumn('discount_scope');
        });
    }
};

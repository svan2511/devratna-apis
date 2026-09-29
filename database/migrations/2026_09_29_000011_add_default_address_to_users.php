<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Profile me saved default landmark address — checkout me auto-fill hota hai.
     * Delivery eligibility ab bhi live GPS se decide hoti hai.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('default_address')->nullable()->after('phone');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('default_address');
        });
    }
};

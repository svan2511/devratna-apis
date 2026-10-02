<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Home offer banners — admin panel se control hote hain.
     * Koi row nahi = app bundled brand slides dikhati hai (design kabhi nahi tootega).
     */
    public function up(): void
    {
        Schema::create('banners', function (Blueprint $table) {
            $table->id();
            $table->string('title', 100);
            $table->string('subtitle', 150)->nullable();
            $table->string('pill_text', 40)->nullable();
            // Menu category key ('thali', 'chinese', ...) ya 'all' — tap pe wahi khulega. Null = no action.
            $table->string('target', 50)->nullable();
            // Preset theme key — app isse Brand colors map karti hai (espresso/terracotta/gold/cream/forest).
            $table->string('theme', 20)->default('espresso');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banners');
    }
};

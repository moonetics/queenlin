<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('discord_settings', function (Blueprint $table) {
            $table->id();
            $table->text('schedule_webhook_url')->nullable();
            $table->text('detail_webhook_url')->nullable();
            $table->boolean('auto_schedule_enabled')->default(true);
            $table->boolean('auto_detail_enabled')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('discord_settings');
    }
};

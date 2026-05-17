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
        Schema::table('schedule_dispatches', function (Blueprint $table): void {
            $table->string('webhook_hash')->nullable()->after('discord_message_ids');
        });

        Schema::table('event_detail_dispatches', function (Blueprint $table): void {
            $table->string('webhook_hash')->nullable()->after('discord_message_ids');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('schedule_dispatches', function (Blueprint $table): void {
            $table->dropColumn('webhook_hash');
        });

        Schema::table('event_detail_dispatches', function (Blueprint $table): void {
            $table->dropColumn('webhook_hash');
        });
    }
};

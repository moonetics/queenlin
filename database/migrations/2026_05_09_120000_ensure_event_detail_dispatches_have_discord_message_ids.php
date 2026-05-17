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
        if (! Schema::hasColumn('event_detail_dispatches', 'discord_message_ids')) {
            Schema::table('event_detail_dispatches', function (Blueprint $table): void {
                $table->json('discord_message_ids')->nullable()->after('discord_message_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('event_detail_dispatches', 'discord_message_ids')) {
            Schema::table('event_detail_dispatches', function (Blueprint $table): void {
                $table->dropColumn('discord_message_ids');
            });
        }
    }
};

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
        Schema::create('schedule_dispatches', function (Blueprint $table): void {
            $table->id();
            $table->date('month')->unique();
            $table->string('discord_message_id')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('updated_sent_at')->nullable();
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedule_dispatches');
    }
};

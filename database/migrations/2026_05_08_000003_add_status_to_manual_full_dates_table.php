<?php

use App\Models\ManualFullDate;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('manual_full_dates', function (Blueprint $table): void {
            $table->string('status')->default(ManualFullDate::STATUS_FULL)->after('event_date');
        });
    }

    public function down(): void
    {
        Schema::table('manual_full_dates', function (Blueprint $table): void {
            $table->dropColumn('status');
        });
    }
};

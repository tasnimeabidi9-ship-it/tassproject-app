<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads_queue', function (Blueprint $table) {
            $table->foreignId('profile_id')->nullable()
                ->constrained('target_profiles')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('leads_queue', function (Blueprint $table) {
            $table->dropConstrainedForeignId('profile_id');
        });
    }
};

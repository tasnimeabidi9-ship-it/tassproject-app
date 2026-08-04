<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_actions_log', function (Blueprint $table) {
            $table->foreignId('message_id')->nullable()
                ->constrained('messages')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ai_actions_log', function (Blueprint $table) {
            $table->dropConstrainedForeignId('message_id');
        });
    }
};

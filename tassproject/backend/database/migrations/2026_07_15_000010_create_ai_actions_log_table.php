<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_actions_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->string('action_type'); // e.g. send_email, send_sms, classify_reply, score_lead
            $table->string('channel')->nullable(); // email / sms / null
            $table->text('content_preview')->nullable();
            $table->string('status')->default('scheduled');
            // scheduled / sent / delivered / opened / replied / failed / cancelled
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'scheduled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_actions_log');
    }
};

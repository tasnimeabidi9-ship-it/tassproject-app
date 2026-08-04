<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained('campaigns')->nullOnDelete();
            $table->string('channel'); // email / sms
            $table->string('direction'); // out / in
            $table->string('subject')->nullable();
            $table->text('body')->nullable();
            $table->string('status')->default('scheduled');
            // scheduled / sent / delivered / opened / clicked / replied / failed
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('clicked_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['contact_id', 'campaign_id']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};

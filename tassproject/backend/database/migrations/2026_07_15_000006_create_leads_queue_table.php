<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads_queue', function (Blueprint $table) {
            $table->id();
            $table->string('raw_name')->nullable();
            $table->string('domain')->nullable();
            $table->string('email')->nullable();
            $table->unsignedTinyInteger('confidence_score')->nullable();
            $table->unsignedTinyInteger('match_score')->nullable();
            $table->string('source_url')->nullable();
            $table->string('approval_status')->default('pending_review');
            // pending_review / approved / rejected
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('found_at')->nullable();
            $table->timestamps();

            $table->index('domain');
            $table->index('email');
            $table->index('approval_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads_queue');
    }
};

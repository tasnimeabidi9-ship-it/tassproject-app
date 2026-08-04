<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scrape_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->nullable()->constrained('target_profiles')->nullOnDelete();
            $table->unsignedInteger('queries_run')->default(0);
            $table->unsignedInteger('leads_found')->default(0);
            $table->unsignedInteger('emails_enriched')->default(0);
            $table->string('status')->default('pending'); // pending/running/completed/failed
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scrape_jobs');
    }
};

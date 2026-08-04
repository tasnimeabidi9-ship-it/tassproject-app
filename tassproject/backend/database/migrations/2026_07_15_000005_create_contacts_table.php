<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('company')->nullable();
            $table->string('website')->nullable();
            $table->string('domain')->nullable();
            $table->string('source_url')->nullable();
            $table->unsignedTinyInteger('match_score')->default(0);
            $table->string('stage')->default('lead_found');
            // lead_found / approved / contacted / replied / interested / won / lost
            $table->string('status')->default('active'); // active/unsubscribed/bounced
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('email');
            $table->index('domain');
            $table->index('stage');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained('messages')->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->text('body');
            $table->string('sentiment')->nullable();
            // positive / interested / negative / out_of_office / unsubscribe
            $table->boolean('read_by_admin')->default(false);
            $table->timestamps();

            $table->index('read_by_admin');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('replies');
    }
};

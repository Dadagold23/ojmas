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
        Schema::create('crossref_deposit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->string('doi')->nullable();
            $table->json('payload')->nullable();
            $table->integer('response_status')->nullable();
            $table->json('response_body')->nullable();
            $table->foreignId('initiated_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('initiated_at')->useCurrent();
            $table->string('event_id')->nullable();
            $table->string('status')->default('pending'); // pending, success, error
            $table->text('error_message')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('crossref_deposit_logs');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('revision_rounds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('round_number');
            $table->enum('status', ['open', 'closed', 'superseded'])->default('open');
            $table->foreignId('opened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('decision_id')->nullable()->constrained('editorial_decisions')->nullOnDelete();
            $table->longText('author_response')->nullable();
            $table->timestamps();

            $table->unique(['submission_id', 'round_number'], 'revision_rounds_submission_round_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('revision_rounds');
    }
};
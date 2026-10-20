<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('manuscript_validation_issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('manuscript_validation_id')->constrained()->cascadeOnDelete();
            $table->enum('severity', ['error', 'warning', 'info'])->default('warning');
            $table->string('category', 60);
            $table->string('code', 80);
            $table->string('rule_id', 80)->nullable();
            $table->string('rule_version', 40)->nullable();
            $table->json('location_json')->nullable();
            $table->text('message');
            $table->text('suggestion')->nullable();
            $table->boolean('was_auto_fixed')->default(false);
            $table->boolean('requires_author_action')->default(false);
            $table->timestamps();

            $table->index(['manuscript_validation_id', 'severity'], 'mvi_validation_severity_index');
            $table->index('code', 'mvi_code_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manuscript_validation_issues');
    }
};
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('manuscript_citations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('manuscript_document_id')->constrained()->cascadeOnDelete();
            $table->text('raw_text');
            $table->string('author_token', 255)->nullable();
            $table->char('year', 4)->nullable();
            $table->string('locator', 80)->nullable();
            $table->unsignedInteger('paragraph_index')->nullable();
            $table->string('match_key', 191)->nullable();
            $table->timestamps();

            $table->index(['manuscript_document_id', 'match_key']);
        });

        Schema::create('manuscript_references', function (Blueprint $table) {
            $table->id();
            $table->foreignId('manuscript_document_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('ordinal')->default(0);
            $table->longText('raw_text');
            $table->text('author_raw')->nullable();
            $table->char('year', 4)->nullable();
            $table->text('title_raw')->nullable();
            $table->text('source_raw')->nullable();
            $table->string('doi_raw', 191)->nullable();
            $table->text('url_raw')->nullable();
            $table->string('pages_raw', 120)->nullable();
            $table->json('parsed_json')->nullable();
            $table->string('match_key', 191)->nullable();
            $table->foreignId('duplicate_of_id')->nullable()->constrained('manuscript_references')->nullOnDelete();
            $table->enum('verification_status', [
                'unverified',
                'verified_external',
                'unverified_suggestion',
            ])->default('unverified');
            $table->timestamps();

            $table->index(['manuscript_document_id', 'match_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manuscript_references');
        Schema::dropIfExists('manuscript_citations');
    }
};
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('submissions', function (Blueprint $table) {
            $table->id();
            $table->string('submission_no')->unique();
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->longText('abstract');
            $table->string('keywords')->nullable();
            $table->string('subject_area')->nullable();
            $table->enum('status', [
                'draft','pending_payment','submitted','screening','under_review',
                'revision_requested','accepted','rejected','published'
            ])->default('submitted');
            $table->boolean('is_corresponding_author')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submissions');
    }
};

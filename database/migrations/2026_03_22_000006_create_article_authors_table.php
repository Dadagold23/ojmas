<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('article_authors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->string('full_name');
            $table->string('email')->nullable();
            $table->string('institution')->nullable();
            $table->string('country')->nullable();
            $table->boolean('is_corresponding')->default(false);
            $table->unsignedInteger('author_order')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('article_authors');
    }
};

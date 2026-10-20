<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('journal_settings', function (Blueprint $table) {
            $table->id();
            $table->string('site_name')->default('OJMAS Journal');
            $table->string('acronym')->nullable();
            $table->string('issn_print')->nullable();
            $table->string('issn_online')->nullable();
            $table->string('publisher_name')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('support_phone')->nullable();
            $table->decimal('submission_fee', 12, 2)->default(0);
            $table->decimal('publication_fee', 12, 2)->default(0);
            $table->text('address')->nullable();
            $table->string('logo')->nullable();
            $table->string('favicon')->nullable();
            $table->text('copyright_text')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_settings');
    }
};

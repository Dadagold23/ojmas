<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ORCID OAuth integration.
 *
 * The pre-existing `users.orcid` and `article_authors.orcid` columns are kept
 * as the display value. Verification is recorded separately so a manually
 * typed identifier can never be mistaken for an authenticated one.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('orcid_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            // Nulled on disconnect so the identifier can be claimed again,
            // while the audit trail keeps the historical value.
            $table->string('orcid_id', 19)->nullable()->unique();
            $table->string('orcid_name')->nullable();
            // Laravel's encrypted cast writes ciphertext into these columns.
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->string('scope')->nullable();
            $table->string('status', 20)->default('disconnected');
            $table->timestamp('authenticated_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });

        Schema::table('article_authors', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('submission_id')
                ->constrained()->nullOnDelete();
            $table->timestamp('orcid_verified_at')->nullable()->after('orcid');
        });

        Schema::create('orcid_coauthor_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_author_id')->constrained()->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            // Encrypted so the corresponding author can re-open the claim link.
            $table->text('token');
            $table->string('email')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('journal_settings', function (Blueprint $table) {
            $table->boolean('orcid_enabled')->default(false);
            $table->boolean('orcid_sandbox')->default(false);
            $table->boolean('orcid_required_for_new_authors')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('journal_settings', function (Blueprint $table) {
            $table->dropColumn(['orcid_enabled', 'orcid_sandbox', 'orcid_required_for_new_authors']);
        });

        Schema::dropIfExists('orcid_coauthor_claims');

        Schema::table('article_authors', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn('orcid_verified_at');
        });

        Schema::dropIfExists('orcid_connections');
    }
};

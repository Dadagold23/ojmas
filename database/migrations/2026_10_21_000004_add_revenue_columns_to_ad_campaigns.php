<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Addendum Module 4 — Advertisement revenue and campaign analytics.
 *
 * Adds nullable revenue-control columns to `ad_campaigns` only: the agreed
 * price, currency, agreement/invoice references, payment status and payment
 * date. Every column is nullable and additive — no column is altered, no
 * existing value is touched, and campaigns without a recorded price behave
 * exactly as before (announcement campaigns legitimately have none).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('ad_campaigns', function (Blueprint $table) {
            $table->decimal('agreed_price', 12, 2)->nullable()->after('sponsor_fee_note');
            $table->char('currency', 3)->nullable()->after('agreed_price');
            $table->string('agreement_reference', 120)->nullable()->after('currency');
            $table->string('invoice_reference', 120)->nullable()->after('agreement_reference');
            // null = price not yet recorded; agreed | invoiced | paid
            $table->string('payment_status', 20)->nullable()->after('invoice_reference');
            $table->date('paid_at')->nullable()->after('payment_status');
        });
    }

    public function down(): void
    {
        Schema::table('ad_campaigns', function (Blueprint $table) {
            $table->dropColumn([
                'agreed_price', 'currency', 'agreement_reference',
                'invoice_reference', 'payment_status', 'paid_at',
            ]);
        });
    }
};

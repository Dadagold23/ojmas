<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Module 6 — one-time migration of push subscription secrets to
 * encrypted-at-rest storage. run once, right after deploying the release
 * that introduces the `encrypted` casts, and only if a previous release
 * already stored subscriptions (clear-text). The command detects
 * clear-text values, encrypts them with the application key, and never
 * invents data: only rows whose secret column does not look like an
 * encrypted payload are touched.
 */
class PushReencryptSecrets extends Command
{
    protected $signature = 'push:reencrypt-secrets {--dry-run : Report what would change without changing it}';

    protected $description = 'Encrypt stored push subscription endpoints/keys that pre-date encryption at rest';

    public function handle(): int
    {
        $fixed = 0;
        $already = 0;
        $detailed = $this->option('dry-run');

        foreach (['endpoint', 'p256dh', 'auth'] as $column) {
            $rows = DB::table('push_subscriptions')->whereNotNull($column)->get(['id', $column]);

            foreach ($rows as $row) {
                $raw = (string) ($row->{$column} ?? '');

                if ($raw === '') {
                    continue;
                }

                // Encrypted payloads decrypt to JSON, so their base64 output
                // starts with the JSON opening brace — anything else is a
                // pre-encryption clear-text value.
                if (! str_starts_with($raw, 'eyJ')) {
                    $fixed++;
                    $this->line("subscription {$row->id}: {$column} stored in the clear — migrating.");
                    if (! $detailed) {
                        DB::table('push_subscriptions')
                            ->where('id', $row->id)
                            ->update([$column => Crypt::encryptString($raw)]);
                    }
                } else {
                    $already++;
                }
            }
        }

        $this->info($detailed
            ? "Dry run: {$fixed} clear-text value(s) found, {$already} already encrypted."
            : "Done: {$fixed} value(s) encrypted, {$already} already encrypted.");

        return self::SUCCESS;
    }
}

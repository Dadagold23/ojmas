<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Generates a VAPID key pair for Web Push. The keys are printed as ready
 * `.env` lines; nothing is written or stored server-side beyond the console
 * output. The private key must go into the production .env only.
 */
class PushGenerateKeys extends Command
{
    protected $signature = 'push:keys {--write : Append the keys to the local .env file if not already configured}';

    protected $description = 'Generate a VAPID key pair (Web Push) and print it as .env lines';

    public function handle(): int
    {
        $keys = \Minishlink\WebPush\VAPID::createVapidKeys();

        $public = $keys['publicKey'];
        $private = $keys['privateKey'];

        $this->line('');
        $this->line('PUSH_VAPID_PUBLIC_KEY='.$public);
        $this->line('PUSH_VAPID_PRIVATE_KEY='.$private);
        $this->line('PUSH_VAPID_SUBJECT=https://ojmaspublish.online');
        $this->line('');

        if (! $this->option('write')) {
            $this->comment('Copy these into the environment (.env on the host); the public key is also the one the browser uses.');

            return self::SUCCESS;
        }

        $envPath = base_path('.env');

        if (! is_file($envPath)) {
            $this->error('No .env file found in the project root; copy the lines above manually.');

            return self::FAILURE;
        }

        $env = file_get_contents($envPath);

        if ($env === false) {
            $this->error('.env is not readable.');

            return self::FAILURE;
        }

        if (str_contains($env, 'PUSH_VAPID_PUBLIC_KEY')) {
            $this->comment('.env already configures PUSH_VAPID_PUBLIC_KEY — leaving it untouched.');

            return self::SUCCESS;
        }

        $block = PHP_EOL.'# Web Push (Addendum Module 3)'.PHP_EOL
            .'PUSH_VAPID_PUBLIC_KEY='.$public.PHP_EOL
            .'PUSH_VAPID_PRIVATE_KEY='.$private.PHP_EOL
            .'PUSH_VAPID_SUBJECT=https://ojmaspublish.online'.PHP_EOL;

        file_put_contents($envPath, $env.(str_ends_with($env, PHP_EOL) ? '' : PHP_EOL).$block);
        $this->info('Keys appended to .env.');

        return self::SUCCESS;
    }
}

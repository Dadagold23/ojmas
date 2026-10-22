<?php

/**
 * Web Push (VAPID) configuration — Addendum Module 3.
 *
 * Keys come from the environment only. The private key is never rendered in
 * any response; only the public key reaches the browser (necessary for the
 * subscription step defined by the standard).
 */
return [
    'vapid' => [
        'subject' => env('PUSH_VAPID_SUBJECT', 'https://ojmaspublish.online'),
        'public_key' => env('PUSH_VAPID_PUBLIC_KEY', ''),
        'private_key' => env('PUSH_VAPID_PRIVATE_KEY', ''),
    ],

    // Production runs on HTTPS; subscriptions are accepted only over secure
    // origins in production. Local development (testing) is exempt so the
    // flow can be exercised on localhost.
    'require_https' => env('PUSH_REQUIRE_HTTPS', true),

    // Delivery attempt policy. A delivery row is retried at most this many
    // times across dispatch runs before it is marked failed.
    'max_attempts' => 3,

    // Subscriptions are disabled (never deleted) after this many consecutive
    // endpoint failures; 404/410 responses disable immediately.
    'max_consecutive_failures' => 5,

    // Queue a job per dispatch batch so delivery happens through the queue
    // when a worker is configured (QUEUE_CONNECTION=database etc.). In this
    // environment the sync driver runs the same job inline.
    'queue' => env('PUSH_QUEUE', null),
];

<?php

namespace App\Http\Controllers;

use App\Models\PushSubscription;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Public Web Push subscription endpoints (§3.4).
 *
 * The browser asks its own permission first; these endpoints only ever see a
 * subscription after the visitor has granted it. Anonymous subscriptions are
 * supported; user linkage happens only for authenticated sessions that land
 * here, and nothing personal is stored beyond what the standard requires.
 */
class PushSubscriptionController extends Controller
{
    public function subscribe(Request $request)
    {
        if ($this->refuseInsecureOrigin($request)) {
            return response()->json(['error' => 'Subscription requires HTTPS in production.'], 400);
        }

        $validated = $this->validateSubscription($request);

        $subscription = PushSubscription::updateOrCreate(
            ['endpoint_hash' => hash('sha256', $validated['endpoint'])],
            [
                'user_id' => $request->user()?->getKey(),
                'endpoint' => $validated['endpoint'],
                'p256dh' => $validated['p256dh'],
                'auth' => $validated['auth'],
                'wants_editorial' => $request->boolean('wants_editorial', true),
                'wants_promotions' => $request->boolean('wants_promotions'), // never default-on
                'user_agent' => substr((string) $request->userAgent(), 0, 250),
            ]
        );

        // Re-enable a re-subscribed endpoint.
        if (! $subscription->is_active) {
            $subscription->update(['is_active' => true, 'failure_count' => 0, 'last_error' => null]);
        }

        return response()->json(['ok' => true, 'id' => $subscription->id]);
    }

    public function unsubscribe(Request $request)
    {
        $validated = $request->validate([
            'endpoint' => ['required', 'string', 'max:1000'],
        ]);

        PushSubscription::query()
            ->where('endpoint_hash', hash('sha256', $validated['endpoint']))
            ->update([
                'is_active' => false,
                'last_error' => 'Unsubscribed by the visitor.',
            ]);

        return response()->json(['ok' => true]);
    }

    /**
     * Consent state for the signed-in owner's own subscription (§3.4/§3.6).
     * The endpoint itself is the ownership proof: only the browser holding
     * the subscription knows it, and a matching anonymous row is claimed on
     * first use so a visitor who subscribed before signing in can still
     * manage what they receive.
     */
    public function preferencesState(Request $request)
    {
        $subscription = $this->ownedSubscription($request);

        return response()->json([
            'wants_editorial' => $subscription->wants_editorial,
            'wants_promotions' => $subscription->wants_promotions,
        ]);
    }

    public function preferences(Request $request)
    {
        $subscription = $this->ownedSubscription($request);

        $validated = $request->validate([
            'wants_editorial' => ['required', 'boolean'],
            'wants_promotions' => ['required', 'boolean'],
        ]);

        $subscription->update([
            'wants_editorial' => (bool) $validated['wants_editorial'],
            'wants_promotions' => (bool) $validated['wants_promotions'],
        ]);

        return response()->json(['ok' => true]);
    }

    /**
     * Resolves the caller's own subscription from the endpoint they present.
     * Another account's endpoint answers 404 exactly like a missing one, so
     * the response never reveals whether someone else's row exists.
     */
    private function ownedSubscription(Request $request): PushSubscription
    {
        abort_if($request->user() === null, 403);

        $validated = $request->validate([
            'endpoint' => ['required', 'string', 'max:1000', 'url'],
        ]);

        $this->assertWebPushEndpoint($validated['endpoint']);

        $subscription = PushSubscription::query()
            ->where('endpoint_hash', hash('sha256', $validated['endpoint']))
            ->first();

        abort_if($subscription === null, 404);
        abort_if(
            $subscription->user_id !== null && $subscription->user_id !== $request->user()->getKey(),
            404
        );

        if ($subscription->user_id === null) {
            $subscription->update(['user_id' => $request->user()->getKey()]);
        }

        return $subscription;
    }

    private function validateSubscription(Request $request): array
    {
        $validated = $request->validate([
            'endpoint' => ['required', 'string', 'max:1000', 'url'],
            'p256dh' => ['required', 'string', 'max:200'],
            'auth' => ['required', 'string', 'max:200'],
        ]);

        // Endpoints pushed to must be plain web push endpoints — anything
        // else (javascript:, data:, file:) is refused outright.
        $this->assertWebPushEndpoint($validated['endpoint']);

        $p256dh = Str::of($validated['p256dh'])->replace(' ', '');
        $auth = Str::of($validated['auth'])->replace(' ', '');
        if ($p256dh->isEmpty() || $auth->isEmpty()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'endpoint' => 'The subscription keys are malformed.',
            ]);
        }

        return $validated;
    }

    private function assertWebPushEndpoint(string $endpoint): void
    {
        $scheme = strtolower((string) parse_url($endpoint, PHP_URL_SCHEME));

        if (! in_array($scheme, ['http', 'https'], true)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'endpoint' => 'Only http(s) subscription endpoints are accepted.',
            ]);
        }
    }

    private function refuseInsecureOrigin(Request $request): bool
    {
        if (! (bool) config('push.require_https', true)) {
            return false;
        }

        if (app()->environment(['local', 'testing'])) {
            return false;
        }

        return ! $request->isSecure();
    }
}

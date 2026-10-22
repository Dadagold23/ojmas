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

    public function preferences(Request $request)
    {
        if ($request->user() === null) {
            return redirect()->route('login');
        }

        $validated = $request->validate([
            'endpoint_hash' => ['required', 'string', 'size:64'],
            'wants_editorial' => ['required', 'boolean'],
            'wants_promotions' => ['required', 'boolean'],
        ]);

        $subscription = PushSubscription::query()
            ->where('user_id', $request->user()->id)
            ->where('endpoint_hash', $validated['endpoint_hash'])
            ->first();

        if ($subscription === null) {
            abort(404);
        }

        $subscription->update([
            'wants_editorial' => (bool) $validated['wants_editorial'],
            'wants_promotions' => (bool) $validated['wants_promotions'],
        ]);

        return back()->with('success', 'Notification preferences saved.');
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
        $scheme = strtolower((string) parse_url($validated['endpoint'], PHP_URL_SCHEME));
        if (! in_array($scheme, ['http', 'https'], true)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'endpoint' => 'Only http(s) subscription endpoints are accepted.',
            ]);
        }

        $p256dh = Str::of($validated['p256dh'])->replace(' ', '');
        $auth = Str::of($validated['auth'])->replace(' ', '');
        if ($p256dh->isEmpty() || $auth->isEmpty()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'endpoint' => 'The subscription keys are malformed.',
            ]);
        }

        return $validated;
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

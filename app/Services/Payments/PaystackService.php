<?php

namespace App\Services\Payments;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class PaystackService implements PaymentGatewayInterface
{
    public function initialize(array $payload): array
    {
        $client = $this->client();

        if (!$client) {
            return [
                'status' => false,
                'message' => 'Paystack secret key is not configured.',
            ];
        }

        try {
            $response = $client->post($this->baseUrl() . '/transaction/initialize', [
                'email' => $payload['email'],
                'amount' => (int) round($payload['amount'] * 100),
                'reference' => $payload['reference'],
                'callback_url' => $payload['callback_url'],
                'metadata' => $payload['metadata'] ?? [],
                'currency' => $payload['currency'] ?? 'NGN',
            ]);

            return $response->json() ?: [
                'status' => false,
                'message' => 'Unable to initialize Paystack transaction.',
            ];
        } catch (ConnectionException $e) {
            Log::error('Paystack initialize connection failed: ' . $e->getMessage(), [
                'reference' => $payload['reference'] ?? null,
            ]);

            return [
                'status' => false,
                'message' => 'Unable to reach Paystack right now. Please try again shortly.',
            ];
} catch (RequestException $e) {
            // Log the gateway's own explanation; the generic message alone is
            // not enough to diagnose why Paystack refused the request.
            Log::error('Paystack initialize request failed: ' . $e->getMessage(), [
                'reference' => $payload['reference'] ?? null,
                'status' => $e->response?->status(),
                'gateway_message' => $e->response?->json('message'),
                'gateway_code' => $e->response?->json('code'),
            ]);

            $gatewayCode = $e->response?->json('code');

            if ($gatewayCode === 'duplicate_reference') {
                return [
                    'status' => false,
                    'message' => 'This payment reference has already been used at Paystack. Please start the payment again.',
                ];
            }

            if ($e->response?->status() === 429) {
                return [
                    'status' => false,
                    'message' => 'Too many payment attempts in a short time. Please wait a minute and try again.',
                ];
            }

            return [
                'status' => false,
                'message' => 'Paystack rejected the payment request. Please confirm your payment details and try again.',
            ];
        } catch (Throwable $e) {
            Log::error('Paystack initialize unexpected failure: ' . $e->getMessage(), [
                'reference' => $payload['reference'] ?? null,
            ]);

            return [
                'status' => false,
                'message' => 'Unexpected payment gateway error. Please try again.',
            ];
        }
    }

    public function verify(string $reference): array
    {
        $client = $this->client();

        if (!$client) {
            return [
                'status' => false,
                'message' => 'Paystack secret key is not configured.',
            ];
        }

        try {
            $response = $client->get($this->baseUrl() . '/transaction/verify/' . urlencode($reference));

            return $response->json() ?: [
                'status' => false,
                'message' => 'Unable to verify Paystack transaction.',
            ];
        } catch (ConnectionException $e) {
            Log::error('Paystack verify connection failed: ' . $e->getMessage(), [
                'reference' => $reference,
            ]);

            return [
                'status' => false,
                'message' => 'Unable to reach Paystack to verify this payment right now.',
            ];
        } catch (RequestException $e) {
            Log::error('Paystack verify request failed: ' . $e->getMessage(), [
                'reference' => $reference,
            ]);

            return [
                'status' => false,
                'message' => 'Paystack could not verify this payment.',
            ];
        } catch (Throwable $e) {
            Log::error('Paystack verify unexpected failure: ' . $e->getMessage(), [
                'reference' => $reference,
            ]);

            return [
                'status' => false,
                'message' => 'Unexpected payment verification error.',
            ];
        }
    }

private function client(): ?PendingRequest
    {
        $secret = config('services.paystack.secret_key');

        if (!$secret) {
            return null;
        }

        $connectTimeout = (int) config('services.paystack.connect_timeout', 30);
        $timeout = (int) config('services.paystack.timeout', 60);

        // The TLS handshake to api.paystack.co is intermittently slow from this
        // network (measured 1.4s up to 12s), so the connect budget has to cover
        // the handshake and leave headroom for the request itself.
        $request = Http::acceptJson()
            ->withToken($secret)
            ->connectTimeout($connectTimeout)
            ->timeout($timeout);

        if (!config('services.paystack.verify_ssl', true)) {
            $request = $request->withOptions(['verify' => false]);
        }

        if (config('services.paystack.force_ipv4', true)) {
            $request = $request->withOptions([
                'curl' => [CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4],
            ]);
        }

        // 4xx responses are deterministic (bad reference, invalid email, etc),
        // so retrying them just delays the error. Retry only transport faults,
        // rate limits and server-side failures.
        return $request->retry(
            (int) config('services.paystack.retry_times', 3),
            (int) config('services.paystack.retry_delay_ms', 800),
            fn ($exception) =>
                $exception instanceof ConnectionException
                || ($exception instanceof RequestException
                    && ($exception->response?->status() ?? 0) !== 400),
            throw: false
        );
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('services.paystack.base_url', 'https://api.paystack.co'), '/');
    }
}

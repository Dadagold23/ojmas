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
            $response = $client->post('https://api.paystack.co/transaction/initialize', [
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
            Log::error('Paystack initialize request failed: ' . $e->getMessage(), [
                'reference' => $payload['reference'] ?? null,
            ]);

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
            $response = $client->get('https://api.paystack.co/transaction/verify/' . urlencode($reference));

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

        return Http::acceptJson()
            ->withToken($secret)
            ->connectTimeout(10)
            ->timeout(15)
            ->retry(2, 200);
    }
}

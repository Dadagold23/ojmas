<?php

namespace App\Services\Payments;

interface PaymentGatewayInterface
{
    public function initialize(array $payload): array;
    public function verify(string $reference): array;
}

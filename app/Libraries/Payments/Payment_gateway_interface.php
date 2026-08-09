<?php

namespace App\Libraries\Payments;

interface Payment_gateway_interface
{
    /**
     * @return array{checkout_id:string,checkout_url:string,expires_at:int}
     */
    public function createCheckout(array $payment): array;

    /** Verify the raw signed request before returning the provider event. */
    public function verifyWebhook(string $rawBody, string $signature): object;

    public function minorUnitExponent(string $currency): int;
}

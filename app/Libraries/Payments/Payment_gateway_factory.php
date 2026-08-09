<?php

namespace App\Libraries\Payments;

use Config\EservicesPayments;
use RuntimeException;

final class Payment_gateway_factory
{
    public static function make(?EservicesPayments $config = null): Payment_gateway_interface
    {
        $config = $config ?: config('EservicesPayments');
        if ($config->provider === 'stripe') {
            return new Stripe_payment_gateway($config);
        }

        throw new RuntimeException('Online payment is unavailable because no approved provider is configured.');
    }
}

<?php

namespace App\Libraries\Auth;

use App\Libraries\Sms\IsmartSmsGateway;

final class IsmartSmsMfaProvider implements MfaProviderInterface
{
    private IsmartSmsGateway $gateway;

    public function __construct(?IsmartSmsGateway $gateway = null)
    {
        $this->gateway = $gateway ?? new IsmartSmsGateway();
    }

    public function name(): string { return 'ismartsms'; }
    public function isConfigured(): bool { return $this->gateway->isConfigured(); }
    public function normalizeDestination(string $destination): ?string { return OmanMobileNumber::normalize($destination); }

    public function send(string $destination, string $code, int $lifetimeSeconds): bool
    {
        if (!preg_match('/^[0-9]{6,8}$/D', $code)) { return false; }
        $message = 'Port of Duqm sign-in code: ' . $code . '. Expires in '
            . max(1, (int) ceil($lifetimeSeconds / 60)) . ' minutes. Do not share this code.';
        // OTP never enters the notification outbox or a preview/log. Only a live accepted send can proceed.
        return $this->gateway->send($destination, $message)['status'] === 'accepted';
    }
}

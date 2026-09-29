<?php

namespace App\Libraries\Auth;

/** One administrator-managed delivery choice for the global login identity. */
final class UserOtpPreference
{
    public function __construct(private $db = null)
    {
        $this->db = $db ?? db_connect();
    }

    public function get(int $userId): string
    {
        if (!$userId || !$this->db->fieldExists('otp_delivery_channel', 'users')) {
            return '';
        }
        $row = $this->db->table('users')->select('otp_delivery_channel')->where('id', $userId)->get()->getRow();
        $value = (string) ($row->otp_delivery_channel ?? '');
        if (!in_array($value, ['', 'email', 'sms'], true)) {
            throw new \RuntimeException('Invalid login OTP delivery preference.');
        }
        return $value;
    }

    /** Call only after the controller's user-management permission check. */
    public function fields($submitted, object $actor, ?object $target, array $contact): array
    {
        if ($submitted === null) { return []; } // Older forms preserve the choice.
        if (!is_string($submitted) || !in_array($submitted, ['', 'email', 'sms'], true)) {
            throw new \DomainException(app_lang('login_otp_invalid_method'));
        }
        $current = $this->get((int) ($target->id ?? 0));
        if ($target && empty($actor->is_admin) && $submitted !== $current) {
            throw new \DomainException(app_lang('login_otp_admin_only'));
        }
        if (!$this->db->fieldExists('otp_delivery_channel', 'users')) {
            if ($submitted === '') { return []; }
            throw new \DomainException(app_lang('login_otp_install_sql'));
        }
        if ($submitted === 'email' && !filter_var($contact['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
            throw new \DomainException(app_lang('login_otp_email_required'));
        }
        if ($submitted === 'sms' && !OmanMobileNumber::normalize((string) ($contact['phone'] ?? ''))) {
            throw new \DomainException(app_lang('login_otp_mobile_required'));
        }
        return ['otp_delivery_channel' => $submitted];
    }
}

<?php

namespace App\Libraries;

/**
 * CR-scoped authorization matrix for vendor portal memberships.
 *
 * Authentication identifies a global user. The selected vendor_users row is
 * the authorization boundary, so a role never grants access to another CR.
 */
final class Vendor_portal_authorizer
{
    public const PROFILE_VIEW = 'profile.view';
    public const PROFILE_EDIT = 'profile.edit';
    public const TENDER_VIEW = 'tender.view';
    public const TENDER_PARTICIPATE = 'tender.participate';
    public const CONTACTS_MANAGE = 'contacts.manage';

    /** @var array<string, list<string>> */
    private const MATRIX = [
        'OWNER' => [
            self::PROFILE_VIEW,
            self::PROFILE_EDIT,
            self::TENDER_VIEW,
            self::TENDER_PARTICIPATE,
            self::CONTACTS_MANAGE,
        ],
        'EDITOR' => [
            self::PROFILE_VIEW,
            self::PROFILE_EDIT,
            self::TENDER_VIEW,
            self::TENDER_PARTICIPATE,
        ],
        'BIDDER' => [
            self::PROFILE_VIEW,
            self::TENDER_VIEW,
            self::TENDER_PARTICIPATE,
        ],
        'VIEWER' => [
            self::PROFILE_VIEW,
            self::TENDER_VIEW,
        ],
        // CONTACT was the original unrestricted role. Treat it as read-only
        // until the data migration maps it to VIEWER explicitly.
        'CONTACT' => [
            self::PROFILE_VIEW,
            self::TENDER_VIEW,
        ],
    ];

    public function roleCode(?object $membership): string
    {
        if (!$membership) {
            return '';
        }

        if ((int) ($membership->is_owner ?? 0) === 1) {
            return 'OWNER';
        }

        return strtoupper(trim((string) ($membership->vendor_role_code ?? '')));
    }

    public function can(?object $membership, string $capability): bool
    {
        if (!$membership || (string) ($membership->membership_status ?? '') !== 'active') {
            return false;
        }

        $role = $this->roleCode($membership);
        return isset(self::MATRIX[$role])
            && in_array($capability, self::MATRIX[$role], true);
    }

    /** @return list<string> */
    public static function assignableRoleCodes(): array
    {
        return ['VIEWER', 'BIDDER', 'EDITOR'];
    }
}

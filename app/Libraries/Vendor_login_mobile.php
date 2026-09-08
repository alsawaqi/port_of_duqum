<?php

namespace App\Libraries;

use App\Libraries\Auth\OmanMobileNumber;
use App\Models\Users_model;
use CodeIgniter\Database\BaseConnection;

/** A CR contact must never replace another CR's established login mobile. */
final class Vendor_login_mobile
{
    private BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?: db_connect();
    }

    public function fillMissingFromApprovedContacts(int $userId): bool
    {
        $user = $this->db->table('users')->where('id', $userId)->where('deleted', 0)->get()->getRow();
        if (!$user || trim((string) $user->phone) !== ''
            || (string) $user->user_type !== 'staff'
            || (int) $user->is_admin !== 0 || (int) $user->role_id !== 0
            || !(new Users_model())->is_vendor_only_identity($userId, $user)) {
            return false;
        }

        $contactsTable = $this->db->prefixTable('vendor_contacts');
        $vendorsTable = $this->db->prefixTable('vendors');
        $membersTable = $this->db->prefixTable('vendor_users');
        $contacts = $this->db->table($contactsTable . ' c')->select('c.*')
            ->join($vendorsTable . ' v', 'v.id = c.vendor_id')
            ->join($membersTable . ' m', 'm.vendor_id = c.vendor_id AND m.user_id = c.user_id')
            ->where('c.user_id', $userId)->where('c.deleted', 0)
            ->where('c.status', 'approved')->where('c.is_active', 1)
            ->where('v.deleted', 0)->whereNotIn('v.status', ['rejected', 'suspended'])
            ->where('m.deleted', 0)->where('m.status', 'active')->get()->getResult();
        $numbers = [];
        foreach ($contacts as $contact) {
            if (Vendor_contact_access::canonicalEmail($contact->email) !== Vendor_contact_access::canonicalEmail($user->email)) {
                continue;
            }
            // Use the personal mobile, never the company's general phone.
            $mobile = OmanMobileNumber::normalize((string) $contact->mobile);
            if ($mobile !== null) {
                $numbers[$mobile] = true;
            }
        }
        if (count($numbers) !== 1) {
            return false;
        }

        // Compare-and-update preserves a mobile saved concurrently elsewhere.
        $this->db->table('users')->where('id', $userId)->where('deleted', 0)
            ->where('phone', $user->phone)->update(['phone' => array_key_first($numbers)]);
        return $this->db->affectedRows() === 1;
    }
}

<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/** Seed least-privilege vendor roles and retire unrestricted CONTACT access. */
class VendorPortalRoleEnforcement extends Migration
{
    public function up()
    {
        $roles = $this->db->prefixTable('vendor_roles');
        $memberships = $this->db->prefixTable('vendor_users');
        if (!$this->db->tableExists($roles) || !$this->db->tableExists($memberships)) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        $definitions = [
            'OWNER' => ['Owner', 'Full authority for the selected vendor CR, including contact administration.'],
            'EDITOR' => ['Editor', 'May maintain the selected CR profile and participate in tenders.'],
            'BIDDER' => ['Bidder', 'May view the selected CR profile and participate in tenders.'],
            'VIEWER' => ['Viewer', 'Read-only access to the selected CR profile and tenders.'],
            'CONTACT' => ['Legacy Contact', 'Legacy role retained for compatibility and enforced as read-only.'],
        ];

        foreach ($definitions as $code => [$name, $description]) {
            $row = $this->db->table($roles)
                ->where('UPPER(TRIM(code))', $code)
                ->orderBy('id', 'ASC')
                ->get(1)
                ->getRow();
            $data = [
                'name' => $name,
                'code' => $code,
                'description' => $description,
                'is_active' => 1,
                'deleted' => 0,
                'updated_at' => $now,
            ];
            if ($row) {
                $this->db->table($roles)->where('id', (int) $row->id)->update($data);
            } else {
                $data['created_at'] = $now;
                $this->db->table($roles)->insert($data);
            }
        }

        $contact = $this->db->table($roles)->select('id')->where('code', 'CONTACT')->get(1)->getRow();
        $viewer = $this->db->table($roles)->select('id')->where('code', 'VIEWER')->get(1)->getRow();
        if ($contact && $viewer) {
            $this->db->table($memberships)
                ->where('is_owner', 0)
                ->where('vendor_role_id', (int) $contact->id)
                ->update([
                    'vendor_role_id' => (int) $viewer->id,
                    'updated_at' => $now,
                ]);
        }
    }

    public function down()
    {
        // Role rows and assignments may already be in use. A destructive
        // rollback would silently broaden or remove access, so retain them.
    }
}

<?php

namespace App\Controllers;

use App\Models\Tender_requests_model;
use App\Models\Tenders_model;
use App\Models\Tender_documents_model;
use App\Models\Tender_target_specialties_model;
use App\Models\Tender_invited_vendors_model;
use App\Models\Tender_evaluations_model;
use App\Models\Tender_request_vendors_model;
 
use App\Models\Tender_request_team_members_model;
use App\Models\Tender_team_members_model;
use CodeIgniter\I18n\Time;

class Tender_procurement_inbox extends Security_Controller
{
    // Keep tender email notifications disabled until SMTP is configured.
    private const TENDER_EMAILS_ENABLED = false;

    protected $db;
    protected $Tender_requests_model;
    protected $Tenders_model;
    protected $Tender_documents_model;
    protected $Tender_target_specialties_model;
    protected $Tender_invited_vendors_model;
    protected $Tender_request_vendors_model;
    protected $Tender_request_team_members_model;
    protected $Tender_team_members_model;
    protected $Tender_evaluations_model;

    function __construct()
    {
        parent::__construct();
        $this->access_only_team_members();

        $this->Tender_requests_model = new Tender_requests_model();
        $this->Tenders_model = new Tenders_model();
        $this->Tender_documents_model = new Tender_documents_model();
        $this->Tender_target_specialties_model = new Tender_target_specialties_model();
        $this->Tender_invited_vendors_model = new Tender_invited_vendors_model();
        $this->Tender_request_vendors_model = new Tender_request_vendors_model();
        $this->Tender_request_team_members_model = new Tender_request_team_members_model();
        $this->Tender_team_members_model = new Tender_team_members_model();
        $this->Tender_evaluations_model = new Tender_evaluations_model();
        $this->db = db_connect();
    }

    function index()
    {
        $this->access_only_tender("procurement", "view");
        return $this->template->rander("tender_procurement_inbox/index");
    }

    function list_data()
{
    $this->access_only_tender("procurement", "view");

    $this->Tenders_model->auto_progress_workflow();

    $req = $this->db->prefixTable("tender_requests");
    $tenders = $this->db->prefixTable("tenders");

    // ✅ join latest tender per request to prevent duplicates
    $sql = "SELECT
                $req.*,
                t.id AS tender_id,
                t.status AS tender_status,
                t.workflow_stage AS tender_workflow_stage,
                t.published_at,
                t.closing_at
            FROM $req
            LEFT JOIN (
                SELECT tender_request_id, MAX(id) AS max_id
                FROM $tenders
                WHERE deleted=0
                GROUP BY tender_request_id
            ) tmax ON tmax.tender_request_id = $req.id
            LEFT JOIN $tenders t ON t.id = tmax.max_id
            WHERE $req.deleted=0
              AND $req.status='committee_approved'
            ORDER BY $req.id DESC";

    $list = $this->db->query($sql)->getResult();

    $result = [];
    foreach ($list as $row) {
        $result[] = $this->_make_row($row);
    }

    echo json_encode(["data" => $result]);
}



function vendor_categories_suggestion()
{
    $this->access_only_tender("procurement", "view");

    $q = trim((string)$this->request->getPost("q"));

    $cats = $this->db->prefixTable("vendor_categories");
    $where = "WHERE deleted=0";

    if ($q) {
        $like = $this->db->escapeLikeString($q);
        $where .= " AND (name LIKE '%$like%')";
    }

    $rows = $this->db->query("SELECT id, name FROM $cats $where ORDER BY name ASC LIMIT 30")->getResult();

    $out = [];
    foreach ($rows as $r) {
        $out[] = ["id" => (int)$r->id, "text" => $r->name];
    }

    return $this->response->setJSON($out);
}

function vendor_subcategories_suggestion()
{
    $this->access_only_tender("procurement", "view");

    $category_id = (int)$this->request->getPost("category_id");
    $q = trim((string)$this->request->getPost("q"));

    if (!$category_id) {
        return $this->response->setJSON([]);
    }

    $subs = $this->db->prefixTable("vendor_sub_categories");
    $where = "WHERE deleted=0 AND vendor_category_id=" . $category_id;

    if ($q) {
        $like = $this->db->escapeLikeString($q);
        $where .= " AND (name LIKE '%$like%')";
    }

    $rows = $this->db->query("SELECT id, name FROM $subs $where ORDER BY name ASC LIMIT 30")->getResult();

    $out = [];
    foreach ($rows as $r) {
        $out[] = ["id" => (int)$r->id, "text" => $r->name];
    }

    return $this->response->setJSON($out);
}


    public function get_vendor_sub_categories_dropdown()
    {
        $this->access_only_tender("procurement", "view");

        $vendor_category_id = (int) $this->request->getGet("vendor_category_id");
        if (!$vendor_category_id) {
            return $this->response->setBody("<option value=''>- " . app_lang("select") . " -</option>");
        }

        $sub = $this->db->prefixTable("vendor_sub_categories");

        $rows = $this->db->query(
            "SELECT id, name FROM $sub
             WHERE deleted=0 AND vendor_category_id=?
             ORDER BY name ASC",
            [$vendor_category_id]
        )->getResult();

        $options = "<option value=''>- " . app_lang("select") . " -</option>";
        foreach ($rows as $r) {
            $id = (int) $r->id;
            $name = esc($r->name);
            $options .= "<option value='{$id}'>{$name}</option>";
        }

        return $this->response->setBody($options);
    }



    function send_invites_by_specialty()
        {
            $this->validate_submitted_data([
                "tender_request_id" => "required|numeric",
                "vendor_category_id" => "required|numeric",
                "vendor_sub_category_id" => "numeric"
            ]);
            $this->access_only_tender("procurement", "update");

            $tender_request_id = (int) $this->request->getPost("tender_request_id");
            $vendor_category_id = (int) $this->request->getPost("vendor_category_id");
            $vendor_sub_category_id = (int) $this->request->getPost("vendor_sub_category_id");

            // Must have tender created first
            $tender = $this->Tenders_model->get_by_request_id($tender_request_id);
            if (!$tender || !$tender->id) {
                return $this->response->setJSON(["success" => false, "message" => "Create the tender first."]);
            }

            // Record the targeting rule (audit)
            $target_data = [
                "tender_id" => (int) $tender->id,
                "vendor_category_id" => $vendor_category_id,
                "vendor_sub_category_id" => $vendor_sub_category_id ?: null,
                "created_by" => $this->login_user->id,
                "created_at" => date("Y-m-d H:i:s"),
                "deleted" => 0
            ];
            $this->Tender_target_specialties_model->ci_save($target_data);

            // Find vendors by specialties + approved status
            $vendors = $this->db->prefixTable("vendors");
            $spec = $this->db->prefixTable("vendor_specialties");

            $where = "WHERE $spec.deleted=0
                    AND $vendors.deleted=0
                    AND $vendors.status='approved'
                    AND $spec.vendor_category_id=" . $vendor_category_id;

            if ($vendor_sub_category_id) {
                $where .= " AND $spec.vendor_sub_category_id=" . $vendor_sub_category_id;
            }

            $rows = $this->db->query(
                "SELECT DISTINCT $vendors.id AS vendor_id, $vendors.email
                FROM $spec
                JOIN $vendors ON $vendors.id=$spec.vendor_id
                $where"
            )->getResult();

            if (!$rows) {
                return $this->response->setJSON(["success" => false, "message" => "No approved vendors found for this specialty."]);
            }

            $tiv = $this->db->prefixTable("tender_invited_vendors");
            $now = date("Y-m-d H:i:s");
            $count = 0;

            foreach ($rows as $r) {
                $vendor_id = (int)$r->vendor_id;
                if (!$vendor_id) continue;

                // Dedup invite
                $exists = $this->db->query(
                    "SELECT id FROM $tiv WHERE deleted=0 AND tender_id=? AND vendor_id=? LIMIT 1",
                    [(int)$tender->id, $vendor_id]
                )->getRow();

                if ($exists && $exists->id) {
                    continue;
                }

                $invite_data = [
                    "tender_id" => (int) $tender->id,
                    "vendor_id" => $vendor_id,
                    "invite_status" => "sent",
                    "invited_by" => $this->login_user->id,
                    "invited_at" => $now,
                    "deleted" => 0
                ];
                $this->Tender_invited_vendors_model->ci_save($invite_data);

                $count++;

                // Email sending hook (optional later):
                // send_app_mail($r->email, "Tender Invitation", $message);
            }

            return $this->response->setJSON([
                "success" => true,
                "message" => "Invites created for $count vendor(s).",
                "count" => $count
            ]);
        }


 

   
   
  function modal_form()
    {
        $this->validate_submitted_data(["id" => "required|numeric"]);
        $this->access_only_tender("procurement", "view");

        $tender_request_id = (int)$this->request->getPost("id");

        $request = $this->Tender_requests_model->get_details(["id" => $tender_request_id])->getRow();
        if (!$request) {
            show_404();
        }

        $tender = $this->Tenders_model->get_by_request_id($tender_request_id);

        $docs = [];
        if ($tender && $tender->id) {
            $docs = $this->Tender_documents_model->get_details(["tender_id" => (int)$tender->id])->getResult();
        }



        $invited_vendors = [];
        if ($tender && $tender->id) {
            $invited_vendors = $this->Tender_invited_vendors_model->get_invited_vendors((int)$tender->id);
        }

        // Vendor categories dropdown (pod_vendor_categories)
        $cats = $this->db->prefixTable("vendor_categories");
        $rows = $this->db->query("SELECT id, name, is_active FROM $cats WHERE deleted=0 ORDER BY name ASC")->getResult();
        $vendor_categories_dropdown = ["" => "- " . app_lang("select_vendor_category") . " -"];
        foreach ($rows as $r) {
            $label = $r->name . (((int) $r->is_active === 0) ? " (inactive)" : "");
            $vendor_categories_dropdown[(int) $r->id] = $label;
        }

        // Vendor groups dropdown (pod_vendor_groups)
        $vg = $this->db->prefixTable("vendor_groups");
        $group_rows = $this->db->query(
            "SELECT id, name, code
             FROM $vg
             WHERE deleted=0
             ORDER BY name ASC"
        )->getResult();
        $vendor_groups_dropdown = ["" => "- " . app_lang("select_vendor_group") . " -"];
        foreach ($group_rows as $gr) {
            $label = $gr->name;
            if (!empty($gr->code)) {
                $label .= " (" . $gr->code . ")";
            }
            $vendor_groups_dropdown[(int) $gr->id] = $label;
        }


        $target = null;
        $target_cat = null;
        $target_sub = null;

        if ($tender && $tender->id) {
            $tts = $this->db->prefixTable("tender_target_specialties");
            $target = $this->db->query(
                "SELECT * FROM $tts WHERE deleted=0 AND tender_id=? ORDER BY id DESC LIMIT 1",
                [(int)$tender->id]
            )->getRow();

            if ($target) {
                $cats = $this->db->prefixTable("vendor_categories");
                $subs = $this->db->prefixTable("vendor_sub_categories");

                $target_cat = $this->db->query("SELECT id, name FROM $cats WHERE deleted=0 AND id=?", [(int)$target->vendor_category_id])->getRow();
                if (!empty($target->vendor_sub_category_id)) {
                    $target_sub = $this->db->query("SELECT id, name FROM $subs WHERE deleted=0 AND id=?", [(int)$target->vendor_sub_category_id])->getRow();
                }
            }
        }

        $selected_target_mode = "specialty";
        $selected_vendor_group_id = 0;

        // If no specialty target exists but we already invited vendors from exactly one group,
        // infer group mode for better edit experience.
        if ((!$target || !(int) ($target->vendor_category_id ?? 0)) && !empty($invited_vendors)) {
            $vendors = $this->db->prefixTable("vendors");
            $tiv = $this->db->prefixTable("tender_invited_vendors");
            $group_map = $this->db->query(
                "SELECT DISTINCT $vendors.vendor_group_id
                 FROM $tiv
                 INNER JOIN $vendors ON $vendors.id = $tiv.vendor_id
                 WHERE $tiv.deleted=0
                   AND $vendors.deleted=0
                   AND $tiv.tender_id=?
                   AND $vendors.vendor_group_id IS NOT NULL",
                [(int) $tender->id]
            )->getResult();

            if (count($group_map) === 1) {
                $selected_target_mode = "group";
                $selected_vendor_group_id = (int) ($group_map[0]->vendor_group_id ?? 0);
            }
        }


        $request_selected_vendors = [];
        if (($request->tender_type ?? "open") === "close") {
            $request_selected_vendors = $this->Tender_request_vendors_model->get_selected_vendors($tender_request_id);
        }

        return $this->template->view("tender_procurement_inbox/modal_form", [
            "request" => $request,
            "tender" => $tender,
            "docs" => $docs,
            "invited_vendors" => $invited_vendors,
            "vendor_categories_dropdown" => $vendor_categories_dropdown,
            "target" => $target,
            "target_cat" => $target_cat,
            "target_sub" => $target_sub,
            "vendor_groups_dropdown" => $vendor_groups_dropdown,
            "selected_target_mode" => $selected_target_mode,
            "selected_vendor_group_id" => $selected_vendor_group_id,
            "request_selected_vendors" => $request_selected_vendors,
        ]);
    }

    function save()
{
    $this->validate_submitted_data([
        "tender_request_id" => "required|numeric",
        "reference" => "required",
        "title" => "required",
        "closing_at" => "required"
    ]);

    $tender_request_id = (int)$this->request->getPost("tender_request_id");

    $request = $this->Tender_requests_model->get_details(["id" => $tender_request_id])->getRow();
    if (!$request) {
        echo json_encode(["success" => false, "message" => "Tender request not found"]);
        return;
    }
    if (($request->status ?? "") !== "committee_approved") {
        echo json_encode(["success" => false, "message" => "Only committee approved requests can be processed"]);
        return;
    }

    $existing = $this->Tenders_model->get_by_request_id($tender_request_id);

    // ✅ permission: create for new, update for existing
    if ($existing && $existing->id) {
        $this->access_only_tender("procurement", "update");
    } else {
        $this->access_only_tender("procurement", "create");
    }

    $reference = trim((string)$this->request->getPost("reference"));
    $title = trim((string)$this->request->getPost("title"));
    $closing_at = $this->_normalize_tender_datetime($this->request->getPost("closing_at"), "end");
if (!$closing_at) {
    echo json_encode([
        "success" => false,
        "message" => "Invalid closing date."
    ]);
    return;
}

    $tender_type = ($request->tender_type ?? "open") === "close" ? "close" : "open";

    $target_mode = strtolower(trim((string) $this->request->getPost("target_mode")));
    if (!in_array($target_mode, ["specialty", "group"], true)) {
        $target_mode = "specialty";
    }

    $vendor_category_id = (int) $this->request->getPost("vendor_category_id");
    $vendor_sub_category_id = (int) $this->request->getPost("vendor_sub_category_id");
    $vendor_group_id = (int) $this->request->getPost("vendor_group_id");

    $request_selected_vendors = $this->Tender_request_vendors_model->get_selected_vendors($tender_request_id);
    $has_request_selected_vendors = count($request_selected_vendors) > 0;
    // Category is sufficient for specialty targeting; subcategory is optional.
    $has_specialty_target = ($target_mode === "specialty" && $vendor_category_id > 0);
    $has_group_target = ($target_mode === "group" && $vendor_group_id > 0);

    if ($tender_type === "close" && !$has_request_selected_vendors && !$has_specialty_target && !$has_group_target) {
        echo json_encode([
            "success" => false,
            "message" => "For CLOSE tender, use invited vendors from the request or select vendor specialty/group."
        ]);
        return;
    }

    $data = [
        "tender_request_id" => $tender_request_id,
        "reference" => $reference,
        "title" => $title,
        "tender_type" => $tender_type,
        "closing_at" => $closing_at,
    ];

    $this->db->transStart();

    if ($existing && $existing->id) {
        $tender_id = (int)$existing->id;
        $this->Tenders_model->ci_save($data, $tender_id);
    } else {
        $data["status"] = "draft";
        $data["created_by"] = $this->login_user->id;
        $data["created_at"] = date("Y-m-d H:i:s");
        $tender_id = $this->Tenders_model->ci_save($data);
    }

    if (!$tender_id) {
        $this->db->transComplete();
        echo json_encode(["success" => false, "message" => "Failed to save tender"]);
        return;
    }

    // Persist specialty target only when selected; clear otherwise.
    if ($has_specialty_target) {
        $this->_save_target_specialty($tender_id, $vendor_category_id, $vendor_sub_category_id);
    } else {
        $this->_save_target_specialty($tender_id, 0, 0);
    }

    // ---- Documents upload ----
    $doc_type = trim((string)$this->request->getPost("doc_type")) ?: "RFP";
    $time_limited = $this->request->getPost("time_limited") ? 1 : 0;
    $expires_in_hours = (int)($this->request->getPost("expires_in_hours") ?: 72);

    $target_path = getcwd() . "/files/tender_files/" . $tender_id . "/";
    if (!is_dir($target_path)) {
        @mkdir($target_path, 0755, true);
    }

    $files = $this->request->getPost("files");

    if ($files && is_array($files) && get_array_value($files, 0)) {
        foreach ($files as $serial) {
            $serial = (int)$serial;
            if (!$serial) continue;

            $original_name = $this->request->getPost("file_name_" . $serial);
            $file_size = (int)$this->request->getPost("file_size_" . $serial);
            $title_input = $this->request->getPost("description_" . $serial);

            if (!$original_name) continue;

            $file_info = move_temp_file($original_name, $target_path, "tender_doc", null, "", "", false, $file_size, true);

            if ($file_info && get_array_value($file_info, "file_name")) {
                $stored_name = get_array_value($file_info, "file_name");

                $doc_data = [
                    "tender_id" => $tender_id,
                    "doc_type" => $doc_type,
                    "title" => $title_input ?: null,
                    "disk" => "local",
                    "path" => "files/tender_files/" . $tender_id . "/" . $stored_name,
                    "original_name" => $original_name,
                    "size_bytes" => $file_size ?: null,
                    "time_limited" => $time_limited,
                    "expires_in_hours" => $time_limited ? $expires_in_hours : null,
                    "uploaded_by" => $this->login_user->id,
                    "created_at" => date("Y-m-d H:i:s"),
                    "deleted" => 0
                ];

                $this->Tender_documents_model->ci_save($doc_data);
            }
        }
    }

        
             // Auto-generate invited vendors snapshot on SAVE
             $invited_count = 0;

             if ($has_request_selected_vendors) {
                 // Request-level selected vendors take priority
                 $invited_count = $this->_sync_invites_from_request($tender_id, $tender_request_id);
             } elseif ($has_specialty_target) {
                 // If procurement targeted a category/subcategory, build the vendor snapshot
                 $invited_count = $this->_sync_invites_by_specialty($tender_id, $vendor_category_id, $vendor_sub_category_id);
             } elseif ($has_group_target) {
                 // Group targeting: invite approved vendors from selected group.
                 $invited_count = $this->_sync_invites_by_vendor_group($tender_id, $vendor_group_id);
             } else {
                 // No request-vendors and no specialty target => clear active invite snapshot
                 $tiv = $this->db->prefixTable("tender_invited_vendors");
                 $this->db->query("UPDATE $tiv SET deleted=1 WHERE tender_id=?", [$tender_id]);
             }


    // optional: publish on save
    $publish_now = $this->request->getPost("publish_now") ? 1 : 0;
    if ($publish_now) {
        $fresh_tender = $this->Tenders_model->get_by_request_id($tender_request_id);
    
        $publish_error = $this->_validate_tender_can_publish($fresh_tender, $closing_at);
        if ($publish_error) {
            $this->db->transComplete();
            echo json_encode([
                "success" => false,
                "message" => $publish_error
            ]);
            return;
        }
    
        $publish_data = $this->_build_publish_payload($fresh_tender);
        $this->Tenders_model->ci_save($publish_data, $tender_id);
    }

    $this->_sync_teams_from_request($tender_id, $tender_request_id);

$this->db->transComplete();

if ($this->db->transStatus() === false) {
    echo json_encode([
        "success" => false,
        "message" => "Database error."
    ]);
    return;
}

if ($publish_now && (int) $invited_count > 0) {
    $this->_send_invitation_notifications((int) $tender_id);
}

echo json_encode([
    "success" => true,
    "message" => ($publish_now ? "Tender published" : "Tender saved") . ". Vendors matched: " . (int) $invited_count,
    "tender_id" => $tender_id
]);
}

private function _save_target_specialty(int $tender_id, int $vendor_category_id = 0, int $vendor_sub_category_id = 0): void
{
    $tts = $this->db->prefixTable("tender_target_specialties");

    // Replace existing active targeting with latest selection.
    $this->db->query("UPDATE $tts SET deleted=1 WHERE tender_id=?", [$tender_id]);

    if ($vendor_category_id <= 0) {
        return;
    }

    $this->db->query(
        "INSERT INTO $tts (tender_id, vendor_category_id, vendor_sub_category_id, created_by, created_at, deleted)
         VALUES (?, ?, ?, ?, ?, 0)",
        [
            $tender_id,
            $vendor_category_id,
            $vendor_sub_category_id > 0 ? $vendor_sub_category_id : null,
            $this->login_user->id,
            date("Y-m-d H:i:s")
        ]
    );
}

private function _sync_invites_from_request(int $tender_id, int $tender_request_id): int
{
    $trv = $this->db->prefixTable("tender_request_vendors");
    $tiv = $this->db->prefixTable("tender_invited_vendors");
    $vendors = $this->db->prefixTable("vendors");

    $this->db->query("UPDATE $tiv SET deleted=1 WHERE tender_id=?", [$tender_id]);

    $rows = $this->db->query(
        "SELECT DISTINCT $trv.vendor_id
         FROM $trv
         JOIN $vendors ON $vendors.id = $trv.vendor_id
         WHERE $trv.deleted=0
           AND $vendors.deleted=0
           AND $vendors.status='approved'
           AND $trv.tender_request_id=?",
        [$tender_request_id]
    )->getResult();

    if (!$rows) {
        return 0;
    }

    $now = date("Y-m-d H:i:s");
    $count = 0;

    foreach ($rows as $r) {
        $vendor_id = (int) $r->vendor_id;
        if (!$vendor_id) {
            continue;
        }

        $this->db->query(
            "INSERT INTO $tiv (tender_id, vendor_id, invite_status, invited_by, invited_at, deleted)
             VALUES (?, ?, 'sent', ?, ?, 0)",
            [$tender_id, $vendor_id, $this->login_user->id, $now]
        );

        $count++;
    }

    return $count;
}


private function _sync_teams_from_request(int $tender_id, int $tender_request_id): void
{
    $grouped = $this->Tender_request_team_members_model->get_grouped_members($tender_request_id);

    $this->Tender_team_members_model->sync_members(
        $tender_id,
        "technical_evaluator",
        array_map(fn($u) => (int) $u->id, $grouped["technical_evaluator"] ?? [])
    );

    $this->Tender_team_members_model->sync_members(
        $tender_id,
        "commercial_evaluator",
        array_map(fn($u) => (int) $u->id, $grouped["commercial_evaluator"] ?? [])
    );

    $this->Tender_team_members_model->sync_members(
        $tender_id,
        "chairman",
        array_map(fn($u) => (int) $u->id, $grouped["chairman"] ?? [])
    );

    $this->Tender_team_members_model->sync_members(
        $tender_id,
        "secretary",
        array_map(fn($u) => (int) $u->id, $grouped["secretary"] ?? [])
    );

    $this->Tender_team_members_model->sync_members(
        $tender_id,
        "itc_member",
        array_map(fn($u) => (int) $u->id, $grouped["itc_member"] ?? [])
    );
}
private function _sync_invites_by_specialty(int $tender_id, int $vendor_category_id, int $vendor_sub_category_id): int
{
    $tiv = $this->db->prefixTable("tender_invited_vendors");
    $vendors = $this->db->prefixTable("vendors");
    $spec = $this->db->prefixTable("vendor_specialties");

    // Rebuild invite snapshot from targeting.
    $this->db->query("UPDATE $tiv SET deleted=1 WHERE tender_id=?", [$tender_id]);

    $sql = "SELECT DISTINCT $vendors.id AS vendor_id
            FROM $spec
            JOIN $vendors ON $vendors.id=$spec.vendor_id
            WHERE $spec.deleted=0
              AND $spec.status='approved'
              AND $vendors.deleted=0
              AND $vendors.status='approved'
              AND $spec.vendor_category_id=?";
    $params = [$vendor_category_id];

    if ($vendor_sub_category_id > 0) {
        $sql .= " AND $spec.vendor_sub_category_id=?";
        $params[] = $vendor_sub_category_id;
    }

    $rows = $this->db->query($sql, $params)->getResult();
    if (!$rows) {
        return 0;
    }

    $now = date("Y-m-d H:i:s");
    $count = 0;

    foreach ($rows as $r) {
        $vendor_id = (int) $r->vendor_id;
        if (!$vendor_id) {
            continue;
        }

        $this->db->query(
            "INSERT INTO $tiv (tender_id, vendor_id, invite_status, invited_by, invited_at, deleted)
             VALUES (?, ?, 'sent', ?, ?, 0)",
            [$tender_id, $vendor_id, $this->login_user->id, $now]
        );
        $count++;
    }

    return $count;
}

private function _sync_invites_by_vendor_group(int $tender_id, int $vendor_group_id): int
{
    $tiv = $this->db->prefixTable("tender_invited_vendors");
    $vendors = $this->db->prefixTable("vendors");

    // Rebuild invite snapshot from group targeting.
    $this->db->query("UPDATE $tiv SET deleted=1 WHERE tender_id=?", [$tender_id]);

    if ($vendor_group_id <= 0) {
        return 0;
    }

    $rows = $this->db->query(
        "SELECT DISTINCT id AS vendor_id
         FROM $vendors
         WHERE deleted=0
           AND status='approved'
           AND vendor_group_id=?",
        [$vendor_group_id]
    )->getResult();

    if (!$rows) {
        return 0;
    }

    $now = date("Y-m-d H:i:s");
    $count = 0;

    foreach ($rows as $r) {
        $vendor_id = (int) ($r->vendor_id ?? 0);
        if (!$vendor_id) {
            continue;
        }

        $this->db->query(
            "INSERT INTO $tiv (tender_id, vendor_id, invite_status, invited_by, invited_at, deleted)
             VALUES (?, ?, 'sent', ?, ?, 0)",
            [$tender_id, $vendor_id, $this->login_user->id, $now]
        );
        $count++;
    }

    return $count;
}

private function _count_active_invites(int $tender_id): int
{
    $tiv = $this->db->prefixTable("tender_invited_vendors");
    $row = $this->db->query(
        "SELECT COUNT(*) AS total
         FROM $tiv
         WHERE deleted=0
           AND tender_id=?",
        [$tender_id]
    )->getRow();

    return (int) ($row->total ?? 0);
}

function publish()
{
    $this->validate_submitted_data(["tender_request_id" => "required|numeric"]);
    $this->access_only_tender("procurement", "update");

    $tender_request_id = (int) $this->request->getPost("tender_request_id");

    $this->Tenders_model->auto_progress_workflow();

    $tender = $this->Tenders_model->get_by_request_id($tender_request_id);
    if (!$tender || !$tender->id) {
        echo json_encode([
            "success" => false,
            "message" => "Create the tender first"
        ]);
        return;
    }

    $normalized_closing_at = $this->_normalize_tender_datetime($tender->closing_at, "end");
    $publish_error = $this->_validate_tender_can_publish($tender, $normalized_closing_at);
    if ($publish_error) {
        echo json_encode([
            "success" => false,
            "message" => $publish_error
        ]);
        return;
    }

    $request_selected_vendors = $this->Tender_request_vendors_model->get_selected_vendors($tender_request_id);
    $has_request_selected_vendors = count($request_selected_vendors) > 0;

    $tts = $this->db->prefixTable("tender_target_specialties");
    $target = $this->db->query(
        "SELECT vendor_category_id, vendor_sub_category_id
         FROM $tts
         WHERE deleted=0 AND tender_id=?
         ORDER BY id DESC
         LIMIT 1",
        [(int) $tender->id]
    )->getRow();

    $invited_count = 0;

    if ($has_request_selected_vendors) {
        $invited_count = $this->_sync_invites_from_request((int) $tender->id, $tender_request_id);
    } elseif ($target && (int) ($target->vendor_category_id ?? 0) > 0) {
        $invited_count = $this->_sync_invites_by_specialty(
            (int) $tender->id,
            (int) $target->vendor_category_id,
            (int) ($target->vendor_sub_category_id ?? 0)
        );
    } else {
        // Keep existing snapshot (e.g., group-targeted invites generated on Save).
        $invited_count = $this->_count_active_invites((int) $tender->id);
    }

    $publish_data = $this->_build_publish_payload($tender);
    $this->Tenders_model->ci_save($publish_data, (int) $tender->id);

    if ((int) $invited_count > 0) {
        $this->_send_invitation_notifications((int) $tender->id);
    }

    echo json_encode([
        "success" => true,
        "message" => "Tender published. Vendors invited: " . (int) $invited_count
    ]);
}

    function delete_document()
    {
        $this->validate_submitted_data(["id" => "required|numeric"]);
        $this->access_only_tender("procurement", "update");

        $id = (int)$this->request->getPost("id");
        $doc_data = ["deleted" => 1];
        $this->Tender_documents_model->ci_save($doc_data, $id);

        echo json_encode(["success" => true, "message" => "Document deleted"]);
    }

    private function _get_tender_business_now(): string
{
    return Time::now('Asia/Muscat')->toDateTimeString();
}

private function _normalize_tender_datetime($value, $edge = "end")
{
    $value = trim((string) $value);
    if ($value === "") {
        return null;
    }

    // HTML datetime-local: YYYY-MM-DDTHH:MM
    if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/', $value)) {
        $value = str_replace("T", " ", $value);
        if (strlen($value) === 16) {
            return $value . ":00";
        }
        return $value;
    }

    // MySQL datetime: YYYY-MM-DD HH:MM:SS
    if (preg_match('/^\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2}$/', $value)) {
        return $value;
    }

    // Date only: YYYY-MM-DD
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return $value . ($edge === "end" ? " 23:59:59" : " 00:00:00");
    }

    $ts = strtotime($value);
    if ($ts === false) {
        return null;
    }

    return date("Y-m-d H:i:s", $ts);
}

private function _build_publish_payload($tender): array
{
    $now = $this->_get_tender_business_now();

    return [
        "status" => "published",
        "workflow_stage" => "bidding",
        "published_at" => $now,

        // Reset downstream workflow dates so publish always starts clean.
        "technical_start_at" => null,
        "technical_end_at" => null,
        "technical_locked_at" => null,
        "committee_3key_start_at" => null,
        "committee_3key_end_at" => null,
        "commercial_start_at" => null,
        "commercial_end_at" => null,
        "commercial_unlocked_at" => null,
        "award_ready_at" => null,

        "updated_at" => $now
    ];
}

private function _validate_tender_can_publish($tender, $normalized_closing_at): ?string
{
    if (!$tender || empty($tender->id)) {
        return "Create the tender first";
    }

    if (!$normalized_closing_at) {
        return "Closing date is required";
    }

    $closingAt = Time::parse($normalized_closing_at, 'Asia/Muscat');
    $now = Time::now('Asia/Muscat');

    if ($closingAt->getTimestamp() <= $now->getTimestamp()) {
        return "Closing date must be in the future.";
    }

    if (($tender->status ?? "") === "closed") {
        return "Closed tenders cannot be published again.";
    }

    if (in_array(($tender->status ?? ""), ["awarded", "cancelled"], true)) {
        return "Awarded or cancelled tenders cannot be published again.";
    }

    if (in_array(($tender->workflow_stage ?? "bidding"), ["technical", "committee_3key", "commercial", "award_decision"], true)) {
        return "This tender has already moved beyond bidding stage and cannot be republished.";
    }

    return null;
}

public function award()
{
    $this->validate_submitted_data(["tender_id" => "required|numeric"]);
    $this->access_only_tender("procurement", "update");

    $tender_id = (int) $this->request->getPost("tender_id");
    $this->Tenders_model->auto_progress_workflow();

    $t = $this->db->prefixTable("tenders");
    $tender = $this->db->query(
        "SELECT * FROM $t WHERE id=? AND deleted=0 LIMIT 1",
        [$tender_id]
    )->getRow();

    if (!$tender) {
        return $this->response->setJSON(["success" => false, "message" => "Tender not found."]);
    }

    if (in_array((string) ($tender->status ?? ""), ["awarded", "cancelled"], true)) {
        return $this->response->setJSON(["success" => false, "message" => "Tender is already finalized."]);
    }

    if (($tender->status ?? "") !== "closed" || ($tender->workflow_stage ?? "") !== "award_decision") {
        return $this->response->setJSON([
            "success" => false,
            "message" => "Tender must be in Award Decision stage before final award."
        ]);
    }

    $summary = $this->_get_commercial_decision_summary($tender_id);
    if ((int) ($summary["approved_count"] ?? 0) !== 1) {
        return $this->response->setJSON([
            "success" => false,
            "message" => "Exactly one commercially approved bid is required before final award."
        ]);
    }

    $winner_vendor_id = (int) ($summary["winner_vendor_id"] ?? 0);
    if (!$winner_vendor_id) {
        return $this->response->setJSON([
            "success" => false,
            "message" => "Unable to identify winner vendor."
        ]);
    }

    $now = $this->_get_tender_business_now();
    $this->Tenders_model->ci_save([
        "status" => "awarded",
        "workflow_stage" => "award_decision",
        "award_ready_at" => $tender->award_ready_at ?: $now,
        "updated_at" => $now
    ], $tender_id);

    $this->_send_award_and_regret_notifications($tender_id, $winner_vendor_id);

    return $this->response->setJSON([
        "success" => true,
        "message" => "Tender awarded successfully."
    ]);
}

public function cancel_tender()
{
    $this->validate_submitted_data(["tender_id" => "required|numeric"]);
    $this->access_only_tender("procurement", "update");

    $tender_id = (int) $this->request->getPost("tender_id");
    $t = $this->db->prefixTable("tenders");
    $tender = $this->db->query(
        "SELECT * FROM $t WHERE id=? AND deleted=0 LIMIT 1",
        [$tender_id]
    )->getRow();

    if (!$tender) {
        return $this->response->setJSON(["success" => false, "message" => "Tender not found."]);
    }

    if (($tender->status ?? "") === "awarded") {
        return $this->response->setJSON(["success" => false, "message" => "Awarded tender cannot be cancelled."]);
    }

    if (($tender->status ?? "") === "cancelled") {
        return $this->response->setJSON(["success" => false, "message" => "Tender is already cancelled."]);
    }

    $this->Tenders_model->ci_save([
        "status" => "cancelled",
        "updated_at" => $this->_get_tender_business_now()
    ], $tender_id);

    $this->_send_cancellation_notifications($tender_id);

    return $this->response->setJSON([
        "success" => true,
        "message" => "Tender cancelled."
    ]);
}

public function retender()
{
    $this->validate_submitted_data(["tender_id" => "required|numeric"]);
    $this->access_only_tender("procurement", "create");

    $source_tender_id = (int) $this->request->getPost("tender_id");
    $t = $this->db->prefixTable("tenders");

    $source = $this->db->query(
        "SELECT * FROM $t WHERE id=? AND deleted=0 LIMIT 1",
        [$source_tender_id]
    )->getRow();

    if (!$source) {
        return $this->response->setJSON(["success" => false, "message" => "Source tender not found."]);
    }

    if (!in_array((string) ($source->status ?? ""), ["closed", "cancelled", "awarded"], true)) {
        return $this->response->setJSON([
            "success" => false,
            "message" => "Only closed/cancelled/awarded tenders can be retendered."
        ]);
    }

    $now = $this->_get_tender_business_now();
    $new_reference = trim((string) ($source->reference ?? "TENDER")) . "-RT-" . date("YmdHis");

    $new_closing_at = null;
    if (!empty($source->closing_at)) {
        $new_closing_at = date("Y-m-d H:i:s", strtotime($source->closing_at . " +7 days"));
        if (strtotime($new_closing_at) <= strtotime($now)) {
            $new_closing_at = date("Y-m-d H:i:s", strtotime($now . " +7 days"));
        }
    }

    $this->db->transStart();

    $new_tender_id = (int) $this->Tenders_model->ci_save([
        "tender_request_id" => (int) ($source->tender_request_id ?? 0),
        "reference" => $new_reference,
        "title" => (string) ($source->title ?? ""),
        "tender_type" => (string) ($source->tender_type ?? "open"),
        "status" => "draft",
        "workflow_stage" => "bidding",
        "published_at" => null,
        "closing_at" => $new_closing_at,
        "technical_start_at" => null,
        "technical_end_at" => null,
        "technical_locked_at" => null,
        "committee_3key_start_at" => null,
        "committee_3key_end_at" => null,
        "commercial_start_at" => null,
        "commercial_end_at" => null,
        "commercial_unlocked_at" => null,
        "award_ready_at" => null,
        "created_by" => (int) $this->login_user->id,
        "created_at" => $now,
        "updated_at" => $now,
        "deleted" => 0
    ]);

    if (!$new_tender_id) {
        $this->db->transComplete();
        return $this->response->setJSON(["success" => false, "message" => "Failed to create retender record."]);
    }

    $tts = $this->db->prefixTable("tender_target_specialties");
    $target = $this->db->query(
        "SELECT * FROM $tts
         WHERE tender_id=? AND deleted=0
         ORDER BY id DESC
         LIMIT 1",
        [$source_tender_id]
    )->getRow();

    if ($target && !empty($target->vendor_category_id)) {
        $this->db->query(
            "INSERT INTO $tts (tender_id, vendor_category_id, vendor_sub_category_id, created_by, created_at, deleted)
             VALUES (?, ?, ?, ?, ?, 0)",
            [
                $new_tender_id,
                (int) $target->vendor_category_id,
                !empty($target->vendor_sub_category_id) ? (int) $target->vendor_sub_category_id : null,
                (int) $this->login_user->id,
                $now
            ]
        );
    }

    $ttm = $this->db->prefixTable("tender_team_members");
    $members = $this->db->query(
        "SELECT user_id, team_role
         FROM $ttm
         WHERE tender_id=? AND deleted=0 AND is_active=1",
        [$source_tender_id]
    )->getResult();

    foreach ($members as $m) {
        $this->db->query(
            "INSERT INTO $ttm (tender_id, user_id, team_role, is_active, created_at, updated_at, deleted)
             VALUES (?, ?, ?, 1, ?, ?, 0)",
            [$new_tender_id, (int) $m->user_id, (string) $m->team_role, $now, $now]
        );
    }

    $td = $this->db->prefixTable("tender_documents");
    $docs = $this->db->query(
        "SELECT doc_type, title, disk, path, original_name, mime_type, size_bytes, time_limited, expires_in_hours
         FROM $td
         WHERE tender_id=? AND deleted=0",
        [$source_tender_id]
    )->getResult();

    foreach ($docs as $d) {
        $this->db->query(
            "INSERT INTO $td
            (tender_id, doc_type, title, disk, path, original_name, mime_type, size_bytes, time_limited, expires_in_hours, uploaded_by, created_at, deleted)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)",
            [
                $new_tender_id,
                (string) $d->doc_type,
                $d->title,
                (string) ($d->disk ?? "local"),
                (string) $d->path,
                $d->original_name,
                $d->mime_type,
                $d->size_bytes,
                (int) ($d->time_limited ?? 0),
                !empty($d->time_limited) ? (int) $d->expires_in_hours : null,
                (int) $this->login_user->id,
                $now
            ]
        );
    }

    $tiv = $this->db->prefixTable("tender_invited_vendors");
    $invites = $this->db->query(
        "SELECT DISTINCT vendor_id
         FROM $tiv
         WHERE tender_id=? AND deleted=0",
        [$source_tender_id]
    )->getResult();

    foreach ($invites as $iv) {
        $vendor_id = (int) ($iv->vendor_id ?? 0);
        if (!$vendor_id) {
            continue;
        }
        $this->db->query(
            "INSERT INTO $tiv (tender_id, vendor_id, invite_status, invited_by, invited_at, deleted)
             VALUES (?, ?, 'sent', ?, ?, 0)",
            [$new_tender_id, $vendor_id, (int) $this->login_user->id, $now]
        );
    }

    $this->db->transComplete();
    if ($this->db->transStatus() === false) {
        return $this->response->setJSON(["success" => false, "message" => "Failed to create retender."]);
    }

    return $this->response->setJSON([
        "success" => true,
        "message" => "Retender created successfully as draft.",
        "new_tender_id" => $new_tender_id
    ]);
}

private function _get_commercial_decision_summary(int $tender_id): array
{
    $tb = $this->db->prefixTable("tender_bids");
    $te = $this->db->prefixTable("tender_evaluations");
    $v = $this->db->prefixTable("vendors");

    $rows = $this->db->query(
        "SELECT
            $tb.id AS bid_id,
            $tb.vendor_id,
            $v.vendor_name,
            $v.email,
            COALESCE(latest_eval.decision, '') AS commercial_decision
        FROM $tb
        INNER JOIN $v ON $v.id = $tb.vendor_id AND $v.deleted = 0
        LEFT JOIN (
            SELECT x.tender_bid_id, x.decision
            FROM $te x
            INNER JOIN (
                SELECT tender_bid_id, MAX(id) AS max_id
                FROM $te
                WHERE deleted = 0
                  AND type = 'commercial'
                GROUP BY tender_bid_id
            ) m ON m.max_id = x.id
            WHERE x.deleted = 0
              AND x.type = 'commercial'
        ) latest_eval ON latest_eval.tender_bid_id = $tb.id
        WHERE $tb.deleted = 0
          AND $tb.tender_id = ?
          AND $tb.status = 'accepted'
        ORDER BY $tb.id ASC",
        [$tender_id]
    )->getResult();

    $approved = [];
    $pending = 0;
    foreach ($rows as $row) {
        $decision = strtolower(trim((string) ($row->commercial_decision ?? "")));
        if ($decision === "accepted") {
            $approved[] = $row;
        } elseif ($decision === "") {
            $pending++;
        }
    }

    $winner = $approved[0] ?? null;

    return [
        "rows" => $rows,
        "pending_count" => $pending,
        "approved_count" => count($approved),
        "winner_vendor_id" => (int) ($winner->vendor_id ?? 0),
        "winner_bid_id" => (int) ($winner->bid_id ?? 0)
    ];
}

private function _get_tender_recipients(int $tender_id): array
{
    $tiv = $this->db->prefixTable("tender_invited_vendors");
    $v = $this->db->prefixTable("vendors");
    $tb = $this->db->prefixTable("tender_bids");

    $recipients = [];

    $invited = $this->db->query(
        "SELECT DISTINCT $v.id AS vendor_id, $v.vendor_name, $v.email
         FROM $tiv
         INNER JOIN $v ON $v.id = $tiv.vendor_id
         WHERE $tiv.deleted = 0
           AND $v.deleted = 0
           AND $tiv.tender_id = ?",
        [$tender_id]
    )->getResult();

    foreach ($invited as $r) {
        $recipients[(int) $r->vendor_id] = $r;
    }

    $bidders = $this->db->query(
        "SELECT DISTINCT $v.id AS vendor_id, $v.vendor_name, $v.email
         FROM $tb
         INNER JOIN $v ON $v.id = $tb.vendor_id
         WHERE $tb.deleted = 0
           AND $v.deleted = 0
           AND $tb.tender_id = ?",
        [$tender_id]
    )->getResult();

    foreach ($bidders as $r) {
        $recipients[(int) $r->vendor_id] = $r;
    }

    return array_values($recipients);
}

private function _send_tender_email(?string $to, string $subject, string $message): void
{
    if (!self::TENDER_EMAILS_ENABLED) {
        return;
    }

    $to = trim((string) $to);
    if (!$to || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return;
    }

    if (function_exists("send_app_mail")) {
        @send_app_mail($to, $subject, $message);
    }
}

private function _send_invitation_notifications(int $tender_id): void
{
    $t = $this->db->prefixTable("tenders");
    $tiv = $this->db->prefixTable("tender_invited_vendors");
    $v = $this->db->prefixTable("vendors");
    $tender = $this->db->query(
        "SELECT reference, title, closing_at FROM $t WHERE id=? AND deleted=0 LIMIT 1",
        [$tender_id]
    )->getRow();

    if (!$tender) {
        return;
    }

    $recipients = $this->db->query(
        "SELECT DISTINCT $v.id AS vendor_id, $v.vendor_name, $v.email
         FROM $tiv
         INNER JOIN $v ON $v.id = $tiv.vendor_id
         WHERE $tiv.deleted = 0
           AND $v.deleted = 0
           AND $tiv.tender_id = ?",
        [$tender_id]
    )->getResult();

    foreach ($recipients as $r) {
        $subject = "Tender Invitation - " . ($tender->reference ?? "Tender");
        $message = "Dear " . ($r->vendor_name ?? "Vendor") . ",\n\n"
            . "You are invited to participate in tender " . ($tender->reference ?? "-")
            . " (" . ($tender->title ?? "-") . ").\n"
            . "Closing date: " . ($tender->closing_at ?? "-") . ".\n\n"
            . "Please login to the vendor portal to review details and submit your bid.\n";

        $this->_send_tender_email($r->email ?? null, $subject, $message);
    }
}

private function _send_award_and_regret_notifications(int $tender_id, int $winner_vendor_id): void
{
    $t = $this->db->prefixTable("tenders");
    $tender = $this->db->query(
        "SELECT reference, title FROM $t WHERE id=? AND deleted=0 LIMIT 1",
        [$tender_id]
    )->getRow();

    if (!$tender) {
        return;
    }

    $recipients = $this->_get_tender_recipients($tender_id);
    foreach ($recipients as $r) {
        $vendor_id = (int) ($r->vendor_id ?? 0);
        if (!$vendor_id) {
            continue;
        }

        if ($vendor_id === $winner_vendor_id) {
            $subject = "Tender Award Notification - " . ($tender->reference ?? "Tender");
            $message = "Dear " . ($r->vendor_name ?? "Vendor") . ",\n\n"
                . "We are pleased to inform you that your bid has been awarded for tender "
                . ($tender->reference ?? "-") . " (" . ($tender->title ?? "-") . ").\n\n"
                . "Procurement will contact you with the next steps.\n";
        } else {
            $subject = "Tender Regret Notification - " . ($tender->reference ?? "Tender");
            $message = "Dear " . ($r->vendor_name ?? "Vendor") . ",\n\n"
                . "Thank you for participating in tender " . ($tender->reference ?? "-")
                . " (" . ($tender->title ?? "-") . ").\n"
                . "After evaluation, another vendor has been selected.\n\n"
                . "We appreciate your participation.\n";
        }

        $this->_send_tender_email($r->email ?? null, $subject, $message);
    }
}

private function _send_cancellation_notifications(int $tender_id): void
{
    $t = $this->db->prefixTable("tenders");
    $tender = $this->db->query(
        "SELECT reference, title FROM $t WHERE id=? AND deleted=0 LIMIT 1",
        [$tender_id]
    )->getRow();

    if (!$tender) {
        return;
    }

    $recipients = $this->_get_tender_recipients($tender_id);
    foreach ($recipients as $r) {
        $subject = "Tender Cancellation - " . ($tender->reference ?? "Tender");
        $message = "Dear " . ($r->vendor_name ?? "Vendor") . ",\n\n"
            . "Please be informed that tender " . ($tender->reference ?? "-")
            . " (" . ($tender->title ?? "-") . ") has been cancelled.\n\n"
            . "You will be notified if it is re-issued.\n";

        $this->_send_tender_email($r->email ?? null, $subject, $message);
    }
}

    private function _make_row($row)
    {
        $req_status = "<span class='badge bg-secondary'>" . esc($row->status) . "</span>";

        $tender_status = $row->tender_id
            ? "<span class='badge bg-info'>" . esc($row->tender_status) . "</span>"
            : "<span class='badge bg-light text-dark'>not created</span>";

        $closing = $row->closing_at ? esc($row->closing_at) : "-";

        $setup = modal_anchor(
            get_uri("tender_procurement_inbox/modal_form"),
            "<i data-feather='settings' class='icon-16'></i>",
            ["title" => "Setup Tender", "data-post-id" => $row->id, "class" => "edit"]
        );

        $publish = "";
        if ($row->tender_id && in_array(($row->tender_status ?? ""), ["draft", ""], true)) {
            $publish = js_anchor(
                "<i data-feather='send' class='icon-16'></i>",
                [
                    "title" => "Publish",
                    "class" => "publish",
                    "data-request-id" => $row->id,
                    "data-action-url" => get_uri("tender_procurement_inbox/publish")
                ]
            );
        }

        $final_actions = "";
        $can_finalize = $row->tender_id
            && (($row->tender_status ?? "") === "closed")
            && (($row->tender_workflow_stage ?? "") === "award_decision");

        if ($can_finalize) {
            $final_actions .= js_anchor(
                "<i data-feather='award' class='icon-16'></i>",
                [
                    "title" => "Award",
                    "class" => "award",
                    "data-tender-id" => (int) $row->tender_id,
                    "data-action-url" => get_uri("tender_procurement_inbox/award")
                ]
            );

            $final_actions .= " " . js_anchor(
                "<i data-feather='x-circle' class='icon-16'></i>",
                [
                    "title" => "Cancel",
                    "class" => "cancel-tender",
                    "data-tender-id" => (int) $row->tender_id,
                    "data-action-url" => get_uri("tender_procurement_inbox/cancel_tender")
                ]
            );

            $final_actions .= " " . js_anchor(
                "<i data-feather='copy' class='icon-16'></i>",
                [
                    "title" => "Retender",
                    "class" => "retender",
                    "data-tender-id" => (int) $row->tender_id,
                    "data-action-url" => get_uri("tender_procurement_inbox/retender")
                ]
            );
        }

        return [
            esc($row->reference),
            esc($row->subject),
            esc($row->company_name ?? "-"),
            esc($row->department_name ?? "-"),
            esc($row->tender_type),
            $req_status,
            $tender_status,
            $closing,
            trim($setup . " " . $publish . " " . $final_actions)
        ];
    }
}
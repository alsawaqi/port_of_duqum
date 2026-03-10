<?php

namespace App\Controllers;

use App\Models\Tender_requests_model;
use App\Models\Tenders_model;
use App\Models\Tender_documents_model;
use App\Models\Tender_target_specialties_model;
use App\Models\Tender_invited_vendors_model;
use App\Models\Tender_request_vendors_model;
 
use App\Models\Tender_request_team_members_model;
use App\Models\Tender_team_members_model;
use CodeIgniter\I18n\Time;

class Tender_procurement_inbox extends Security_Controller
{
    protected $Tender_requests_model;
    protected $Tenders_model;
    protected $Tender_documents_model;
    protected $Tender_request_vendors_model;
    protected $Tender_request_team_members_model;
    protected $Tender_team_members_model;

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

    $vendor_category_id = (int) $this->request->getPost("vendor_category_id");
    $vendor_sub_category_id = (int) $this->request->getPost("vendor_sub_category_id");

    $request_selected_vendors = $this->Tender_request_vendors_model->get_selected_vendors($tender_request_id);
    $has_request_selected_vendors = count($request_selected_vendors) > 0;
    // Category is sufficient for targeting; subcategory is optional.
    $has_specialty_target = ($vendor_category_id > 0);

    if ($tender_type === "close" && !$has_request_selected_vendors && !$has_specialty_target) {
        echo json_encode([
            "success" => false,
            "message" => "For CLOSE tender, use invited vendors from the request or select vendor category/subcategory."
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

    // Always persist current targeting rule for this tender (audit + reload support).
    $this->_save_target_specialty($tender_id, $vendor_category_id, $vendor_sub_category_id);

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
        $tiv = $this->db->prefixTable("tender_invited_vendors");
        $this->db->query(
            "UPDATE $tiv SET deleted=1 WHERE tender_id=?",
            [(int) $tender->id]
        );
    }

    $publish_data = $this->_build_publish_payload($tender);
    $this->Tenders_model->ci_save($publish_data, (int) $tender->id);

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

    if (in_array(($tender->workflow_stage ?? "bidding"), ["technical", "committee_3key", "commercial", "award_decision"], true)) {
        return "This tender has already moved beyond bidding stage and cannot be republished.";
    }

    return null;
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

        return [
            esc($row->reference),
            esc($row->subject),
            esc($row->company_name ?? "-"),
            esc($row->department_name ?? "-"),
            esc($row->tender_type),
            $req_status,
            $tender_status,
            $closing,
            $setup . " " . $publish
        ];
    }
}
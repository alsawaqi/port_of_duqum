<?php

namespace App\Controllers;

use App\Controllers\Security_Controller;
use App\Models\Vendor_update_requests_model;
use App\Models\Vendors_model;
use Config\Database;

class Vendor_update_requests extends Security_Controller
{
    protected $Vendor_update_requests_model;
    protected $Vendors_model;
    protected $db;



    public function __construct()
    {
        parent::__construct();

        $this->access_only_team_members();

        $this->Vendor_update_requests_model = new Vendor_update_requests_model();
        $this->Vendors_model = new Vendors_model();
        $this->db = Database::connect(); // ✅ REQUIRED
    }



    public function index()
    {
        $this->access_only_vendor_update_requests_view();

        $view_data = [
            "can_view_vendor_update_requests" => $this->can_view_vendor_update_requests(),
            "can_view_vendor_update_requests_by_vendor" => $this->can_view_vendor_update_requests_by_vendor(),
            "can_review_vendor_update_requests" => $this->can_review_vendor_update_requests(),
            "can_approve_vendor_update_requests" => $this->can_approve_vendor_update_requests(),
            "can_reject_vendor_update_requests" => $this->can_reject_vendor_update_requests()
        ];

        return $this->template->rander("vendor_update_requests/index", $view_data);
    }


   


    function vendors()
    {
        $this->access_only_vendor_update_requests_view();
        $this->access_only_vendor_update_requests_by_vendor_view();

        $view_data = [
            "can_view_vendor_update_requests" => $this->can_view_vendor_update_requests(),
            "can_view_vendor_update_requests_by_vendor" => $this->can_view_vendor_update_requests_by_vendor(),
            "can_review_vendor_update_requests" => $this->can_review_vendor_update_requests(),
            "can_approve_vendor_update_requests" => $this->can_approve_vendor_update_requests(),
            "can_reject_vendor_update_requests" => $this->can_reject_vendor_update_requests()
        ];

        return $this->template->rander("vendor_update_requests/vendors", $view_data);
    }



    function vendors_list_data()
    {
        $this->access_only_vendor_update_requests_view();
        $this->access_only_vendor_update_requests_by_vendor_view();

        $can_open_vendor = $this->can_view_vendor_update_requests_by_vendor();

        $list = $this->Vendor_update_requests_model
            ->get_grouped_by_vendor_details(["statuses" => ["pending", "review"]])
            ->getResult();

        $result = [];
        foreach ($list as $row) {
            $vendor_name = $row->vendor_name ? esc($row->vendor_name) : "-";
            $vendor_link = $vendor_name;
            if ($can_open_vendor) {
                $vendor_link = anchor(
                    get_uri("vendor_update_requests/vendor/" . (int)$row->vendor_id),
                    $vendor_name
                );
            }

            $row_data = [
                $vendor_link,
                (int)$row->pending_count,
                (int)$row->review_count,
                (int)$row->total_count,
                $row->last_request_at ? format_to_relative_time($row->last_request_at) : "-",
            ];

            if ($can_open_vendor) {
                $row_data[] = anchor(
                    get_uri("vendor_update_requests/vendor/" . (int)$row->vendor_id),
                    "<i data-feather='arrow-right-circle' class='icon-16'></i>",
                    ["class" => "btn btn-default btn-sm", "title" => "Open vendor requests"]
                );
            }

            $result[] = $row_data;
        }

        return $this->response->setJSON(["data" => $result]);
    }

    function vendor($vendor_id = 0)
    {
        $this->access_only_vendor_update_requests_view();
        $this->access_only_vendor_update_requests_by_vendor_view();




        $vendor_id = (int)$vendor_id;
        if (!$vendor_id) {
            show_404();
        }

        $vendor_info = $this->Vendors_model->get_one($vendor_id);
        if (empty($vendor_info) || (int)$vendor_info->id !== $vendor_id) {
            show_404();
        }

        $view_data = [
            "vendor_id" => $vendor_id,
            "vendor_info" => $vendor_info,
            "can_view_vendor_update_requests" => $this->can_view_vendor_update_requests(),
            "can_view_vendor_update_requests_by_vendor" => $this->can_view_vendor_update_requests_by_vendor(),
            "can_review_vendor_update_requests" => $this->can_review_vendor_update_requests(),
            "can_approve_vendor_update_requests" => $this->can_approve_vendor_update_requests(),
            "can_reject_vendor_update_requests" => $this->can_reject_vendor_update_requests()
        ];

        return $this->template->rander("vendor_update_requests/vendor", $view_data);
    }


    // Backward-compat aliases used by some views
    function preview_modal_form()
    {
        return $this->view();
    }

    function review_modal_form()
    {
        return $this->review_modal();
    }

    function vendor_list_data($vendor_id = 0)
    {
        $this->access_only_vendor_update_requests_view();
        $this->access_only_vendor_update_requests_by_vendor_view();

        $vendor_id = (int)$vendor_id;
        if (!$vendor_id) {
            return $this->response->setJSON(["data" => []]);
        }

        $list_data = $this->Vendor_update_requests_model->get_details([
            "vendor_id" => $vendor_id
        ])->getResult();

        $result = [];
        foreach ($list_data as $row) {
            $result[] = $this->_make_row_for_vendor_page($row);
        }

        return $this->response->setJSON(["data" => $result]);
    }


    public function specialties_list_data()
    {
        $vendorId = $this->login_user->vendor_id; // or however you get current vendor id

        $vsTable   = $this->db->prefixTable("vendor_specialties");
        $vcTable   = $this->db->prefixTable("vendor_categories");
        $vscTable  = $this->db->prefixTable("vendor_sub_categories");

        $rows = $this->db->table("$vsTable AS vs")
            ->select("vs.id,
                  vs.specialty_description,
                  vc.name  AS category_name,
                  vsc.name AS sub_category_name")
            ->join("$vcTable AS vc",  "vc.id  = vs.vendor_category_id",    "left")
            ->join("$vscTable AS vsc", "vsc.id = vs.vendor_sub_category_id", "left")
            ->where("vs.vendor_id", $vendorId)
            ->where("vs.deleted", 0)
            ->orderBy("vs.id", "DESC")
            ->get()
            ->getResult();

        $data = [];
        foreach ($rows as $r) {
            $data[] = [
                esc($r->category_name ?? "-"),
                esc($r->sub_category_name ?? "-"),
                esc($r->specialty_description ?? "-"),
            ];
        }

        return $this->response->setJSON(["data" => $data]);
    }


    private function _make_row_for_vendor_page($row)
    {
        $changes = json_decode($row->changes ?? "");
        if (!$changes || !is_object($changes)) {
            $changes = (object) [];
        }

        $module = $changes->module ?? "-";
        $action = $changes->action ?? "-";


        $specialtiesDetails = "-";


        // Only for specialties module
        if (isset($changes->module) && $changes->module === "specialties") {
            // Try AFTER first, then fallback to BEFORE
            $after  = isset($changes->after)  ? (array)$changes->after  : [];
            $before = isset($changes->before) ? (array)$changes->before : [];

            $vendorCategoryId    = $after["vendor_category_id"]    ?? $before["vendor_category_id"]    ?? null;
            $vendorSubCategoryId = $after["vendor_sub_category_id"] ?? $before["vendor_sub_category_id"] ?? null;

            $parts = [];

            if ($vendorCategoryId) {
                $cat = $this->db->table($this->db->prefixTable("vendor_categories"))
                    ->select("name")
                    ->where("id", (int)$vendorCategoryId)
                    ->where("deleted", 0)
                    ->get()
                    ->getRow();

                if ($cat && !empty($cat->name)) {
                    $parts[] = esc($cat->name);
                }
            }

            if ($vendorSubCategoryId) {
                $sub = $this->db->table($this->db->prefixTable("vendor_sub_categories"))
                    ->select("name")
                    ->where("id", (int)$vendorSubCategoryId)
                    ->where("deleted", 0)
                    ->get()
                    ->getRow();

                if ($sub && !empty($sub->name)) {
                    $parts[] = esc($sub->name);
                }
            }

            if (!empty($parts)) {
                // e.g. "Electrical → Cables"
                $specialtiesDetails = "<span class='badge bg-light text-dark'>" . implode(" &raquo; ", $parts) . "</span>";
            }
        }

        // checkbox only for pending
        $checkbox = "";
        if ($row->status === "pending") {
            $checkbox = "<input type='checkbox' class='bulk-request-checkbox' value='" . (int)$row->id . "' />";
        }

        $statusLabel = "<span class='badge bg-secondary'>Unknown</span>";
        if ($row->status === "pending")  $statusLabel = "<span class='badge bg-warning'>Pending</span>";
        if ($row->status === "review")   $statusLabel = "<span class='badge bg-info'>Review</span>";
        if ($row->status === "approved") $statusLabel = "<span class='badge bg-success'>Approved</span>";
        if ($row->status === "rejected") $statusLabel = "<span class='badge bg-danger'>Rejected</span>";

        $can_view = $this->can_view_vendor_update_requests();
        $can_review = $this->can_review_vendor_update_requests();
        $can_approve = $this->can_approve_vendor_update_requests();
        $can_reject = $this->can_reject_vendor_update_requests();

        $actions = "";
        if ($can_view) {
            $actions .= modal_anchor(
                get_uri("vendor_update_requests/preview_modal_form"),
                "<i data-feather='eye' class='icon-16'></i>",
                ["class" => "edit", "title" => app_lang("view"), "data-post-id" => $row->id]
            );
        }

        if ($row->status === "pending") {
            if ($can_approve) {
                $actions .= " " . js_anchor(
                    "<i data-feather='check-circle' class='icon-16'></i>",
                    [
                        "title" => app_lang("approve"),
                        "class" => "text-success approve-one",
                        "data-id" => (int)$row->id
                    ]
                );
            }

            if ($can_reject) {
                $actions .= " " . js_anchor(
                    "<i data-feather='x-circle' class='icon-16'></i>",
                    [
                        "title" => app_lang("reject"),
                        "class" => "text-danger reject-one",
                        "data-id" => (int)$row->id
                    ]
                );
            }

            if ($can_review) {
                // review is still one-by-one (modal)
                $actions .= " " . modal_anchor(
                    get_uri("vendor_update_requests/review_modal_form"),
                    "<i data-feather='message-square' class='icon-16'></i>",
                    ["class" => "edit", "title" => "Review", "data-post-id" => $row->id]
                );
            }
        }

        return [
            $checkbox,
            ucfirst(esc($module)),
            ucfirst(esc($action)),
            $specialtiesDetails, // <-- new column
            $row->requested_by ? esc($row->requested_by) : "-",
            $row->created_at ? format_to_relative_time($row->created_at) : "-",
            $statusLabel,
            $actions
        ];
    }

    function bulk_approve()
    {
        $this->access_only_vendor_update_requests_approve();

        $ids = $this->request->getPost("ids");
        if (!is_array($ids) || !count($ids)) {
            return $this->response->setJSON(["success" => false, "message" => "No requests selected."]);
        }

        $ids = array_values(array_filter(array_map("intval", $ids)));

        $approved = 0;
        $skipped  = 0;

        $this->db->transBegin();
        try {
            foreach ($ids as $id) {
                $row = $this->Vendor_update_requests_model->get_one($id);

                if (!$row || (int)$row->deleted === 1 || $row->status !== "pending") {
                    $skipped++;
                    continue;
                }

                $changes = json_decode($row->changes ?? "[]", true);
                if (!is_array($changes) || !count($changes)) {
                    $skipped++;
                    continue;
                }

                $this->_apply_changes($changes, "approved");

                // update request row itself
                $this->db->table($this->db->prefixTable("vendor_update_requests"))
                    ->where("id", $id)
                    ->update([
                        "status"      => "approved",
                        "reviewed_by" => $this->login_user->id ?? null,
                        "reviewed_at" => get_current_utc_time(),
                    ]);

                $approved++;
            }

            if ($this->db->transStatus() === false) {
                throw new \Exception("Transaction failed.");
            }

            $this->db->transCommit();

            return $this->response->setJSON([
                "success" => true,
                "message" => "Approved: {$approved}. Skipped: {$skipped}."
            ]);
        } catch (\Throwable $e) {
            $this->db->transRollback();
            log_message("error", "BULK APPROVE FAILED: " . $e->getMessage());

            return $this->response->setJSON([
                "success" => false,
                "message" => "Bulk approve failed: " . $e->getMessage()
            ]);
        }
    }

    function bulk_reject()
    {
        $this->access_only_vendor_update_requests_reject();

        $ids = $this->request->getPost("ids");
        $reason = trim((string)$this->request->getPost("reason"));

        if (!is_array($ids) || !count($ids)) {
            return $this->response->setJSON(["success" => false, "message" => "No requests selected."]);
        }
        if (!$reason) {
            return $this->response->setJSON(["success" => false, "message" => "Reject reason is required."]);
        }

        $ids = array_values(array_filter(array_map("intval", $ids)));

        $rejected = 0;
        $skipped  = 0;

        $this->db->transBegin();
        try {
            foreach ($ids as $id) {
                $row = $this->Vendor_update_requests_model->get_one($id);

                if (!$row || (int)$row->deleted === 1 || $row->status !== "pending") {
                    $skipped++;
                    continue;
                }

                $changes = json_decode($row->changes ?? "[]", true);
                if (!is_array($changes) || !count($changes)) {
                    $skipped++;
                    continue;
                }

                $this->_apply_changes($changes, "rejected"); // no if(...)




                $this->db->table($this->db->prefixTable("vendor_update_requests"))
                    ->where("id", $id)
                    ->update([
                        "status"         => "rejected",
                        "review_comment" => $reason,
                        "reviewed_by"    => $this->login_user->id ?? null,
                        "reviewed_at"    => get_current_utc_time(),
                    ]);

                $rejected++;
            }

            if ($this->db->transStatus() === false) {
                throw new \Exception("Transaction failed.");
            }

            $this->db->transCommit();

            return $this->response->setJSON([
                "success" => true,
                "message" => "Rejected: {$rejected}. Skipped: {$skipped}."
            ]);
        } catch (\Throwable $e) {
            $this->db->transRollback();
            log_message("error", "BULK REJECT FAILED: " . $e->getMessage());

            return $this->response->setJSON([
                "success" => false,
                "message" => "Bulk reject failed: " . $e->getMessage()
            ]);
        }
    }




    public function list_data()
    {
        $this->access_only_vendor_update_requests_view();
        try {
            $list = $this->Vendor_update_requests_model
                ->get_details(["deleted" => 0])
                ->getResult();

            $result = [];
            foreach ($list as $row) {
                $result[] = $this->_make_row($row);
            }

            return $this->response->setJSON(["data" => $result]);
        } catch (\Throwable $e) {
            log_message("error", "VUR list_data failed: " . $e->getMessage());

            return $this->response->setJSON([
                "data" => [],
                "error" => $e->getMessage()
            ]);
        }
    }



    public function reject_modal()
    {
        $this->access_only_vendor_update_requests_reject();
        $this->validate_submitted_data([
            "id" => "required|numeric"
        ]);

        $view_data["id"] = $this->request->getPost("id");

        return $this->template->view(
            "vendor_update_requests/reject_modal",
            $view_data
        );
    }


    private function _make_row($row)
    {
        $changes = json_decode($row->changes ?? "");
        if (!$changes || !is_object($changes)) {
            $changes = (object) [];
        }

        $vendor = $this->Vendors_model->get_one($row->vendor_id);

        $approval = $this->_approval_badge($row->status);

        $can_view = $this->can_view_vendor_update_requests();
        $can_review = $this->can_review_vendor_update_requests();
        $can_approve = $this->can_approve_vendor_update_requests();
        $can_reject = $this->can_reject_vendor_update_requests();

        $actions = "";
        if ($can_view) {
            $actions .= modal_anchor(
                get_uri("vendor_update_requests/view"),
                "<i data-feather='eye' class='icon-16'></i>",
                [
                    "title" => app_lang("view_details"),
                    "data-post-id" => $row->id,
                    "class" => "btn btn-default btn-sm"
                ]
            );
        }

        // ONLY allow approve / reject if pending
        if ($row->status === "pending") {
            if ($can_approve) {
                $actions .= ajax_anchor(
                    get_uri("vendor_update_requests/approve"),
                    "<i data-feather='check' class='icon-16'></i>",
                    [
                        "title" => app_lang("approve"),
                        "class" => "btn btn-success btn-sm ml5",
                        "data-post-id" => $row->id,
                        "data-reload-on-success" => "1"
                    ]
                );
            }

            if ($can_review) {
                $actions .= modal_anchor(
                    get_uri("vendor_update_requests/review_modal"),
                    "<i data-feather='message-square' class='icon-16'></i>",
                    [
                        "title" => app_lang("review"),
                        "class" => "btn btn-info btn-sm ml5",
                        "data-post-id" => $row->id
                    ]
                );
            }

            if ($can_reject) {
                $actions .= modal_anchor(
                    get_uri("vendor_update_requests/reject_modal"),
                    "<i data-feather='x' class='icon-16'></i>",
                    [
                        "title" => app_lang("reject"),
                        "class" => "btn btn-danger btn-sm ml5",
                        "data-post-id" => $row->id
                    ]
                );
            }
        }


        return [
            $row->id,
            $vendor->vendor_name ?? "-",
            ucfirst($changes->module ?? "-"),
            ucfirst($changes->action ?? "-"),
            $row->requested_by_name ?? "-",
            format_to_datetime($row->created_at),
            $approval,
            $actions
        ];
    }

    private function _approval_badge($status)
    {
        switch ($status) {
            case "approved":
                $class = "bg-success";
                break;
            case "rejected":
                $class = "bg-danger";
                break;
            case "review":
                $class = "bg-info";
                break;
            default:
                $class = "bg-warning text-dark";
                $status = "pending";
        }

        return "<span class='badge {$class}'>" . app_lang($status) . "</span>";
    }


    public function view()
    {
        $this->access_only_vendor_update_requests_view();
        $this->validate_submitted_data(["id" => "required|numeric"]);

        $id = (int) $this->request->getPost("id");

        $model_info = $this->Vendor_update_requests_model->get_one($id);

        if (!$model_info || $model_info->deleted) {
            return $this->response->setJSON([
                "success" => false,
                "message" => app_lang("error_occurred")
            ]);
        }

        $changes = json_decode($model_info->changes ?? "{}", true);
        if (!is_array($changes)) {
            $changes = [];
        }

        // ✅ Only enrich specialties requests
        if (!empty($changes["table"]) && $changes["table"] === "vendor_specialties") {
            $catTable = $this->db->prefixTable("vendor_categories");       // pod_vendor_categories
            $subTable = $this->db->prefixTable("vendor_sub_categories");   // pod_vendor_sub_categories

            // helper closure to resolve names by id
            $resolveNames = function (&$payload) use ($catTable, $subTable) {
                if (!is_array($payload)) return;

                $catId = isset($payload["vendor_category_id"]) ? (int) $payload["vendor_category_id"] : 0;
                $subId = isset($payload["vendor_sub_category_id"]) ? (int) $payload["vendor_sub_category_id"] : 0;

                if ($catId) {
                    $catRow = $this->db->table($catTable)
                        ->select("name")
                        ->where("id", $catId)
                        ->where("deleted", 0)
                        ->get()
                        ->getRow();
                    if ($catRow) {
                        $payload["vendor_category_name"] = $catRow->name;
                    }
                }

                if ($subId) {
                    $subRow = $this->db->table($subTable)
                        ->select("name")
                        ->where("id", $subId)
                        ->where("deleted", 0)
                        ->get()
                        ->getRow();
                    if ($subRow) {
                        $payload["vendor_sub_category_name"] = $subRow->name;
                    }
                }
            };

            // Enrich BEFORE + AFTER
            if (isset($changes["before"])) {
                $resolveNames($changes["before"]);
            }
            if (isset($changes["after"])) {
                $resolveNames($changes["after"]);
            }
        }

        $view_data["model_info"] = $model_info;
        $view_data["changes"]    = $changes;

        return $this->template->view(
            "vendor_update_requests/view_modal",
            $view_data
        );
    }


    public
    function approve($id = null)
    {
        $this->access_only_vendor_update_requests_approve();

        $id = $id ?? $this->request->getPost("id");
        $id = (int) $id;

        if (!$id) {
            return $this->response->setJSON([
                "success" => false,
                "message" => "Missing request id."
            ]);
        }

        // Fetch request (direct query so we can reliably detect missing rows)
        $vurTable = $this->db->prefixTable("vendor_update_requests");
        $request = $this->db->table($vurTable)
            ->where("id", $id)
            ->where("deleted", 0)
            ->get()
            ->getRow();

        if (!$request) {
            return $this->response->setJSON([
                "success" => false,
                "message" => "Request not found."
            ]);
        }

        if ($request->status !== "pending") {
            return $this->response->setJSON([
                "success" => false,
                "message" => "Only pending requests can be approved."
            ]);
        }

        $changes = json_decode($request->changes ?? "{}", true);
        if (!is_array($changes) || !count($changes)) {
            return $this->response->setJSON([
                "success" => false,
                "message" => "Invalid changes payload."
            ]);
        }

        $this->db->transBegin();

        try {
            $this->_apply_changes($changes, "approved");

            $ok = $this->db->table($vurTable)
                ->where("id", $id)
                ->update([
                    "status" => "approved",
                    "reviewed_by" => $this->login_user->id,
                    "reviewed_at" => date("Y-m-d H:i:s"),
                    "updated_at" => date("Y-m-d H:i:s")
                ]);

            if (!$ok) {
                $err = $this->db->error();
                throw new \Exception("Failed to update request row: " . ($err["message"] ?? "unknown"));
            }

            if ($this->db->transStatus() === false) {
                $err = $this->db->error();
                throw new \Exception("Transaction failed: " . ($err["message"] ?? "unknown"));
            }

            $this->db->transCommit();

            return $this->response->setJSON([
                "success" => true,
                "message" => "Approved successfully."
            ]);
        } catch (\Throwable $e) {
            $this->db->transRollback();
            log_message("error", "VUR APPROVE FAILED: " . $e->getMessage());

            return $this->response->setJSON([
                "success" => false,
                "message" => "Approve failed: " . $e->getMessage()
            ]);
        }
    }

    function reject()
    {
        $this->access_only_vendor_update_requests_reject();
        $this->validate_submitted_data([
            "id"     => "required|numeric",
            "reason" => "required"
        ]);

        $id     = (int) $this->request->getPost("id");
        $reason = trim((string)$this->request->getPost("reason"));

        $vurTable = $this->db->prefixTable("vendor_update_requests");

        $row = $this->db->table($vurTable)
            ->where("id", $id)
            ->where("deleted", 0)
            ->get()
            ->getRow();

        if (!$row) {
            return $this->response->setJSON(["success" => false, "message" => "Request not found."]);
        }

        // (Optional but recommended) prevent rejecting non-pending requests
        if ($row->status !== "pending") {
            return $this->response->setJSON(["success" => false, "message" => "Only pending requests can be rejected."]);
        }

        $changes = json_decode($row->changes, true);
        if (!is_array($changes)) {
            return $this->response->setJSON(["success" => false, "message" => "Invalid changes JSON on this request."]);
        }

        $this->db->transBegin();

        try {
            // If you want rejection to also revert/remove the target data, keep this:
            $this->_apply_changes($changes, "rejected");

            $ok = $this->db->table($vurTable)->where("id", $id)->update([
                "status"         => "rejected",
                "reviewed_by"    => $this->login_user->id,
                "reviewed_at"    => date("Y-m-d H:i:s"),
                "review_comment" => $reason
            ]);

            if (!$ok) {
                $error = $this->db->error();
                throw new \RuntimeException("VUR update failed: " . ($error["message"] ?? "unknown"));
            }

            if ($this->db->transStatus() === false) {
                $error = $this->db->error();
                throw new \RuntimeException("Transaction failed: " . ($error["message"] ?? "unknown"));
            }

            $this->db->transCommit();

            return $this->response->setJSON([
                "success" => true,
                "message" => app_lang("record_saved")
            ]);
        } catch (\Throwable $e) {
            $this->db->transRollback();
            log_message("error", "REJECT FAILED: " . $e->getMessage());

            return $this->response->setJSON([
                "success" => false,
                "message" => $e->getMessage()
            ]);
        }
    }


    public function review_modal()
    {
        $this->access_only_vendor_update_requests_review();
        $this->validate_submitted_data([
            "id" => "required|numeric"
        ]);

        $view_data["id"] = $this->request->getPost("id");

        return $this->template->view(
            "vendor_update_requests/review_modal",
            $view_data
        );
    }


    public function review()
    {
        $this->access_only_vendor_update_requests_review();

        $this->validate_submitted_data([
            "id" => "required|numeric",
            "comment" => "required"
        ]);

        $id = (int) $this->request->getPost("id");
        $comment = trim((string) $this->request->getPost("comment"));

        $vurTable = $this->db->prefixTable("vendor_update_requests");

        $row = $this->db->table($vurTable)
            ->where("id", $id)
            ->where("deleted", 0)
            ->get()
            ->getRow();

        if (!$row || $row->status !== "pending") {
            return $this->response->setJSON([
                "success" => false,
                "message" => app_lang("invalid_request")
            ]);
        }

        $ok = $this->db->table($vurTable)->where("id", $id)->update([
            "status"         => "review",
            "reviewed_by"    => $this->login_user->id,
            "reviewed_at"    => date("Y-m-d H:i:s"),
            "review_comment" => $comment
        ]);

        if (!$ok) {
            $error = $this->db->error();
            return $this->response->setJSON([
                "success" => false,
                "message" => "DB ERROR: " . ($error["message"] ?? "unknown")
            ]);
        }

        return $this->response->setJSON([
            "success" => true,
            "message" => app_lang("record_saved")
        ]);
    }







    public function view_document($vurId)
    {
        // admin-only already enforced in your controller

        $vurId = (int) $vurId;

        $vur = $this->Vendor_update_requests_model->get_one($vurId);
        if (!$vur || (int)$vur->deleted === 1) {
            return $this->response->setStatusCode(404, "Not found");
        }

        $changes = json_decode($vur->changes ?? "{}", true);
        $after = $changes["after"] ?? [];
        if (!is_array($after)) $after = [];

        // 1) best: path stored in VUR changes
        $relPath = $after["path"] ?? null;

        // 2) fallback: if no path in changes, load the document row by record_id
        if (!$relPath && !empty($changes["record_id"]) && !empty($changes["table"])) {
            $table = strtolower((string)$changes["table"]);
            if (str_contains($table, "vendor_documents")) {
                $docId = (int)$changes["record_id"];
                $docRow = $this->db->table($this->db->prefixTable("vendor_documents"))
                    ->where("id", $docId)
                    ->where("deleted", 0)
                    ->get()
                    ->getRow();
                if ($docRow) {
                    $relPath = $docRow->path;
                    $after["original_name"] = $after["original_name"] ?? $docRow->original_name;
                    $after["mime_type"] = $after["mime_type"] ?? $docRow->mime_type;
                }
            }
        }

        if (!$relPath) {
            return $this->response->setStatusCode(404, "No document in this request");
        }

        // sanitize (prevent ../)
        $relPath = str_replace("\\", "/", $relPath);
        $relPath = preg_replace("#\.\.+#", "", $relPath);
        $relPath = ltrim($relPath, "/");

        $baseCandidates = [
            WRITEPATH . "uploads/",
            FCPATH . "uploads/",
            FCPATH
        ];

        $fullPath = null;
        foreach ($baseCandidates as $base) {
            $try = rtrim($base, "/\\") . "/" . $relPath;
            if (is_file($try)) {
                $fullPath = $try;
                break;
            }
        }

        if (!$fullPath) {
            return $this->response->setStatusCode(404, "File missing on server: " . $relPath);
        }

        $downloadName = $after["original_name"] ?? basename($fullPath);
        $mime = $after["mime_type"] ?? (function_exists("mime_content_type") ? mime_content_type($fullPath) : "application/octet-stream");

        // inline for images/pdf
        $inline = (str_starts_with($mime, "image/") || $mime === "application/pdf");

        return $this->response
            ->setHeader("Content-Type", $mime)
            ->setHeader("Content-Disposition", ($inline ? "inline" : "attachment") . '; filename="' . addslashes($downloadName) . '"')
            ->setBody(file_get_contents($fullPath));
    }




    private function _filter_payload_for_table(string $table, array $payload): array
    {
        // Safety: some tables may not have status/deleted columns.
        // Filter payload to existing table columns to avoid "Unknown column" errors.
        try {
            $fields = $this->db->getFieldNames($table);
        } catch (\Throwable $e) {
            return $payload;
        }

        if (!is_array($fields) || !count($fields)) {
            return $payload;
        }

        $allowed = array_flip($fields);
        return array_intersect_key($payload, $allowed);
    }

    private function _apply_changes(array $changes, string $decision): bool
    {
        $tableRaw  = trim((string)($changes["table"] ?? ""));
        $action    = strtolower(trim((string)($changes["action"] ?? "")));
        $record_id = (int)($changes["record_id"] ?? 0);

        if (!$tableRaw) {
            throw new \RuntimeException("Missing table in changes JSON.");
        }
        if (!$record_id) {
            throw new \RuntimeException("Missing record_id in changes JSON.");
        }

        // prevent double prefix if table already contains prefix
        $prefix = method_exists($this->db, "getPrefix") ? $this->db->getPrefix() : "";
        if ($prefix && strpos($tableRaw, $prefix) === 0) {
            $tableRaw = substr($tableRaw, strlen($prefix));
        }

        $table = $this->db->prefixTable($tableRaw);

        // normalize action names
        if ($action === "create") $action = "insert";

        $before = $changes["before"] ?? [];
        $after  = $changes["after"] ?? [];

        if (!is_array($before)) $before = [];
        if (!is_array($after))  $after  = [];

        unset($before["id"]);
        unset($after["id"]);

        $builder = $this->db->table($table);

        $throwDbError = function ($prefixMsg) use ($table) {
            $error = $this->db->error();
            throw new \RuntimeException($prefixMsg . " on {$table}: " . ($error["message"] ?? "unknown db error"));
        };

        // APPROVE
        if ($decision === "approved") {
            if ($action === "insert" || $action === "update") {
                // Apply AFTER + approve
                $payload = $after;
                $payload["status"]  = "approved";
                $payload["deleted"] = 0;

                $payload = $this->_filter_payload_for_table($table, $payload);

                if (count($payload)) {
                    $ok = $builder->where("id", $record_id)->update($payload);
                    if (!$ok) $throwDbError("APPROVE update failed");
                }
            } elseif ($action === "delete") {
                $payload = [
                    "status"  => "approved",
                    "deleted" => 1
                ];

                $payload = $this->_filter_payload_for_table($table, $payload);

                if (count($payload)) {
                    $ok = $builder->where("id", $record_id)->update($payload);
                    if (!$ok) $throwDbError("APPROVE delete-flag failed");
                }
            } else {
                throw new \RuntimeException("Unsupported action: {$action}");
            }

            return true;
        }

        // REJECT
        if ($action === "insert") {
            // New records created by vendors should not become active if rejected.
            $payload = [
                "status"  => "rejected",

            ];

            $payload = $this->_filter_payload_for_table($table, $payload);

            if (count($payload)) {
                $ok = $builder->where("id", $record_id)->update($payload);
                if (!$ok) $throwDbError("REJECT insert failed");
            }
        } elseif ($action === "update") {
            // Revert to BEFORE (includes original status/fields)
            $payload = $before;
            $payload["deleted"] = 0;

            $payload = $this->_filter_payload_for_table($table, $payload);

            if (count($payload)) {
                $ok = $builder->where("id", $record_id)->update($payload);
                if (!$ok) $throwDbError("REJECT revert update failed");
            }
        } elseif ($action === "delete") {
            // Undo the delete (restore BEFORE if available)
            $payload = $before;
            $payload["deleted"] = 0;

            if (!count($payload)) {
                $payload = ["deleted" => 0, "status" => "approved"];
            }

            $payload = $this->_filter_payload_for_table($table, $payload);

            if (count($payload)) {
                $ok = $builder->where("id", $record_id)->update($payload);
                if (!$ok) $throwDbError("REJECT undo-delete failed");
            }
        } else {
            throw new \RuntimeException("Unsupported action: {$action}");
        }
        return true;
    }
}

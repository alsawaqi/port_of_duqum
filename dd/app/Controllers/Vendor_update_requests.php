<?php

namespace App\Controllers;

use App\Controllers\Security_Controller;
use App\Models\Vendor_update_requests_model;
use App\Models\Vendors_model;
use App\Libraries\Vendor_contact_access;
use Config\Database;

class Vendor_update_requests extends Security_Controller
{
    protected $Vendor_update_requests_model;
    protected $Vendors_model;
    protected $db;
    protected $Vendor_contact_access;



    public function __construct()
    {
        parent::__construct();

        $this->access_only_team_members();

        $this->Vendor_update_requests_model = new Vendor_update_requests_model();
        $this->Vendors_model = new Vendors_model();
        $this->Vendor_contact_access = new Vendor_contact_access(db_connect());
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
        if (strtolower($this->request->getMethod()) !== "post") {
            return $this->response
                ->setStatusCode(405)
                ->setHeader("Allow", "POST")
                ->setJSON(["success" => false, "message" => "This action requires a POST request."]);
        }

        $this->access_only_vendor_update_requests_approve();

        $ids = $this->request->getPost("ids");
        if (!is_array($ids) || !count($ids)) {
            return $this->response->setJSON(["success" => false, "message" => "No requests selected."]);
        }

        $ids = array_values(array_unique(array_filter(
            array_map("intval", $ids),
            static fn(int $id): bool => $id > 0
        )));
        sort($ids, SORT_NUMERIC);

        $approved = 0;
        $skipped  = 0;

        $this->db->transBegin();
        try {
            foreach ($ids as $id) {
                $row = $this->lockPendingRequest($id);

                if (!$row || (string) ($row->status ?? "") !== "pending") {
                    $skipped++;
                    continue;
                }

                $changes = json_decode($row->changes ?? "[]", true);
                if (!is_array($changes) || !count($changes)) {
                    $skipped++;
                    continue;
                }

                $this->_apply_changes($changes, "approved", (int) $row->vendor_id);

                $this->finalizePendingRequest($id, [
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
                "message" => "Unable to approve the selected requests. Please try again."
            ]);
        }
    }

    function bulk_reject()
    {
        if (strtolower($this->request->getMethod()) !== "post") {
            return $this->response
                ->setStatusCode(405)
                ->setHeader("Allow", "POST")
                ->setJSON(["success" => false, "message" => "This action requires a POST request."]);
        }

        $this->access_only_vendor_update_requests_reject();

        $ids = $this->request->getPost("ids");
        $reason = trim((string)$this->request->getPost("reason"));

        if (!is_array($ids) || !count($ids)) {
            return $this->response->setJSON(["success" => false, "message" => "No requests selected."]);
        }
        if (!$reason) {
            return $this->response->setJSON(["success" => false, "message" => "Reject reason is required."]);
        }

        $ids = array_values(array_unique(array_filter(
            array_map("intval", $ids),
            static fn(int $id): bool => $id > 0
        )));
        sort($ids, SORT_NUMERIC);

        $rejected = 0;
        $skipped  = 0;

        $this->db->transBegin();
        try {
            foreach ($ids as $id) {
                $row = $this->lockPendingRequest($id);

                if (!$row || (string) ($row->status ?? "") !== "pending") {
                    $skipped++;
                    continue;
                }

                $changes = json_decode($row->changes ?? "[]", true);
                if (!is_array($changes) || !count($changes)) {
                    $skipped++;
                    continue;
                }

                $this->_apply_changes($changes, "rejected", (int) $row->vendor_id); // no if(...)




                $this->finalizePendingRequest($id, [
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
                "message" => "Unable to reject the selected requests. Please try again."
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

        $action_options = "";
        if ($can_view) {
            $action_options .= "<option value='view'>" . app_lang("view_details") . "</option>";
        }

        // ONLY allow approve / reject if pending
        if ($row->status === "pending") {
            if ($can_approve) {
                $action_options .= "<option value='approve'>" . app_lang("approve") . "</option>";
            }

            if ($can_review) {
                $action_options .= "<option value='review'>" . app_lang("review") . "</option>";
            }

            if ($can_reject) {
                $action_options .= "<option value='reject'>" . app_lang("reject") . "</option>";
            }
        }

        $actions = $action_options
            ? "<select class='form-select form-select-sm vur-action-select' data-id='" . (int)$row->id . "'>"
                . "<option value=''>Select action</option>"
                . $action_options
                . "</select>"
            : "<span class='text-muted'>-</span>";


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
    function approve()
    {
        if (strtolower($this->request->getMethod()) !== "post") {
            return $this->response
                ->setStatusCode(405)
                ->setHeader("Allow", "POST")
                ->setJSON(["success" => false, "message" => "This action requires a POST request."]);
        }

        $this->access_only_vendor_update_requests_approve();

        $id = (int) $this->request->getPost("id");

        if (!$id) {
            return $this->response->setJSON([
                "success" => false,
                "message" => "Missing request id."
            ]);
        }

        $this->db->transBegin();
        try {
            $request = $this->lockPendingRequest($id);

            if (!$request) {
                $this->db->transRollback();
                return $this->response->setJSON([
                    "success" => false,
                    "message" => "Request not found."
                ]);
            }

            if ((string) ($request->status ?? "") !== "pending") {
                $this->db->transRollback();
                return $this->response->setJSON([
                    "success" => false,
                    "message" => "Only pending requests can be approved."
                ]);
            }

            $changes = json_decode($request->changes ?? "{}", true);
            if (!is_array($changes) || !count($changes)) {
                $this->db->transRollback();
                return $this->response->setJSON([
                    "success" => false,
                    "message" => "Invalid changes payload."
                ]);
            }

            $this->_apply_changes($changes, "approved", (int) $request->vendor_id);

            $this->finalizePendingRequest($id, [
                "status" => "approved",
                "reviewed_by" => $this->login_user->id,
                "reviewed_at" => date("Y-m-d H:i:s"),
            ]);

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
                "message" => "Unable to approve this request. Please try again."
            ]);
        }
    }

    function reject()
    {
        if (strtolower($this->request->getMethod()) !== "post") {
            return $this->response
                ->setStatusCode(405)
                ->setHeader("Allow", "POST")
                ->setJSON(["success" => false, "message" => "This action requires a POST request."]);
        }

        $this->access_only_vendor_update_requests_reject();
        $this->validate_submitted_data([
            "id"     => "required|numeric",
            "reason" => "required"
        ]);

        $id     = (int) $this->request->getPost("id");
        $reason = trim((string)$this->request->getPost("reason"));

        $this->db->transBegin();
        try {
            $row = $this->lockPendingRequest($id);

            if (!$row) {
                $this->db->transRollback();
                return $this->response->setJSON(["success" => false, "message" => "Request not found."]);
            }

            if ((string) ($row->status ?? "") !== "pending") {
                $this->db->transRollback();
                return $this->response->setJSON(["success" => false, "message" => "Only pending requests can be rejected."]);
            }

            $changes = json_decode($row->changes, true);
            if (!is_array($changes)) {
                $this->db->transRollback();
                return $this->response->setJSON(["success" => false, "message" => "Invalid changes JSON on this request."]);
            }

            // If you want rejection to also revert/remove the target data, keep this:
            $this->_apply_changes($changes, "rejected", (int) $row->vendor_id);

            $this->finalizePendingRequest($id, [
                "status"         => "rejected",
                "reviewed_by"    => $this->login_user->id,
                "reviewed_at"    => date("Y-m-d H:i:s"),
                "review_comment" => $reason
            ]);

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
                "message" => "Unable to reject this request. Please try again."
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
        if (strtolower($this->request->getMethod()) !== "post") {
            return $this->response
                ->setStatusCode(405)
                ->setHeader("Allow", "POST")
                ->setJSON(["success" => false, "message" => "This action requires a POST request."]);
        }

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
        $this->access_only_vendor_update_requests_view();
        $this->access_only_vendor_update_requests_by_vendor_view();

        $vurId = (int) $vurId;

        $vur = $this->Vendor_update_requests_model->get_one($vurId);
        if (!$vur || (int)$vur->deleted === 1) {
            return $this->response->setStatusCode(404, "Not found");
        }

        return $this->streamVendorRequestDocument($vur);
    }




    private function streamVendorRequestDocument(object $request)
    {
        $changes = json_decode($request->changes ?? "{}", true);
        if (!is_array($changes)
            || strtolower(trim((string) ($changes["table"] ?? ""))) !== "vendor_documents"
            || (int) ($changes["record_id"] ?? 0) < 1) {
            return $this->response->setStatusCode(404, "No document in this request");
        }

        // The canonical row is authoritative. Bind it to the request's CR and
        // never trust a path embedded in the approval-request JSON.
        $document = $this->db->table($this->db->prefixTable("vendor_documents"))
            ->where("id", (int) $changes["record_id"])
            ->where("vendor_id", (int) $request->vendor_id)
            ->where("deleted", 0)
            ->get()
            ->getRow();
        if (!$document || empty($document->path)) {
            return $this->response->setStatusCode(404, "No document in this request");
        }

        $base = realpath(WRITEPATH . "uploads/vendor_documents");
        $relative = ltrim(str_replace("\\", "/", (string) $document->path), "/");
        $fullPath = realpath(WRITEPATH . "uploads/" . $relative);
        $basePrefix = $base ? rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR : "";
        if (!$base || !$fullPath || !is_file($fullPath) || strpos($fullPath, $basePrefix) !== 0) {
            return $this->response->setStatusCode(404, "File missing");
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($fullPath) ?: "application/octet-stream";
        if (!in_array($mime, ["image/jpeg", "image/png", "application/pdf"], true)) {
            return $this->response->setStatusCode(404, "Unsupported document");
        }

        $originalName = (string) ($document->original_name ?: basename($fullPath));
        $downloadName = preg_replace('/[\x00-\x1F\x7F"\\\\\/]+/', "_", basename($originalName));
        $downloadName = $downloadName ?: "vendor-document";

        return $this->response
            ->setHeader("Content-Type", $mime)
            ->setHeader(
                "Content-Disposition",
                'inline; filename="' . $downloadName . '"; filename*=UTF-8\'\'' . rawurlencode($downloadName)
            )
            ->setHeader("Cache-Control", "private, no-store, max-age=0")
            ->setHeader("Pragma", "no-cache")
            ->setHeader("X-Content-Type-Options", "nosniff")
            ->setBody(file_get_contents($fullPath));
    }

    /**
     * Lock one request before making a decision. The caller must already have
     * an open transaction and must recheck the pending status on this row.
     */
    private function lockPendingRequest(int $id): ?object
    {
        if ($id < 1) {
            return null;
        }

        $table = $this->db->prefixTable("vendor_update_requests");
        $row = $this->db->query(
            "SELECT * FROM {$table}
             WHERE id = ? AND deleted = 0
             FOR UPDATE",
            [$id]
        )->getRow();

        return $row ?: null;
    }

    /**
     * Finalize only the pending row that is currently locked by the caller.
     */
    private function finalizePendingRequest(int $id, array $data): void
    {
        $data["updated_at"] = $data["updated_at"] ?? get_current_utc_time();

        $ok = $this->db->table($this->db->prefixTable("vendor_update_requests"))
            ->where("id", $id)
            ->where("deleted", 0)
            ->where("status", "pending")
            ->update($data);

        if ($ok && $this->db->affectedRows() === 1) {
            return;
        }

        $error = $this->db->error();
        log_message(
            "error",
            "VUR DECISION FINALIZE FAILED for request {$id}: "
                . ($error["message"] ?? "pending request was not updated")
        );
        throw new \RuntimeException("Unable to finalize the pending vendor update request.");
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

    private function _apply_changes(array $changes, string $decision, int $expectedVendorId): bool
    {
        $tableRaw  = strtolower(trim((string)($changes["table"] ?? "")));
        $action    = strtolower(trim((string)($changes["action"] ?? "")));
        $record_id = (int)($changes["record_id"] ?? 0);

        if (!$tableRaw || $expectedVendorId < 1) {
            throw new \RuntimeException("Missing table in changes JSON.");
        }
        if (!$record_id) {
            throw new \RuntimeException("Missing record_id in changes JSON.");
        }

        // prevent double prefix if table already contains prefix
        $prefix = method_exists($this->db, "getPrefix") ? $this->db->getPrefix() : "";
        if ($prefix && strpos($tableRaw, $prefix) === 0) {
            $tableRaw = strtolower(substr($tableRaw, strlen($prefix)));
        }

        // Stored change JSON is data, never a free-form database instruction.
        // Bind every module to one CR-owned table and its editable fields.
        $policies = [
            "bank" => [
                "table" => "vendor_bank_accounts",
                "fields" => ["bank_name", "bank_branch", "bank_account_no", "bank_swift_code", "iban", "letter_head_path", "status", "deleted"],
            ],
            "specialties" => [
                "table" => "vendor_specialties",
                "fields" => ["vendor_category_id", "vendor_sub_category_id", "specialty_type", "specialty_name", "specialty_description", "status", "deleted"],
            ],
            "branches" => [
                "table" => "vendor_branches",
                "fields" => ["name", "address", "country_id", "region_id", "city_id", "phone", "email", "is_main", "is_active", "status", "deleted"],
            ],
            "documents" => [
                "table" => "vendor_documents",
                "fields" => ["vendor_document_type_id", "issued_at", "expires_at", "status", "disk", "path", "original_name", "mime_type", "size_bytes", "uploaded_by", "deleted"],
            ],
            "credentials" => [
                "table" => "vendor_credentials",
                "fields" => ["type", "number", "issue_date", "expiry_date", "notes", "status", "deleted"],
            ],
            "contacts" => [
                "table" => "vendor_contacts",
                "fields" => ["user_id", "contacts_name", "phone", "fax", "designation", "email", "email_2", "mobile", "role", "is_primary", "is_active", "status", "created_at", "updated_at", "deleted"],
            ],
        ];

        $module = strtolower(trim((string) ($changes["module"] ?? "")));
        $policy = $policies[$module] ?? null;
        if (!$policy || !hash_equals($policy["table"], $tableRaw)) {
            throw new \RuntimeException("Unsupported vendor update target.");
        }

        if (!in_array($action, ["create", "insert", "update", "delete"], true)) {
            throw new \RuntimeException("Unsupported action: {$action}");
        }

        $isVendorContact = strtolower($tableRaw) === "vendor_contacts";
        $table = $this->db->prefixTable($tableRaw);

        // The request row's vendor_id is the CR authorization boundary. Lock
        // the target so its ownership cannot change during this decision.
        $ownedTarget = $this->db->query(
            "SELECT id FROM {$table} WHERE id = ? AND vendor_id = ? FOR UPDATE",
            [$record_id, $expectedVendorId]
        )->getRow();
        if (!$ownedTarget) {
            throw new \RuntimeException("Vendor update target is outside the requested CR.");
        }

        // normalize action names
        if ($action === "create") $action = "insert";

        $before = $changes["before"] ?? [];
        $after  = $changes["after"] ?? [];

        if (!is_array($before)) $before = [];
        if (!is_array($after))  $after  = [];

        unset($before["id"]);
        unset($after["id"]);

        $allowedFields = array_flip($policy["fields"]);
        $before = array_intersect_key($before, $allowedFields);
        $after = array_intersect_key($after, $allowedFields);

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
                    $ok = $builder->where("id", $record_id)->where("vendor_id", $expectedVendorId)->update($payload);
                    if (!$ok) $throwDbError("APPROVE update failed");
                }

                if ($isVendorContact) {
                    $this->Vendor_contact_access->approveContact(
                        $record_id,
                        (int) ($this->login_user->id ?? 0)
                    );
                }
            } elseif ($action === "delete") {
                $payload = [
                    "status"  => "approved",
                    "deleted" => 1
                ];

                $payload = $this->_filter_payload_for_table($table, $payload);

                if (count($payload)) {
                    $ok = $builder->where("id", $record_id)->where("vendor_id", $expectedVendorId)->update($payload);
                    if (!$ok) $throwDbError("APPROVE delete-flag failed");
                }

                if ($isVendorContact) {
                    $this->Vendor_contact_access->suspendContactMembership($record_id);
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
                $ok = $builder->where("id", $record_id)->where("vendor_id", $expectedVendorId)->update($payload);
                if (!$ok) $throwDbError("REJECT insert failed");
            }

            if ($isVendorContact) {
                $this->Vendor_contact_access->suspendContactMembership($record_id);
            }
        } elseif ($action === "update") {
            // Revert to BEFORE (includes original status/fields)
            $payload = $before;
            $payload["deleted"] = 0;

            $payload = $this->_filter_payload_for_table($table, $payload);

            if (count($payload)) {
                $ok = $builder->where("id", $record_id)->where("vendor_id", $expectedVendorId)->update($payload);
                if (!$ok) $throwDbError("REJECT revert update failed");
            }

            if ($isVendorContact && empty($before["is_active"])) {
                $this->Vendor_contact_access->suspendContactMembership($record_id);
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
                $ok = $builder->where("id", $record_id)->where("vendor_id", $expectedVendorId)->update($payload);
                if (!$ok) $throwDbError("REJECT undo-delete failed");
            }

            if ($isVendorContact && empty($before["is_active"])) {
                $this->Vendor_contact_access->suspendContactMembership($record_id);
            }
        } else {
            throw new \RuntimeException("Unsupported action: {$action}");
        }
        return true;
    }
}

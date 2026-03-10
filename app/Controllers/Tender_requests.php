<?php

namespace App\Controllers;

use App\Models\Tender_commercial_users_model;
use App\Models\Tender_committee_users_model;
use App\Models\Tender_department_manager_users_model;
use App\Models\Tender_department_users_model;
use App\Models\Tender_request_approvals_model;
use App\Models\Tender_request_team_members_model;
use App\Models\Tender_request_vendors_model;
use App\Models\Tender_requests_model;
use App\Models\Tender_technical_users_model;

class Tender_requests extends Security_Controller
{
    protected $db;
    protected $Tender_requests_model;
    protected $Tender_request_vendors_model;
    protected $Tender_request_approvals_model;
    protected $Tender_request_team_members_model;
    protected $Tender_department_users_model;
    protected $Tender_committee_users_model;
    protected $Tender_department_manager_users_model;
    protected $Tender_technical_users_model;
    protected $Tender_commercial_users_model;

    public function __construct()
    {
        parent::__construct();
        $this->access_only_team_members();

        $this->db = db_connect();

        $this->Tender_requests_model = new Tender_requests_model();
        $this->Tender_request_vendors_model = new Tender_request_vendors_model();
        $this->Tender_request_approvals_model = new Tender_request_approvals_model();
        $this->Tender_request_team_members_model = new Tender_request_team_members_model();
        $this->Tender_department_users_model = new Tender_department_users_model();
        $this->Tender_committee_users_model = new Tender_committee_users_model();
        $this->Tender_department_manager_users_model = new Tender_department_manager_users_model();
        $this->Tender_technical_users_model = new Tender_technical_users_model();
        $this->Tender_commercial_users_model = new Tender_commercial_users_model();
    }

    private function _require_requests_access_json()
    {
        // These endpoints are used by the create/update modal, so allow any of:
        // view OR create OR update permissions for Tender Requests.
        if (
            $this->can_tender('requests', 'view')
            || $this->can_tender('requests', 'create')
            || $this->can_tender('requests', 'update')
        ) {
            return null;
        }

        return $this->response->setStatusCode(403)->setJSON([]);
    }

    public function index()
    {
        $this->access_only_tender('requests', 'view');

        return $this->template->rander('tender_requests/index');
    }

    public function modal_form()
    {
        $this->validate_submitted_data(['id' => 'numeric']);
        $id = (int) $this->request->getPost('id');
        $this->access_only_tender('requests', $id ? 'update' : 'create');

        $model_info = $this->Tender_requests_model->get_one($id);

        $db = db_connect();
        $companies = $db->prefixTable('companies');

        $rows = $db->query("SELECT id, name FROM $companies WHERE deleted=0 AND is_active=1 ORDER BY name ASC")->getResult();
        $company_dropdown = ['' => '- '.app_lang('select').' -'];
        foreach ($rows as $r) {
            $company_dropdown[$r->id] = $r->name;
        }

        $selected_vendors = [];
        if ($id) {
            $selected_vendors = $this->Tender_request_vendors_model->get_selected_vendors($id);
        }

        $requester_user_id = ($id && !empty($model_info->requester_id))
            ? (int) $model_info->requester_id
            : (int) $this->login_user->id;

        $requester_display = $this->_selected_user_option($requester_user_id);
        $requester_assignment = $this->_get_requester_assignment($requester_user_id);

        $requester_context_locked = false;

        if (!$this->login_user->is_admin && $requester_assignment) {
            $requester_context_locked = true;

            if (!$id || empty($model_info->id)) {
                $model_info = (object) [
                    'id' => 0,
                    'company_id' => (int) $requester_assignment->company_id,
                    'department_id' => (int) $requester_assignment->department_id,
                    'request_date' => date('Y-m-d'),
                    'announcement' => 'local',
                    'tender_type' => 'open',
                    'evaluation_method' => 'separate',
                    'technical_weight' => 70,
                    'commercial_weight' => 30,
                ];
            } else {
                $model_info->company_id = (int) $requester_assignment->company_id;
                $model_info->department_id = (int) $requester_assignment->department_id;
            }
        }

        $company_context = (int) ($model_info->company_id ?? 0);
        $department_context = (int) ($model_info->department_id ?? 0);

        $department_manager_assignment = $this->_get_department_manager_assignment($company_context, $department_context);

        $selected_technical_users = [];
        $selected_commercial_users = [];
        $selected_itc_members = [];
        $selected_chairman = null;
        $selected_secretary = null;

        if ($id) {
            $selected_technical_users = $this->Tender_request_team_members_model->get_members($id, 'technical_evaluator');
            $selected_commercial_users = $this->Tender_request_team_members_model->get_members($id, 'commercial_evaluator');
            $selected_itc_members = $this->Tender_request_team_members_model->get_members($id, 'itc_member');

            $chairman = $this->Tender_request_team_members_model->get_members($id, 'chairman');
            $secretary = $this->Tender_request_team_members_model->get_members($id, 'secretary');

            $selected_chairman = !empty($chairman) ? $chairman[0] : null;
            $selected_secretary = !empty($secretary) ? $secretary[0] : null;
        }

        // PRELOAD FULL POOLS FOR THE CURRENT COMPANY
        $technical_pool_users = $this->_get_pool_users_by_company('tender_technical_users', $company_context);
        $commercial_pool_users = $this->_get_pool_users_by_company('tender_commercial_users', $company_context);
        $committee_pool_users = $this->_get_pool_users_by_company('tender_committee_users', $company_context);

        return $this->template->view('tender_requests/modal_form', [
            'model_info' => $model_info,
            'company_dropdown' => $company_dropdown,
            'selected_vendors' => $selected_vendors,
            'selected_technical_users' => $selected_technical_users,
            'selected_commercial_users' => $selected_commercial_users,
            'selected_itc_members' => $selected_itc_members,
            'selected_chairman' => $selected_chairman,
            'selected_secretary' => $selected_secretary,
            'technical_pool_users' => $technical_pool_users,
            'commercial_pool_users' => $commercial_pool_users,
            'committee_pool_users' => $committee_pool_users,
            'requester_display' => $requester_display,
            'requester_assignment' => $requester_assignment,
            'requester_context_locked' => $requester_context_locked,
            'department_manager_assignment' => $department_manager_assignment,
        ]);
    }

    private function _get_requester_assignment(?int $user_id = null)
    {
        $user_id = $user_id ?: (int) $this->login_user->id;

        $tdu = $this->db->prefixTable('tender_department_users');
        $companies = $this->db->prefixTable('companies');
        $departments = $this->db->prefixTable('departments');

        return $this->db->query(
            "SELECT
                $tdu.*,
                $companies.name AS company_name,
                $departments.name AS department_name
            FROM $tdu
            LEFT JOIN $companies ON $companies.id = $tdu.company_id AND $companies.deleted=0
            LEFT JOIN $departments ON $departments.id = $tdu.department_id AND $departments.deleted=0
            WHERE $tdu.deleted=0
            AND $tdu.status='active'
            AND $tdu.user_id=?
            ORDER BY $tdu.id DESC
            LIMIT 1",
            [$user_id]
        )->getRow();
    }

    private function _is_valid_committee_user(int $user_id, int $company_id): bool
    {
        if (!$user_id || !$company_id) {
            return false;
        }

        $tcu = $this->db->prefixTable('tender_committee_users');

        $row = $this->db->query(
            "SELECT id
            FROM $tcu
            WHERE deleted=0
            AND status='active'
            AND user_id=?
            AND company_id=?
            LIMIT 1",
            [$user_id, $company_id]
        )->getRow();

        return (bool) $row;
    }

    private function _sanitize_committee_member_ids(array $itc_member_user_ids, int $chairman_user_id, int $secretary_user_id): array
    {
        $itc_member_user_ids = $this->_normalize_user_ids($itc_member_user_ids);

        return array_values(array_diff(
            $itc_member_user_ids,
            array_filter([$chairman_user_id, $secretary_user_id])
        ));
    }

    private function _validate_committee_selection(int $company_id, int $chairman_user_id, int $secretary_user_id, array $itc_member_user_ids): array
    {
        $errors = [];

        if ($chairman_user_id && !$this->_is_valid_committee_user($chairman_user_id, $company_id)) {
            $errors[] = 'Chairman must be selected from Tender Committee Users.';
        }

        if ($secretary_user_id && !$this->_is_valid_committee_user($secretary_user_id, $company_id)) {
            $errors[] = 'Secretary must be selected from Tender Committee Users.';
        }

        if ($chairman_user_id && $secretary_user_id && $chairman_user_id === $secretary_user_id) {
            $errors[] = 'Chairman and Secretary must be different users.';
        }

        $clean_member_ids = $this->_sanitize_committee_member_ids($itc_member_user_ids, $chairman_user_id, $secretary_user_id);

        foreach ($clean_member_ids as $member_user_id) {
            if (!$this->_is_valid_committee_user((int) $member_user_id, $company_id)) {
                $errors[] = 'All ITT Members must be selected from Tender Committee Users.';
                break;
            }
        }

        return $errors;
    }

    private function _calc_tender_fee($budget_omr): float
    {
        return round(((float) $budget_omr) * 0.0005, 3);
    }

    private function _weights_are_valid($technical_weight, $commercial_weight): bool
    {
        return ((int) $technical_weight + (int) $commercial_weight) === 100;
    }

    private function _normalize_user_ids($ids): array
    {
        if (!is_array($ids)) {
            $ids = array_filter(explode(',', (string) $ids));
        }

        $result = [];
        foreach ($ids as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $result[] = $id;
            }
        }

        return array_values(array_unique($result));
    }

    private function _to_db_datetime($value): ?string
    {
        $value = trim((string) $value);
        if (!$value) {
            return null;
        }

        $ts = strtotime($value);

        return $ts ? date('Y-m-d H:i:s', $ts) : null;
    }

    private function _selected_user_option($user_id)
    {
        $user_id = (int) $user_id;
        if (!$user_id) {
            return null;
        }

        $users = $this->db->prefixTable('users');

        return $this->db->query(
            "SELECT
                id,
                TRIM(CONCAT(COALESCE(first_name,''), ' ', COALESCE(last_name,''))) AS full_name,
                email
             FROM $users
             WHERE deleted=0 AND id=?",
            [$user_id]
        )->getRow();
    }

    private function _request_completion_errors($request_row, int $request_id): array
    {
        $errors = [];

        if (!(int) ($request_row->department_manager_user_id ?? 0)) {
            $errors[] = 'Department Manager is not configured for this department.';
        }

        $grouped = $this->Tender_request_team_members_model->get_grouped_members($request_id);

        if (count($grouped['technical_evaluator'] ?? []) < 1) {
            $errors[] = 'At least one Technical Evaluation Team member is required.';
        }

        if (count($grouped['commercial_evaluator'] ?? []) < 1) {
            $errors[] = 'At least one Commercial Evaluation Team member is required.';
        }

        if (count($grouped['chairman'] ?? []) !== 1) {
            $errors[] = 'Exactly one ITT Chairman is required.';
        }

        if (count($grouped['secretary'] ?? []) !== 1) {
            $errors[] = 'Exactly one ITT Secretary is required.';
        }

        $chairman_user_id = (int) ($grouped['chairman'][0]->id ?? 0);
        $secretary_user_id = (int) ($grouped['secretary'][0]->id ?? 0);

        if ($chairman_user_id && $secretary_user_id && $chairman_user_id === $secretary_user_id) {
            $errors[] = 'ITT Chairman and ITT Secretary must be different users.';
        }

        $member_ids = [];
        foreach (($grouped['itc_member'] ?? []) as $member) {
            $member_ids[] = (int) ($member->id ?? 0);
        }

        $member_ids = $this->_sanitize_committee_member_ids($member_ids, $chairman_user_id, $secretary_user_id);

        if (count($member_ids) < 1) {
            $errors[] = 'At least one separate ITT Member is required in addition to Chairman and Secretary.';
        }

        return $errors;
    }

    public function save()
    {
        $this->validate_submitted_data([
            'id' => 'numeric',
            'reference' => 'required',
            'budget_omr' => 'required|numeric',
            'subject' => 'required',
            'brief_description' => 'required',
            'announcement' => 'required',
            'tender_type' => 'required',
            'evaluation_method' => 'required',
            'technical_weight' => 'required|numeric',
            'commercial_weight' => 'required|numeric',
        ]);

        $id = (int) $this->request->getPost('id');
        $this->access_only_tender('requests', $id ? 'update' : 'create');

        $existing = $id ? $this->Tender_requests_model->get_one($id) : null;

        if ($id && (!$existing || !$existing->id || (int) ($existing->deleted ?? 0) === 1)) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Tender request not found.',
            ]);
        }

        if ($existing && !$this->login_user->is_admin && (int) $existing->requester_id !== (int) $this->login_user->id) {
            app_redirect('forbidden');
            exit;
        }

        if ($existing && !in_array((string) $existing->status, ['draft', 'rejected'], true)) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Only draft or rejected requests can be edited.',
            ]);
        }

        $technical_weight = (int) $this->request->getPost('technical_weight');
        $commercial_weight = (int) $this->request->getPost('commercial_weight');

        if (!$this->_weights_are_valid($technical_weight, $commercial_weight)) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Technical and Commercial weights must total 100.',
            ]);
        }

        $budget = (float) $this->request->getPost('budget_omr');
        $fee = $this->_calc_tender_fee($budget);

        $requester_assignment = !$this->login_user->is_admin
            ? $this->_get_requester_assignment((int) $this->login_user->id)
            : null;

        if (!$this->login_user->is_admin && !$requester_assignment) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'No Tender Department User assignment is linked to this account.',
            ]);
        }

        if (!$this->login_user->is_admin && $requester_assignment) {
            $company_id = (int) $requester_assignment->company_id;
            $department_id = (int) $requester_assignment->department_id;
        } else {
            $company_id = $this->request->getPost('company_id');
            $department_id = $this->request->getPost('department_id');

            $company_id = (ctype_digit((string) $company_id) && (int) $company_id > 0) ? (int) $company_id : null;
            $department_id = (ctype_digit((string) $department_id) && (int) $department_id > 0) ? (int) $department_id : null;
        }

        if (!(int) $company_id || !(int) $department_id) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Company and Department are required.',
            ]);
        }

        $department_manager_assignment = $this->_get_department_manager_assignment((int) $company_id, (int) $department_id);

        $technical_user_ids = $this->_normalize_user_ids($this->request->getPost('technical_user_ids') ?? []);
        $commercial_user_ids = $this->_normalize_user_ids($this->request->getPost('commercial_user_ids') ?? []);
        $itc_member_user_ids = $this->_normalize_user_ids($this->request->getPost('itc_member_user_ids') ?? []);

        $chairman_user_id = (int) $this->request->getPost('chairman_user_id');
        $secretary_user_id = (int) $this->request->getPost('secretary_user_id');

        $pool_errors = [];

        $pool_errors = array_merge(
            $pool_errors,
            $this->_validate_pool_selection('tender_technical_users', $technical_user_ids, (int) $company_id, 'Technical Evaluation Team members')
        );

        $pool_errors = array_merge(
            $pool_errors,
            $this->_validate_pool_selection('tender_commercial_users', $commercial_user_ids, (int) $company_id, 'Commercial Evaluation Team members')
        );

        $pool_errors = array_merge(
            $pool_errors,
            $this->_validate_committee_selection((int) $company_id, $chairman_user_id, $secretary_user_id, $itc_member_user_ids)
        );

        if (!empty($pool_errors)) {
            return $this->response->setJSON([
                'success' => false,
                'message' => implode(' ', $pool_errors),
            ]);
        }

        $itc_member_user_ids = $this->_sanitize_committee_member_ids(
            $itc_member_user_ids,
            $chairman_user_id,
            $secretary_user_id
        );

        $data = [
            'reference' => trim((string) $this->request->getPost('reference')),
            'company_id' => $company_id,
            'department_id' => $department_id,
            'requester_id' => ($existing && $existing->id) ? (int) $existing->requester_id : (int) $this->login_user->id,
            'request_date' => $this->request->getPost('request_date') ?: date('Y-m-d'),
            'budget_omr' => $budget,
            'tender_fee' => $fee,
            'estimated_previous_amount' => $this->request->getPost('estimated_previous_amount') ?: null,
            'estimated_previous_notes' => trim((string) $this->request->getPost('estimated_previous_notes')),
            'subject' => trim((string) $this->request->getPost('subject')),
            'brief_description' => trim((string) $this->request->getPost('brief_description')),
            'announcement' => $this->request->getPost('announcement'),
            'tender_type' => $this->request->getPost('tender_type'),
            'evaluation_method' => $this->request->getPost('evaluation_method'),
            'technical_weight' => $technical_weight,
            'commercial_weight' => $commercial_weight,
            'department_manager_user_id' => $department_manager_assignment->user_id ?? null,
            'department_manager_title' => $department_manager_assignment->job_title ?? 'Department Manager',
        ];

        $invited_vendor_ids = $this->request->getPost('invited_vendor_ids') ?? [];
        if (!is_array($invited_vendor_ids)) {
            $invited_vendor_ids = array_filter(explode(',', (string) $invited_vendor_ids));
        }
        $invited_vendor_ids = array_values(array_unique(array_map('intval', $invited_vendor_ids)));

        if (($data['tender_type'] ?? 'open') === 'close' && !count($invited_vendor_ids)) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Invited suppliers are required for Close tender.',
            ]);
        }

        if (!$id) {
            $data['status'] = 'draft';
        }

        $data = clean_data($data);
        $save_id = $this->Tender_requests_model->ci_save($data, $id);

        if (!$save_id) {
            return $this->response->setJSON([
                'success' => false,
                'message' => app_lang('error_occurred'),
            ]);
        }

        if (($data['tender_type'] ?? 'open') === 'close') {
            $this->Tender_request_vendors_model->sync_request_vendors($save_id, $invited_vendor_ids, $this->login_user->id);
        } else {
            $this->Tender_request_vendors_model->sync_request_vendors($save_id, [], $this->login_user->id);
        }

        $this->Tender_request_team_members_model->sync_members(
            $save_id,
            'technical_evaluator',
            $technical_user_ids,
            (int) $this->login_user->id
        );

        $this->Tender_request_team_members_model->sync_members(
            $save_id,
            'commercial_evaluator',
            $commercial_user_ids,
            (int) $this->login_user->id
        );

        $this->Tender_request_team_members_model->sync_members(
            $save_id,
            'chairman',
            $chairman_user_id ? [$chairman_user_id] : [],
            (int) $this->login_user->id
        );

        $this->Tender_request_team_members_model->sync_members(
            $save_id,
            'secretary',
            $secretary_user_id ? [$secretary_user_id] : [],
            (int) $this->login_user->id
        );

        $this->Tender_request_team_members_model->sync_members(
            $save_id,
            'itc_member',
            $itc_member_user_ids,
            (int) $this->login_user->id
        );

        return $this->response->setJSON([
            'success' => true,
            'data' => $this->_row_data($save_id),
            'id' => $save_id,
            'message' => app_lang('record_saved'),
        ]);
    }

    public function submit()
    {
        $this->validate_submitted_data(['id' => 'required|numeric']);
        $this->access_only_tender('requests', 'update');

        $id = (int) $this->request->getPost('id');
        $info = $this->Tender_requests_model->get_one($id);

        if (!$info || !$info->id || (int) ($info->deleted ?? 0) === 1) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Tender request not found.',
            ]);
        }

        if (!$this->login_user->is_admin && (int) $info->requester_id !== (int) $this->login_user->id) {
            app_redirect('forbidden');
            exit;
        }

        if (!in_array((string) $info->status, ['draft', 'rejected'], true)) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Only draft or rejected requests can be submitted.',
            ]);
        }

        if (($info->tender_type ?? 'open') === 'close') {
            $selected_vendors = $this->Tender_request_vendors_model->get_selected_vendors($id);
            if (!count($selected_vendors)) {
                return $this->response->setJSON([
                    'success' => false,
                    'message' => 'Close tender requests must have invited suppliers before submission.',
                ]);
            }
        }

        $completion_errors = $this->_request_completion_errors($info, $id);
        if (!empty($completion_errors)) {
            return $this->response->setJSON([
                'success' => false,
                'message' => implode(' ', $completion_errors),
            ]);
        }

        $db = db_connect();
        $db->transStart();

        $this->Tender_requests_model->ci_save([
            'status' => 'submitted',
            'department_manager_signed_at' => null,
            'department_manager_reject_comment' => null,
            'finance_verified_by' => null,
            'finance_verified_at' => null,
            'finance_reject_comment' => null,
            'committee_approved_by' => null,
            'committee_approved_at' => null,
            'committee_reject_comment' => null,
        ], $id);

        $this->Tender_request_approvals_model->log_stage(
            $id,
            'requester_submit',
            'submitted',
            null,
            (int) $this->login_user->id
        );

        $db->transComplete();

        if ($db->transStatus() === false) {
            return $this->response->setJSON([
                'success' => false,
                'message' => app_lang('error_occurred'),
            ]);
        }

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Tender request submitted successfully.',
        ]);
    }

    public function list_data()
    {
        $this->access_only_tender('requests', 'view');

        $options = [];
        if (!$this->login_user->is_admin) {
            $options['requester_id'] = (int) $this->login_user->id;
        }

        $list_data = $this->Tender_requests_model->get_details($options)->getResult();
        $result = [];

        foreach ($list_data as $data) {
            $result[] = $this->_make_row($data);
        }

        return $this->response->setJSON(['data' => $result]);
    }

    private function _row_data($id)
    {
        $data = $this->Tender_requests_model->get_details(['id' => $id])->getRow();

        return $this->_make_row($data);
    }

    private function _make_row($data)
    {
        $status = "<span class='badge bg-secondary'>".esc($data->status).'</span>';
        $options = '';

        if (in_array((string) $data->status, ['draft', 'rejected'], true)) {
            $options .= modal_anchor(
                get_uri('tender_requests/modal_form'),
                "<i data-feather='edit' class='icon-16'></i>",
                ['class' => 'edit', 'title' => app_lang('edit'), 'data-post-id' => $data->id]
            );

            $options .= js_anchor(
                "<i data-feather='send' class='icon-16'></i>",
                [
                    'title' => 'Submit',
                    'class' => 'submit',
                    'data-id' => $data->id,
                    'data-action-url' => get_uri('tender_requests/submit'),
                    'data-action' => 'post',
                ]
            );
        }

        return [
            $data->reference,
            $data->subject,
            $data->budget_omr,
            $data->tender_fee,
            $status,
            $options,
        ];
    }

    public function departments_by_company($company_id = 0)
    {
        $company_id = (int) ($company_id ?: $this->request->getGet('company_id'));
        if ($resp = $this->_require_requests_access_json()) {
            return $resp;
        }

        if (!$company_id) {
            return $this->response->setJSON([]);
        }

        $db = db_connect();
        $departments = $db->prefixTable('departments');

        $rows = $db->query(
            "SELECT id, name
             FROM $departments
             WHERE deleted=0 AND is_active=1 AND company_id=?
             ORDER BY name ASC",
            [$company_id]
        )->getResult();

        $out = [];
        foreach ($rows as $r) {
            $out[] = ['id' => (int) $r->id, 'text' => $r->name];
        }

        return $this->response->setJSON($out);
    }

    public function vendors_suggestion()
    {
        if ($resp = $this->_require_requests_access_json()) {
            return $resp;
        }

        $q = trim((string) $this->request->getPost('q'));
        $db = db_connect();
        $vendors = $db->prefixTable('vendors');

        $where = "WHERE $vendors.deleted=0 AND $vendors.status='approved'";

        if ($q) {
            $like = $db->escapeLikeString($q);
            $where .= " AND ($vendors.vendor_name LIKE '%$like%' OR $vendors.email LIKE '%$like%')";
        }

        $rows = $db->query(
            "SELECT id, vendor_name
             FROM $vendors
             $where
             ORDER BY vendor_name ASC
             LIMIT 20"
        )->getResult();

        $result = [];
        foreach ($rows as $r) {
            $result[] = [
                'id' => (int) $r->id,
                'text' => $r->vendor_name,
            ];
        }

        return $this->response->setJSON($result);
    }

    public function users_suggestion()
    {
        if ($resp = $this->_require_requests_access_json()) {
            return $resp;
        }

        $q = trim((string) $this->request->getPost('q'));
        $users = $this->db->prefixTable('users');

        $where = "WHERE $users.deleted=0
                  AND $users.status='active'
                  AND $users.user_type='staff'";

        if ($q) {
            $like = $this->db->escapeLikeString($q);
            $where .= " AND (
                $users.first_name LIKE '%$like%' OR
                $users.last_name LIKE '%$like%' OR
                $users.email LIKE '%$like%'
            )";
        }

        $rows = $this->db->query(
            "SELECT
                id,
                TRIM(CONCAT(COALESCE(first_name,''), ' ', COALESCE(last_name,''))) AS full_name,
                email
             FROM $users
             $where
             ORDER BY first_name ASC, last_name ASC
             LIMIT 30"
        )->getResult();

        $result = [];
        foreach ($rows as $r) {
            $text = trim($r->full_name);
            if ($r->email) {
                $text .= ' ('.$r->email.')';
            }

            $result[] = [
                'id' => (int) $r->id,
                'text' => $text,
            ];
        }

        return $this->response->setJSON($result);
    }

    public function department_managers_suggestion()
    {
        if ($resp = $this->_require_requests_access_json()) {
            return $resp;
        }

        $company_id = (int) $this->request->getPost('company_id');
        $department_id = (int) $this->request->getPost('department_id');
        $q = trim((string) $this->request->getPost('q'));

        if (!$company_id || !$department_id) {
            return $this->response->setJSON([]);
        }

        $tdu = $this->db->prefixTable('tender_department_manager_users');
        $users = $this->db->prefixTable('users');

        $where = "WHERE $tdu.deleted=0
              AND $tdu.status='active'
              AND $tdu.company_id=$company_id
              AND $tdu.department_id=$department_id
              AND $users.deleted=0
              AND $users.status='active'";

        if ($q !== '') {
            $like = $this->db->escapeLikeString($q);
            $where .= " AND (
            $users.first_name LIKE '%$like%' OR
            $users.last_name LIKE '%$like%' OR
            $users.email LIKE '%$like%'
        )";
        }

        $rows = $this->db->query(
            "SELECT DISTINCT
            $users.id,
            TRIM(CONCAT(COALESCE($users.first_name,''), ' ', COALESCE($users.last_name,''))) AS full_name,
            $users.email
         FROM $tdu
         LEFT JOIN $users ON $users.id = $tdu.user_id
         $where
         ORDER BY $users.first_name ASC, $users.last_name ASC
         LIMIT 20"
        )->getResult();

        $result = [];
        foreach ($rows as $r) {
            $text = trim($r->full_name);
            if ($r->email) {
                $text .= ' ('.$r->email.')';
            }

            $result[] = [
                'id' => (int) $r->id,
                'text' => $text,
            ];
        }

        return $this->response->setJSON($result);
    }

    public function committee_users_suggestion()
    {
        if ($resp = $this->_require_requests_access_json()) {
            return $resp;
        }

        $company_id = (int) $this->request->getPost('company_id');
        $q = trim((string) $this->request->getPost('q'));

        if (!$company_id) {
            return $this->response->setJSON([]);
        }

        $tcu = $this->db->prefixTable('tender_committee_users');
        $users = $this->db->prefixTable('users');

        $where = "WHERE $tcu.deleted=0
              AND $tcu.status='active'
              AND $tcu.company_id=$company_id
              AND $users.deleted=0
              AND $users.status='active'";

        if ($q !== '') {
            $like = $this->db->escapeLikeString($q);
            $where .= " AND (
            $users.first_name LIKE '%$like%' OR
            $users.last_name LIKE '%$like%' OR
            $users.email LIKE '%$like%'
        )";
        }

        $rows = $this->db->query(
            "SELECT DISTINCT
            $users.id,
            TRIM(CONCAT(COALESCE($users.first_name,''), ' ', COALESCE($users.last_name,''))) AS full_name,
            $users.email
         FROM $tcu
         LEFT JOIN $users ON $users.id = $tcu.user_id
         $where
         ORDER BY $users.first_name ASC, $users.last_name ASC
         LIMIT 20"
        )->getResult();

        $result = [];
        foreach ($rows as $r) {
            $text = trim($r->full_name);
            if ($r->email) {
                $text .= ' ('.$r->email.')';
            }

            $result[] = [
                'id' => (int) $r->id,
                'text' => $text,
            ];
        }

        return $this->response->setJSON($result);
    }

    private function _get_department_manager_assignment(int $company_id, int $department_id)
    {
        if (!$company_id || !$department_id) {
            return null;
        }

        $tdmu = $this->db->prefixTable('tender_department_manager_users');
        $users = $this->db->prefixTable('users');

        return $this->db->query(
            "SELECT
                $tdmu.*,
                $users.email,
                $users.job_title,
                TRIM(CONCAT(COALESCE($users.first_name,''), ' ', COALESCE($users.last_name,''))) AS full_name
            FROM $tdmu
            LEFT JOIN $users ON $users.id = $tdmu.user_id AND $users.deleted=0
            WHERE $tdmu.deleted=0
            AND $tdmu.status='active'
            AND $tdmu.company_id=?
            AND $tdmu.department_id=?
            ORDER BY $tdmu.id DESC
            LIMIT 1",
            [$company_id, $department_id]
        )->getRow();
    }

    private function _is_valid_pool_user(string $table, int $user_id, int $company_id): bool
    {
        if (!$user_id || !$company_id) {
            return false;
        }

        $t = $this->db->prefixTable($table);

        $row = $this->db->query(
            "SELECT id
            FROM $t
            WHERE deleted=0
            AND status='active'
            AND user_id=?
            AND company_id=?
            LIMIT 1",
            [$user_id, $company_id]
        )->getRow();

        return (bool) $row;
    }

    private function _is_valid_department_manager(int $user_id, int $company_id, int $department_id): bool
    {
        if (!$user_id || !$company_id || !$department_id) {
            return false;
        }

        $tdmu = $this->db->prefixTable('tender_department_manager_users');

        $row = $this->db->query(
            "SELECT id
         FROM $tdmu
         WHERE deleted=0
           AND status='active'
           AND user_id=?
           AND company_id=?
           AND department_id=?
         LIMIT 1",
            [$user_id, $company_id, $department_id]
        )->getRow();

        return (bool) $row;
    }

    private function _validate_pool_selection(string $table, array $user_ids, int $company_id, string $label): array
    {
        $errors = [];

        foreach ($user_ids as $user_id) {
            if (!$this->_is_valid_pool_user($table, (int) $user_id, $company_id)) {
                $errors[] = "All {$label} must be selected from the correct Tender Users master.";
                break;
            }
        }

        return $errors;
    }

    public function department_manager_context()
    {
        if ($resp = $this->_require_requests_access_json()) {
            return $resp;
        }

        $company_id = (int) $this->request->getGet('company_id');
        $department_id = (int) $this->request->getGet('department_id');

        $manager = $this->_get_department_manager_assignment($company_id, $department_id);

        return $this->response->setJSON([
            'user_id' => (int) ($manager->user_id ?? 0),
            'name' => $manager->full_name ?? '',
            'title' => $manager->job_title ?? '',
            'email' => $manager->email ?? '',
        ]);
    }

    public function technical_users_suggestion()
    {
        if ($resp = $this->_require_requests_access_json()) {
            return $resp;
        }

        $company_id = (int) $this->request->getPost('company_id');
        $q = trim((string) $this->request->getPost('q'));

        if (!$company_id) {
            return $this->response->setJSON([]);
        }

        $ttu = $this->db->prefixTable('tender_technical_users');
        $users = $this->db->prefixTable('users');

        $where = "WHERE $ttu.deleted=0
              AND $ttu.status='active'
              AND $ttu.company_id=$company_id
              AND $users.deleted=0
              AND $users.status='active'";

        if ($q !== '') {
            $like = $this->db->escapeLikeString($q);
            $where .= " AND (
            $users.first_name LIKE '%$like%' OR
            $users.last_name LIKE '%$like%' OR
            $users.email LIKE '%$like%'
        )";
        }

        $rows = $this->db->query(
            "SELECT DISTINCT
            $users.id,
            TRIM(CONCAT(COALESCE($users.first_name,''), ' ', COALESCE($users.last_name,''))) AS full_name,
            $users.email
         FROM $ttu
         LEFT JOIN $users ON $users.id = $ttu.user_id
         $where
         ORDER BY $users.first_name ASC, $users.last_name ASC
         LIMIT 20"
        )->getResult();

        $result = [];
        foreach ($rows as $r) {
            $text = trim($r->full_name);
            if ($r->email) {
                $text .= ' ('.$r->email.')';
            }
            $result[] = ['id' => (int) $r->id, 'text' => $text];
        }

        return $this->response->setJSON($result);
    }

    public function commercial_users_suggestion()
    {
        if ($resp = $this->_require_requests_access_json()) {
            return $resp;
        }

        $company_id = (int) $this->request->getPost('company_id');
        $q = trim((string) $this->request->getPost('q'));

        if (!$company_id) {
            return $this->response->setJSON([]);
        }

        $tcu = $this->db->prefixTable('tender_commercial_users');
        $users = $this->db->prefixTable('users');

        $where = "WHERE $tcu.deleted=0
              AND $tcu.status='active'
              AND $tcu.company_id=$company_id
              AND $users.deleted=0
              AND $users.status='active'";

        if ($q !== '') {
            $like = $this->db->escapeLikeString($q);
            $where .= " AND (
            $users.first_name LIKE '%$like%' OR
            $users.last_name LIKE '%$like%' OR
            $users.email LIKE '%$like%'
        )";
        }

        $rows = $this->db->query(
            "SELECT DISTINCT
            $users.id,
            TRIM(CONCAT(COALESCE($users.first_name,''), ' ', COALESCE($users.last_name,''))) AS full_name,
            $users.email
         FROM $tcu
         LEFT JOIN $users ON $users.id = $tcu.user_id
         $where
         ORDER BY $users.first_name ASC, $users.last_name ASC
         LIMIT 20"
        )->getResult();

        $result = [];
        foreach ($rows as $r) {
            $text = trim($r->full_name);
            if ($r->email) {
                $text .= ' ('.$r->email.')';
            }
            $result[] = ['id' => (int) $r->id, 'text' => $text];
        }

        return $this->response->setJSON($result);
    }

    private function _get_pool_users_by_company(string $table, int $company_id): array
    {
        if (!$company_id) {
            return [];
        }

        $pool = $this->db->prefixTable($table);
        $users = $this->db->prefixTable('users');

        return $this->db->query(
            "SELECT DISTINCT
            $users.id,
            TRIM(CONCAT(COALESCE($users.first_name,''), ' ', COALESCE($users.last_name,''))) AS full_name,
            $users.email
         FROM $pool
         INNER JOIN $users ON $users.id = $pool.user_id
         WHERE $pool.deleted=0
           AND $pool.status='active'
           AND $pool.company_id=?
           AND $users.deleted=0
           AND $users.status='active'
         ORDER BY $users.first_name ASC, $users.last_name ASC",
            [$company_id]
        )->getResult();
    }
}

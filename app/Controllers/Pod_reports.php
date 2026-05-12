<?php

namespace App\Controllers;

use App\Models\Pod_dashboard_stats_model;

class Pod_reports extends Security_Controller
{
    protected $db;
    protected $Stats_model;

    public function __construct()
    {
        parent::__construct();
        $this->access_only_team_members();
        $this->access_only_pod_reports();

        $this->db = db_connect();
        $this->Stats_model = new Pod_dashboard_stats_model();
    }

    public function index()
    {
        return $this->template->rander("pod_reports/index", [
            "vendors" => $this->_vendor_stats(),
            "tenders" => $this->_tender_stats(),
            "gate_pass" => $this->_gate_pass_stats(),
            "ptw" => $this->_ptw_stats(),
            "recent_activity" => $this->_recent_activity(),
        ]);
    }

    private function _vendor_stats(): array
    {
        $vendors = $this->db->prefixTable("vendors");
        $docs = $this->db->prefixTable("vendor_documents");

        return [
            "total" => $this->_scalar("SELECT COUNT(*) FROM $vendors WHERE deleted=0"),
            "approved" => $this->_scalar("SELECT COUNT(*) FROM $vendors WHERE deleted=0 AND status='approved'"),
            "submitted" => $this->_scalar("SELECT COUNT(*) FROM $vendors WHERE deleted=0 AND status IN ('new','submitted','revise')"),
            "suspended" => $this->_scalar("SELECT COUNT(*) FROM $vendors WHERE deleted=0 AND status IN ('suspended','expired','rejected')"),
            "docs_expiring" => $this->_scalar("SELECT COUNT(*) FROM $docs WHERE deleted=0 AND expires_at IS NOT NULL AND expires_at >= CURDATE() AND expires_at <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)"),
            "docs_expired" => $this->_scalar("SELECT COUNT(*) FROM $docs WHERE deleted=0 AND expires_at IS NOT NULL AND expires_at < CURDATE()"),
            "status" => $this->_map("SELECT status, COUNT(*) AS total FROM $vendors WHERE deleted=0 GROUP BY status"),
        ];
    }

    private function _tender_stats(): array
    {
        $tenders = $this->db->prefixTable("tenders");
        $bids = $this->db->prefixTable("tender_bids");

        return [
            "total" => $this->_scalar("SELECT COUNT(*) FROM $tenders WHERE deleted=0"),
            "active" => $this->_scalar("SELECT COUNT(*) FROM $tenders WHERE deleted=0 AND status IN ('draft','published','closed') AND workflow_stage <> 'award_decision'"),
            "awarded" => $this->_scalar("SELECT COUNT(*) FROM $tenders WHERE deleted=0 AND status='awarded'"),
            "cancelled" => $this->_scalar("SELECT COUNT(*) FROM $tenders WHERE deleted=0 AND status='cancelled'"),
            "bids" => $this->_scalar("SELECT COUNT(*) FROM $bids WHERE deleted=0 AND status <> 'draft'"),
            "status" => $this->_map("SELECT status, COUNT(*) AS total FROM $tenders WHERE deleted=0 GROUP BY status"),
            "stage" => $this->_map("SELECT workflow_stage AS status, COUNT(*) AS total FROM $tenders WHERE deleted=0 GROUP BY workflow_stage"),
            "durations" => [
                "Bid Window" => $this->_avg_duration("SELECT AVG(TIMESTAMPDIFF(SECOND, COALESCE(published_at, release_at, created_at), closing_at)) FROM $tenders WHERE deleted=0 AND closing_at IS NOT NULL"),
                "Technical Evaluation" => $this->_avg_duration("SELECT AVG(TIMESTAMPDIFF(SECOND, technical_start_at, technical_locked_at)) FROM $tenders WHERE deleted=0 AND technical_start_at IS NOT NULL AND technical_locked_at IS NOT NULL"),
                "Commercial Evaluation" => $this->_avg_duration("SELECT AVG(TIMESTAMPDIFF(SECOND, commercial_start_at, award_ready_at)) FROM $tenders WHERE deleted=0 AND commercial_start_at IS NOT NULL AND award_ready_at IS NOT NULL"),
            ],
        ];
    }

    private function _gate_pass_stats(): array
    {
        $requests = $this->db->prefixTable("gate_pass_requests");
        $stats = $this->Stats_model->gate_pass_kpis([]);
        $processing = $this->Stats_model->gate_pass_avg_processing_times(["days" => 30]);

        return [
            "total" => $this->_scalar("SELECT COUNT(*) FROM $requests WHERE deleted=0"),
            "in_progress" => (int) ($stats["in_progress"] ?? 0),
            "issued_valid" => (int) ($stats["issued_valid"] ?? 0),
            "returned" => $this->_scalar("SELECT COUNT(*) FROM $requests WHERE deleted=0 AND status='returned'"),
            "status" => $stats["status"] ?? [],
            "stage" => $stats["stage"] ?? [],
            "processing" => $processing["stage_avg"] ?? [],
        ];
    }

    private function _ptw_stats(): array
    {
        $apps = $this->db->prefixTable("ptw_applications");
        $stats = $this->Stats_model->ptw_kpis([]);
        $processing = $this->Stats_model->ptw_avg_processing_times(["days" => 30]);

        return [
            "total" => $this->_scalar("SELECT COUNT(*) FROM $apps WHERE deleted=0"),
            "in_progress" => (int) ($stats["in_progress"] ?? 0),
            "approved" => $this->_scalar("SELECT COUNT(*) FROM $apps WHERE deleted=0 AND status='approved'"),
            "rejected" => $this->_scalar("SELECT COUNT(*) FROM $apps WHERE deleted=0 AND status='rejected'"),
            "status" => $stats["status"] ?? [],
            "stage" => $stats["stage"] ?? [],
            "processing" => $processing["stage_avg"] ?? [],
        ];
    }

    private function _recent_activity(): array
    {
        $activity = [];
        $vendors = $this->db->prefixTable("vendors");
        $tenders = $this->db->prefixTable("tenders");
        $gate_pass = $this->db->prefixTable("gate_pass_requests");
        $ptw = $this->db->prefixTable("ptw_applications");

        foreach ($this->db->query("SELECT vendor_name AS title, status, COALESCE(updated_at, created_at) AS event_at FROM $vendors WHERE deleted=0 ORDER BY COALESCE(updated_at, created_at) DESC, id DESC LIMIT 5")->getResult() as $row) {
            $activity[] = ["module" => "Vendor", "title" => $row->title ?: "-", "status" => $row->status ?: "-", "event_at" => $row->event_at];
        }
        foreach ($this->db->query("SELECT CONCAT(reference, ' - ', title) AS title, status, COALESCE(updated_at, created_at) AS event_at FROM $tenders WHERE deleted=0 ORDER BY COALESCE(updated_at, created_at) DESC, id DESC LIMIT 5")->getResult() as $row) {
            $activity[] = ["module" => "Tender", "title" => $row->title ?: "-", "status" => $row->status ?: "-", "event_at" => $row->event_at];
        }
        foreach ($this->db->query("SELECT reference AS title, status, COALESCE(submitted_at, updated_at, created_at) AS event_at FROM $gate_pass WHERE deleted=0 ORDER BY COALESCE(submitted_at, updated_at, created_at) DESC, id DESC LIMIT 5")->getResult() as $row) {
            $activity[] = ["module" => "Gate Pass", "title" => $row->title ?: "-", "status" => $row->status ?: "-", "event_at" => $row->event_at];
        }
        foreach ($this->db->query("SELECT reference AS title, status, COALESCE(submitted_at, completed_at, updated_at, created_at) AS event_at FROM $ptw WHERE deleted=0 ORDER BY COALESCE(submitted_at, completed_at, updated_at, created_at) DESC, id DESC LIMIT 5")->getResult() as $row) {
            $activity[] = ["module" => "PTW", "title" => $row->title ?: "-", "status" => $row->status ?: "-", "event_at" => $row->event_at];
        }

        usort($activity, static function ($a, $b) {
            return strtotime((string) ($b["event_at"] ?? "")) <=> strtotime((string) ($a["event_at"] ?? ""));
        });

        return array_slice($activity, 0, 12);
    }

    private function _scalar(string $sql): int
    {
        $row = $this->db->query($sql)->getRowArray() ?: [];
        $values = array_values($row);
        return (int) ($values[0] ?? 0);
    }

    private function _map(string $sql): array
    {
        $rows = $this->db->query($sql)->getResult();
        $map = [];
        foreach ($rows as $row) {
            $map[(string) ($row->status ?? "-")] = (int) ($row->total ?? 0);
        }
        arsort($map);
        return $map;
    }

    private function _avg_duration(string $sql): string
    {
        $row = $this->db->query($sql)->getRowArray() ?: [];
        $values = array_values($row);
        $seconds = (int) round((float) ($values[0] ?? 0));
        return $this->_duration_label($seconds);
    }

    private function _duration_label(int $seconds): string
    {
        if ($seconds <= 0) {
            return "-";
        }

        $minutes = intdiv($seconds, 60);
        $days = intdiv($minutes, 1440);
        $hours = intdiv($minutes % 1440, 60);
        $mins = $minutes % 60;
        $parts = [];
        if ($days) {
            $parts[] = $days . "d";
        }
        if ($hours) {
            $parts[] = $hours . "h";
        }
        if (!$parts && $mins) {
            $parts[] = $mins . "m";
        }
        return $parts ? implode(" ", $parts) : "0m";
    }
}

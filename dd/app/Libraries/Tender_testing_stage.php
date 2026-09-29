<?php

namespace App\Libraries;

class Tender_testing_stage
{
    private const ORDERED_OPTIONS = [
        "published_bidding" => "1. Tender Published / Bid Submission",
        "clarification" => "2. Clarification Period",
        "site_visit" => "3. Site Visit",
        "technical_3key" => "4. Tender Closing / Bid Opening",
        "technical" => "5. Technical Evaluation",
        "commercial" => "6. Commercial Evaluation",
    ];

    public function options(bool $include_blank = true): array
    {
        if (!$include_blank) {
            return self::ORDERED_OPTIONS;
        }

        return ["" => "- Keep normal date-based flow -"] + self::ORDERED_OPTIONS;
    }

    public function normalize($stage): ?string
    {
        $stage = strtolower(trim((string) $stage));
        return array_key_exists($stage, self::ORDERED_OPTIONS) ? $stage : null;
    }

    public function label(string $stage): string
    {
        return self::ORDERED_OPTIONS[$stage] ?? ucwords(str_replace("_", " ", $stage));
    }

    public function build_payload(string $stage, $tender = null, ?string $now = null): array
    {
        $stage = $this->normalize($stage);
        if (!$stage) {
            return [];
        }

        $now = $now ?: date("Y-m-d H:i:s");
        $publishedAt = !empty($tender->published_at) ? (string) $tender->published_at : $now;
        $releasePast = $this->at($now, "-1 hour");
        $pastRelease = $this->at($now, "-3 days");
        $pastPurchase = $this->at($now, "-2 days");
        $pastVisit = $this->at($now, "-1 day");
        $pastClarification = $this->at($now, "-12 hours");
        $pastClosing = $this->at($now, "-30 minutes");
        $pastBidOpening = $this->at($now, "-25 minutes");
        $pastTechnicalStart = $this->at($now, "-20 minutes");
        $pastTechnicalEnd = $this->at($now, "-10 minutes");
        $pastCommercialOpening = $this->at($now, "-5 minutes");
        $futureShort = $this->at($now, "+4 hours");
        $futureMedium = $this->at($now, "+1 day");
        $futureLong = $this->at($now, "+2 days");
        $futureFinal = $this->at($now, "+3 days");

        $payload = [
            "published_at" => $publishedAt,
            "updated_at" => $now,
        ];

        if (in_array($stage, ["published_bidding", "clarification", "site_visit"], true)) {
            $payload = array_merge($payload, [
                "status" => "published",
                "workflow_stage" => "bidding",
                "technical_start_at" => null,
                "technical_end_at" => null,
                "technical_locked_at" => null,
                "commercial_unlocked_at" => null,
                "commercial_start_at" => null,
                "commercial_end_at" => null,
                "award_ready_at" => null,
            ]);

            if ($stage === "published_bidding") {
                return array_merge($payload, [
                    "release_at" => $now,
                    "document_purchase_deadline" => $futureShort,
                    "site_visit_at" => $this->at($now, "+6 hours"),
                    "clarification_deadline" => $futureMedium,
                    "closing_at" => $futureLong,
                    "bid_opening_at" => $this->at($futureLong, "+1 hour"),
                    "technical_eval_deadline" => $futureFinal,
                    "commercial_eval_deadline" => $this->at($futureFinal, "+1 day"),
                ]);
            }

            if ($stage === "clarification") {
                return array_merge($payload, [
                    "release_at" => $releasePast,
                    "document_purchase_deadline" => $futureShort,
                    "site_visit_at" => $this->at($now, "+6 hours"),
                    "clarification_deadline" => $futureMedium,
                    "closing_at" => $futureLong,
                    "bid_opening_at" => $this->at($futureLong, "+1 hour"),
                    "technical_eval_deadline" => $futureFinal,
                    "commercial_eval_deadline" => $this->at($futureFinal, "+1 day"),
                ]);
            }

            return array_merge($payload, [
                "release_at" => $releasePast,
                "document_purchase_deadline" => $futureShort,
                "site_visit_at" => $now,
                "clarification_deadline" => $this->at($now, "+8 hours"),
                "closing_at" => $futureMedium,
                "bid_opening_at" => $this->at($futureMedium, "+1 hour"),
                "technical_eval_deadline" => $futureLong,
                "commercial_eval_deadline" => $futureFinal,
            ]);
        }

        $payload = array_merge($payload, [
            "status" => "closed",
            "release_at" => $pastRelease,
            "document_purchase_deadline" => $pastPurchase,
            "site_visit_at" => $pastVisit,
            "clarification_deadline" => $pastClarification,
            "closing_at" => $pastClosing,
            "bid_opening_at" => $pastBidOpening,
        ]);

        if ($stage === "technical_3key") {
            return array_merge($payload, [
                "workflow_stage" => "technical_3key",
                "bid_opening_at" => $futureMedium,
                "technical_start_at" => null,
                "technical_end_at" => null,
                "technical_locked_at" => null,
                "technical_eval_deadline" => $futureLong,
                "commercial_unlocked_at" => null,
                "commercial_start_at" => null,
                "commercial_end_at" => null,
                "commercial_eval_deadline" => $futureFinal,
                "award_ready_at" => null,
            ]);
        }

        if ($stage === "technical") {
            return array_merge($payload, [
                "workflow_stage" => "technical",
                "technical_start_at" => $now,
                "technical_end_at" => $futureMedium,
                "technical_eval_deadline" => $futureMedium,
                "technical_locked_at" => null,
                "commercial_unlocked_at" => null,
                "commercial_start_at" => null,
                "commercial_end_at" => null,
                "commercial_eval_deadline" => $futureLong,
                "award_ready_at" => null,
            ]);
        }

        return array_merge($payload, [
            "workflow_stage" => "commercial",
            "technical_start_at" => $pastTechnicalStart,
            "technical_end_at" => $pastTechnicalEnd,
            "technical_eval_deadline" => $pastTechnicalEnd,
            "technical_locked_at" => $pastCommercialOpening,
            "commercial_unlocked_at" => $now,
            "commercial_start_at" => $now,
            "commercial_end_at" => $futureMedium,
            "commercial_eval_deadline" => $futureMedium,
            "award_ready_at" => null,
        ]);
    }

    public function opening_actions(string $stage): array
    {
        $stage = $this->normalize($stage);
        if (!$stage) {
            return [];
        }

        if (in_array($stage, ["published_bidding", "clarification", "site_visit", "technical_3key"], true)) {
            return [
                "expire" => ["technical", "commercial"],
                "unlock" => [],
            ];
        }

        if ($stage === "technical") {
            return [
                "expire" => ["commercial"],
                "unlock" => ["technical"],
            ];
        }

        return [
            "expire" => [],
            "unlock" => ["technical"],
        ];
    }

    private function at(string $base, string $modifier): string
    {
        return date("Y-m-d H:i:s", strtotime($base . " " . $modifier));
    }
}

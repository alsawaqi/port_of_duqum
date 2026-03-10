<?php echo form_open(get_uri("tender_technical_inbox/save_evaluations"), ["id" => "tender-technical-evaluation-form", "class" => "general-form"]); ?>
<input type="hidden" name="tender_id" value="<?php echo (int) ($tender->id ?? 0); ?>" />

<div class="modal-body">
    <h5 class="mb-3">Technical Evaluation</h5>

    <div class="row">
        <div class="col-md-6">
            <div><strong>Reference:</strong> <?php echo esc($tender->reference ?? "-"); ?></div>
            <div><strong>Title:</strong> <?php echo esc($tender->title ?? "-"); ?></div>
        </div>
        <div class="col-md-6">
            <div><strong>Tender Type:</strong> <?php echo esc($tender->tender_type ?? "-"); ?></div>
            <div><strong>Status:</strong> <span class="badge bg-dark"><?php echo esc(ucfirst($tender->status ?? "closed")); ?></span></div>
            <div><strong>Closed At:</strong> <?php echo !empty($tender->closing_at) ? format_to_datetime($tender->closing_at) : "-"; ?></div>
        </div>
    </div>

    <hr>

    <div class="alert alert-info mb-3">
        Only <strong>technical proposal documents</strong> are available in this stage. Commercial documents and prices stay outside the technical review screen.
    </div>

    <div class="alert alert-secondary mb-4">
        Score each criterion from <strong>0</strong> up to its configured <strong>weight</strong>. Current technical outcome is stored as <strong>Accepted</strong> or <strong>Rejected</strong> on the bid.
    </div>

    <?php if (empty($bids)) { ?>
        <div class="text-muted">No bids are available for technical evaluation.</div>
    <?php } else { ?>
        <?php foreach ($bids as $bid) {
            $bid_id = (int) $bid->id;
            $current_evaluation = $evaluation_by_bid[$bid_id] ?? null;
            $current_scores = $scores_by_bid[$bid_id] ?? [];
            $current_status = strtolower((string) ($bid->status ?? "submitted"));
            $decision_value = in_array($current_status, ["accepted", "rejected"], true) ? $current_status : "";

            $badge_class = "bg-primary";
            if ($current_status === "accepted") {
                $badge_class = "bg-success";
            } elseif ($current_status === "rejected") {
                $badge_class = "bg-danger";
            }
        ?>
            <div class="card mb-4 border">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <div>
                        <strong><?php echo esc($bid->vendor_name ?? ("Bid #" . $bid_id)); ?></strong>
                    </div>
                    <span class="badge <?php echo $badge_class; ?>"><?php echo esc(ucfirst($current_status)); ?></span>
                </div>

                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <div><strong>Submitted At:</strong></div>
                            <div><?php echo !empty($bid->submitted_at) ? format_to_datetime($bid->submitted_at) : "-"; ?></div>
                        </div>
                        <div class="col-md-4">
                            <div><strong>Your Saved Total:</strong></div>
                            <div>
                                <?php echo $current_evaluation ? esc(number_format((float) ($current_evaluation->total_score ?? 0), 3)) : "-"; ?>
                                / <?php echo esc(number_format((float) ($stage_max_score ?? 0), 3)); ?>
                            </div>
                        </div>
                        <div class="col-md-4 text-md-end">
                            <div><strong>Technical Proposal:</strong></div>
                            <div class="mt-1">
                                <?php if (!empty($bid->technical_doc_id)) { ?>
                                    <a href="<?php echo get_uri("tender_technical_inbox/download_bid_document/" . $bid->technical_doc_id); ?>" class="btn btn-default btn-sm">
                                        <i data-feather="download" class="icon-14"></i>
                                        <?php echo esc($bid->technical_doc_name ?: "Download"); ?>
                                    </a>
                                <?php } else { ?>
                                    <span class="text-muted">No technical file uploaded</span>
                                <?php } ?>
                            </div>
                        </div>
                    </div>

                    <div class="form-group mb-3">
                        <label><strong>Set Technical Outcome</strong></label>
                        <select name="decision[<?php echo $bid_id; ?>]" class="form-control">
                            <option value="">- Select -</option>
                            <option value="accepted" <?php echo $decision_value === "accepted" ? "selected" : ""; ?>>Technical Accepted</option>
                            <option value="rejected" <?php echo $decision_value === "rejected" ? "selected" : ""; ?>>Technical Rejected</option>
                        </select>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered table-sm align-middle">
                            <thead>
                                <tr>
                                    <th style="width: 35%;">Criterion</th>
                                    <th style="width: 10%;">Weight</th>
                                    <th style="width: 20%;">Score</th>
                                    <th style="width: 35%;">Comment</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($criteria as $criterion) {
                                    $criterion_id = (int) $criterion->id;
                                    $saved_score_row = $current_scores[$criterion_id] ?? null;
                                    $saved_score = $saved_score_row ? $saved_score_row->score : "";
                                    $saved_comment = $saved_score_row ? $saved_score_row->comment : "";
                                ?>
                                    <tr>
                                        <td><?php echo esc($criterion->name ?? "-"); ?></td>
                                        <td><?php echo esc(number_format((float) ($criterion->weight ?? 0), 3)); ?></td>
                                        <td>
                                            <input
                                                type="number"
                                                name="scores[<?php echo $bid_id; ?>][<?php echo $criterion_id; ?>]"
                                                class="form-control criterion-score"
                                                data-bid="<?php echo $bid_id; ?>"
                                                step="0.001"
                                                min="0"
                                                max="<?php echo esc((string) ($criterion->weight ?? 0)); ?>"
                                                value="<?php echo esc((string) $saved_score); ?>"
                                                placeholder="0.000" />
                                        </td>
                                        <td>
                                            <textarea
                                                name="criterion_comments[<?php echo $bid_id; ?>][<?php echo $criterion_id; ?>]"
                                                class="form-control"
                                                rows="2"
                                                placeholder="Optional comment for this criterion"><?php echo esc((string) $saved_comment); ?></textarea>
                                        </td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="row mt-3 mb-2">
                        <div class="col-md-8"></div>
                        <div class="col-md-4 text-md-end">
                            <strong>Total Score:</strong>
                            <span id="bid-total-<?php echo $bid_id; ?>" class="bid-total-value" data-bid="<?php echo $bid_id; ?>">
                                <?php echo esc(number_format((float) ($current_evaluation->total_score ?? 0), 3)); ?>
                            </span>
                            / <?php echo esc(number_format((float) ($stage_max_score ?? 0), 3)); ?>
                        </div>
                    </div>

                    <div class="form-group mt-3 mb-0">
                        <label><strong>Overall Comments</strong></label>
                        <textarea
                            name="evaluation_comments[<?php echo $bid_id; ?>]"
                            class="form-control"
                            rows="3"
                            placeholder="Overall technical remarks for this bidder"><?php echo esc((string) ($current_evaluation->comments ?? "")); ?></textarea>
                    </div>
                </div>
            </div>
        <?php } ?>
    <?php } ?>
</div>

<div class="modal-footer">
    <button type="button" class="btn btn-default" data-bs-dismiss="modal"><?php echo app_lang("close"); ?></button>
    <?php if (!empty($bids)) { ?>
        <button type="submit" class="btn btn-primary">Save Technical Evaluation</button>
    <?php } ?>
</div>

<?php echo form_close(); ?>

<script>
    $(document).ready(function () {
        $("#tender-technical-evaluation-form").appForm({
            onSuccess: function (response) {
                $("#tender-technical-inbox-table").appTable({reload: true});
            }
        });

        function updateBidTotal(bidId) {
            var total = 0;
            $(".criterion-score[data-bid='" + bidId + "']").each(function () {
                var value = parseFloat($(this).val());
                if (!isNaN(value)) {
                    total += value;
                }
            });
            $("#bid-total-" + bidId).text(total.toFixed(3));
        }

        $(".criterion-score").on("input", function () {
            updateBidTotal($(this).data("bid"));
        });

        $(".bid-total-value").each(function () {
            updateBidTotal($(this).data("bid"));
        });

        if (typeof feather !== "undefined") {
            feather.replace();
        }
    });
</script>
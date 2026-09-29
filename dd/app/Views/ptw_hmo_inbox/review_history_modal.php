<div class="modal-body clearfix">
    <h4 class="mb15">
        <?php echo app_lang("review_history"); ?> - <?php echo esc((string)($application->reference ?? "-")); ?>
    </h4>

    <?php echo view("ptw_common/review_history_timeline", ["reviews" => $reviews ?? [], "stage_label" => "HMO"]); ?>
</div>

<div class="modal-footer">
    <button type="button" class="btn btn-default" data-dismiss="modal"><?php echo app_lang("close"); ?></button>
</div>

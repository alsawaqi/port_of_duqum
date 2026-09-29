<div class="modal-body text-center">
    <div class="mb-3">
        <i data-feather="lock" class="icon-48 text-warning"></i>
    </div>

    <h4><?= app_lang("action_not_allowed") ?></h4>

    <p class="text-muted">
        This section has a pending approval request.<br>
        You cannot make changes until it is reviewed by the admin.
    </p>

    <div class="mt-4">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
            <?= app_lang("close") ?>
        </button>
    </div>
</div>
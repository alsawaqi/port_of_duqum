<div class="card mb-3">
    <div class="card-body">
        <h2 class="h4">Registration review</h2>
        <p>Status: <strong><?php echo esc(ucwords(str_replace('_', ' ', $application->status))); ?></strong>
            &middot; Registration fee: <strong><?php echo esc($application->currency . ' ' . $application->amount); ?></strong></p>
        <?php if (!empty($application->riyada_document_id)): ?>
            <p><a class="btn btn-default" target="_blank" rel="noopener" href="<?php echo get_uri('vendors/vendor_document_preview/' . (int) $application->riyada_document_id); ?>">View Riyadha certificate</a></p>
        <?php endif; ?>
        <?php if (!empty($application->review_note)): ?>
            <p><strong>Review note:</strong> <?php echo nl2br(esc($application->review_note)); ?></p>
        <?php endif; ?>
        <?php if ($application->status === 'pending_review' && $can_review): ?>
            <p>Review the Riyadha certificate. Approve the waiver to register this vendor, or request payment if the vendor is not eligible.</p>
            <?php echo form_open(get_uri('vendors/review_registration'), ['id' => 'registration-review-form', 'class' => 'general-form']); ?>
            <input type="hidden" name="vendor_id" value="<?php echo (int) $vendor->id; ?>">
            <div class="form-group">
                <label for="registration-decision">Decision</label>
                <select name="decision" id="registration-decision" class="form-control" required>
                    <option value="">Choose a decision</option>
                    <option value="approve_waiver">Approve waiver and register vendor</option>
                    <option value="require_payment">Require payment</option>
                </select>
            </div>
            <div id="registration-payment-amount" class="form-group d-none">
                <label for="registration-amount">Amount (<?php echo esc($application->currency); ?>)</label>
                <input id="registration-amount" name="amount" class="form-control" type="number" min="0.001" step="0.001">
            </div>
            <div class="form-group">
                <label for="registration-note">Reason / note to applicant</label>
                <textarea id="registration-note" name="note" class="form-control" maxlength="2000"></textarea>
            </div>
            <button class="btn btn-primary" type="submit">Save registration decision</button>
            <?php echo form_close(); ?>
            <script>
                $(function () {
                    $('#registration-decision').on('change', function () {
                        var payment = this.value === 'require_payment';
                        $('#registration-payment-amount').toggleClass('d-none', !payment);
                        $('#registration-amount').prop('required', payment).prop('disabled', !payment);
                        $('#registration-note').prop('required', payment);
                    }).trigger('change');
                    $('#registration-review-form').appForm({isModal: false, onSuccess: function () { window.location.reload(); }});
                });
            </script>
        <?php elseif ($application->status === 'pending_payment'): ?>
            <p>The applicant can sign in and pay. Registration completes automatically after the bank verifies payment.</p>
        <?php endif; ?>
    </div>
</div>

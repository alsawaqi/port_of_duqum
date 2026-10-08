<div id="tender-audience-preview" class="card border mt15 mb15" style="display:none;">
    <div class="card-body">
        <h5><?php echo app_lang('tender_audience_title'); ?></h5>
        <p class="text-muted"><?php echo app_lang('tender_audience_help'); ?></p>
        <p id="tender-audience-status" role="status" aria-live="polite"></p>
        <div class="table-responsive" style="max-height:320px; overflow:auto;">
            <table class="table table-striped mb0">
                <thead><tr>
                    <th><?php echo app_lang('vendor_name'); ?></th>
                    <th><?php echo app_lang('cr_number'); ?></th>
                    <th><?php echo app_lang('vendor_group'); ?></th>
                    <th><?php echo app_lang('vendor_grade'); ?></th>
                    <th><?php echo app_lang('tender_audience_source'); ?></th>
                </tr></thead>
                <tbody id="tender-audience-rows"></tbody>
            </table>
        </div>
        <div class="d-flex flex-wrap align-items-center gap-2 mt10">
            <button type="button" class="btn btn-default btn-sm" id="tender-audience-prev"><?php echo app_lang('previous'); ?></button>
            <span id="tender-audience-page"></span>
            <button type="button" class="btn btn-default btn-sm" id="tender-audience-next"><?php echo app_lang('next'); ?></button>
        </div>
        <small class="text-muted"><?php echo app_lang('tender_audience_refresh_note'); ?></small>
    </div>
</div>
<script>
$(function () {
    var $form = $("#tender-procurement-form"), $preview = $("#tender-audience-preview");
    var page = 1, total = 0, request = null, revision = 0, timer = null;
    var messages = <?php echo json_encode(array_combine(
        ['loading', 'count', 'empty', 'failed', 'extra', 'matched'],
        array_map('app_lang', ['tender_audience_loading', 'tender_audience_count', 'tender_audience_empty', 'tender_audience_failed', 'tender_audience_extra', 'tender_audience_matched'])
    ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    function refresh(reset) {
        if (reset) page = 1;
        var currentRevision = ++revision;
        if (request) request.abort();
        var combined = $form.find('#target_mode').val() === 'combined';
        $preview.toggle(combined);
        if (!combined) return;
        var values = {page: page};
        ['vendor_group_id', 'vendor_grade_id', 'vendor_category_id', 'vendor_sub_category_id', 'tender_id', 'tender_request_id'].forEach(function (name) {
            values[name] = $form.find('[name="' + name + '"]').val() || '';
        });
        values.specific_vendor_ids = $form.find('[name="specific_vendor_ids[]"]').map(function () { return this.value; }).get();
        $('#tender-audience-status').removeClass('text-danger').text(messages.loading);
        $('#tender-audience-rows').empty();
        $('#tender-audience-prev, #tender-audience-next').prop('disabled', true);
        $('#tender-audience-page').empty();
        request = $.getJSON(<?php echo json_encode(get_uri('tender_procurement_inbox/preview_vendor_selection')); ?>, values)
            .done(function (result) {
                if (currentRevision !== revision) return;
                if (!result.success) {
                    $('#tender-audience-status').addClass('text-danger').text(result.message || messages.failed);
                    return;
                }
                total = result.total;
                $('#tender-audience-status').text(total ? messages.count.replace('{count}', total) : messages.empty);
                result.vendors.forEach(function (vendor) {
                    var $row = $('<tr>');
                    [vendor.name, vendor.cr_number, vendor.vendor_group, vendor.grade, vendor.explicit ? messages.extra : messages.matched].forEach(function (value) {
                        $('<td>').text(value || '-').appendTo($row);
                    });
                    $row.appendTo('#tender-audience-rows');
                });
                $('#tender-audience-page').text(page + ' / ' + Math.max(1, Math.ceil(total / 50)));
                $('#tender-audience-prev').prop('disabled', page <= 1);
                $('#tender-audience-next').prop('disabled', page * 50 >= total);
            }).fail(function (_, status) {
                if (status !== 'abort' && currentRevision === revision) $('#tender-audience-status').addClass('text-danger').text(messages.failed);
            });
    }
    function schedule() { clearTimeout(timer); timer = setTimeout(function () { refresh(true); }, 200); }
    $form.find('#target_mode, #vendor_group_id, #vendor_grade_id, #vendor_category_id, #vendor_sub_category_id').on('change', schedule);
    new MutationObserver(schedule).observe($form.find('#selected-vendor-tags')[0], {childList: true});
    $('#tender-audience-prev').on('click', function () { page--; refresh(false); });
    $('#tender-audience-next').on('click', function () { page++; refresh(false); });
    schedule();
});
</script>

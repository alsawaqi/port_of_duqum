<script>
(function ($) {
    // Headers also cover JSON and multipart requests. No cross-origin token
    // disclosure and no automatic retry of a state-changing request.
    $.ajaxPrefilter(function (options, originalOptions, xhr) {
        var target;
        try { target = new URL(options.url || location.href, location.href); } catch (e) { return; }
        if (target.origin !== location.origin || /^(GET|HEAD|OPTIONS)$/i.test(options.type || 'GET')) { return; }
        xhr.setRequestHeader(<?php echo json_encode(config('Security')->headerName); ?>, AppHelper.csrfHash);
    });
    $(document).off('ajaxError.podCsrf').on('ajaxError.podCsrf', function (event, xhr) {
        var result = xhr.responseJSON;
        if (xhr.status === 403 && result && result.csrf_expired && window.appAlert) {
            appAlert.error(result.message, {duration: 10000});
        }
    });
})(jQuery);
</script>

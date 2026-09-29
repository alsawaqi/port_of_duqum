(function () {
    'use strict';
    window.podScrollToFormStep = function (panel, invalid) {
        if (!panel) { return; }
        requestAnimationFrame(function () {
            var target = invalid ? panel.querySelector('.has-error, .is-invalid, [aria-invalid="true"], input.error, select.error, textarea.error') : null;
            var hasError = !!target;
            target = target || panel;
            target.style.scrollMarginTop = '96px';
            target.scrollIntoView({block: 'start', inline: 'nearest', behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth'});
            var focus = hasError ? target.querySelector('input:not([type="hidden"]), select, textarea, button') : panel.querySelector('h2, h3, h4, h5, h6, legend');
            focus = focus || (hasError && target.matches('input,select,textarea') ? target : (!hasError ? panel : null));
            if (focus && focus.getClientRects().length) {
                if (!focus.matches('input,select,textarea,button')) { focus.setAttribute('tabindex', '-1'); }
                focus.focus({preventScroll: true});
            }
        });
    };
    // Legacy staff/import wizards update their active tab before this bubbles.
    document.addEventListener('click', function (event) {
        var next = event.target.closest('#form-next, #form-previous');
        if (!next || next.disabled) { return; }
        var form = next.closest('form') || next.closest('.modal-content');
        if (!form) { return; }
        requestAnimationFrame(function () {
            var panel = form.querySelector('.tab-pane.active, .tab-content > .active') || form.querySelector('.modal-body') || form;
            window.podScrollToFormStep(panel, !!panel.querySelector('.has-error, [aria-invalid="true"], input.error'));
        });
    });
})();

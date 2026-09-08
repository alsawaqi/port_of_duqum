<aside class="registration-guide">
    <nav aria-label="<?php echo app_lang('guest_form_sections'); ?>">
        <p class="registration-guide-label"><?php echo app_lang('guest_form_sections'); ?></p>
        <?php foreach ($registration_sections as $index => $section) { ?>
            <a href="#<?php echo esc($section[0]); ?>"><span><?php echo sprintf('%02d', $index + 1); ?></span><?php echo app_lang($section[1]); ?></a>
        <?php } ?>
    </nav>
    <p class="registration-guide-note"><?php echo app_lang('guest_required_fields'); ?></p>
</aside>

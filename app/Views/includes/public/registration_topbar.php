<link rel="stylesheet" href="<?php echo base_url('assets/css/guest-registration.css') . '?v=' . filemtime(FCPATH . 'assets/css/guest-registration.css'); ?>">
<header class="registration-topbar">
    <div class="registration-topbar-inner">
        <a class="registration-brand" href="<?php echo get_uri('signin'); ?>">
            <img src="<?php echo base_url('assets/images/port-duqum-signin-logo.png'); ?>" alt="" width="42" height="42">
            <span>Port of Duqm<small><?php echo app_lang('guest_portal_label'); ?></small></span>
        </a>
        <a class="registration-signin" href="<?php echo get_uri('signin'); ?>"><?php echo app_lang('signin'); ?> <i data-feather="arrow-right" class="icon-16"></i></a>
    </div>
</header>

<?php
/** @var string $active dashboard|requests|scan */
$active = $active ?? "requests";
$items = [
    "dashboard" => [
        "uri" => "gate_pass_security_inbox/dashboard",
        "icon" => "activity",
        "label" => app_lang("gate_pass_security_dashboard"),
    ],
    "requests" => [
        "uri" => "gate_pass_security_inbox",
        "icon" => "list",
        "label" => app_lang("gate_pass_security_nav_requests"),
    ],
    "scan" => [
        "uri" => "gate_pass_security_inbox/scan",
        "icon" => "camera",
        "label" => app_lang("gate_pass_security_scan_qr"),
    ],
];
?>
<nav class="gp-sec-hub-nav" aria-label="<?php echo app_lang('gate_pass_security_requests'); ?>">
    <div class="gp-sec-hub-nav-inner">
        <?php foreach ($items as $key => $item):
            $is_on = ($active === $key);
            ?>
            <a href="<?php echo get_uri($item["uri"]); ?>"
               class="gp-sec-hub-nav-item <?php echo $is_on ? "is-active" : ""; ?>">
                <i data-feather="<?php echo esc($item["icon"]); ?>" class="icon-18"></i>
                <span><?php echo esc($item["label"]); ?></span>
            </a>
        <?php endforeach; ?>
    </div>
</nav>
<style>
.gp-sec-hub-page-inner { max-width: 1280px; margin: 0 auto; }
.gp-sec-page-title { margin: 0 0 6px; font-size: 1.35rem; font-weight: 800; color: #0f172a; letter-spacing: -.02em; }
.gp-sec-page-desc { font-size: 14px; max-width: 720px; line-height: 1.5; }
.gp-sec-hub-nav { margin-bottom: 16px; }
.gp-sec-hub-nav-inner {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    padding: 6px;
    border-radius: 14px;
    border: 1px solid rgba(15, 23, 42, .08);
    background: linear-gradient(180deg, rgba(248,250,252,.95), #fff);
    box-shadow: 0 4px 18px rgba(15, 23, 42, .05);
}
.gp-sec-hub-nav-item {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 16px;
    border-radius: 11px;
    font-size: 13px;
    font-weight: 650;
    color: #475569;
    text-decoration: none;
    border: 1px solid transparent;
    transition: background .15s ease, color .15s ease, border-color .15s ease, box-shadow .15s ease;
}
.gp-sec-hub-nav-item:hover {
    background: rgba(15, 23, 42, .04);
    color: #0f172a;
    border-color: rgba(15, 23, 42, .08);
}
.gp-sec-hub-nav-item.is-active {
    background: linear-gradient(135deg, #1d4ed8, #2563eb);
    color: #fff;
    border-color: rgba(37, 99, 235, .35);
    box-shadow: 0 6px 20px rgba(37, 99, 235, .28);
}
.gp-sec-hub-nav-item.is-active:hover { color: #fff; filter: brightness(1.03); }
@media (max-width: 576px) {
    .gp-sec-hub-nav-item { flex: 1 1 100%; justify-content: center; }
}
</style>

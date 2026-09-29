<?php
$title = $title ?? "";
$subtitle = $subtitle ?? "";
$icon = $icon ?? "grid";
$breadcrumbs = $breadcrumbs ?? [];
$actions = $actions ?? "";
$home_label = $home_label ?? app_lang("home");
if (!$home_label || $home_label === "default_lang.home") {
    $home_label = "Home";
}
$home_url = $home_url ?? get_uri("dashboard");
?>

<div class="pod-page-header">
    <div class="pod-page-heading">
        <span class="pod-page-icon" aria-hidden="true">
            <i data-feather="<?php echo esc($icon); ?>" class="icon-18"></i>
        </span>
        <div class="pod-page-title-copy">
            <h1><?php echo esc($title); ?></h1>
            <?php if ($subtitle) { ?>
                <p><?php echo esc($subtitle); ?></p>
            <?php } ?>
        </div>
    </div>

    <div class="pod-page-header-right">
        <nav class="pod-page-breadcrumb" aria-label="Breadcrumb">
            <a href="<?php echo esc($home_url, "attr"); ?>"><?php echo esc($home_label); ?></a>
            <?php foreach ($breadcrumbs as $breadcrumb) {
                $label = is_array($breadcrumb) ? get_array_value($breadcrumb, "label") : $breadcrumb;
                $url = is_array($breadcrumb) ? get_array_value($breadcrumb, "url") : "";
                if (!$label) {
                    continue;
                }
            ?>
                <i data-feather="chevron-right" class="icon-14" aria-hidden="true"></i>
                <?php if ($url) { ?>
                    <a href="<?php echo esc($url, "attr"); ?>"><?php echo esc($label); ?></a>
                <?php } else { ?>
                    <span><?php echo esc($label); ?></span>
                <?php } ?>
            <?php } ?>
        </nav>

        <?php if ($actions) { ?>
            <div class="pod-page-actions">
                <?php echo $actions; ?>
            </div>
        <?php } ?>
    </div>
</div>

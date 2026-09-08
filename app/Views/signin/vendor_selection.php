<?php
$memberships = $memberships ?? [];
$is_switching_vendor = !empty($is_switching_vendor);
?>

<div class="pod-signin-card pod-vendor-select-card">
    <div class="pod-signin-head">
        <span class="pod-signin-logo">
            <img src="<?php echo base_url("assets/images/port-duqum-signin-logo.png"); ?>" alt="Port of Duqm" />
        </span>
        <div>
            <span class="pod-signin-kicker">Vendor Portal</span>
            <h2><?php echo $is_switching_vendor ? "Switch vendor profile" : "Choose vendor profile"; ?></h2>
            <p>Select the company and CR you want to work with.</p>
        </div>
    </div>

    <?php echo form_open("signin/select_vendor", ["id" => "vendor-selection-form"]); ?>
        <div class="pod-vendor-options" role="radiogroup" aria-label="Available vendor profiles">
            <?php foreach ($memberships as $index => $membership) {
                $vendor_id = (int) ($membership->vendor_id ?? 0);
                $input_id = "vendor-context-" . $vendor_id;
                $cr_number = trim((string) ($membership->cr_number ?? ""));
                $role_name = trim((string) ($membership->vendor_role_name ?? ""));
                ?>
                <label class="pod-vendor-option" for="<?php echo esc($input_id); ?>">
                    <input
                        type="radio"
                        name="vendor_id"
                        id="<?php echo esc($input_id); ?>"
                        value="<?php echo $vendor_id; ?>"
                        <?php echo $index === 0 ? "checked" : ""; ?>
                        required>
                    <span class="pod-vendor-option-check"><i data-feather="check" class="icon-16"></i></span>
                    <span class="pod-vendor-option-copy">
                        <strong><?php echo esc($membership->vendor_name ?? "Vendor"); ?></strong>
                        <span>CR: <?php echo esc($cr_number !== "" ? $cr_number : "Not provided"); ?></span>
                        <?php if ($role_name !== "") { ?>
                            <small><?php echo esc($role_name); ?></small>
                        <?php } ?>
                    </span>
                </label>
            <?php } ?>
        </div>

        <button class="w-100 btn btn-lg btn-primary pod-auth-submit" type="submit">
            Continue to vendor portal
        </button>
    <?php echo form_close(); ?>

    <div class="pod-signin-links">
        <?php if ($is_switching_vendor) { ?>
            <?php echo anchor("vendor_portal", "Cancel"); ?>
        <?php } else { ?>
            <?php echo form_open("signin/sign_out", ["class" => "pod-inline-signout-form"]); ?>
                <button type="submit" class="pod-link-button">Sign in with another account</button>
            <?php echo form_close(); ?>
        <?php } ?>
    </div>
</div>

<style>
    .pod-vendor-select-card { max-width: 620px; }
    .pod-vendor-options { display: grid; gap: 12px; margin: 24px 0; max-height: 380px; overflow: auto; }
    .pod-vendor-option { position: relative; display: flex; gap: 14px; align-items: center; padding: 16px; border: 1px solid #dbe3ec; border-radius: 14px; background: #fff; cursor: pointer; transition: .18s ease; }
    .pod-vendor-option:hover { border-color: #6f8fb2; box-shadow: 0 8px 24px rgba(21, 45, 72, .08); }
    .pod-vendor-option:has(input:checked) { border-color: #145b8f; background: #f3f8fc; box-shadow: 0 0 0 2px rgba(20, 91, 143, .12); }
    .pod-vendor-option input { position: absolute; opacity: 0; pointer-events: none; }
    .pod-vendor-option-check { display: grid; place-items: center; width: 30px; height: 30px; border: 2px solid #bcc9d6; border-radius: 50%; color: transparent; flex: 0 0 auto; }
    .pod-vendor-option:has(input:checked) .pod-vendor-option-check { border-color: #145b8f; background: #145b8f; color: #fff; }
    .pod-vendor-option-copy { display: grid; gap: 3px; min-width: 0; }
    .pod-vendor-option-copy strong { color: #112a42; font-size: 16px; }
    .pod-vendor-option-copy span { color: #52677c; }
    .pod-vendor-option-copy small { color: #7a8b9d; text-transform: uppercase; letter-spacing: .04em; }
    .pod-inline-signout-form { display: inline; }
    .pod-link-button { appearance: none; border: 0; background: transparent; color: #145b8f; padding: 0; cursor: pointer; text-decoration: underline; }
</style>

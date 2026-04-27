<?php
$model_info = $model_info ?? null;
$is_edit = !empty($model_info->id);
$field_id = "operational-user-" . uniqid();
$password_required = $is_edit ? "false" : "true";
?>

<div class="operational-user-identity" id="<?php echo $field_id; ?>" data-is-edit="<?php echo $is_edit ? "1" : "0"; ?>">
    <input type="hidden" name="existing_user_id" value="" class="js-existing-user-id" />

    <div class="form-group">
        <div class="row">
            <label class="col-md-3" for="<?php echo $field_id; ?>-email"><?php echo app_lang("email"); ?></label>
            <div class="col-md-9">
                <?php
                echo form_input([
                    "id" => $field_id . "-email",
                    "name" => "email",
                    "type" => "email",
                    "value" => (string) ($model_info->email ?? ""),
                    "class" => "form-control js-operational-email",
                    "placeholder" => app_lang("email"),
                    "data-rule-required" => true,
                    "data-rule-email" => true,
                    "data-msg-required" => app_lang("field_required"),
                    "data-msg-email" => app_lang("enter_valid_email"),
                ]);
                ?>
                <small class="text-muted js-operational-user-hint">
                    <?php echo $is_edit ? app_lang("edit_assignment_user_hint") : app_lang("new_user_required_assignment_hint"); ?>
                </small>
            </div>
        </div>
    </div>

    <div class="alert alert-info js-existing-user-notice hide">
        <?php echo app_lang("existing_user_found_assignment_hint"); ?>
    </div>

    <div class="alert alert-danger js-non-staff-user-notice hide">
        <?php echo app_lang("email_belongs_to_non_staff_user"); ?>
    </div>

    <div class="js-new-user-fields">
        <div class="form-group">
            <div class="row">
                <label class="col-md-3" for="<?php echo $field_id; ?>-first-name"><?php echo app_lang("first_name"); ?></label>
                <div class="col-md-9">
                    <?php
                    echo form_input([
                        "id" => $field_id . "-first-name",
                        "name" => "first_name",
                        "value" => (string) ($model_info->first_name ?? ""),
                        "class" => "form-control js-new-user-required",
                        "placeholder" => app_lang("first_name"),
                        "data-rule-required" => $is_edit ? false : true,
                        "data-msg-required" => app_lang("field_required"),
                    ]);
                    ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label class="col-md-3" for="<?php echo $field_id; ?>-last-name"><?php echo app_lang("last_name"); ?></label>
                <div class="col-md-9">
                    <?php
                    echo form_input([
                        "id" => $field_id . "-last-name",
                        "name" => "last_name",
                        "value" => (string) ($model_info->last_name ?? ""),
                        "class" => "form-control js-new-user-required",
                        "placeholder" => app_lang("last_name"),
                        "data-rule-required" => $is_edit ? false : true,
                        "data-msg-required" => app_lang("field_required"),
                    ]);
                    ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label class="col-md-3" for="<?php echo $field_id; ?>-phone"><?php echo app_lang("phone"); ?></label>
                <div class="col-md-9">
                    <?php
                    echo form_input([
                        "id" => $field_id . "-phone",
                        "name" => "phone",
                        "value" => (string) ($model_info->phone ?? ""),
                        "class" => "form-control",
                        "placeholder" => app_lang("phone"),
                    ]);
                    ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label class="col-md-3" for="<?php echo $field_id; ?>-password"><?php echo app_lang("password"); ?></label>
                <div class="col-md-9">
                    <?php
                    echo form_password([
                        "id" => $field_id . "-password",
                        "name" => "password",
                        "class" => "form-control js-operational-password js-new-user-required",
                        "placeholder" => app_lang("password"),
                        "data-rule-required" => $is_edit ? false : true,
                        "data-rule-noSpacePassword" => true,
                        "data-msg-required" => app_lang("field_required"),
                    ]);
                    ?>
                    <small class="text-muted"><?php echo $is_edit ? app_lang("leave_blank_to_keep") : app_lang("password_no_spaces"); ?></small>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label class="col-md-3" for="<?php echo $field_id; ?>-password-confirm"><?php echo app_lang("password_confirm"); ?></label>
                <div class="col-md-9">
                    <?php
                    echo form_password([
                        "id" => $field_id . "-password-confirm",
                        "name" => "password_confirm",
                        "class" => "form-control js-operational-password-confirm js-new-user-required",
                        "placeholder" => app_lang("password_confirm"),
                        "data-rule-required" => $is_edit ? false : true,
                        "data-rule-equalTo" => "#" . $field_id . "-password",
                        "data-msg-required" => app_lang("field_required"),
                        "data-msg-equalTo" => app_lang("passwords_do_not_match"),
                    ]);
                    ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function () {
    if ($.validator && !$.validator.methods.noSpacePassword) {
        $.validator.addMethod("noSpacePassword", function (value) {
            return !value || !/\s/.test(value);
        }, "<?php echo app_lang("password_no_spaces"); ?>");
    }

    var $block = $("#<?php echo $field_id; ?>");
    var $email = $block.find(".js-operational-email");
    var $newFields = $block.find(".js-new-user-fields");
    var $requiredFields = $block.find(".js-new-user-required");
    var $existingNotice = $block.find(".js-existing-user-notice");
    var $nonStaffNotice = $block.find(".js-non-staff-user-notice");
    var $existingUserId = $block.find(".js-existing-user-id");
    var isEdit = $block.data("is-edit") === 1;
    var lookupTimer = null;

    function setRequired($field, required) {
        if (required) {
            $field.attr("data-rule-required", "true");
            if ($field.rules) {
                $field.rules("add", {required: true});
            }
        } else {
            $field.removeAttr("data-rule-required");
            if ($field.rules) {
                $field.rules("remove", "required");
            }
        }
    }

    function setNewUserMode() {
        $existingUserId.val("");
        $existingNotice.addClass("hide");
        $nonStaffNotice.addClass("hide");
        $newFields.removeClass("hide");

        if (!isEdit) {
            $requiredFields.each(function () {
                setRequired($(this), true);
            });
        }
    }

    function setExistingUserMode(user) {
        $existingUserId.val(user.id || "");
        $existingNotice.removeClass("hide");
        $nonStaffNotice.addClass("hide");

        if (!isEdit) {
            $newFields.addClass("hide");
            $requiredFields.each(function () {
                setRequired($(this), false);
            });
        }
    }

    function setNonStaffMode() {
        $existingUserId.val("");
        $existingNotice.addClass("hide");
        $nonStaffNotice.removeClass("hide");
        $newFields.addClass("hide");
        $requiredFields.each(function () {
            setRequired($(this), false);
        });
    }

    function lookupEmail() {
        var email = $.trim($email.val() || "");
        if (!email || email.indexOf("@") < 0) {
            setNewUserMode();
            return;
        }

        $.ajax({
            url: "<?php echo_uri("operational_user_lookup/check_email"); ?>",
            type: "POST",
            dataType: "json",
            data: {email: email},
            success: function (response) {
                if (response && response.exists && response.user) {
                    if (response.user.can_assign) {
                        setExistingUserMode(response.user);
                    } else {
                        setNonStaffMode();
                    }
                    return;
                }

                setNewUserMode();
            },
            error: function () {
                setNewUserMode();
            }
        });
    }

    $email.on("input", function () {
        clearTimeout(lookupTimer);
        lookupTimer = setTimeout(lookupEmail, 450);
    });

    $email.on("blur", lookupEmail);

    $block.find(".js-operational-password").on("input", function () {
        var hasPassword = !!$(this).val();
        setRequired($block.find(".js-operational-password-confirm"), hasPassword || "<?php echo $password_required; ?>" === "true");
    });
});
</script>

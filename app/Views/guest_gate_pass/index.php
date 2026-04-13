<div id="page-content" class="page-wrapper clearfix gv-page">
    <style>
        /* ===== Guest Gate Pass - Pro UI ===== */
        .gv-page {
            --gv-radius: 18px;
            --gv-shadow: 0 10px 30px rgba(16, 24, 40, .08);
            --gv-border: rgba(15, 23, 42, .08);
        }
        .gv-shell { max-width: 920px; margin: 0 auto; }
        .gv-card {
            border: 1px solid var(--gv-border);
            border-radius: var(--gv-radius);
            box-shadow: var(--gv-shadow);
            overflow: hidden;
            background: #fff;
            transform: translateY(12px);
            opacity: 0;
            transition: transform .6s ease, opacity .6s ease;
        }
        .gv-ready .gv-card { transform: translateY(0); opacity: 1; }

        .gv-hero {
            padding: 26px 26px 18px;
            background:
                radial-gradient(900px 260px at 20% 0%, rgba(99, 102, 241, .18), transparent 60%),
                radial-gradient(700px 220px at 85% 30%, rgba(34, 197, 94, .14), transparent 55%),
                linear-gradient(180deg, rgba(15, 23, 42, .04), rgba(15, 23, 42, 0));
            border-bottom: 1px solid var(--gv-border);
        }
        .gv-hero h1 { margin: 0; font-size: 22px; font-weight: 800; color: #0f172a; }
        .gv-hero p { margin: 6px 0 0; color: #475569; font-size: 13px; }

        .gv-badges { margin-top: 14px; display: flex; flex-wrap: wrap; gap: 8px; }
        .gv-badge {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 7px 10px;
            border-radius: 999px;
            border: 1px solid var(--gv-border);
            background: rgba(255, 255, 255, .6);
            font-size: 12px;
            color: #334155;
            backdrop-filter: blur(6px);
        }

        .gv-body { padding: 22px 26px 10px; }

        .gv-section {
            border: 1px solid var(--gv-border);
            border-radius: 16px;
            padding: 16px 16px 6px;
            background: #fff;
            margin-bottom: 14px;
            opacity: 0;
            transform: translateY(8px);
            transition: opacity .5s ease, transform .5s ease;
        }
        .gv-ready .gv-section { opacity: 1; transform: translateY(0); }
        .gv-ready .gv-section:nth-child(1){ transition-delay: .05s; }
        .gv-ready .gv-section:nth-child(2){ transition-delay: .10s; }

        .gv-section-title { display:flex; align-items:center; justify-content:space-between; margin-bottom:10px; }
        .gv-section-title h5 { margin:0; font-size:14px; font-weight:800; color:#0f172a; display:inline-flex; align-items:center; gap:10px; }
        .gv-section-title .dot { width:9px; height:9px; border-radius:999px; background: rgba(99,102,241,.9); box-shadow:0 0 0 4px rgba(99,102,241,.16); }
        .gv-help { margin:0; color:#64748b; font-size:12px; }

        .gv-form .form-group { margin-bottom: 14px; }
        .gv-form label { font-weight: 700; color: #0f172a; font-size: 13px; }
        .gv-form .form-control {
            border-radius: 12px !important;
            border: 1px solid rgba(15, 23, 42, .14) !important;
            height: 44px;
            transition: box-shadow .2s ease, border-color .2s ease;
        }
        .gv-form .form-control:focus {
            border-color: rgba(99,102,241,.55) !important;
            box-shadow: 0 0 0 4px rgba(99,102,241,.15) !important;
        }
        .gv-phone-split select.form-control {
            font-size: 13px;
            padding-right: 28px;
        }

        .gv-footer {
            padding: 14px 26px;
            border-top: 1px solid var(--gv-border);
            background: rgba(255, 255, 255, .78);
            backdrop-filter: blur(10px);
            display:flex; align-items:center; justify-content:space-between; gap:12px;
        }
        .gv-footer .note { font-size: 12px; color: #64748b; margin: 0; }

        .gv-btn {
            border-radius: 12px;
            height: 44px;
            padding: 0 16px;
            font-weight: 800;
            display:inline-flex; align-items:center; gap:10px;
        }
        .gv-btn .spinner {
            width:16px; height:16px;
            border-radius:999px;
            border: 2px solid rgba(255,255,255,.55);
            border-top-color: rgba(255,255,255,1);
            animation: gvSpin .8s linear infinite;
            display:none;
        }
        .gv-submitting .gv-btn .spinner { display:inline-block; }
        .gv-submitting .gv-btn { pointer-events:none; opacity:.95; }
        @keyframes gvSpin { to { transform: rotate(360deg); } }

        .is-invalid { border-color: rgba(239, 68, 68, .7) !important; box-shadow: 0 0 0 4px rgba(239,68,68,.12) !important; }
    </style>

    <div class="gv-shell">
        <div class="card gv-card">
            <div class="gv-hero">
                <h1>Gate Pass Account Application</h1>
                <p>Apply for a visitor account to request Gate Pass access. Your account will be reviewed and activated by our team.</p>
                <p class="gv-help" style="margin-top:10px;"><?php echo app_lang("gate_pass_signup_vs_gate_pass_account"); ?></p>

                <div class="gv-badges">
                    <span class="gv-badge"><i data-feather="shield"></i> Secure submission</span>
                    <span class="gv-badge"><i data-feather="zap"></i> Fast approval workflow</span>
                    <span class="gv-badge"><i data-feather="check-circle"></i> Smart validation</span>
                </div>
            </div>

            <?php echo form_open(
                get_uri("guest_gate_pass/save"),
                [
                    "id"    => "guest-gp-form",
                    "class" => "general-form gv-form",
                    "role"  => "form"
                ]
            ); ?>

            <div class="gv-body">

                <div class="gv-section">
                    <div class="gv-section-title">
                        <h5><span class="dot"></span> Account Information</h5>
                        <p class="gv-help">Basic details used for portal login</p>
                    </div>

                    <div class="row">
                        <div class="col-lg-6">
                            <div class="form-group">
                                <label>First Name <span class="text-danger">*</span></label>
                                <input name="first_name" class="form-control" required>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="form-group">
                                <label>Last Name</label>
                                <input name="last_name" class="form-control">
                            </div>
                        </div>

                        <div class="col-lg-12">
                            <div class="form-group">
                                <label>Email <span class="text-danger">*</span></label>
                                <input name="email" id="email" class="form-control" required type="email">
                                <div id="email-error" class="alert alert-danger mt10 d-none"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="gv-section">
                    <div class="gv-section-title">
                        <h5><span class="dot"></span> Contact & Security</h5>
                        <p class="gv-help">OTP channel + contact details</p>
                    </div>

                    <div class="row">
                        <?php
                        $intl_dial_codes = $intl_dial_codes ?? [];
                        $default_dial = "+968";
                        ?>
                        <div class="col-lg-12">
                            <div class="form-group">
                                <label>Phone Number <span class="text-danger">*</span></label>
                                <div class="row gv-phone-split">
                                    <div class="col-sm-5 col-md-4 mb10 mb-sm-0">
                                        <select name="phone_country_code" id="phone_country_code" class="form-control" required title="Country code">
                                            <?php foreach ($intl_dial_codes as $d) {
                                                $sel = ($d["code"] === $default_dial) ? " selected" : "";
                                                echo "<option value=\"" . esc($d["code"]) . "\"{$sel}>" . esc($d["country"] . " (" . $d["code"] . ")") . "</option>\n";
                                            } ?>
                                        </select>
                                    </div>
                                    <div class="col-sm-7 col-md-8">
                                        <input name="phone_local" id="phone_local" class="form-control gv-digits-only" required type="text" inputmode="numeric" pattern="[0-9]{4,15}" maxlength="15" autocomplete="tel-national" placeholder="71234567" title="Numbers only (no spaces or symbols)">
                                    </div>
                                </div>
                                <small class="text-muted">Choose your country code, then enter your mobile number using digits only (no spaces or symbols), without the leading zero.</small>
                            </div>
                        </div>

                        <div class="col-lg-12">
                            <div class="form-group">
                                <label>Emergency Number <span class="text-danger">*</span></label>
                                <div class="row gv-phone-split">
                                    <div class="col-sm-5 col-md-4 mb10 mb-sm-0">
                                        <select name="emergency_country_code" id="emergency_country_code" class="form-control" required title="Country code">
                                            <?php foreach ($intl_dial_codes as $d) {
                                                $sel = ($d["code"] === $default_dial) ? " selected" : "";
                                                echo "<option value=\"" . esc($d["code"]) . "\"{$sel}>" . esc($d["country"] . " (" . $d["code"] . ")") . "</option>\n";
                                            } ?>
                                        </select>
                                    </div>
                                    <div class="col-sm-7 col-md-8">
                                        <input name="emergency_local" id="emergency_local" class="form-control gv-digits-only" required type="text" inputmode="numeric" pattern="[0-9]{4,15}" maxlength="15" autocomplete="tel-national" placeholder="71234567" title="Numbers only (no spaces or symbols)">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-4">
                            <div class="form-group">
                                <label>OTP Channel <span class="text-danger">*</span></label>
                                <select name="otp_channel" class="form-control">
                                    <option value="phone">Phone</option>
                                    <option value="email">Email</option>
                                </select>
                            </div>
                        </div>

                        <div class="col-lg-12">
                            <div class="form-group">
                                <label>Password <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="password" id="password" name="password" class="form-control" autocomplete="new-password">
                                    <button class="btn btn-outline-secondary" type="button" id="toggle-password" style="border-radius: 12px;">
                                        <i data-feather="eye" class="icon-16"></i>
                                    </button>
                                </div>
                                <small class="text-muted">Required only when this email is not already registered. Minimum 8 characters recommended.</small>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if (get_setting("re_captcha_secret_key")) { ?>
                <div class="gv-section">
                    <div class="gv-section-title">
                        <h5><span class="dot"></span> Verification</h5>
                        <p class="gv-help"><?php echo app_lang("gate_pass_signup_recaptcha_hint"); ?></p>
                    </div>
                    <?php echo view("signin/re_captcha"); ?>
                </div>
                <?php } ?>

            </div>

            <div class="gv-footer">
                <p class="note mb0">
                    By submitting, you confirm the information is accurate and agree to our review process.
                </p>

                <button type="submit" class="btn btn-primary gv-btn">
                    <span class="spinner"></span>
                    <span class="btn-text"><?php echo app_lang('submit'); ?></span>
                    <i data-feather="arrow-right" class="icon-16"></i>
                </button>
            </div>

            <?php echo form_close(); ?>
        </div>
    </div>
</div>

<script>
$(document).ready(function () {

    setTimeout(function () {
        $(".gv-page").addClass("gv-ready");
        if (window.feather) feather.replace();
    }, 60);

    function bindDigitsOnly($el) {
        var strip = function () {
            var v = $el.val().replace(/\D/g, "");
            if (v !== $el.val()) {
                $el.val(v);
            }
        };
        $el.on("input change blur", strip);
        $el.on("paste", function (e) {
            e.preventDefault();
            var t = (e.originalEvent || e).clipboardData.getData("text") || "";
            $el.val(t.replace(/\D/g, "").slice(0, 15));
        });
    }
    bindDigitsOnly($("#phone_local"));
    bindDigitsOnly($("#emergency_local"));

    $("#toggle-password").on("click", function() {
        const $pw = $("#password");
        const isText = $pw.attr("type") === "text";
        $pw.attr("type", isText ? "password" : "text");
        $(this).find("svg").remove();
        $(this).append(`<i data-feather="${isText ? "eye" : "eye-off"}" class="icon-16"></i>`);
        if (window.feather) feather.replace();
    });

    function clearErrors() {
        $("#email-error").addClass("d-none").text("");
        $("#email").removeClass("is-invalid");
    }

    function showError(field, message) {
        if (field === "email") {
            $("#email").addClass("is-invalid");
            $("#email-error").removeClass("d-none").text(message);
        }
    }

    $("#guest-gp-form").appForm({
        isModal: false,
        onSubmit: function () {
            $("#phone_local, #emergency_local").each(function () {
                var $i = $(this);
                $i.val(String($i.val() || "").replace(/\D/g, "").slice(0, 15));
            });
            clearErrors();
            $(".gv-card").addClass("gv-submitting");
            appLoader.show();
        },
        onSuccess: function (res) {
            appLoader.hide();
            $(".gv-card").removeClass("gv-submitting");

            appAlert.success(res.message || "Submitted", {duration: 10000});
            $("#guest-gp-form")[0].reset();
        },
        onError: function (result) {
            appLoader.hide();
            $(".gv-card").removeClass("gv-submitting");

            var res = (result && result.responseJSON) ? result.responseJSON : result;

            if (res && res.errors) {
                if (res.errors.email) showError("email", res.errors.email);
                return false;
            }

            appAlert.error((res && res.message) ? res.message : "Error occurred");
            return false;
        }
    });

    if (window.feather) feather.replace();
});
</script>

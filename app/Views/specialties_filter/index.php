<?php
// If controller doesn't pass it, keep filters enabled (backward compatible).
$can_filter_vendor_specialties = isset($can_filter_vendor_specialties) ? (bool) $can_filter_vendor_specialties : true;
$filter_disabled_attr = $can_filter_vendor_specialties ? "" : "disabled='disabled'";
?>

<div id="page-content" class="page-wrapper clearfix vendor-specialties-filter-page">
    <style>
        .vendor-specialties-filter-page {
            --vf-radius: 18px;
            --vf-shadow: 0 10px 30px rgba(15, 23, 42, .12);
            --vf-border: rgba(15, 23, 42, .06);
        }

        .vf-shell {
            max-width: 1200px;
            margin: 0 auto;
        }

        .vf-card {
            border-radius: var(--vf-radius);
            border: 1px solid var(--vf-border);
            box-shadow: var(--vf-shadow);
            overflow: hidden;
            background: #fff;
            transform: translateY(10px);
            opacity: 0;
            transition: transform .5s ease, opacity .5s ease;
        }

        .vf-card.is-ready {
            transform: translateY(0);
            opacity: 1;
        }

        .vf-header {
            padding: 18px 20px 14px;
            background:
                radial-gradient(600px 220px at 10% 0%, rgba(59, 130, 246, .20), transparent 60%),
                radial-gradient(600px 220px at 90% 30%, rgba(34, 197, 94, .18), transparent 60%),
                linear-gradient(180deg, rgba(15, 23, 42, .02), rgba(15, 23, 42, 0.02));
            border-bottom: 1px solid var(--vf-border);
        }

        .vf-header h1 {
            margin: 0;
            font-size: 20px;
            font-weight: 700;
            color: #0f172a;
        }

        .vf-header p {
            margin: 4px 0 0;
            font-size: 13px;
            color: #64748b;
        }

        .vf-body {
            padding: 16px 20px 18px;
        }

        .vf-filters {
            border-radius: 14px;
            border: 1px solid rgba(15, 23, 42, .08);
            background: #f9fafb;
            padding: 12px 14px;
            margin-bottom: 14px;
            display: flex;
            flex-wrap: wrap;
            gap: 10px 16px;
            align-items: flex-end;
        }

        .vf-filters .form-group {
            margin-bottom: 0;
        }

        .vf-filters label {
            font-size: 12px;
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 4px;
        }

        .vf-filters .form-control,
        .vf-filters .select2-container--default .select2-selection--single {
            border-radius: 10px !important;
            border: 1px solid rgba(15, 23, 42, .14) !important;
            height: 40px;
            font-size: 13px;
        }

        .vf-filters .select2-container--default .select2-selection--single {
            display: flex;
            align-items: center;
            padding: 0 9px;
        }

        .vf-filters .select2-container--default .select2-selection__arrow {
            height: 40px;
        }

        .vf-filters .select2-container {
            width: 100% !important;
        }

        .vf-filters .btn-refresh {
            border-radius: 10px;
            height: 40px;
            padding: 0 14px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .vf-card.pulse {
            animation: vfPulse .3s ease-in-out;
        }

        @keyframes vfPulse {
            0% {
                box-shadow: var(--vf-shadow);
            }

            50% {
                box-shadow: 0 0 0 2px rgba(59, 130, 246, .35);
            }

            100% {
                box-shadow: var(--vf-shadow);
            }
        }
    </style>

    <div class="vf-shell">
        <div class="card vf-card">
            <div class="vf-header">
                <h1><?php echo app_lang("vendor_specialties_filter"); ?></h1>
                <p><?php echo app_lang("filter_vendors_by_specialty_category"); ?></p>
            </div>

            <div class="vf-body">
                <div class="vf-filters row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="filter_category_id">
                                <?php echo app_lang("category"); ?>
                            </label>
                            <?php
                            echo form_dropdown(
                                "filter_category_id",
                                $categories_dropdown,
                                "",
                                "class='form-control select2' id='filter_category_id' $filter_disabled_attr"
                            );
                            ?>
                        </div>
                    </div>

                    <div class="col-md-4" id="sub-category-wrapper">
                        <div class="form-group">
                            <label for="filter_sub_category_id">
                                <?php echo app_lang("sub_category"); ?>
                            </label>
                            <select id="filter_sub_category_id" class="form-control" <?php echo $filter_disabled_attr; ?>>
                                <option value="">
                                    - <?php echo app_lang("select_sub_category"); ?> -
                                </option>
                            </select>
                        </div>
                    </div>

                    <div class="col-md-4 d-flex justify-content-end">
                        <button type="button" id="btn-reset-filters"
                            class="btn btn-outline-secondary btn-refresh" <?php echo $filter_disabled_attr; ?>>
                            <i data-feather="rotate-ccw" class="icon-16"></i>
                            <span><?php echo app_lang("reset_filters"); ?></span>
                        </button>
                    </div>
                </div>

                <div class="table-responsive">
                    <table id="vendor-specialties-filter-table" class="display" width="100%"></table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {
        const $page = $(".vendor-specialties-filter-page");
        const $card = $(".vf-card");
        const $category = $("#filter_category_id");
        const $subCategory = $("#filter_sub_category_id");
        const canFilter = <?php echo $can_filter_vendor_specialties ? "true" : "false"; ?>;

        if (!canFilter) {
            $category.prop("disabled", true);
            $subCategory.prop("disabled", true);
            $("#btn-reset-filters").prop("disabled", true);
        }

        // Smooth entrance
        setTimeout(function() {
            $card.addClass("is-ready");
            if (window.feather) feather.replace();
        }, 60);

        // Init Select2
        $category.select2({
            width: "100%",
            allowClear: true
        });
        $subCategory.select2({
            width: "100%",
            allowClear: true
        });

        // Load sub-categories when category changes
        $category.on("change", function() {
            if (!canFilter) {
                return;
            }
            let id = $(this).val() || 0;

            // Reset sub-category
            $subCategory.html("<option value=''>- <?php echo app_lang('select_sub_category'); ?> -</option>")
                .trigger("change");

            if (id) {
                $("#sub-category-wrapper").addClass("loading");

                $("#filter_sub_category_id").load(
                    "<?php echo get_uri('vendors/get_vendor_sub_categories'); ?>/" + id,
                    function() {
                        $("#sub-category-wrapper").removeClass("loading");
                        $subCategory.select2({
                            width: "100%",
                            allowClear: true
                        });
                        reloadTable();
                    }
                );
            } else {
                reloadTable();
            }
        });

        $subCategory.on("change", function() {
            if (!canFilter) {
                return;
            }
            reloadTable();
        });

        $("#btn-reset-filters").on("click", function() {
            if (!canFilter) {
                return;
            }
            $category.val("").trigger("change");
            $subCategory.html("<option value=''>- <?php echo app_lang('select_sub_category'); ?> -</option>")
                .trigger("change");
            reloadTable();
        });

        // DataTable – we use DataTables directly; appTable JS is already loaded with the theme
        const dt = $("#vendor-specialties-filter-table").DataTable({
            processing: true,
            serverSide: false,
            ajax: {
                url: "<?php echo get_uri('vendors/vendor_specialties_filter_list_data'); ?>",
                type: "POST",
                data: function(d) {
                    d.category_id = canFilter ? ($category.val() || "") : "";
                    d.sub_category_id = canFilter ? ($subCategory.val() || "") : "";
                }
            },
            order: [
                [0, "asc"]
            ],
            columns: [{
                    title: "<?php echo app_lang('vendor'); ?>"
                },
                {
                    title: "<?php echo app_lang('email'); ?>"
                },
                {
                    title: "<?php echo app_lang('category'); ?>"
                },
                {
                    title: "<?php echo app_lang('sub_category'); ?>"
                },
                {
                    title: "<?php echo app_lang('specialty_name'); ?>"
                },
                {
                    title: "<?php echo app_lang('type'); ?>"
                },
                {
                    title: "<?php echo app_lang('description'); ?>"
                }
            ]
        });

        function reloadTable() {
            dt.ajax.reload();
            $card.addClass("pulse");
            setTimeout(function() {
                $card.removeClass("pulse");
            }, 280);
        }
    });
</script>
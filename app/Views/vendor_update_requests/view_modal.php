 <?php
    // ------------------------------
    // Helpers (scoped, safe to keep in view)
    // ------------------------------
    if (!function_exists("vur_stringify_value")) {
        function vur_stringify_value($v): string
        {
            if ($v === null) return "-";
            if (is_bool($v)) return $v ? "true" : "false";
            if (is_numeric($v)) return (string)$v;

            if (is_array($v) || is_object($v)) {
                $json = json_encode($v, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                return $json !== false ? $json : "-";
            }

            $s = (string)$v;
            $s = trim($s);
            return $s === "" ? "-" : $s;
        }
    }

    if (!function_exists("vur_flatten")) {
        function vur_flatten(array $arr, string $prefix = ""): array
        {
            $out = [];
            foreach ($arr as $k => $v) {
                $key = $prefix === "" ? (string)$k : ($prefix . "." . $k);

                if (is_array($v)) {
                    // If it's an associative/nested array, flatten deeper.
                    // If it's a simple numeric list, stringify it as JSON for readability.
                    $isAssoc = array_keys($v) !== range(0, count($v) - 1);
                    if ($isAssoc) {
                        $out += vur_flatten($v, $key);
                    } else {
                        $out[$key] = vur_stringify_value($v);
                    }
                } else {
                    $out[$key] = vur_stringify_value($v);
                }
            }
            return $out;
        }
    }

    $module   = esc($changes["module"] ?? "-");
    $table    = esc($changes["table"] ?? "-");
    $action   = esc($changes["action"] ?? "-");
    $recordId = esc($changes["record_id"] ?? "-");

    $before = $changes["before"] ?? [];
    $after  = $changes["after"] ?? [];

    if (!is_array($before)) $before = [];
    if (!is_array($after))  $after  = [];

    $beforeFlat = vur_flatten($before);
    $afterFlat  = vur_flatten($after);

    $keys = array_unique(array_merge(array_keys($beforeFlat), array_keys($afterFlat)));
    sort($keys);

    // stats
    $changedCount = 0;
    foreach ($keys as $k) {
        $b = $beforeFlat[$k] ?? null;
        $a = $afterFlat[$k] ?? null;
        if ($b !== $a) $changedCount++;
    }

    $hasDoc = !empty($after["path"]) || (str_contains(strtolower($changes["table"] ?? ""), "vendor_documents"));
    ?>

 <div class="modal-body clearfix">
     <div class="vur-modal">
         <style>
             /* =========================
               VUR Modal – Pro Diff UI
            ========================== */
             .vur-modal {
                 --vur-radius: 16px;
                 --vur-border: rgba(15, 23, 42, .10);
                 --vur-shadow: 0 14px 40px rgba(16, 24, 40, .10);
                 --vur-muted: #64748b;
                 --vur-title: #0f172a;
                 --vur-bg: #ffffff;
             }

             .vur-wrap {
                 border: 1px solid var(--vur-border);
                 border-radius: var(--vur-radius);
                 background: var(--vur-bg);
                 box-shadow: var(--vur-shadow);
                 overflow: hidden;

                 opacity: 0;
                 transform: translateY(10px);
                 transition: opacity .45s ease, transform .45s ease;
             }

             .vur-ready .vur-wrap {
                 opacity: 1;
                 transform: translateY(0);
             }

             .vur-hero {
                 padding: 16px 16px 12px;
                 border-bottom: 1px solid var(--vur-border);
                 background:
                     radial-gradient(760px 220px at 20% 0%, rgba(59, 130, 246, .16), transparent 60%),
                     radial-gradient(560px 200px at 85% 25%, rgba(34, 197, 94, .14), transparent 55%),
                     linear-gradient(180deg, rgba(15, 23, 42, .03), rgba(15, 23, 42, 0));
             }

             .vur-hero-top {
                 display: flex;
                 align-items: flex-start;
                 justify-content: space-between;
                 gap: 12px;
             }

             .vur-title {
                 margin: 0;
                 font-size: 16px;
                 font-weight: 800;
                 color: var(--vur-title);
                 letter-spacing: -.2px;
             }

             .vur-sub {
                 margin: 6px 0 0;
                 color: var(--vur-muted);
                 font-size: 12px;
             }

             .vur-pills {
                 margin-top: 10px;
                 display: flex;
                 flex-wrap: wrap;
                 gap: 8px;
             }

             .vur-pill {
                 display: inline-flex;
                 align-items: center;
                 gap: 8px;
                 padding: 6px 10px;
                 border: 1px solid var(--vur-border);
                 border-radius: 999px;
                 background: rgba(255, 255, 255, .75);
                 backdrop-filter: blur(8px);
                 font-size: 12px;
                 color: #334155;
             }

             .vur-pill i,
             .vur-pill svg {
                 width: 14px;
                 height: 14px;
             }

             .vur-actions {
                 display: flex;
                 gap: 8px;
                 flex-wrap: wrap;
                 justify-content: flex-end;
             }

             .vur-btn {
                 border-radius: 12px;
                 font-weight: 700;
             }

             .vur-body {
                 padding: 14px 16px 10px;
             }

             .vur-toolbar {
                 display: flex;
                 align-items: center;
                 justify-content: space-between;
                 gap: 12px;
                 flex-wrap: wrap;
                 padding: 10px 12px;
                 border: 1px solid var(--vur-border);
                 border-radius: 14px;
                 background: #fff;
                 margin-bottom: 12px;
             }

             .vur-search {
                 max-width: 360px;
                 width: 100%;
             }

             .vur-search input {
                 border-radius: 12px;
                 height: 40px;
                 border: 1px solid rgba(15, 23, 42, .14);
                 box-shadow: none;
             }

             .vur-toggle {
                 display: flex;
                 align-items: center;
                 gap: 10px;
                 color: #334155;
                 font-size: 12px;
                 font-weight: 700;
                 white-space: nowrap;
             }

             .vur-table {
                 border: 1px solid var(--vur-border);
                 border-radius: 14px;
                 overflow: hidden;
                 margin-bottom: 10px;
             }

             .vur-table table {
                 margin: 0;
             }

             .vur-table thead th {
                 font-size: 12px;
                 text-transform: none;
                 color: #0f172a;
                 background: rgba(15, 23, 42, .03);
                 border-bottom: 1px solid var(--vur-border) !important;
                 vertical-align: middle;
             }

             .vur-k {
                 font-weight: 800;
                 color: #0f172a;
                 font-size: 12px;
                 letter-spacing: -.1px;
             }

             .vur-val {
                 font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
                 font-size: 12px;
                 color: #0f172a;
                 background: rgba(15, 23, 42, .02);
                 border: 1px solid rgba(15, 23, 42, .08);
                 border-radius: 10px;
                 padding: 8px 10px;
                 max-height: 120px;
                 overflow: auto;
                 white-space: pre-wrap;
                 word-break: break-word;
             }

             .vur-status {
                 font-weight: 800;
                 font-size: 11px;
                 border-radius: 999px;
                 padding: 6px 10px;
                 display: inline-flex;
                 align-items: center;
                 gap: 6px;
                 border: 1px solid var(--vur-border);
             }

             .vur-status.changed {
                 background: rgba(59, 130, 246, .10);
                 color: #1d4ed8;
             }

             .vur-status.unchanged {
                 background: rgba(15, 23, 42, .05);
                 color: #334155;
             }

             .vur-status.added {
                 background: rgba(34, 197, 94, .10);
                 color: #15803d;
             }

             .vur-status.removed {
                 background: rgba(239, 68, 68, .10);
                 color: #b91c1c;
             }

             .vur-raw {
                 margin-top: 10px;
                 border: 1px solid var(--vur-border);
                 border-radius: 14px;
                 overflow: hidden;
             }

             .vur-raw-header {
                 padding: 10px 12px;
                 background: rgba(15, 23, 42, .03);
                 border-bottom: 1px solid var(--vur-border);
                 display: flex;
                 align-items: center;
                 justify-content: space-between;
                 gap: 10px;
             }

             .vur-raw-title {
                 margin: 0;
                 font-weight: 900;
                 font-size: 12px;
                 color: #0f172a;
             }

             .vur-raw pre {
                 margin: 0;
                 padding: 12px;
                 background: #fff;
                 max-height: 240px;
                 overflow: auto;
                 font-size: 12px;
             }

             /* subtle row highlight */
             .vur-row-changed td {
                 background: rgba(59, 130, 246, .04);
             }
         </style>

         <div class="vur-wrap">
             <!-- HERO -->
             <div class="vur-hero">
                 <div class="vur-hero-top">
                     <div>
                         <h4 class="vur-title"><?php echo app_lang("view_details"); ?></h4>
                         <p class="vur-sub">
                             Field comparison: <strong><?php echo (int)$changedCount; ?></strong> changed out of <strong><?php echo (int)count($keys); ?></strong>
                         </p>

                         <div class="vur-pills">
                             <span class="vur-pill"><i data-feather="package"></i> <?php echo app_lang("module"); ?>: <strong><?php echo $module; ?></strong></span>
                             <span class="vur-pill"><i data-feather="database"></i> <?php echo app_lang("table"); ?>: <strong><?php echo $table; ?></strong></span>
                             <span class="vur-pill"><i data-feather="activity"></i> <?php echo app_lang("action"); ?>: <strong><?php echo $action; ?></strong></span>
                             <span class="vur-pill"><i data-feather="hash"></i> <?php echo app_lang("record_id"); ?>: <strong><?php echo $recordId; ?></strong></span>
                         </div>
                     </div>

                     <div class="vur-actions">
                         <?php if ($hasDoc): ?>
                             <?php echo anchor(
                                    get_uri("vendor_update_requests/view_document/" . $model_info->id),
                                    "<i data-feather='file-text' class='icon-16'></i> " . app_lang("view") . " " . app_lang("document"),
                                    ["target" => "_blank", "class" => "btn btn-default btn-sm vur-btn"]
                                ); ?>
                         <?php endif; ?>

                         <button type="button" class="btn btn-default btn-sm vur-btn" data-bs-toggle="collapse" data-bs-target="#vurRawJson" aria-expanded="false">
                             <i data-feather="code" class="icon-16"></i> Raw JSON
                         </button>
                     </div>
                 </div>
             </div>

             <div class="vur-body">
                 <!-- TOOLBAR -->
                 <div class="vur-toolbar">
                     <div class="vur-search">
                         <input id="vur-search" type="text" class="form-control" placeholder="Search field or value...">
                     </div>

                     <label class="vur-toggle">
                         <input type="checkbox" id="vur-only-changed" class="form-check-input" style="margin-top:0;">
                         Show changed only
                     </label>
                 </div>

                 <!-- DIFF TABLE -->
                 <div class="vur-table table-responsive">
                     <table class="table table-sm table-hover">
                         <thead>
                             <tr>
                                 <th style="min-width: 220px;">Field</th>
                                 <th style="min-width: 280px;"><?php echo app_lang("before"); ?></th>
                                 <th style="min-width: 280px;"><?php echo app_lang("after"); ?></th>
                                 <th class="text-center" style="width: 140px;">Status</th>
                             </tr>
                         </thead>
                         <tbody id="vur-diff-body">
                             <?php foreach ($keys as $k): ?>
                                 <?php
                                    $bExists = array_key_exists($k, $beforeFlat);
                                    $aExists = array_key_exists($k, $afterFlat);

                                    $bVal = $bExists ? $beforeFlat[$k] : null;
                                    $aVal = $aExists ? $afterFlat[$k] : null;

                                    $status = "unchanged";
                                    $label  = "Unchanged";

                                    if (!$bExists && $aExists) {
                                        $status = "added";
                                        $label = "Added";
                                    } else if ($bExists && !$aExists) {
                                        $status = "removed";
                                        $label = "Removed";
                                    } else if ($bVal !== $aVal) {
                                        $status = "changed";
                                        $label = "Changed";
                                    }

                                    $isRowChanged = ($status !== "unchanged");
                                    $rowClass = $isRowChanged ? "vur-row-changed" : "";
                                    ?>
                                 <tr class="<?php echo $rowClass; ?>"
                                     data-status="<?php echo esc($status); ?>"
                                     data-search="<?php echo esc(strtolower($k . ' ' . (string)$bVal . ' ' . (string)$aVal)); ?>">
                                     <td>
                                         <div class="vur-k"><?php echo esc($k); ?></div>
                                     </td>

                                     <td>
                                         <div class="vur-val"><?php echo esc($bExists ? $bVal : "-"); ?></div>
                                     </td>

                                     <td>
                                         <div class="vur-val"><?php echo esc($aExists ? $aVal : "-"); ?></div>
                                     </td>

                                     <td class="text-center">
                                         <span class="vur-status <?php echo esc($status); ?>">
                                             <?php if ($status === "changed"): ?><i data-feather="refresh-cw"></i><?php endif; ?>
                                             <?php if ($status === "unchanged"): ?><i data-feather="minus-circle"></i><?php endif; ?>
                                             <?php if ($status === "added"): ?><i data-feather="plus-circle"></i><?php endif; ?>
                                             <?php if ($status === "removed"): ?><i data-feather="x-circle"></i><?php endif; ?>
                                             <?php echo esc($label); ?>
                                         </span>
                                     </td>
                                 </tr>
                             <?php endforeach; ?>

                             <?php if (!count($keys)): ?>
                                 <tr>
                                     <td colspan="4" class="text-center text-muted p15">No fields found in before/after.</td>
                                 </tr>
                             <?php endif; ?>
                         </tbody>
                     </table>
                 </div>

                 <!-- RAW JSON COLLAPSE -->
                 <div class="collapse" id="vurRawJson">
                     <div class="vur-raw">
                         <div class="vur-raw-header">
                             <p class="vur-raw-title mb0">Raw JSON (Before / After)</p>
                             <div class="d-flex gap-2">
                                 <button type="button" class="btn btn-default btn-sm" id="vur-copy-before">
                                     <i data-feather="copy" class="icon-16"></i> Copy Before
                                 </button>
                                 <button type="button" class="btn btn-default btn-sm" id="vur-copy-after">
                                     <i data-feather="copy" class="icon-16"></i> Copy After
                                 </button>
                             </div>
                         </div>

                         <div class="row m0">
                             <div class="col-md-6 p0" style="border-right:1px solid var(--vur-border);">
                                 <div class="p10" style="border-bottom:1px solid var(--vur-border); font-weight:800; font-size:12px;">
                                     <?php echo app_lang("before"); ?>
                                 </div>
                                 <pre id="vur-raw-before"><?php echo esc(json_encode($before, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)); ?></pre>
                             </div>
                             <div class="col-md-6 p0">
                                 <div class="p10" style="border-bottom:1px solid var(--vur-border); font-weight:800; font-size:12px;">
                                     <?php echo app_lang("after"); ?>
                                 </div>
                                 <pre id="vur-raw-after"><?php echo esc(json_encode($after, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)); ?></pre>
                             </div>
                         </div>
                     </div>
                 </div>

             </div><!-- /vur-body -->
         </div><!-- /vur-wrap -->
     </div><!-- /vur-modal -->
 </div>

 <div class="modal-footer">
     <button type="button" class="btn btn-default" data-bs-dismiss="modal">
         <?php echo app_lang("close"); ?>
     </button>
 </div>

 <script>
     (function() {
         var $root = $(".vur-modal").last(); // modal content is injected, scope to latest
         setTimeout(function() {
             $root.addClass("vur-ready");
             if (window.feather) feather.replace();
         }, 30);

         function applyFilters() {
             var q = ($root.find("#vur-search").val() || "").toLowerCase().trim();
             var onlyChanged = $root.find("#vur-only-changed").is(":checked");

             $root.find("#vur-diff-body tr").each(function() {
                 var $tr = $(this);
                 var status = ($tr.data("status") || "").toString();
                 var hay = ($tr.data("search") || "").toString();

                 var matchQ = !q || hay.indexOf(q) !== -1;
                 var matchChanged = !onlyChanged || status !== "unchanged";

                 $tr.toggle(matchQ && matchChanged);
             });
         }

         $root.on("input", "#vur-search", applyFilters);
         $root.on("change", "#vur-only-changed", applyFilters);

         function copyText(text) {
             if (navigator.clipboard && window.isSecureContext) {
                 navigator.clipboard.writeText(text);
                 return;
             }
             var $tmp = $("<textarea>").val(text).appendTo("body").select();
             document.execCommand("copy");
             $tmp.remove();
         }

         $root.on("click", "#vur-copy-before", function() {
             copyText($root.find("#vur-raw-before").text());
         });

         $root.on("click", "#vur-copy-after", function() {
             copyText($root.find("#vur-raw-after").text());
         });

         if (window.feather) feather.replace();
     })();
 </script>
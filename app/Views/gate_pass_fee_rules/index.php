<div id="page-content" class="page-wrapper clearfix">
  <div class="card">
    <div class="page-title clearfix">
      <h1>Gate Pass Fee Rules</h1>
      <div class="title-button-group">
        <?php echo modal_anchor(
          get_uri("gate_pass_fee_rules/modal_form"),
          "<i data-feather='plus-circle' class='icon-16'></i> Add Rule",
          ["class" => "btn btn-default"]
        ); ?>
      </div>
    </div>

    <div class="table-responsive">
      <table id="gate-pass-fee-rules-table" class="display" width="100%"></table>
    </div>
  </div>
</div>

<script>
$(document).ready(function () {
  $("#gate-pass-fee-rules-table").appTable({
    source: '<?php echo_uri("gate_pass_fee_rules/list_data"); ?>',
    columns: [
      {title: "Duration (days)"},
      {title: "Rate Type"},
      {title: "<?php echo app_lang('amount'); ?>"},
      {title: "Waivable"},
      {title: "<?php echo app_lang('status'); ?>"},
      {title: '<i data-feather="menu" class="icon-16"></i>', class:"text-center option w120"}
    ]
  });
});
</script>

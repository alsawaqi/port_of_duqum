<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Connecting to Bank Muscat</title></head>
<body style="font-family:system-ui,sans-serif;padding:32px"><main style="max-width:620px;margin:8vh auto">
<h1>Connecting to Bank Muscat</h1><p>Your order reference is <?= esc($payment->provider_checkout_id) ?>.</p>
<form id="smartpay-hosted-form" method="post" action="<?= esc($form['gateway_url'], 'attr') ?>">
<?php foreach ($form['fields'] as $name => $value): ?><input type="hidden" name="<?= esc($name, 'attr') ?>" value="<?= esc($value, 'attr') ?>"><?php endforeach; ?>
<button type="submit">Continue to secure payment</button>
</form><script>document.getElementById('smartpay-hosted-form').submit();</script>
</main></body></html>

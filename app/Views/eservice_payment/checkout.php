<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Bank Muscat payment</title></head>
<body style="font-family:system-ui,sans-serif;background:#f5f7fa;color:#172b45;margin:0;padding:32px">
<main style="max-width:620px;margin:8vh auto;background:white;padding:32px;border-radius:12px">
<h1>Bank Muscat payment</h1>
<p>Amount: <strong><?= esc($payment->currency) ?> <?= esc(number_format((float)$payment->amount, 3)) ?></strong></p>
<p>Order reference: <?= esc($payment->provider_checkout_id) ?></p>
<?php if ($payment->status === 'processing' && empty($payment->handed_off_at) && strtotime($payment->expires_at . ' UTC') > time()): ?>
<p>Continue to Bank Muscat SmartPay to enter your payment details securely.</p>
<?= form_open('eservice_payment/handoff/' . $payment->public_id, ['id' => 'payment-handoff']) ?>
<button type="submit" style="background:#075a75;color:white;border:0;border-radius:6px;padding:12px 18px;font-size:16px">Continue to Bank Muscat</button>
<?= form_close() ?>
<?php else: ?>
<p>This checkout was already sent to the bank or is no longer available.</p>
<p><a href="<?= esc(get_uri('eservice_payment/result/' . $payment->public_id), 'attr') ?>">View payment status</a></p>
<?php endif; ?>
</main></body></html>

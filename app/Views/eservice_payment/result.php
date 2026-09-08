<?php
$returnUrl = get_uri('signin');
if ($payment) {
    $metadata = json_decode((string)$payment->metadata, true) ?: [];
    $candidate = (string)($metadata[$payment->status === 'paid' ? 'success_url' : 'cancel_url'] ?? '');
    $base = parse_url(get_uri(''));
    $parsed = parse_url($candidate);
    if ($parsed && ($parsed['scheme'] ?? '') === ($base['scheme'] ?? '') && ($parsed['host'] ?? '') === ($base['host'] ?? '')
        && ($parsed['port'] ?? null) === ($base['port'] ?? null) && !isset($parsed['user']) && !isset($parsed['pass'])) {
        $returnUrl = $candidate;
    }
    $messages = [
        'paid' => $payment->settlement_status === 'applied' ? 'Bank Muscat confirmed your payment. The fee has been applied.' : 'Bank Muscat confirmed your payment and the details were saved. Accounting must reconcile its application to this fee. Do not pay again.',
        'failed' => 'The bank confirmed that this payment failed. You can return to your application to start a new attempt.',
        'cancelled' => 'The bank confirmed that this payment was cancelled. You can return to your application to start a new attempt.',
        'expired' => 'This unused checkout expired. Return to your application to start a new attempt.',
    ];
    $message = $messages[$payment->status] ?? 'This transaction is awaiting bank confirmation. Do not pay again until its status is confirmed. Accounting can recheck the bank status.';
}
?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Payment status</title></head>
<body style="font-family:system-ui,sans-serif;background:#f5f7fa;color:#172b45;margin:0;padding:32px"><main style="max-width:620px;margin:8vh auto;background:white;padding:32px;border-radius:12px">
<h1>Payment status</h1><p><?= esc($message) ?></p>
<?php if ($payment): ?><p>Order reference: <strong><?= esc($payment->provider_checkout_id) ?></strong></p><p>Amount: <?= esc($payment->currency) ?> <?= esc(number_format((float)$payment->amount, 3)) ?></p><?php endif; ?>
<p><a href="<?= esc($returnUrl, 'attr') ?>">Return to your application</a></p>
</main></body></html>

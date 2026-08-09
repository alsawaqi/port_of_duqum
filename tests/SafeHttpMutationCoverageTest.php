<?php

$root = dirname(__DIR__);
$filterSource = (string)file_get_contents($root . "/app/Filters/SafeHttpMethods.php");
if (!preg_match('/private const MUTATING_ACTION\s*=\s*(.*?);/s', $filterSource, $assignment)) {
    fwrite(STDERR, "Unable to read the mutating-action route policy." . PHP_EOL);
    exit(1);
}
preg_match_all("/'((?:\\\\.|[^'])*)'/", $assignment[1], $pieces);
$mutatingActionPattern = "";
foreach ($pieces[1] as $piece) {
    $mutatingActionPattern .= stripcslashes($piece);
}
if ($mutatingActionPattern === "" || @preg_match($mutatingActionPattern, "save") !== 1) {
    fwrite(STDERR, "The mutating-action route policy is invalid." . PHP_EOL);
    exit(1);
}

$writeIndicators = '/\b(?:ci_save|insert|update|delete|setInitialPassword|transStart|transBegin|file_put_contents|rename|unlink|send_app_mail|curl_exec)\s*\(/i';
$intentionalReadSideEffects = [
    "Event_tracker::load",
    "Invoices::download_xml",
    "Invoices::get_send_invoice_template",
    "Paypal_redirect::index",
    "Paytm_redirect::index",
    "Pwa::service_worker",
    "Stripe_redirect::index",
    "Stripe_redirect::subscription",
];
$violations = [];
foreach (glob($root . "/app/Controllers/*.php") as $file) {
    $source = (string)file_get_contents($file);
    if (!preg_match_all('/(?:^|\R)\s*(?:public\s+)?function\s+([A-Za-z_][A-Za-z0-9_]*)\s*\([^)]*\)\s*\{/m', $source, $methods, PREG_OFFSET_CAPTURE)) {
        continue;
    }
    foreach ($methods[1] as [$name, $nameOffset]) {
        if ($name === "__construct") {
            continue;
        }
        $open = strpos($source, "{", $nameOffset);
        $depth = 0;
        $end = $open;
        for ($cursor = $open, $length = strlen($source); $cursor < $length; $cursor++) {
            if ($source[$cursor] === "{") {
                $depth++;
            } elseif ($source[$cursor] === "}" && --$depth === 0) {
                $end = $cursor;
                break;
            }
        }
        $body = substr($source, $open, $end - $open + 1);
        $qualifiedName = basename($file, ".php") . "::" . $name;
        if (
            preg_match($writeIndicators, $body)
            && !preg_match($mutatingActionPattern, $name)
            && !in_array($qualifiedName, $intentionalReadSideEffects, true)
        ) {
            $violations[] = $qualifiedName;
        }
    }
}

if ($violations) {
    fwrite(STDERR, "State-changing controller actions remain reachable with GET: " . implode(", ", $violations) . PHP_EOL);
    exit(1);
}

echo "Safe HTTP mutation route coverage passed." . PHP_EOL;

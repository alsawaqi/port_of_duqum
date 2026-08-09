<?php

$vendorController = file_get_contents(__DIR__ . "/../app/Controllers/Vendor_portal.php");
$staffController = file_get_contents(__DIR__ . "/../app/Controllers/Tender_clarifications.php");

$fail = static function (string $message): void {
    fwrite(STDERR, "Assertion failed: {$message}" . PHP_EOL);
    exit(1);
};

$sliceBetween = static function (string $source, string $start, string $end, string $message) use ($fail): string {
    $startPosition = strpos($source, $start);
    if ($startPosition === false) {
        $fail($message . " (start marker missing)");
    }

    $endPosition = strpos($source, $end, $startPosition + strlen($start));
    if ($endPosition === false) {
        $fail($message . " (end marker missing)");
    }

    return substr($source, $startPosition, $endPosition - $startPosition);
};

$assertContains = static function (string $needle, string $haystack, string $message) use ($fail): void {
    if (strpos($haystack, $needle) === false) {
        $fail($message . " (missing: {$needle})");
    }
};

$assertBefore = static function (string $first, string $second, string $haystack, string $message) use ($fail): void {
    $firstPosition = strpos($haystack, $first);
    $secondPosition = strpos($haystack, $second);
    if ($firstPosition === false || $secondPosition === false || $firstPosition >= $secondPosition) {
        $fail($message);
    }
};

$vendorSave = $sliceBetween(
    $vendorController,
    "public function save_clarification()",
    "public function download_clarification_attachment",
    "vendor clarification save action should be inspectable"
);
$staffReply = $sliceBetween(
    $staffController,
    "public function save_reply()",
    "public function download_attachment",
    "staff clarification reply action should be inspectable"
);

foreach (
    [
        "vendor clarification submission" => [$vendorSave, '"vendor_clarification_post_{$user_id}_{$ip_hash}"'],
        "staff clarification reply" => [$staffReply, '"tender_clarification_reply_{$user_id}_{$ip_hash}"'],
    ] as $label => [$action, $expectedKey]
) {
    $assertContains('$user_id = (int) ($this->login_user->id ?? 0)', $action, "{$label} rate limit is identity-scoped");
    $assertContains('hash("sha256", (string) $this->request->getIPAddress())', $action, "{$label} hashes the source IP in its cache key");
    $assertContains($expectedKey, $action, "{$label} uses a dedicated throttle bucket");
    $assertContains('service("throttler")', $action, "{$label} uses the existing CodeIgniter throttler");
    $assertContains('$throttler->check($throttle_key, 10, 60)', $action, "{$label} is limited to ten posts per minute");
    $assertContains('->setStatusCode(429)', $action, "{$label} returns HTTP 429 when limited");
    $assertContains('->setHeader("Retry-After"', $action, "{$label} tells the client when it may retry");
    $assertBefore('$throttler->check($throttle_key, 10, 60)', '->ci_save(', $action, "{$label} must be throttled before its database write");
}

echo "Tender clarification rate-limit contracts passed." . PHP_EOL;

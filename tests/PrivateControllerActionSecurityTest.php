<?php

$root = dirname(__DIR__);

$fail = static function (string $message): void {
    fwrite(STDERR, "Assertion failed: {$message}" . PHP_EOL);
    exit(1);
};
$assertContains = static function (string $needle, string $source, string $message) use ($fail): void {
    if (!str_contains($source, $needle)) {
        $fail($message . " Missing: " . $needle);
    }
};

$safeHttpSource = (string) file_get_contents($root . "/app/Filters/SafeHttpMethods.php");
$actionResolution = strpos(
    $safeHttpSource,
    '$segments = $request->getUri()->getSegments();'
);
$safeActionResolution = strpos(
    $safeHttpSource,
    '$action = strtolower((string) ($segments[1] ?? \'\'));'
);
$privateActionGuard = strpos($safeHttpSource, "str_starts_with(\$action, '_')");
$methodResolution = strpos($safeHttpSource, '$method = strtoupper($request->getMethod());');
$safeVerbReturn = strpos(
    $safeHttpSource,
    "if (!in_array(\$method, ['GET', 'HEAD'], true))"
);

if ($actionResolution === false || $safeActionResolution === false || $privateActionGuard === false) {
    $fail("underscore-prefixed controller actions are not rejected globally without throwing on short URLs");
}
if ($methodResolution === false || $privateActionGuard > $methodResolution) {
    $fail("the private-action guard must run for every HTTP method");
}
if ($safeVerbReturn === false || $privateActionGuard > $safeVerbReturn) {
    $fail("POST/OPTIONS must not bypass the private-action guard");
}
$assertContains(
    "->setStatusCode(404)",
    $safeHttpSource,
    "private controller actions should not be disclosed"
);

$filtersSource = (string) file_get_contents($root . "/app/Controllers/Filters.php");
$assertContains(
    'private function _save_custom_filters($filters_array)',
    $filtersSource,
    "the custom-filter persistence helper must not be routable"
);
if (preg_match('/(?:^|\R)\s*(?:public\s+)?function\s+_save_custom_filters\s*\(/m', $filtersSource)) {
    $fail("the custom-filter persistence helper remains public");
}
if (str_contains($filtersSource, '$this->_save_custom_filters($filters, $id)')) {
    $fail("the filter save caller still passes an obsolete route-style argument");
}

echo "Private controller action security contracts passed." . PHP_EOL;

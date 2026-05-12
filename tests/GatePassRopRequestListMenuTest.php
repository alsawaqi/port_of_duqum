<?php

$root = dirname(__DIR__);

$read = function (string $path) use ($root): string {
    $full = $root . "/" . $path;
    return is_file($full) ? file_get_contents($full) : "";
};

$assertContains = function (string $needle, string $haystack, string $message): void {
    if (strpos($haystack, $needle) === false) {
        fwrite(STDERR, "FAIL: " . $message . PHP_EOL);
        fwrite(STDERR, "Missing: " . $needle . PHP_EOL);
        exit(1);
    }
};

$leftMenu = $read("app/Libraries/Left_menu.php");
$securityController = $read("app/Controllers/Security_Controller.php");
$requestListController = $read("app/Controllers/Gate_pass_request_list.php");

$assertContains(
    '"gate_pass_rop_requests"',
    $leftMenu,
    "ROP users should keep their assignment-based menu group"
);
$assertContains(
    'array("name" => "gate_pass_rop_inbox", "url" => "gate_pass_rop_inbox", "class" => "list"),
                                    array("name" => "gate_pass_filter_requests", "url" => "gate_pass_request_list", "class" => "filter"),
                                    array("name" => "gate_pass_blocked_visitors", "url" => "gate_pass_blocked_visitors", "class" => "slash")',
    $leftMenu,
    "ROP menu should expose the existing Filter Requests page"
);
$assertContains(
    '_is_active_gate_pass_rop_user',
    $securityController,
    "Gate pass access should have an ROP assignment fallback"
);
$assertContains(
    '$section === "request_list" && $action === "view" && $this->_is_active_gate_pass_rop_user()',
    $securityController,
    "Assigned ROP users should be able to view the Filter Requests page without master permissions"
);
$assertContains(
    'access_only_gate_pass("request_list", "view")',
    $requestListController,
    "Filter Requests should still use the central Gate Pass access check"
);

echo "OK" . PHP_EOL;

<?php

$root = dirname(__DIR__);

$read = function (string $path) use ($root): string {
    $full = $root . "/" . $path;
    return is_file($full) ? file_get_contents($full) : "";
};

$assertTrue = function ($condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: " . $message . PHP_EOL);
        exit(1);
    }
};

$assertContains = function (string $needle, string $haystack, string $message) use ($assertTrue): void {
    $assertTrue(strpos($haystack, $needle) !== false, $message);
};

$assertNotContains = function (string $needle, string $haystack, string $message) use ($assertTrue): void {
    $assertTrue(strpos($haystack, $needle) === false, $message);
};

$labelFiles = [
    "app/Views/tender_procurement_inbox/form.php",
    "app/Views/tender_procurement_manager_inbox/details.php",
    "app/Views/vendor_portal/tenders/details.php",
    "app/Views/vendor_portal/tenders/view_modal.php",
    "app/Views/vendor_portal/tenders/bid_modal.php",
    "app/Views/tender_reports/details.php",
    "app/Views/tender_commercial_inbox/bid_modal_form.php",
];

foreach ($labelFiles as $file) {
    $content = $read($file);
    $assertContains(
        "Part No (Optional)",
        $content,
        "$file should use the approved optional part-number wording"
    );
    $assertTrue(
        !preg_match('/>\s*Brand\s*</i', $content),
        "$file should not render the retired Brand heading"
    );
}

$form = $read("app/Views/tender_procurement_inbox/form.php");
$assertContains(
    "recalculateRfqTotals",
    $form,
    "procurement item schedule should recalculate totals when quantity or unit price changes"
);
$assertContains(
    "tender-rfq-line-total",
    $form,
    "each procurement item row should expose a derived line total"
);
$assertContains(
    "tender-rfq-grand-total",
    $form,
    "the procurement item schedule should expose a derived grand total"
);
$assertTrue(
    (bool) preg_match(
        '/(?:qty|quantity)(?:Text)?\)?\s*\*\s*(?:parseFloat\()?\s*(?:unitPrice|unit_price|price)(?:Text)?|(?:unitPrice|unit_price|price)(?:Text)?\)?\s*\*\s*(?:parseFloat\()?\s*(?:qty|quantity)(?:Text)?/i',
        $form
    ),
    "line total should be calculated as quantity multiplied by unit price"
);
$assertNotContains(
    'name="rfq_item_total',
    $form,
    "derived procurement totals should not be submitted as trusted input"
);

echo "OK" . PHP_EOL;

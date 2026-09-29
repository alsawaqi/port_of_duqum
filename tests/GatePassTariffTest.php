<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/Libraries/Gate_pass_tariff.php';
use App\Libraries\Gate_pass_tariff;

$checks = 0;
$check = static function (bool $ok, string $message) use (&$checks): void {
    $checks++;
    if (!$ok) { throw new RuntimeException($message); }
};
$bands = [[1,1,'2.000','0.000'], [2,7,'3.000','0.000'], [8,14,'5.000','0.000'],
    [15,90,'20.000','5.000'], [91,180,'35.000','5.000'], [181,365,'50.000','5.000']];
foreach ($bands as [$min, $max, $amount, $induction]) {
    $rule = (object)['min_days'=>$min,'max_days'=>$max,'amount'=>$amount,'induction_amount'=>$induction,'currency'=>'OMR','rate_type'=>'flat'];
    foreach (array_unique([$min, $max, (int)floor(($min+$max)/2)]) as $days) {
        foreach ([0,1,2,7] as $count) {
            $q = Gate_pass_tariff::breakdown($rule, $days, $count);
            $check($q['total'] === number_format(((float)$amount + (float)$induction) * $count, 3, '.', ''), "Total: {$days} days / {$count} people");
            $check($q['tariff_subtotal'] === number_format((float)$amount*$count, 3, '.', ''), 'Tariff subtotal');
            $check($q['induction_subtotal'] === number_format((float)$induction*$count, 3, '.', ''), 'Induction subtotal');
        }
    }
}
foreach (['daily','weekly','monthly'] as $type) {
    $rule->rate_type = $type;
    try { Gate_pass_tariff::breakdown($rule, 200, 2); throw new RuntimeException('Legacy multiplying rule accepted'); }
    catch (DomainException $e) { $check(true, 'Legacy rule refused'); }
}
$rule->rate_type = 'flat';
foreach ([[0,1],[366,1],[200,-1],[7,1]] as [$days,$count]) {
    try { Gate_pass_tariff::breakdown($rule, $days, $count); throw new RuntimeException('Invalid duration/count accepted'); }
    catch (DomainException $e) { $check(true, 'Invalid input refused'); }
}
$rule->amount = '0.001'; $rule->induction_amount = '0.002';
$check(Gate_pass_tariff::breakdown($rule, 200, 999)['total'] === '2.997', 'Exact baisa arithmetic');
foreach (['-1','abc','0.0001','1e3'] as $bad) {
    $rule->amount = $bad;
    try { Gate_pass_tariff::breakdown($rule, 200, 1); throw new RuntimeException('Invalid money accepted'); }
    catch (DomainException $e) { $check(true, 'Invalid amount refused'); }
}
echo "GatePassTariffTest: {$checks} checks passed\n";

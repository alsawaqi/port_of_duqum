<?php

// Opt-in integration test for closed tender filtering, persistence and vendor visibility.
// Requires a populated local QA schema; all fixture writes roll back. No notifications are sent.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (getenv('POD_RUN_TENDER_AUDIENCE_DB_TESTS') !== '1') { echo "SKIP: opt-in local database test\n"; exit; }
if (!defined('FCPATH')) {
chdir(dirname(__DIR__));
define('FCPATH', getcwd() . DIRECTORY_SEPARATOR);
require FCPATH . 'app/Config/Paths.php';
require FCPATH . 'system/Boot.php';
class TenderAudienceBootstrap extends CodeIgniter\Boot {
    public static function init(): void {
        $paths = new Config\Paths();
        static::definePathConstants($paths);
        static::loadConstants();
        static::loadDotEnv($paths);
        static::defineEnvironment();
        static::loadCommonFunctions();
        static::loadAutoloader();
    }
}
TenderAudienceBootstrap::init();
$database = (new Config\Database())->default;
if (ENVIRONMENT === 'production' || $database['database'] !== 'bedotscpanel_poderp'
    || !in_array($database['hostname'], ['localhost', '127.0.0.1'], true)) {
    throw new RuntimeException('Restricted to the local development database.');
}
if (!defined('CI_DEBUG')) { define('CI_DEBUG', true); }
putenv('PODC_RECAPTCHA_ENABLED=false'); // Process only; never change application settings.
helper(['general', 'plugin', 'date_time', 'safe_serialization', 'url', 'language', 'email']);
require_once APPPATH . 'ThirdParty/PHP-Hooks/php-hooks.php';
config('Rise')->app_settings_array = ['language' => 'english'];
}


$db=db_connect();
if (ENVIRONMENT==='production' || !in_array($db->hostname,['localhost','127.0.0.1'],true)) throw new RuntimeException('Local development database only');
$db->transException(true);

$clone = static function(string $table,array $changes) use($db): int {
    $row=$db->table($table)->get(1)->getRowArray(); unset($row['id']);
    foreach($db->query('SHOW COLUMNS FROM `'.$db->prefixTable($table).'`')->getResult() as $field) {
        if(str_contains($field->Extra,'GENERATED')) { unset($row[$field->Field]); }
    }
    $db->table($table)->insert(array_replace($row,$changes));return (int)$db->insertID();
};

$actor = (int) $db->table('users')->where('deleted', 0)->get(1)->getRow()->id;
$db->transBegin();
try {
$stamp=strtoupper(bin2hex(random_bytes(4)));$out=[];
$insert=static function($table,$data) use($db) { $db->table($table)->insert($data);return (int)$db->insertID(); };
$group=$insert('vendor_groups',['name'=>'Target QA SME '.$stamp,'code'=>'QA'.$stamp,'is_active'=>1,'deleted'=>0]);
$otherGroup=$insert('vendor_groups',['name'=>'Target QA Local '.$stamp,'code'=>'QB'.$stamp,'is_active'=>1,'deleted'=>0]);
$grade=$insert('vendor_grades',['name'=>'Target QA Grade A '.$stamp,'code'=>'GA'.$stamp,'is_active'=>1,'deleted'=>0]);
$otherGrade=$insert('vendor_grades',['name'=>'Target QA Grade B '.$stamp,'code'=>'GB'.$stamp,'is_active'=>1,'deleted'=>0]);
$category=$insert('vendor_categories',['name'=>'Target QA Electrical '.$stamp,'is_active'=>1,'deleted'=>0]);
$otherCategory=$insert('vendor_categories',['name'=>'Target QA Civil '.$stamp,'is_active'=>1,'deleted'=>0]);
$sub=$insert('vendor_sub_categories',['name'=>'Target QA Wiring '.$stamp,'vendor_category_id'=>$category,'is_active'=>1,'deleted'=>0]);
$otherSub=$insert('vendor_sub_categories',['name'=>'Target QA Roads '.$stamp,'vendor_category_id'=>$otherCategory,'is_active'=>1,'deleted'=>0]);
$out=compact('group','otherGroup','grade','otherGrade','category','otherCategory','sub','otherSub','stamp');
foreach(['match','wrong_grade','wrong_group','wrong_specialty','pending_specialty','extra','suspended','deleted'] as $kind) {
    $id=$clone('vendors',['vendor_name'=>'Target QA '.$kind.' '.$stamp,'cr_number'=>'QA'.$stamp.$kind,'email'=>'target-'.$stamp.'-'.$kind.'@example.invalid','vendor_code'=>null,
        'vendor_group_id'=>in_array($kind,['wrong_group','extra'])?$otherGroup:$group,'vendor_grade_id'=>in_array($kind,['wrong_grade','extra'])?$otherGrade:$grade,
        'status'=>$kind==='suspended'?'suspended':'approved','deleted'=>$kind==='deleted'?1:0]);
    $out['vendors'][$kind]=$id;
    $insert('vendor_specialties',['vendor_id'=>$id,'vendor_category_id'=>in_array($kind,['wrong_specialty','extra'])?$otherCategory:$category,
        'vendor_sub_category_id'=>in_array($kind,['wrong_specialty','extra'])?$otherSub:$sub,'specialty_type'=>'service','specialty_name'=>'QA','status'=>$kind==='pending_specialty'?'pending':'approved','deleted'=>0]);
}
$out['tender']=$clone('tenders',['tender_request_id'=>null,'reference'=>'TARGET-'.$stamp,'title'=>'Target QA closed tender '.$stamp,'company_id'=>1,'department_id'=>1,'tender_type'=>'close','status'=>'draft','workflow_stage'=>'bidding','deleted'=>0,
 'release_at'=>'2026-10-04 08:00:00','closing_at'=>'2026-12-03 23:59:59','document_purchase_deadline'=>null,'site_visit_at'=>null,'clarification_deadline'=>null,'bid_opening_at'=>null,'technical_eval_deadline'=>null,'commercial_eval_deadline'=>null,
 'procurement_manager_status'=>'draft','procurement_manager_payload'=>null,'created_by'=>$actor]);

$checks=0;
$check=static function($ok,$label) use(&$checks) { if(!$ok)throw new RuntimeException($label);$checks++; };
$selector=new App\Libraries\Tender_vendor_selection($db);
$base=['vendor_group_id'=>$group,'vendor_grade_id'=>$grade,'vendor_category_id'=>$category,'vendor_sub_category_id'=>$sub,'specific_vendor_ids'=>[]];
$v=$out['vendors'];$tid=$out['tender'];
$ids=static function($selection)use($selector){$ids=array_column($selector->recipients($selection),'id');sort($ids);return $ids;};
$same=static function($actual,$expected)use($check){sort($expected);$check($actual===$expected,'Audience IDs differ: '.json_encode([$actual,$expected]));};
$same($ids($base),[$v['match']]);
$same($ids(array_replace($base,['vendor_category_id'=>0,'vendor_sub_category_id'=>0])),[$v['match'],$v['wrong_specialty'],$v['pending_specialty']]);
$same($ids(array_replace($base,['vendor_grade_id'=>0])),[$v['match'],$v['wrong_grade']]);
$same($ids(array_replace($base,['vendor_group_id'=>0])),[$v['match'],$v['wrong_group']]);
$selection=$selector->validate($base+[]);
$selection['specific_vendor_ids']=[$v['extra'],$v['match'],$v['extra']];$selection=$selector->validate($selection);
$same($ids($selection),[$v['match'],$v['extra']]);
$check(count($selection['specific_vendor_ids'])===2,'Explicit IDs deduplicated');
$same($ids($selector->validate(['specific_vendor_ids'=>[$v['extra']]])),[$v['extra']]);
$same($ids($selector->validate([])),[]);
foreach([
 ['vendor_group_id'=>-1],['vendor_grade_id'=>'1 OR 1=1'],['vendor_group_id'=>[$group]],['vendor_category_id'=>999999999],
 ['vendor_category_id'=>$category,'vendor_sub_category_id'=>$otherSub],['vendor_sub_category_id'=>$sub],
 ['specific_vendor_ids'=>[$v['suspended']]],['specific_vendor_ids'=>[$v['deleted']]],['specific_vendor_ids'=>'1'],['specific_vendor_ids'=>['1x']],
] as $bad) {
 try{$selector->validate($bad);$check(false,'Invalid selection accepted');}catch(DomainException $e){$check(true,'Invalid selection refused');}
}
$db->table('vendor_groups')->where('id',$group)->update(['is_active'=>0]);
try{$selector->validate($base);$check(false,'Inactive group accepted');}catch(DomainException $e){$check(true,'Inactive group refused');}
$db->table('vendor_groups')->where('id',$group)->update(['is_active'=>1]);
$selector->save($tid,$selection,$actor);
$db->table('tenders')->where('id',$tid)->update(['status'=>'published','release_at'=>null,'published_at'=>null,'closing_at'=>date('Y-m-d H:i:s',time()+86400*30)]);
$tenders=new App\Models\Tenders_model();
$checkAccess=static function($expected)use($tenders,$tid,$v,$check){
 foreach($v as $kind=>$id){
  $detail=$tenders->get_vendor_visible_tender($tid,$id);
  $listed=array_map('intval',array_column($tenders->get_vendor_visible_tenders($id)->getResultArray(),'id'));
  $allowed=in_array($kind,$expected,true);
  $check((bool)$detail===$allowed,'Detail audience: '.$kind);
  $check(in_array($tid,$listed,true)===$allowed,'List audience: '.$kind);
  if($allowed)$check((int)$detail->procurement_approved_for_submission===1,'Allowed audience can submit: '.$kind);
 }
};
$checkAccess(['match','extra']);
// Matching must use the same approved specialty row, not category and subcategory from different rows.
$db->table('vendor_specialties')->where('vendor_id',$v['match'])->update(['vendor_sub_category_id'=>null]);
$selector->save($tid,$base,$actor);$checkAccess([]);
$db->table('vendor_specialties')->where('vendor_id',$v['match'])->update(['vendor_sub_category_id'=>$sub]);
$checkAccess(['match']);
// Removing filters must not produce a broad closed tender.
$selector->save($tid,$selector->validate([]),$actor);$checkAccess([]);
// Preserve ordinary open tender discovery after clearing filters.
$db->table('tenders')->where('id',$tid)->update(['tender_type'=>'open']);
$check((bool)$tenders->get_vendor_visible_tender($tid,$v['wrong_grade']),'Open tender remains visible without filters');
$db->table('tenders')->where('id',$tid)->update(['tender_type'=>'close']);
$selector->save($tid,$selection,$actor);
// Both controller paths must store the same combination, not flatten to a union.
foreach([App\Controllers\Tender_procurement_inbox::class,App\Controllers\Tender_procurement_manager_inbox::class] as $class){
 $controller=(new ReflectionClass($class))->newInstanceWithoutConstructor();
 foreach(['db'=>$db,'login_user'=>(object)['id'=>$actor]] as $key=>$value){$p=new ReflectionProperty($class,$key);$p->setValue($controller,$value);}
 if(str_contains($class,'manager')){
  $persist=new ReflectionMethod($class,'_sync_target_rule_from_payload');$persist->invoke($controller,$tid,$selection+['target_mode'=>'combined']);
  $sync=new ReflectionMethod($class,'_sync_invites_from_payload');$sync->invoke($controller,(object)['id'=>$tid,'tender_type'=>'close'],$selection+['target_mode'=>'combined']);
 }else{
  $persist=new ReflectionMethod($class,'_save_target_rule');$persist->invoke($controller,$tid,'combined',$category,$sub,$group,$grade,$selection['specific_vendor_ids']);
  $sync=new ReflectionMethod($class,'_replace_invites');$sync->invoke($controller,$tid,$ids($selection));
 }
 $rows=$db->table('tender_target_specialties')->where(['tender_id'=>$tid,'deleted'=>0])->get()->getResultArray();
 $check(count($rows)===1 && (int)$rows[0]['vendor_group_id']===$group && (int)$rows[0]['vendor_grade_id']===$grade && (int)$rows[0]['vendor_category_id']===$category,'Controller preserves all dimensions: '.$class);
 $active=array_map('intval',array_column($db->table('tender_invited_vendors')->where(['tender_id'=>$tid,'deleted'=>0])->get()->getResultArray(),'vendor_id'));sort($active);
 $same($active,[$v['match'],$v['extra']]);
 $checkAccess(['match','extra']);
}
$requestId = $clone('tender_requests', ['reference' => 'TARGET-REQUEST-' . $stamp, 'status' => 'committee_approved']);
foreach ([$v['match'], $v['suspended']] as $vendorId) {
    $db->table('tender_request_vendors')->insert(['tender_request_id' => $requestId, 'vendor_id' => $vendorId, 'deleted' => 0]);
}
$fallback = $selector->withRequestFallback($selector->validate([]), $requestId);
$same($ids($fallback), [$v['match']]);
$same($ids($selector->withRequestFallback($selection, $requestId)), [$v['match'], $v['extra']]);
$same($ids($selector->withRequestFallback($selector->validate([]), 0)), []);
echo "PASS: $checks tender audience database checks; fixture writes rolled back.\n";

} finally { $db->transRollback(); }

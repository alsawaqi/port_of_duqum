<?php
// Exercise the real entry-point catch with real framework exception classes.
// No application database, sessions, or external delivery is booted.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root=dirname(__DIR__);
$temp=sys_get_temp_dir().'/pod_http_'.bin2hex(random_bytes(8));
mkdir($temp.'/app/Config',0777,true); mkdir($temp.'/system');
register_shutdown_function(static function () use($temp): void {
    foreach(['app/Config/Paths.php','system/Boot.php','index.php','run.php','status.txt','error.log'] as $f) {
        if(is_file($temp.'/'.$f))unlink($temp.'/'.$f);
    }
    foreach(['/app/Config','/app','/system',''] as $d)rmdir($temp.$d);
});
copy($root.'/index.php',$temp.'/index.php');
file_put_contents($temp.'/app/Config/Paths.php','<?php namespace Config; class Paths { public $systemDirectory=__DIR__."/../../system"; }');
$system=var_export(str_replace('\\','/',$root).'/system/',true);
file_put_contents($temp.'/system/Boot.php','<?php namespace CodeIgniter;
spl_autoload_register(static function($class) { if(str_starts_with($class,"CodeIgniter\\\\"))require '.$system.'.str_replace("\\\\","/",substr($class,12)).".php"; });
class Boot { static function bootWeb($paths) {
    $code=(int)$_SERVER["QA_STATUS"];
    if($code===404)throw new \\CodeIgniter\\Exceptions\\PageNotFoundException("synthetic-secret",404);
    if($code===403)throw new \\CodeIgniter\\Security\\Exceptions\\SecurityException("synthetic-secret",403);
    throw new \\RuntimeException("synthetic-secret");
} }');
file_put_contents($temp.'/run.php','<?php
$_SERVER["QA_STATUS"]=$argv[1]; $_SERVER["REQUEST_METHOD"]="POST";
$_SERVER["REQUEST_URI"]="/index.php/guest_vendor/save/12345?password=hidden-query";
if($argv[2]==="json")$_SERVER["HTTP_X_REQUESTED_WITH"]="XMLHttpRequest";
register_shutdown_function(static function(){file_put_contents(__DIR__."/status.txt",(string)http_response_code());});
require __DIR__."/index.php";');
$n=0;$check=static function($ok,$label)use(&$n){$n++;if(!$ok)throw new RuntimeException($label);};
foreach([404,403,500] as $status)foreach(['json','text'] as $format){
    $process=proc_open([PHP_BINARY,'-d','error_log='.$temp.'/error.log',$temp.'/run.php',(string)$status,$format],
        [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,$root);
    fclose($pipes[0]);$output=stream_get_contents($pipes[1]);fclose($pipes[1]);
    $err=stream_get_contents($pipes[2]);fclose($pipes[2]);proc_close($process);
    $check((int)file_get_contents($temp.'/status.txt')===$status,'Correct HTTP status '.$status.' '.$format);
    $check(!str_contains($output.$err,'synthetic-secret')&&!str_contains($output,$temp),'No internal error disclosure');
    if($format==='json'){
        $json=json_decode($output,true,512,JSON_THROW_ON_ERROR);
        $check($json['success']===false && $json['csrf_expired']===($status===403),'AJAX failure classification');
        $check($status!==403||str_contains($json['message'],'Refresh'),'Expired form gives recovery instruction');
    } else $check(!str_starts_with($output,'{')&&strlen($output)>20,'Readable browser error');
}
$log=file_get_contents($temp.'/error.log');
$check(str_contains($log,'HTTP 404 POST /guest_vendor/save'),'Diagnostic identifies route and status');
$check(!str_contains($log,'hidden-query')&&!str_contains($log,'synthetic-secret')&&!str_contains($log,'12345'),'Diagnostics exclude query, message and record identifier');
echo "HTTP failure handling: $n checks passed.\n";

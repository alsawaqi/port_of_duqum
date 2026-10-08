<?php
if (PHP_SAPI !== 'cli') { exit; }
chdir(dirname(__DIR__));
define('FCPATH', getcwd().DIRECTORY_SEPARATOR); define('ENVIRONMENT','development'); define('CI_DEBUG',true);
require 'app/Config/Paths.php'; require 'system/Boot.php';
class GatePassPdfTestBoot extends CodeIgniter\Boot {
    static function init(): void {
        static::definePathConstants(new Config\Paths()); static::loadConstants();
        static::loadCommonFunctions(); static::loadAutoloader();
    }
}
GatePassPdfTestBoot::init(); helper(['general','date_time','url','language','form']);
config('Rise')->app_settings_array=['language'=>'english','timezone'=>'Asia/Muscat','date_format'=>'Y-m-d'];
$n=0; $check=static function($ok,$label) use (&$n): void { $n++; if (!$ok) throw new RuntimeException($label); };
set_error_handler(static function($level,$message,$file,$line) {
    if (error_reporting() & $level) throw new ErrorException($message,0,$level,$file,$line);
});
// Test every output pixel against the QR encoder matrix, including quiet zone.
foreach (['qa-token',str_repeat('a',128),'https://example.invalid/scan?token=qa'] as $token) {
    mt_srand(1729); // TCPDF randomly samples QR masks; compare the same mask.
    $png=App\Libraries\Gate_pass_qr::png($token,2);
    $check(substr($png,0,8)==="\x89PNG\r\n\x1a\n",'PNG signature');
    $compressed='';$size=0;
    for($offset=8;$offset<strlen($png);) {
        $length=unpack('N',substr($png,$offset,4))[1];$type=substr($png,$offset+4,4);$data=substr($png,$offset+8,$length);
        $check(pack('N',crc32($type.$data))===substr($png,$offset+8+$length,4),'Valid PNG chunk checksum');
        if($type==='IHDR') { $size=unpack('N',$data)[1]; }
        if($type==='IDAT') { $compressed.=$data; }
        $offset+=12+$length;
    }
    $raw=gzuncompress($compressed);mt_srand(1729);$matrix=(new TCPDF2DBarcode($token,'QRCODE,H'))->getBarcodeArray();
    $check(strlen($raw)===($size+1)*$size,'Complete uncompressed PNG image');
    for($y=0;$y<$size;$y++) {
        $row=substr($raw,$y*($size+1),$size+1);$check($row[0]==="\x00",'Unfiltered grayscale row');
        for($x=0;$x<$size;$x++) {
            $my=intdiv($y,2)-4;$mx=intdiv($x,2)-4;
            $expected=$my>=0&&$mx>=0&&!empty($matrix['bcode'][$my][$mx])?"\x00":"\xff";
            if($row[$x+1]!==$expected) throw new RuntimeException('QR pixel mismatch');
        }
    }
    $check(true,'Every QR pixel including white border matches encoder');
}
foreach (['english','arabic'] as $locale) {
    service('language')->setLocale($locale);
    $check(gate_pass_option_label('visitor')===($locale==='arabic'?'زائر':'Visitor'),'Translated fixed option');
    $check(gate_pass_option_label('Training')===($locale==='arabic'?'تدريب':'Training'),'Translated standard purpose');
    $check(gate_pass_option_label('My custom company')==='My custom company','Custom master names preserved');
    $check(gate_pass_visit_duration_label('2026-09-01','2026-09-03')===($locale==='arabic'?'3 أيام':'3 days'),'Translated duration');
    $inboxRequest=(object)['id'=>1,'reference'=>'DATES-QA','company_name'=>'QA','department_name'=>'IT',
        'purpose_name'=>'Training','visit_from'=>'2026-09-01 00:00:00','visit_to'=>'2026-09-03 23:59:59',
        'status'=>'security_approved','stage'=>'rop','fee_amount'=>3,'currency'=>'OMR'];
    foreach (['Gate_pass_department_requests','Gate_pass_commercial_inbox','Gate_pass_security_inbox',
        'Gate_pass_rop_inbox','Gate_pass_request_list'] as $controllerName) {
        $inboxClass=new ReflectionClass('App\\Controllers\\'.$controllerName);
        $inbox=$inboxClass->newInstanceWithoutConstructor();
        $row=$inboxClass->getMethod('_make_row')->invoke($inbox,$inboxRequest);
        $rowText=implode(' ', $row);
        $check(str_contains($rowText,'2026-09-01'), "$controllerName keeps the visit start date");
        $check(str_contains($rowText,'2026-09-03') && !str_contains($rowText,'2026-09-04'),
            "$controllerName does not display tomorrow as the visit end date");
    }
    $request=(object)['id'=>1,'reference'=>'PDF-QA','visit_from'=>'2026-09-01','visit_to'=>'2026-09-03 23:59:59','request_type'=>'person','fee_amount'=>3,'currency'=>'OMR','company_name'=>'QA Company','purpose_name'=>'Training','department_name'=>'IT','status'=>'rop_approved','stage'=>'issued'];
    $pass=(object)['id'=>1,'gate_pass_request_id'=>1,'status'=>'active','gate_pass_no'=>'QA-PASS','qr_token'=>'qa-token-only','valid_from'=>'2026-09-01','valid_to'=>'2026-09-03'];
    $details=App\Libraries\Gate_pass_pdf::details($request,$pass,[],[]);
    $check($details['to']==='2026-09-03','Calendar end date does not shift to the next day');
    $pdf=(new App\Libraries\Gate_pass_pdf())->build($request,$pass,[],[]);
    $check($pdf->getNumPages()===2,'The gate pass and HSSE instructions are two pages');
    $binary=$pdf->Output('qa.pdf','S');
    $check(str_starts_with($binary,'%PDF-') && strlen($binary)>2000,"$locale PDF renders");
    $check(str_replace('\\','/',K_PATH_CACHE)===str_replace('\\','/',WRITEPATH.'cache/pdf/'),'PDF uses application writable cache');
}
echo "GatePassDownloadPortabilityTest: $n checks passed; QR pixel checks and EN/AR PDF rendering.\n";

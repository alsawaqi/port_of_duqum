<?php
declare(strict_types=1);

// The real policy runs against SQLite fixtures. Upload transport is a small test double;
// real multipart uploads and content validation are also exercised through HTTP locally.
namespace CodeIgniter\Database { abstract class BaseConnection {} }
namespace CodeIgniter\HTTP\Files {
    class UploadedFile {
        public function __construct(private int $error = UPLOAD_ERR_OK, private bool $moved = false) {}
        public function getError(): int { return $this->error; }
        public function isValid(): bool { return $this->error === UPLOAD_ERR_OK; }
        public function hasMoved(): bool { return $this->moved; }
    }
}
namespace {
    require __DIR__ . '/../app/Libraries/Vendor_registration_documents.php';
    function app_lang(string $key): string { return $key; }
    final class DocumentsDb extends \CodeIgniter\Database\BaseConnection {
        public PDO $pdo;
        public function __construct() {
            $this->pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $this->pdo->exec('CREATE TABLE pod_vendor_document_types (id INTEGER PRIMARY KEY, name TEXT, code TEXT, vendor_group_id INTEGER, is_required INTEGER, is_active INTEGER DEFAULT 1, deleted INTEGER DEFAULT 0)');
            $this->pdo->exec("INSERT INTO pod_vendor_document_types (id,name,code,vendor_group_id,is_required,is_active,deleted) VALUES
                (1,'Global CR','CR',NULL,1,1,0),(2,'Group A certificate','A',10,1,1,0),
                (3,'Optional A','AO',10,0,1,0),(4,'Group B certificate','B',20,1,1,0),
                (5,'Inactive A','AI',10,1,0,0),(6,'Deleted A','AD',10,1,1,1),
                (7,'Riyadha','RIYADHA',NULL,0,1,0)");
        }
        public function prefixTable(string $name): string { return 'pod_' . $name; }
        public function query(string $sql, array $binds): object {
            $statement = $this->pdo->prepare($sql); $statement->execute($binds);
            return new class($statement) {
                public function __construct(private PDOStatement $statement) {}
                public function getResult(): array { return $this->statement->fetchAll(PDO::FETCH_OBJ); }
            };
        }
    }
    $db = new DocumentsDb(); $policy = new \App\Libraries\Vendor_registration_documents($db);
    $checks = 0;
    $check = static function (bool $ok, string $label) use (&$checks): void {
        $checks++; if (!$ok) { throw new RuntimeException($label); }
    };
    $reject = static function (callable $action, string $label) use ($check): void {
        try { $action(); } catch (DomainException $e) { $check(true, $label); return; }
        $check(false, $label);
    };
    $file = static fn(int $error = UPLOAD_ERR_OK, bool $moved = false) => new \CodeIgniter\HTTP\Files\UploadedFile($error, $moved);
    $a = $policy->definitions(10); $b = $policy->definitions(20);
    $check(isset($a[1],$a[2],$a[3],$a[7]) && count($a)===4, 'Group A includes global, own and optional types only');
    $check(isset($b[1],$b[4],$b[7]) && count($b)===3, 'Switching to B changes the applicable types');
    $check($a[1]['required'] && $a[2]['required'] && !$a[3]['required'], 'Admin required flags are respected');
    $check(!isset($a[5],$a[6]), 'Inactive and deleted document types are excluded');
    $reject(fn()=>$policy->submission($a,[],[]), 'All required files are enforced');
    $reject(fn()=>$policy->submission($a,[],['registration_file_1'=>$file()]), 'One required upload cannot satisfy another type');
    $uploads=['registration_file_1'=>$file(),'registration_file_2'=>$file()];
    $rows=$policy->submission($a,[],$uploads);
    $check(count($rows)===2 && $rows[0]['issued_at']===null && $rows[0]['expires_at']===null, 'Required files save without optional dates or files');
    $check(count($policy->submission($a,[],$uploads+['registration_file_3'=>$file(UPLOAD_ERR_NO_FILE)]))===2, 'An empty optional upload is skipped');
    $rows=$policy->submission($a,['registration_issued_at'=>[2=>'2026-01-01'],'registration_expires_at'=>[2=>'2027-01-01']],$uploads);
    $check($rows[1]['issued_at']==='2026-01-01' && $rows[1]['expires_at']==='2027-01-01', 'Dates stay attached to the correct fixed type');
    foreach ([4,5,6,999] as $id) {
        $reject(fn()=>$policy->submission($a,[],$uploads+['registration_file_'.$id=>$file()]), 'Foreign inactive deleted or unknown fixed type refused');
        $reject(fn()=>$policy->submission($a,['vendor_document_type_id'=>[$id]],$uploads+['file'=>[$file()]]), 'Foreign inactive deleted or unknown additional type refused');
    }
    $rows=$policy->submission($a,['vendor_document_type_id'=>[3,1],'issued_at'=>['','2025-01-01']],$uploads+['file'=>[$file(),$file()]]);
    $check(count($rows)===4 && $rows[2]['vendor_document_type_id']===3 && $rows[3]['issued_at']==='2025-01-01', 'Additional files, including another copy of a type, retain their own metadata');
    $reject(fn()=>$policy->submission($a,['vendor_document_type_id'=>[3]],$uploads), 'Selected additional type without a file is refused');
    $reject(fn()=>$policy->submission($a,[],$uploads+['file'=>[$file()]]), 'File without a type is refused');
    foreach ([UPLOAD_ERR_INI_SIZE,UPLOAD_ERR_PARTIAL,UPLOAD_ERR_NO_FILE] as $error) {
        $reject(fn()=>$policy->submission($a,[],['registration_file_1'=>$file(),'registration_file_2'=>$file($error)]), 'Required invalid upload is refused');
    }
    $reject(fn()=>$policy->submission($a,[],['registration_file_1'=>$file(),'registration_file_2'=>$file(0,true)]), 'Moved upload cannot be reused');
    $reject(fn()=>$policy->submission($a,['registration_issued_at'=>[3=>'2026-01-01']],$uploads), 'Optional dates without file are not silently discarded');
    foreach (['2026-02-31','0000-00-00','2026-9-1','bad',['nested']] as $date) {
        $reject(fn()=>$policy->submission($a,['registration_issued_at'=>[1=>$date]],$uploads), 'Malformed dates are refused before SQL');
    }
    $reject(fn()=>$policy->submission($a,['registration_issued_at'=>[1=>'2026-02-01'],'registration_expires_at'=>[1=>'2026-01-01']],$uploads), 'Expiry cannot precede issue date');
    $waiver=$policy->definitions(10,7);
    $check(count($waiver)===4 && $waiver[7]['required'], 'Waiver forces existing Riyadha required without another row');
    $reject(fn()=>$policy->submission($waiver,[],$uploads,7), 'Waiver cannot omit Riyadha');
    $rows=$policy->submission($waiver,[],$uploads+['registration_file_7'=>$file()],7);
    $check(count($rows)===3 && count(array_filter($rows,fn($row)=>$row['registration_riyada']))===1, 'One Riyadha upload links to the waiver review');
    $rows=$policy->submission($waiver,[],$uploads+['riyada_file'=>$file()],7);
    $check(count($rows)===3 && $rows[2]['registration_riyada'], 'An open point-1 form still obeys all current requirements');
    $reject(fn()=>$policy->definitions(10,4), 'Waiver cannot use another group document');
    $db->pdo->exec('UPDATE pod_vendor_document_types SET is_required=1 WHERE id=3');
    $reject(fn()=>$policy->submission($policy->definitions(10),[],$uploads), 'New requirements set after page load are enforced');
    $db->pdo->exec('UPDATE pod_vendor_document_types SET is_required=0');
    $check($policy->submission($policy->definitions(10),[],[])===[], 'No required types means documents may be omitted');
    echo "Vendor registration documents: {$checks} checks passed. SQLite fixtures; no network.\n";
}

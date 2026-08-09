<?php

$root = dirname(__DIR__);
require_once $root . '/app/Helpers/safe_serialization_helper.php';

final class UnsafeUnserializeProbe
{
    public static $wakeupCalled = false;

    public function __wakeup()
    {
        self::$wakeupCalled = true;
    }
}

$assertTrue = static function ($condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, 'FAIL: ' . $message . PHP_EOL);
        exit(1);
    }
};

$arrayValue = [
    'id' => 17,
    'roles' => ['chairman', 'secretary'],
    'enabled' => true,
];
$assertTrue(
    safe_unserialize(serialize($arrayValue)) === $arrayValue,
    'safe_unserialize must preserve valid serialized arrays'
);
$assertTrue(
    safe_unserialize('b:0;') === false,
    'safe_unserialize must preserve a serialized false value'
);
$assertTrue(
    safe_unserialize('not serialized') === false,
    'malformed serialized input must fail closed'
);
$assertTrue(
    safe_unserialize('') === false && safe_unserialize(null) === false && safe_unserialize([]) === false,
    'empty and non-string inputs must fail closed'
);

$serializedObject = serialize(new UnsafeUnserializeProbe());
$decodedObject = safe_unserialize($serializedObject);
$assertTrue(
    UnsafeUnserializeProbe::$wakeupCalled === false,
    'safe_unserialize must not instantiate an allowed class or invoke __wakeup'
);
$assertTrue(
    is_object($decodedObject) && get_class($decodedObject) === '__PHP_Incomplete_Class',
    'serialized objects must be decoded only as incomplete classes'
);

$helperPath = realpath($root . '/app/Helpers/safe_serialization_helper.php');
$helperSource = (string) file_get_contents($helperPath);
$assertTrue(
    strpos($helperSource, "\\unserialize(\$value, ['allowed_classes' => false])") !== false,
    'the helper must force allowed_classes=false on the built-in decoder'
);
$assertTrue(
    strpos($helperSource, 'return safe_unserialize(') === false,
    'the helper must call the PHP built-in and must not recurse'
);

$autoloadSource = (string) file_get_contents($root . '/app/Config/Autoload.php');
$assertTrue(
    strpos($autoloadSource, "public \$helpers = ['safe_serialization'];") !== false,
    'the safe serialization helper must be globally autoloaded'
);

$unsafeCalls = [];
$safeCallCount = 0;
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root . '/app', FilesystemIterator::SKIP_DOTS)
);
foreach ($iterator as $file) {
    if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') {
        continue;
    }

    $path = $file->getPathname();
    $normalizedPath = str_replace('\\', '/', $path);
    if (strpos($normalizedPath, '/app/ThirdParty/') !== false || realpath($path) === $helperPath) {
        continue;
    }

    $source = (string) file_get_contents($path);
    $safeCallCount += preg_match_all('/\bsafe_unserialize\s*\(/i', $source);
    if (preg_match('/(?<![A-Za-z0-9_])unserialize\s*\(/i', $source)) {
        $unsafeCalls[] = $normalizedPath;
    }
}

$assertTrue(
    !$unsafeCalls,
    'application-owned PHP must not call unserialize directly: ' . implode(', ', $unsafeCalls)
);
$assertTrue(
    $safeCallCount >= 170,
    'the application-owned deserialization call sites must use safe_unserialize'
);

fwrite(STDOUT, 'Safe unserialize tests passed.' . PHP_EOL);

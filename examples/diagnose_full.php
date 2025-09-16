<?php

require __DIR__ . '/../vendor/autoload.php';

use GuzzleHttp\Psr7\Utils;

// Пути к файлам
$originalSidecarPath = __DIR__ . '/../samples/VIDEO.sidecar';
$mySidecarPath       = __DIR__ . '/../samples/VIDEO.sidecar.mine';
$encryptedPath       = __DIR__ . '/../samples/VIDEO.encrypted';
$originalVideoPath   = __DIR__ . '/../samples/VIDEO.original';
$keyPath             = __DIR__ . '/../samples/VIDEO.key';

// Загрузка ключа
$key = file_get_contents($keyPath);

// Загрузка sidecar
$originalSidecar = file_get_contents($originalSidecarPath);
$mySidecar       = file_get_contents($mySidecarPath);

// 1️⃣ IV Comparison
echo "=== IV Comparison ===\n";
$originalIV = substr($originalSidecar, 0, 16);
$myIV       = substr($mySidecar, 0, 16);
if ($originalIV === $myIV) {
    echo "IV matches ✅\n";
} else {
    echo "IV differs ❌\n";
    echo "Original: " . bin2hex($originalIV) . "\n";
    echo "Mine:     " . bin2hex($myIV) . "\n";
}

// 2️⃣ Payload Comparison
echo "\n=== Payload Comparison ===\n";
$originalPayload = substr($originalSidecar, 16);
$myPayload       = substr($mySidecar, 16);

$minLen = min(strlen($originalPayload), strlen($myPayload));
$diffCount = 0;
for ($i = 0; $i < $minLen; $i++) {
    if ($originalPayload[$i] !== $myPayload[$i]) $diffCount++;
}

if ($diffCount === 0 && strlen($originalPayload) === strlen($myPayload)) {
    echo "Sidecar payload matches ✅\n";
} else {
    echo "Sidecar payload differs ❌\n";
    echo "Original payload length: " . strlen($originalPayload) . "\n";
    echo "My payload length:       " . strlen($myPayload) . "\n";
    echo "Different bytes count:   $diffCount\n";
}

// 3️⃣ HMAC Comparison (last 10 bytes)
echo "\n=== Sidecar HMAC Comparison ===\n";
$originalHmac10 = substr($originalPayload, -10);
$myHmac10       = substr($myPayload, -10);

if ($originalHmac10 === $myHmac10) {
    echo "Sidecar HMAC matches ✅\n";
} else {
    echo "Sidecar HMAC differs ❌\n";
    echo "Original: " . bin2hex($originalHmac10) . "\n";
    echo "Mine:     " . bin2hex($myHmac10) . "\n";
}

// 4️⃣ Padding Analysis
echo "\n=== Padding Analysis ===\n";
$lastByteOrig = ord(substr($originalPayload, -11, 1)); // last byte before 10-byte HMAC
$lastByteMine = ord(substr($myPayload, -11, 1));
echo "Original PKCS7 pad length: $lastByteOrig\n";
echo "My PKCS7 pad length:       $lastByteMine\n";

// 5️⃣ Compare encrypted video file vs decrypted payload from original sidecar
echo "\n=== Encrypted Video vs Original Sidecar Payload ===\n";
$encryptedData = file_get_contents($encryptedPath);
$sidecarPayloadFromOriginal = $originalPayload; // decrypted?

$minLen = min(strlen($encryptedData), strlen($sidecarPayloadFromOriginal));
$diffCount = 0;
for ($i = 0; $i < $minLen; $i++) {
    if ($encryptedData[$i] !== $sidecarPayloadFromOriginal[$i]) $diffCount++;
}

if ($diffCount === 0 && strlen($encryptedData) === strlen($sidecarPayloadFromOriginal)) {
    echo "Encrypted video matches original sidecar payload ✅\n";
} else {
    echo "Encrypted video differs from original sidecar payload ❌\n";
    echo "Encrypted length: " . strlen($encryptedData) . "\n";
    echo "Sidecar payload length: " . strlen($sidecarPayloadFromOriginal) . "\n";
    echo "Different bytes count: $diffCount\n";
}

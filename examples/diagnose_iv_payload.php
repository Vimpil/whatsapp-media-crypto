<?php
require __DIR__ . '/../vendor/autoload.php';

function hexPreview(string $data, int $length = 32): string {
    return substr(bin2hex($data), 0, $length * 2);
}

$originalSidecar = __DIR__ . '/../samples/VIDEO.sidecar';
$mineSidecar = __DIR__ . '/../samples/VIDEO.sidecar.mine';
$keyFile = __DIR__ . '/../samples/VIDEO.key';

$origData = file_get_contents($originalSidecar);
$mineData = file_get_contents($mineSidecar);
$key = file_get_contents($keyFile);

if (strlen($origData) < 16 || strlen($mineData) < 16) {
    die("Sidecar too short!\n");
}

// Extract IVs
$origIv = substr($origData, 0, 16);
$mineIv = substr($mineData, 0, 16);

echo "=== IV Comparison ===\n";
echo "Original IV: " . bin2hex($origIv) . "\n";
echo "Mine IV:     " . bin2hex($mineIv) . "\n";
echo ($origIv === $mineIv ? "✅ IV matches\n" : "❌ IV differs\n");

$origPayload = substr($origData, 16);
$minePayload = substr($mineData, 16);

echo "\n=== Payload Comparison ===\n";
$minLen = min(strlen($origPayload), strlen($minePayload));
$diffCount = 0;
for ($i = 0; $i < $minLen; $i++) {
    if ($origPayload[$i] !== $minePayload[$i]) $diffCount++;
}
echo "Original payload length: " . strlen($origPayload) . "\n";
echo "Mine payload length:     " . strlen($minePayload) . "\n";
echo "Different bytes count:   $diffCount\n";

echo "\nFirst 32 bytes comparison (hex):\n";
echo "Original: " . hexPreview($origPayload) . "\n";
echo "Mine:     " . hexPreview($minePayload) . "\n";

// Optional: show more bytes if needed
$showMore = 64;
echo "\nFirst $showMore bytes (hex) of original vs mine:\n";
echo "Original: " . hexPreview($origPayload, $showMore) . "\n";
echo "Mine:     " . hexPreview($minePayload, $showMore) . "\n";

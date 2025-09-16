<?php
require __DIR__ . '/../vendor/autoload.php';

use GuzzleHttp\Psr7\Utils;
use WhatsAppMedia\StreamFactory;

// File paths
$originalVideoPath = __DIR__ . '/../samples/original/VIDEO.original';
$keyPath = __DIR__ . '/../samples/original/VIDEO.key';
$outputEncryptedPath = __DIR__ . '/../samples/VIDEO.encrypted';
$outputSidecarPath = __DIR__ . '/../samples/VIDEO.sidecar';

try {
    // Check if source files exist
    if (!file_exists($originalVideoPath)) {
        throw new \RuntimeException("Source file not found: $originalVideoPath");
    }
    if (!file_exists($keyPath)) {
        throw new \RuntimeException("Key file not found: $keyPath");
    }

    // Read the key
    $mediaKey = file_get_contents($keyPath);

    // Open source file
    $source = Utils::streamFor(fopen($originalVideoPath, 'rb'));

    // Create output directory if it doesn't exist
    $outputDir = dirname($outputEncryptedPath);
    if (!is_dir($outputDir)) {
        mkdir($outputDir, 0777, true);
    }

    // Create encrypting stream with sidecar generation
    $encStream = StreamFactory::createEncryptingStream(
        $source,
        $mediaKey,
        'VIDEO',
        true // enable sidecar generation
    );

    // Write encrypted data
    $outputFile = fopen($outputEncryptedPath, 'wb');
    try {
        while (!$encStream->eof()) {
            $data = $encStream->read(8192);
            if ($data === '') {
                break;
            }
            fwrite($outputFile, $data);
        }
    } finally {
        fclose($outputFile);
    }

    // Save the sidecar after complete encryption
    file_put_contents($outputSidecarPath, $encStream->getSidecar());

    echo "Video successfully encrypted: $outputEncryptedPath\n";
    echo "Sidecar saved: $outputSidecarPath\n";

    // Check file sizes
    $originalSize = filesize($originalVideoPath);
    $encryptedSize = filesize($outputEncryptedPath);
    $sidecarSize = filesize($outputSidecarPath);

    echo "\nFile information:\n";
    echo "Original file size: " . number_format($originalSize) . " bytes\n";
    echo "Encrypted file size: " . number_format($encryptedSize) . " bytes\n";
    echo "Sidecar size: " . number_format($sidecarSize) . " bytes\n";

} catch (\InvalidArgumentException $e) {
    echo "Validation error: " . $e->getMessage() . "\n";
    exit(1);
} catch (\RuntimeException $e) {
    echo "Encryption error: " . $e->getMessage() . "\n";
    exit(1);
}

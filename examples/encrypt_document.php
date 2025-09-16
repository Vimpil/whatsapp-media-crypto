<?php
require __DIR__ . '/../vendor/autoload.php';

use GuzzleHttp\Psr7\Utils;
use WhatsAppMedia\StreamFactory;

// File paths
$originalDocumentPath = __DIR__ . '/../samples/original/DOCUMENT.original';
$keyPath = __DIR__ . '/../samples/original/DOCUMENT.key';
$outputEncryptedPath = __DIR__ . '/../samples/DOCUMENT.encrypted';

try {
    // Check if source files exist
    if (!file_exists($originalDocumentPath)) {
        throw new \RuntimeException("Source file not found: $originalDocumentPath");
    }
    if (!file_exists($keyPath)) {
        throw new \RuntimeException("Key file not found: $keyPath");
    }

    // Read the key
    $mediaKey = file_get_contents($keyPath);

    // Open the source file
    $source = Utils::streamFor(fopen($originalDocumentPath, 'rb'));

    // Create the output directory if it doesn't exist
    $outputDir = dirname($outputEncryptedPath);
    if (!is_dir($outputDir)) {
        mkdir($outputDir, 0777, true);
    }

    // Create the encrypting stream via the factory
    $encStream = StreamFactory::createEncryptingStream(
        $source,
        $mediaKey,
        'DOCUMENT'
    );

    // Write the encrypted data
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

    // Check file sizes
    $originalSize = filesize($originalDocumentPath);
    $encryptedSize = filesize($outputEncryptedPath);

    echo "Document successfully encrypted: $outputEncryptedPath\n";
    echo "\nFile information:\n";
    echo "Original file size: " . number_format($originalSize) . " bytes\n";
    echo "Encrypted file size: " . number_format($encryptedSize) . " bytes\n";

} catch (\InvalidArgumentException $e) {
    echo "Validation error: " . $e->getMessage() . "\n";
    exit(1);
} catch (\RuntimeException $e) {
    echo "Encryption error: " . $e->getMessage() . "\n";
    exit(1);
}

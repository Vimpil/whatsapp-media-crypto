<?php
require __DIR__ . '/../vendor/autoload.php';

use GuzzleHttp\Psr7\Utils;
use WhatsAppMedia\StreamFactory;

// File paths
$encryptedVideoPath = __DIR__ . '/../samples/original/VIDEO.encrypted';
$keyPath = __DIR__ . '/../samples/original/VIDEO.key';
$outputDecryptedPath = __DIR__ . '/../samples/VIDEO.decrypted';

try {
    // Check if files exist
    if (!file_exists($encryptedVideoPath)) {
        throw new \RuntimeException("Encrypted file not found: $encryptedVideoPath");
    }
    if (!file_exists($keyPath)) {
        throw new \RuntimeException("Key file not found: $keyPath");
    }

    // Read the key and create a stream for the encrypted file
    $mediaKey = file_get_contents($keyPath);
    $source = Utils::streamFor(fopen($encryptedVideoPath, 'rb'));

    // Create the decrypting stream
    $decStream = StreamFactory::createDecryptingStream(
        $source,
        $mediaKey,
        'VIDEO'
    );

    // Create the output directory if it doesn't exist
    $outputDir = dirname($outputDecryptedPath);
    if (!is_dir($outputDir)) {
        mkdir($outputDir, 0777, true);
    }

    // Write the decrypted data
    $outputFile = fopen($outputDecryptedPath, 'wb');
    try {
        while (!$decStream->eof()) {
            $data = $decStream->read(8192);
            if ($data === '') {
                break;
            }
            fwrite($outputFile, $data);
        }
    } finally {
        fclose($outputFile);
    }

    echo "Video successfully decrypted: $outputDecryptedPath\n";

} catch (\InvalidArgumentException $e) {
    echo "Validation error: " . $e->getMessage() . "\n";
    exit(1);
} catch (\RuntimeException $e) {
    echo "Decryption error: " . $e->getMessage() . "\n";
    exit(1);
}

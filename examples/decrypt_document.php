<?php
require __DIR__ . '/../vendor/autoload.php';

use GuzzleHttp\Psr7\Utils;
use WhatsAppMedia\StreamFactory;

// Paths to the encrypted document and key
$encryptedDocumentPath = __DIR__ . '/../samples/DOCUMENT.encrypted';
$keyPath = __DIR__ . '/../samples/original/DOCUMENT.key';
$outputDecryptedPath = __DIR__ . '/../samples/DOCUMENT.decrypted';

try {
    // Check if the encrypted file and key exist
    if (!file_exists($encryptedDocumentPath)) {
        throw new \RuntimeException("Encrypted file not found: $encryptedDocumentPath");
    }
    if (!file_exists($keyPath)) {
        throw new \RuntimeException("Key file not found: $keyPath");
    }

    // Read the media key
    $mediaKey = file_get_contents($keyPath);

    // Open the encrypted document file
    $source = Utils::streamFor(fopen($encryptedDocumentPath, 'rb'));

    // Create the decrypting stream via factory
    $decStream = StreamFactory::createDecryptingStream(
        $source,
        $mediaKey,
        'DOCUMENT'
    );

    // Create the output directory if it doesn't exist
    $outputDir = dirname($outputDecryptedPath);
    if (!is_dir($outputDir)) {
        mkdir($outputDir, 0777, true);
    }

    // Write the decrypted data to a new file
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

    echo "Document successfully decrypted: $outputDecryptedPath\n";

} catch (\InvalidArgumentException $e) {
    echo "Validation error: " . $e->getMessage() . "\n";
    exit(1);
} catch (\RuntimeException $e) {
    echo "Decryption error: " . $e->getMessage() . "\n";
    exit(1);
}

<?php

require __DIR__ . '/../vendor/autoload.php';

use GuzzleHttp\Psr7\Utils;
use WhatsAppMedia\MediaKey;
use WhatsAppMedia\Stream\EncryptingStream;
use WhatsAppMedia\Stream\SidecarGenerator;

// Paths to the original video and key
$originalVideoPath = __DIR__ . '/../samples/VIDEO.original';
$keyPath = __DIR__ . '/../samples/VIDEO.key';
$outputEncryptedPath = __DIR__ . '/../samples/VIDEO.encrypted';
$sidecarPath = __DIR__ . '/../samples/VIDEO.sidecar.mine';

// Read the media key
$mediaKey = file_get_contents($keyPath);
$parts = MediaKey::expand($mediaKey, 'VIDEO');

// Open the original video file
$source = Utils::streamFor(fopen($originalVideoPath, 'rb'));

// Create the encrypting stream
$encStream = new EncryptingStream($source, $parts['cipherKey'], $parts['macKey'], $parts['iv']);

// Write the encrypted data to a new file
$outputFile = fopen($outputEncryptedPath, 'wb');
while (!$encStream->eof()) {
    fwrite($outputFile, $encStream->read(8192));
}
fclose($outputFile);

// Generate sidecar file for streaming
$sidecarGenerator = new SidecarGenerator(Utils::streamFor(fopen($outputEncryptedPath, 'rb')), $parts['macKey']);
$sidecarGenerator->generateSidecar($sidecarPath);

// Output success messages
echo "Encrypted video saved to: $outputEncryptedPath\n";
echo "Sidecar file saved to: $sidecarPath\n";


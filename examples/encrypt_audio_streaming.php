<?php

require __DIR__ . '/../vendor/autoload.php';

use GuzzleHttp\Psr7\Utils;
use WhatsAppMedia\MediaKey;
use WhatsAppMedia\Stream\EncryptingStream;
use WhatsAppMedia\Stream\SidecarGenerator;

// Paths to the original audio and key
$originalAudioPath = __DIR__ . '/../samples/AUDIO.original';
$keyPath = __DIR__ . '/../samples/AUDIO.key';
$outputEncryptedPath = __DIR__ . '/../samples/AUDIO.encrypted';
$sidecarPath = __DIR__ . '/../samples/AUDIO.sidecar.mine';

// Read the media key
$mediaKey = file_get_contents($keyPath);
$parts = MediaKey::expand($mediaKey, 'AUDIO');

// Open the original audio file
$source = Utils::streamFor(fopen($originalAudioPath, 'rb'));

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
echo "Encrypted audio saved to: $outputEncryptedPath\n";
echo "Sidecar file saved to: $sidecarPath\n";


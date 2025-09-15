<?php
require __DIR__ . '/../vendor/autoload.php';

use GuzzleHttp\Psr7\Utils;
use WhatsAppMedia\MediaKey;
use WhatsAppMedia\Stream\DecryptingStream;

// Load the encrypted file and key
$encryptedFile = __DIR__ . '/../samples/VIDEO.encrypted';
$keyFile = __DIR__ . '/../samples/VIDEO.key';
$outputFile = __DIR__ . '/../samples/VIDEO.generated.mine';

$mediaKey = file_get_contents($keyFile);
$parts = MediaKey::expand($mediaKey, 'VIDEO');

// Open the encrypted file for reading
$encStream = Utils::streamFor(fopen($encryptedFile, 'rb'));

// Create a decrypting stream
$decStream = new DecryptingStream($encStream, $parts['cipherKey'], $parts['macKey'], $parts['iv']);

// Write the decrypted content to the output file
$out = fopen($outputFile, 'wb');
while (!$decStream->eof()) {
    fwrite($out, $decStream->read(8192));
}
fclose($out);

echo "Decryption complete. Output written to VIDEO.generated.mine\n";

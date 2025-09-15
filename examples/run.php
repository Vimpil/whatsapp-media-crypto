<?php
require __DIR__ . '/../vendor/autoload.php';

use GuzzleHttp\Psr7\Utils;
use WhatsAppMedia\MediaKey;
use WhatsAppMedia\Stream\EncryptingStream;
use WhatsAppMedia\Stream\DecryptingStream;

$source = Utils::streamFor(fopen(__DIR__ . '/../samples/video.original', 'rb'));
$mediaKey = file_get_contents(__DIR__ . '/../samples/video.key');
$parts = MediaKey::expand($mediaKey, 'VIDEO');

// Шифрование
$encStream = new EncryptingStream($source, $parts['cipherKey'], $parts['macKey'], $parts['iv']);
file_put_contents(__DIR__ . '/../samples/video.encrypted', $encStream->read(8192));
file_put_contents(__DIR__ . '/../samples/video.sidecar', $encStream->getSidecar());

// Дешифрование
$encStream2 = Utils::streamFor(fopen(__DIR__ . '/../samples/video.encrypted', 'rb'));
$decStream = new DecryptingStream($encStream2, $parts['cipherKey'], $parts['macKey'], $parts['iv']);

$out = fopen(__DIR__ . '/../samples/video.decrypted', 'wb');
while (!$decStream->eof()) {
    fwrite($out, $decStream->read(8192));
}
fclose($out);

// Проверка
if (file_get_contents(__DIR__ . '/../samples/video.original') === file_get_contents(__DIR__ . '/../samples/video.decrypted')) {
    echo "✅ Decryption successful!";
} else {
    echo "❌ Decryption failed!";
}

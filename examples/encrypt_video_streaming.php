<?php
require __DIR__ . '/../vendor/autoload.php';

use GuzzleHttp\Psr7\Utils;
use WhatsAppMedia\MediaKey;
use WhatsAppMedia\Stream\EncryptingStreamWithSidecar;

// Пути к оригинальному видео и mediaKey
$originalVideoPath = __DIR__ . '/../samples/VIDEO.original';
$keyPath = __DIR__ . '/../samples/VIDEO.key';
$outputEncryptedPath = __DIR__ . '/../samples/VIDEO.encrypted';
$sidecarPath = __DIR__ . '/../samples/VIDEO.sidecar.mine';

// Загружаем mediaKey
$mediaKey = file_get_contents($keyPath);

// Расширяем ключ на части для шифрования
$parts = MediaKey::expand($mediaKey, 'VIDEO');

// Берём IV из MediaKey::expand() — это важно!
$iv = $parts['iv'];

// Открываем оригинальный видеофайл
$source = Utils::streamFor(fopen($originalVideoPath, 'rb'));

// Создаём шифрующий поток с sidecar
$encStream = new EncryptingStreamWithSidecar(
    $source,
    $parts['cipherKey'],
    $parts['macKey'],
    $iv
);

// Записываем зашифрованное видео
$outputFile = fopen($outputEncryptedPath, 'wb');
while (!$encStream->eof()) {
    fwrite($outputFile, $encStream->read(8192));
}
fclose($outputFile);

// Генерация sidecar
file_put_contents($sidecarPath, $encStream->getSidecar());

echo "Encrypted video saved to: $outputEncryptedPath\n";
echo "Sidecar file saved to: $sidecarPath\n";

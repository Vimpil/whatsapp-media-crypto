<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/MediaKey.php';
require_once __DIR__ . '/../src/Stream/DecryptingStream.php';
<?php
use WhatsApp\MediaKey;
use WhatsApp\Stream\DecryptingStream;

function decryptImage($inputPath, $outputPath, $keyPath) {
    $key = file_get_contents($keyPath);
    $mediaKey = new MediaKey($key);

    $inputStream = fopen($inputPath, 'rb');
    $outputStream = fopen($outputPath, 'wb');

    $decryptingStream = new DecryptingStream($inputStream, $mediaKey);

    while (!feof($decryptingStream)) {
        fwrite($outputStream, fread($decryptingStream, 8192));
    }

    fclose($decryptingStream);
    fclose($outputStream);

    echo "Image decrypted successfully: $outputPath\n";
}

$inputImage = __DIR__ . '/../samples/IMAGE.encrypted';
$outputImage = __DIR__ . '/../samples/IMAGE.original.decrypted';
$keyFile = __DIR__ . '/../samples/IMAGE.key';

decryptImage($inputImage, $outputImage, $keyFile);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/MediaKey.php';
require_once __DIR__ . '/../src/Stream/EncryptingStream.php';

use WhatsApp\MediaKey;
use WhatsApp\Stream\EncryptingStream;

function encryptImage($inputPath, $outputPath, $keyPath) {
    $key = file_get_contents($keyPath);
    $mediaKey = new MediaKey($key);

    $inputStream = fopen($inputPath, 'rb');
    $outputStream = fopen($outputPath, 'wb');

    $encryptingStream = new EncryptingStream($inputStream, $mediaKey);

    while (!feof($encryptingStream)) {
        fwrite($outputStream, fread($encryptingStream, 8192));
    }

    fclose($encryptingStream);
    fclose($outputStream);

    echo "Image encrypted successfully: $outputPath\n";
}

$inputImage = __DIR__ . '/../samples/IMAGE.original';
$outputImage = __DIR__ . '/../samples/IMAGE.encrypted';
$keyFile = __DIR__ . '/../samples/IMAGE.key';

encryptImage($inputImage, $outputImage, $keyFile);

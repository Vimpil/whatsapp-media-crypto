<?php
require __DIR__ . '/../vendor/autoload.php';

use GuzzleHttp\Psr7\Utils;
use WhatsAppMedia\StreamFactory;

// Пути к файлам
$originalDocumentPath = __DIR__ . '/../samples/original/DOCUMENT.original';
$keyPath = __DIR__ . '/../samples/original/DOCUMENT.key';
$outputEncryptedPath = __DIR__ . '/../samples/DOCUMENT.encrypted';

try {
    // Проверяем существование исходных файлов
    if (!file_exists($originalDocumentPath)) {
        throw new \RuntimeException("Исходный файл не найден: $originalDocumentPath");
    }
    if (!file_exists($keyPath)) {
        throw new \RuntimeException("Файл ключа не найден: $keyPath");
    }

    // Читаем ключ
    $mediaKey = file_get_contents($keyPath);

    // Открываем исходный файл
    $source = Utils::streamFor(fopen($originalDocumentPath, 'rb'));

    // Создаем директорию для выходного файла, если её нет
    $outputDir = dirname($outputEncryptedPath);
    if (!is_dir($outputDir)) {
        mkdir($outputDir, 0777, true);
    }

    // Создаём шифрующий поток через фабрику
    $encStream = StreamFactory::createEncryptingStream(
        $source,
        $mediaKey,
        'DOCUMENT'
    );

    // Записываем зашифрованные данные
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

    // Проверяем размеры файлов
    $originalSize = filesize($originalDocumentPath);
    $encryptedSize = filesize($outputEncryptedPath);

    echo "Документ успешно зашифрован: $outputEncryptedPath\n";
    echo "\nИнформация о файлах:\n";
    echo "Размер исходного файла: " . number_format($originalSize) . " байт\n";
    echo "Размер зашифрованного файла: " . number_format($encryptedSize) . " байт\n";

} catch (\InvalidArgumentException $e) {
    echo "Ошибка валидации: " . $e->getMessage() . "\n";
    exit(1);
} catch (\RuntimeException $e) {
    echo "Ошибка шифрования: " . $e->getMessage() . "\n";
    exit(1);
}

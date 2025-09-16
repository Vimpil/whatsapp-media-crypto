<?php
use PHPUnit\Framework\TestCase;
use GuzzleHttp\Psr7\Utils;
use WhatsAppMedia\MediaKey;
use WhatsAppMedia\StreamFactory;

class CryptoTest extends TestCase
{
    private const SAMPLE_DIR = __DIR__ . '/../samples/original/';

    /**
     * Тестирование базового шифрования/дешифрования для всех типов медиа
     *
     * @dataProvider mediaTypeProvider
     */
    public function testEncryptDecrypt(string $mediaType)
    {
        $original = self::SAMPLE_DIR . "$mediaType.original";
        $mediaKey = file_get_contents(self::SAMPLE_DIR . "$mediaType.key");
        $originalData = file_get_contents($original);

        // Шифрование
        $source = Utils::streamFor(fopen($original, 'rb'));
        $encStream = StreamFactory::createEncryptingStream($source, $mediaKey, $mediaType);
        $encrypted = '';
        while (!$encStream->eof()) {
            $encrypted .= $encStream->read(8192);
        }

        // Дешифрование
        $decStream = StreamFactory::createDecryptingStream(
            Utils::streamFor($encrypted),
            $mediaKey,
            $mediaType
        );
        $decrypted = '';
        while (!$decStream->eof()) {
            $decrypted .= $decStream->read(8192);
        }

        $this->assertNotEmpty($encrypted, "Зашифрованные данные не должны быть пустыми для $mediaType");
        $this->assertTrue(
            hash('sha256', $originalData) === hash('sha256', $decrypted),
            "Расшифрованные данные должны совпадать с оригиналом для $mediaType"
        );
    }

    /**
     * Тестирование генерации и валидации сайдкара для стримящихся медиа
     *
     * @dataProvider streamableMediaTypeProvider
     */
    public function testSidecarGeneration(string $mediaType)
    {
        $original = self::SAMPLE_DIR . "$mediaType.original";
        $mediaKey = file_get_contents(self::SAMPLE_DIR . "$mediaType.key");
        $expectedSidecar = file_get_contents(self::SAMPLE_DIR . "$mediaType.sidecar");

        // Шифрование с генерацией сайдкара
        $source = Utils::streamFor(fopen($original, 'rb'));
        $encStream = StreamFactory::createEncryptingStream(
            $source,
            $mediaKey,
            $mediaType,
            true // включаем генерацию сайдкара
        );

        // Читаем зашифрованные данные, чтобы сгенерировался сайдкар
        while (!$encStream->eof()) {
            $encStream->read(8192);
        }

        $generatedSidecar = $encStream->getSidecar();

        $this->assertNotEmpty($generatedSidecar, "Сайдкар не должен быть пустым для $mediaType");
        $this->assertEquals(
            $expectedSidecar,
            $generatedSidecar,
            "Сгенерированный сайдкар должен совпадать с ожидаемым для $mediaType"
        );
    }

    /**
     * Тест некорректного MAC
     */
    public function testInvalidMac()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('MAC verification failed');

        $mediaKey = file_get_contents(self::SAMPLE_DIR . "VIDEO.key");
        $encrypted = "corrupted data";

        $decStream = StreamFactory::createDecryptingStream(
            Utils::streamFor($encrypted),
            $mediaKey,
            'VIDEO'
        );

        // Попытка чтения должна вызвать исключение
        while (!$decStream->eof()) {
            $decStream->read(8192);
        }
    }

    /**
     * Тест попытки получения сайдкара для неподдерживаемого типа
     */
    public function testSidecarForUnsupportedType()
    {
        $this->expectException(\InvalidArgumentException::class);

        $source = Utils::streamFor('test data');
        $mediaKey = random_bytes(32);

        StreamFactory::createEncryptingStream(
            $source,
            $mediaKey,
            'IMAGE',
            true // попытка включить генерацию сайдкара для изображения
        );
    }

    /**
     * Тест обработки пустого потока
     */
    public function testEmptyStream()
    {
        $mediaKey = random_bytes(32);
        $source = Utils::streamFor('');

        $encStream = StreamFactory::createEncryptingStream($source, $mediaKey, 'VIDEO');
        $encrypted = '';
        while (!$encStream->eof()) {
            $encrypted .= $encStream->read(8192);
        }

        $this->assertNotEmpty($encrypted, 'Даже пустой поток должен дать зашифрованные данные (из-за паддинга)');
    }

    /**
     * Тест некорректного ключа
     */
    public function testInvalidKeyLength()
    {
        $this->expectException(\InvalidArgumentException::class);

        $source = Utils::streamFor('test');
        $invalidKey = random_bytes(16); // Неверная длина ключа

        StreamFactory::createEncryptingStream($source, $invalidKey, 'VIDEO');
    }

    public function mediaTypeProvider(): array
    {
        return [
            ['VIDEO'],
            ['AUDIO'],
            ['IMAGE'],
            ['DOCUMENT']
        ];
    }

    public function streamableMediaTypeProvider(): array
    {
        return [
            ['VIDEO'],
            ['AUDIO']
        ];
    }
}

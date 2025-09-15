<?php
use PHPUnit\Framework\TestCase;
use GuzzleHttp\Psr7\Utils;
use WhatsAppMedia\MediaKey;
use WhatsAppMedia\Stream\EncryptingStream;
use WhatsAppMedia\Stream\DecryptingStream;

class CryptoTest extends TestCase
{
    public function testEncryptDecrypt()
    {
        $original = __DIR__ . '/../samples/video.original';
        $mediaKey = file_get_contents(__DIR__ . '/../samples/video.key');
        $parts = MediaKey::expand($mediaKey, 'VIDEO');

        $originalData = file_get_contents($original);
        $source = Utils::streamFor(fopen($original, 'rb'));
        $encStream = new EncryptingStream($source, $parts['cipherKey'], $parts['macKey'], $parts['iv']);
        $encrypted = '';
        while (!$encStream->eof()) {
            $encrypted .= $encStream->read(8192);
        }

        $decStream = new DecryptingStream(Utils::streamFor($encrypted), $parts['cipherKey'], $parts['macKey'], $parts['iv']);
        $decrypted = '';
        while (!$decStream->eof()) {
            $decrypted .= $decStream->read(8192);
        }

        // Debugging: Log derived keys and intermediate data
        $logFile = __DIR__ . '/debug_output.log';
        file_put_contents($logFile, "IV: " . bin2hex($parts['iv']) . "\n", FILE_APPEND);
        file_put_contents($logFile, "Cipher Key: " . bin2hex($parts['cipherKey']) . "\n", FILE_APPEND);
        file_put_contents($logFile, "MAC Key: " . bin2hex($parts['macKey']) . "\n", FILE_APPEND);
        file_put_contents($logFile, "Original Data SHA256: " . hash('sha256', $originalData) . "\n", FILE_APPEND);

        // Debugging: Log compact information for analysis
        $debugData = [
            'original_len' => strlen($originalData),
            'decrypted_len' => strlen($decrypted),
            'original_sha256' => hash('sha256', $originalData),
            'decrypted_sha256' => hash('sha256', $decrypted),
            'equal' => $originalData === $decrypted,
        ];
        file_put_contents(__DIR__ . '/crypto_debug.log', json_encode($debugData, JSON_PRETTY_PRINT));

        $this->assertNotEmpty($encrypted, 'Encrypted data should not be empty.');
        $this->assertTrue(
            hash('sha256', $originalData) === hash('sha256', $decrypted),
            'Decrypted data should match the original data.'
        );
    }
}

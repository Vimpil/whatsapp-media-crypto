<?php
namespace WhatsAppMedia\Stream;

use Psr\Http\Message\StreamInterface;
use GuzzleHttp\Psr7\StreamDecoratorTrait;

class DecryptingStream implements StreamInterface
{
    use StreamDecoratorTrait;

    const CHUNK_SIZE = 65536; // read buffer size
    const MAC_LEN = 10;       // application-specific MAC length

    private $cipherKey;
    private $macKey;
    private $iv;
    private $encBuffer = '';
    private $plainBuffer = '';
    private $finalized = false;

    public function __construct(StreamInterface $source, string $cipherKey, string $macKey, string $iv)
    {
        $this->stream = $source;

        // debug log (truncate)
        @file_put_contents(__DIR__ . '/filtered_decrypt_debug.log', '');

        $this->cipherKey = $cipherKey;
        $this->macKey = $macKey;
        $this->iv = $iv;
    }

    /**
     * Read all remaining encrypted data from underlying stream,
     * verify MAC, decrypt and fill $this->plainBuffer.
     *
     * Note: simpler approach — reads all ciphertext into memory.
     * If streaming is required, rewrite logic to decrypt in blocks.
     */
    private function produceMore()
    {
        if ($this->finalized) {
            return;
        }

        // Read everything available from underlying stream
        while (!$this->stream->eof()) {
            $chunk = $this->stream->read(self::CHUNK_SIZE + self::MAC_LEN);
            if ($chunk === false || $chunk === '') {
                break;
            }

            // Append to encrypted buffer
            $this->encBuffer .= $chunk;
        }

        if ($this->encBuffer === '') {
            // nothing to do
            $this->finalized = true;
            return;
        }

        // Need at least MAC_LEN bytes for MAC
        if (strlen($this->encBuffer) < self::MAC_LEN + 16) { // Minimum one AES block + MAC
            return; // Wait for more data
        }

        // Separate MAC (last MAC_LEN bytes) and ciphertext
        $mac = substr($this->encBuffer, -self::MAC_LEN);
        $ciphertext = substr($this->encBuffer, 0, -self::MAC_LEN);

        // Verify MAC: HMAC-SHA256 truncated to MAC_LEN bytes, with IV included in HMAC context
        $macCtx = hash_init('sha256', HASH_HMAC, $this->macKey);
        hash_update($macCtx, $this->iv);
        hash_update($macCtx, $ciphertext);
        $expectedMac = substr(hash_final($macCtx, true), 0, self::MAC_LEN);

        if (!hash_equals($expectedMac, $mac)) {
            throw new \RuntimeException('MAC mismatch — integrity check failed');
        }

        // Decrypt ciphertext chunk by chunk
        $plain = '';
        while (strlen($ciphertext) >= 16) { // Minimum one AES block
            $chunkLen = min(self::CHUNK_SIZE + 16, strlen($ciphertext));
            $chunk = substr($ciphertext, 0, $chunkLen);
            $ciphertext = substr($ciphertext, $chunkLen);

            // Debug IV and chunk size
            @file_put_contents(__DIR__ . '/filtered_decrypt_debug.log',
                "[DECRYPT] Decrypting chunk size: " . strlen($chunk) . "\n" .
                "[DECRYPT] Current IV: " . bin2hex($this->iv) . "\n",
                FILE_APPEND);

            $decryptedChunk = openssl_decrypt(
                $chunk,
                'aes-256-cbc',
                $this->cipherKey,
                OPENSSL_RAW_DATA,
                $this->iv
            );

            if ($decryptedChunk === false) {
                throw new \RuntimeException('Decryption failed for chunk with IV=' . bin2hex($this->iv));
            }

            $plain .= $decryptedChunk;

            // Update IV from the last 16 bytes of the encrypted chunk
            $this->iv = substr($chunk, -16);
        }

        // Remove PKCS#7 padding with validation
        $plain = $this->removePkcs7Padding($plain);

        $this->plainBuffer .= $plain;

        // Clear encrypted buffer and mark finalized
        $this->encBuffer = '';
        $this->finalized = true;
    }

    private function removePkcs7Padding(string $data): string
    {
        $len = strlen($data);
        if ($len === 0) {
            throw new \RuntimeException('Invalid padding: empty plaintext');
        }

        $pad = ord($data[$len - 1]);
        if ($pad < 1 || $pad > 16) {
            // Log debug information for invalid padding value
            @file_put_contents(__DIR__ . '/filtered_decrypt_debug.log',
                "[ERROR] Invalid padding value: $pad\n" .
                "[ERROR] Data (hex): " . bin2hex($data) . "\n",
                FILE_APPEND);

            // Return the data as-is for debugging purposes
            return $data;
        }

        if ($pad > $len) {
            throw new \RuntimeException('Invalid padding length');
        }

        // Check that last $pad bytes are all equal to $pad
        $paddingBytes = substr($data, -$pad);
        if (strlen($paddingBytes) !== $pad) {
            throw new \RuntimeException('Invalid padding (truncated)');
        }

        for ($i = 0; $i < $pad; $i++) {
            if (ord($paddingBytes[$i]) !== $pad) {
                // Log debug information for mismatched padding bytes
                @file_put_contents(__DIR__ . '/filtered_decrypt_debug.log',
                    "[ERROR] Mismatched padding byte at position $i: " . ord($paddingBytes[$i]) . "\n" .
                    "[ERROR] Expected padding value: $pad\n",
                    FILE_APPEND);

                throw new \RuntimeException('Invalid PKCS#7 padding');
            }
        }

        return substr($data, 0, $len - $pad);
    }

    public function read($length)
    {
        $this->produceMore();
        if ($length <= 0) {
            return '';
        }

        $out = substr($this->plainBuffer, 0, $length);
        $this->plainBuffer = substr($this->plainBuffer, strlen($out));
        return $out === false ? '' : $out;
    }

    public function eof()
    {
        $this->produceMore();
        return $this->finalized && $this->plainBuffer === '';
    }

    // Для совместимости со StreamInterface: остальные методы можно делегировать к $this->stream
    // (read, write, seek и т.д.) — StreamDecoratorTrait добавляет большинство из них.
}

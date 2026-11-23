<?php
declare(strict_types=1);

namespace WhatsAppMedia\Stream;

use Psr\Http\Message\StreamInterface;
use GuzzleHttp\Psr7\StreamDecoratorTrait;

/**
 * Base class for WhatsApp media encryption/decryption streams
 */
abstract class AbstractCryptoStream implements StreamInterface
{
    use StreamDecoratorTrait;

    protected const CHUNK_SIZE = 65536; // 64 KB
    protected const MAC_LEN = 10; // Length of the truncated MAC (WhatsApp-specific, non-AEAD)
    protected const AES_BLOCK_SIZE = 16; // AES block size in bytes

    /** @var StreamInterface Source stream to be encrypted or decrypted */
    protected StreamInterface $stream;

    /** @var string Key used for AES-CBC encryption or decryption */
    protected string $cipherKey;

    /** @var string Key used for HMAC (Hash-based Message Authentication Code) calculation */
    protected string $macKey;

    /** @var string Initialization vector for AES-CBC */
    protected string $iv;

    /** @var string Buffer to store processed data temporarily */
    protected string $buffer = '';

    /** @var bool Indicates whether the stream has been finalized */
    protected bool $finalized = false;

    /**
     * @param StreamInterface $source Source stream to encrypt/decrypt
     * @param string $cipherKey Key for AES-CBC encryption/decryption
     * @param string $macKey Key for HMAC calculation
     * @param string $iv Initialization vector
     */
    public function __construct(
        StreamInterface $source,
        string $cipherKey,
        string $macKey,
        string $iv
    ) {
        $this->stream = $source;
        $this->cipherKey = $cipherKey;
        $this->macKey = $macKey;
        $this->iv = $iv;
    }

    /**
     * Process a chunk of data
     *
     * @param string $chunk Raw data chunk to process
     * @return string Processed (encrypted/decrypted) data
     */
    abstract protected function processChunk(string $chunk): string;

    /**
     * Read from the stream
     *
     * @param int $length Number of bytes to read
     * @return string
     */
    abstract public function read($length);

    /**
     * Check if stream has reached EOF
     */
    public function eof(): bool
    {
        return ($this->finalized || $this->stream->eof()) && $this->buffer === '';
    }

    /**
     * Get the initialization vector used for encryption/decryption
     */
    public function getIv(): string
    {
        return $this->iv;
    }

    /**
     * Get the MAC key used for HMAC calculation
     */
    public function getMacKey(): string
    {
        return $this->macKey;
    }

    /**
     * Add PKCS7 padding to the data
     *
     * @param string $data Data to pad
     * @param int $blockSize Block size (default AES block size)
     * @return string Padded data
     */
    protected function pkcs7_pad(string $data, int $blockSize = self::AES_BLOCK_SIZE): string
    {
        $padLen = $blockSize - (strlen($data) % $blockSize);
        return $data . str_repeat(chr($padLen), $padLen);
    }

    /**
     * Remove PKCS7 padding from the data
     *
     * @param string $data Padded data
     * @return string Unpadded data
     * @throws \RuntimeException If padding is invalid
     */
    protected function pkcs7_unpad(string $data): string
    {
        if ($data === '') {
            return '';
        }

        $padLen = ord($data[strlen($data) - 1]);
        if ($padLen < 1 || $padLen > self::AES_BLOCK_SIZE) {
            throw new \RuntimeException('Invalid PKCS7 padding');
        }

        $padding = substr($data, -$padLen);
        if ($padding !== str_repeat(chr($padLen), $padLen)) {
            throw new \RuntimeException('Invalid PKCS7 padding');
        }

        return substr($data, 0, -$padLen);
    }

    /**
     * Truncate a full-length HMAC to the protocol MAC length.
     *
     * WhatsApp uses HMAC-SHA256 truncated to 10 bytes; this is not an AEAD
     * construction and should not be copied for generic crypto.
     */
    protected function truncateMac(string $mac): string
    {
        return substr($mac, 0, self::MAC_LEN);
    }
}

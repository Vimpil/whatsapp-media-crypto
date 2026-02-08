<?php
declare(strict_types=1);

namespace WhatsAppMedia\Stream;

use Psr\Http\Message\StreamInterface;

/**
 * Stream that encrypts media and optionally generates a sidecar (HMAC per chunk).
 *
 * Sidecar configuration (WhatsApp-compatible):
 * - Chunk size: 64KB (SIDECAR_CHUNK_SIZE)
 * - Overlap: 16 bytes from the next chunk (SIDECAR_OVERLAP)
 * - Per-chunk MAC: HMAC-SHA256 truncated to 10 bytes (MAC_LEN)
 */
class EncryptingStream extends AbstractCryptoStream
{
    private const SIDECAR_CHUNK_SIZE = 65536; // 64KB chunk for sidecar
    private const SIDECAR_OVERLAP = 16;       // 16-byte overlap for HMAC

    /** @var resource HMAC context for the entire stream */
    private $macCtx;

    /** @var string Buffer for sidecar chunks not yet processed */
    private string $sidecarBuffer = '';

    /** @var string Complete sidecar generated */
    private string $sidecar = '';

    /** @var bool Whether to generate sidecar */
    private bool $generateSidecar;

    /**
     * @param StreamInterface $source Source stream to encrypt
     * @param string $cipherKey AES-256-CBC cipher key
     * @param string $macKey HMAC-SHA256 MAC key
     * @param string $iv Initialization vector
     * @param bool $generateSidecar Whether to generate sidecar for streamable media
     */
    public function __construct(
        StreamInterface $source,
        string $cipherKey,
        string $macKey,
        string $iv,
        bool $generateSidecar = false
    ) {
        parent::__construct($source, $cipherKey, $macKey, $iv);
        $this->generateSidecar = $generateSidecar;

        // Initialize HMAC context with IV
        $this->macCtx = hash_init('sha256', HASH_HMAC, $this->macKey);
        hash_update($this->macCtx, $this->iv);
    }

    /**
     * Read up to $length bytes from the encrypted stream.
     *
     * This method ensures that enough data is produced to satisfy the requested
     * length, encrypting chunks as needed. If the buffer is empty and the source
     * stream is at EOF, the method finalizes the encryption process.
     *
     * @param int $length Number of bytes to read.
     * @return string Encrypted data.
     * @throws \RuntimeException If encryption fails.
     */
    public function read($length): string
    {
        $this->produceMore();
        $data = substr($this->buffer, 0, $length);
        $this->buffer = (string)substr($this->buffer, strlen($data));
        return $data === false ? '' : $data;
    }

    /**
     * Check end of stream
     */
    public function eof(): bool
    {
        return ($this->finalized || $this->stream->eof()) && $this->buffer === '';
    }

    /**
     * Return the sidecar after reading the full stream.
     *
     * Note: The sidecar is fully defined only after the encrypted stream has
     * been completely read/drained. This method will read from the underlying
     * stream until EOF if necessary.
     *
     * @return string
     * @throws \RuntimeException If sidecar generation is not enabled.
     */
    public function getSidecar(): string
    {
        if (!$this->generateSidecar) {
            throw new \RuntimeException('Sidecar generation is not enabled');
        }

        // Ensure the entire stream is processed
        while (!$this->eof()) {
            $this->read(8192);
        }

        return $this->sidecar;
    }

    /**
     * Encrypt a single chunk and optionally generate sidecar.
     *
     * @param string $chunk
     * @return string
     * @throws \RuntimeException If encryption fails.
     */
    protected function processChunk(string $chunk): string
    {
        $isFinal = $this->stream->eof();
        $dataToEncrypt = $isFinal ? $this->pkcs7_pad($chunk) : $chunk;

        if ($dataToEncrypt === '') {
            return '';
        }

        $encrypted = openssl_encrypt(
            $dataToEncrypt,
            'AES-256-CBC',
            $this->cipherKey,
            OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING,
            $this->iv
        );

        if ($encrypted === false) {
            throw new \RuntimeException('Encryption failed');
        }

        hash_update($this->macCtx, $encrypted);

        if ($this->generateSidecar) {
            $this->processSidecarChunk($encrypted);
        }

        if ($isFinal) {
            $mac = $this->truncateMac(hash_final($this->macCtx, true));
            $this->finalized = true;
            return $encrypted . $mac;
        }

        return $encrypted;
    }

    /**
     * Process chunks for sidecar generation.
     */
    private function processSidecarChunk(string $encrypted): void
    {
        $this->sidecarBuffer .= $encrypted;

        while (strlen($this->sidecarBuffer) >= self::SIDECAR_CHUNK_SIZE) {
            $chunk = substr($this->sidecarBuffer, 0, self::SIDECAR_CHUNK_SIZE);

            if (!$this->stream->eof()) {
                $overlap = substr($this->sidecarBuffer, self::SIDECAR_CHUNK_SIZE, self::SIDECAR_OVERLAP);
                if ($overlap !== false) {
                    $chunk .= $overlap;
                }
            }

            // HMAC for this sidecar chunk
            $hmacCtx = hash_init('sha256', HASH_HMAC, $this->macKey);
            hash_update($hmacCtx, $chunk);
            $this->sidecar .= $this->truncateMac(hash_final($hmacCtx, true));

            $this->sidecarBuffer = substr($this->sidecarBuffer, self::SIDECAR_CHUNK_SIZE);
        }

        // Handle remaining data at EOF
        if ($this->stream->eof() && $this->sidecarBuffer !== '') {
            $hmacCtx = hash_init('sha256', HASH_HMAC, $this->macKey);
            hash_update($hmacCtx, $this->sidecarBuffer);
            $this->sidecar .= $this->truncateMac(hash_final($hmacCtx, true));
            $this->sidecarBuffer = '';
        }
    }

    /**
     * Fill buffer with processed chunks
     */
    private function produceMore(): void
    {
        if ($this->finalized || strlen($this->buffer) >= self::CHUNK_SIZE) {
            return;
        }

        $chunk = $this->stream->read(self::CHUNK_SIZE);

        if ($chunk === '' && $this->stream->eof()) {
            // Pad empty stream
            $processed = $this->processChunk('');
            if ($processed !== '') {
                $this->buffer .= $processed;
            }
            return;
        }

        $processed = $this->processChunk($chunk);
        if ($processed !== '') {
            $this->buffer .= $processed;
        }
    }
}

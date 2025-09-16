<?php
declare(strict_types=1);

namespace WhatsAppMedia\Stream;

use Psr\Http\Message\StreamInterface;

class EncryptingStream extends AbstractCryptoStream
{
    private const SIDECAR_CHUNK_SIZE = 65536; // 64KB
    private const SIDECAR_OVERLAP = 16;       // 16 bytes overlap

    /** @var resource HMAC context */
    private $macCtx;

    /** @var string Buffer for sidecar */
    private $sidecarBuffer = '';

    /** @var string Sidecar */
    private $sidecar = '';

    /** @var bool Generate sidecar */
    private $generateSidecar;

    public function __construct(
        StreamInterface $source,
        string $cipherKey,
        string $macKey,
        string $iv,
        bool $generateSidecar = false
    ) {
        parent::__construct($source, $cipherKey, $macKey, $iv);
        $this->generateSidecar = $generateSidecar;
        $this->macCtx = hash_init('sha256', HASH_HMAC, $this->macKey);
        hash_update($this->macCtx, $this->iv);
    }

    public function read($length): string
    {
        $this->produceMore();
        $data = substr($this->buffer, 0, $length);
        $this->buffer = (string)substr($this->buffer, strlen($data));
        return $data === false ? '' : $data;
    }

    public function eof(): bool
    {
        return ($this->finalized || $this->stream->eof()) && $this->buffer === '';
    }

    /**
     * Get the generated sidecar
     */
    public function getSidecar(): string
    {
        if (!$this->generateSidecar) {
            throw new \RuntimeException('Sidecar generation is not enabled');
        }
        // Read the entire stream to generate complete sidecar
        while (!$this->eof()) {
            $this->read(8192);
        }
        return $this->sidecar;
    }

    protected function processChunk(string $chunk): string
    {

        $isFinal = $this->stream->eof();
        $dataToEncrypt = $isFinal ? $this->pkcs7_pad($chunk) : $chunk;

        if (empty($dataToEncrypt)) {
            return ''; // Return empty only if no data and not final
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

        // Process sidecar
        if ($this->generateSidecar) {
            $this->processSidecarChunk($encrypted);
        }

        if ($isFinal) {
            $mac = substr(hash_final($this->macCtx, true), 0, self::MAC_LEN);
            $this->finalized = true;
            return $encrypted . $mac;
        }

        return $encrypted;
    }

    private function processSidecarChunk(string $encrypted): void
    {
        $this->sidecarBuffer .= $encrypted;

        while (strlen($this->sidecarBuffer) >= self::SIDECAR_CHUNK_SIZE) {
            // Get chunk for sidecar with overlap
            $chunk = substr($this->sidecarBuffer, 0, self::SIDECAR_CHUNK_SIZE);
            if (!$this->stream->eof()) {
                $overlap = substr($this->sidecarBuffer, self::SIDECAR_CHUNK_SIZE, self::SIDECAR_OVERLAP);
                if ($overlap !== false) {
                    $chunk .= $overlap;
                }
            }

            // Generate HMAC for chunk
            $hmacCtx = hash_init('sha256', HASH_HMAC, $this->macKey);
            hash_update($hmacCtx, $chunk);
            $this->sidecar .= substr(hash_final($hmacCtx, true), 0, self::MAC_LEN);

            // Move buffer
            $this->sidecarBuffer = substr($this->sidecarBuffer, self::SIDECAR_CHUNK_SIZE);
        }

        // Process remaining data at end of file
        if ($this->stream->eof() && !empty($this->sidecarBuffer)) {
            $hmacCtx = hash_init('sha256', HASH_HMAC, $this->macKey);
            hash_update($hmacCtx, $this->sidecarBuffer);
            $this->sidecar .= substr(hash_final($hmacCtx, true), 0, self::MAC_LEN);
            $this->sidecarBuffer = '';
        }
    }

    private function produceMore(): void
    {
        if ($this->finalized) {
            return;
        }

        if (strlen($this->buffer) >= self::CHUNK_SIZE) {
            return;
        }

        $chunk = $this->stream->read(self::CHUNK_SIZE);

        if ($chunk === '' && $this->stream->eof()) {
            // Apply padding for empty streams at the end
            $processed = $this->processChunk('');
            if (!empty($processed)) {
                $this->buffer .= $processed;
            }
            return;
        }

        $processed = $this->processChunk($chunk);
        if (!empty($processed)) {
            $this->buffer .= $processed;
        }
    }
}

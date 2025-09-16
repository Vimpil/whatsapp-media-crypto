<?php

namespace WhatsAppMedia\Stream;

use Psr\Http\Message\StreamInterface;

class SidecarGenerator
{
    private $stream;
    private $macKey;

    public function __construct(StreamInterface $stream, string $macKey)
    {
        $this->stream = $stream;
        $this->macKey = $macKey;
    }

    /**
     * Generate sidecar file for a stream containing encrypted data.
     *
     * Rules:
     * - Chunk size = CHUNK_SIZE (64K)
     * - For each chunk compute HMAC-SHA256(chunk, macKey) and append first 10 bytes
     * - For the final chunk (< chunk size) compute HMAC over the remainder and append first 10 bytes
     *
     * @param string $outputPath Destination path for sidecar binary
     * @param int $chunkSize default 65536 (64K)
     */
    public function generateSidecar(string $outputPath, int $chunkSize = 65536): void
    {
        $logFile = __DIR__ . '/sidecar_debug.log';
        file_put_contents($logFile, "Starting sidecar generation\n", FILE_APPEND);

        // Make sure stream position is at start when possible.
        if (method_exists($this->stream, 'rewind')) {
            try {
                $this->stream->rewind();
            } catch (\Throwable $e) {
                // ignore non-rewindable streams
            }
        }

        $sidecar = '';
        $buffer = '';

        // Read encrypted stream in fixed-size chunks
        while (!$this->stream->eof()) {
            $buffer .= $this->stream->read($chunkSize);

            // Process chunks of size chunkSize + 16
            while (strlen($buffer) >= $chunkSize + 16) {
                $window = substr($buffer, 0, $chunkSize + 16);
                $sig = hash_hmac('sha256', $window, $this->macKey, true);
                $sidecar .= substr($sig, 0, 10);
                $buffer = substr($buffer, $chunkSize);
            }
        }

        // Final chunk (< chunkSize + 16)
        if (strlen($buffer) > 0) {
            $sig = hash_hmac('sha256', $buffer, $this->macKey, true);
            $sidecar .= substr($sig, 0, 10);
        }

        // Write sidecar binary
        file_put_contents($outputPath, $sidecar);

        file_put_contents($logFile, "Sidecar generation completed. bytes=" . strlen($sidecar) . "\n", FILE_APPEND);
    }

    private const CHUNK_SIZE = 65536; // 64 KB

    public function generate(StreamInterface $encryptedStream): string
    {
        $encryptedStream->rewind();
        $sidecar = '';
        $buffer = '';

        while (!$encryptedStream->eof()) {
            $chunk = $encryptedStream->read(self::CHUNK_SIZE);
            if ($chunk === false) {
                $chunk = '';
            }

            $buffer .= $chunk;

            // Process chunks of size CHUNK_SIZE + 16
            while (strlen($buffer) >= self::CHUNK_SIZE + 16) {
                $window = substr($buffer, 0, self::CHUNK_SIZE + 16);
                $hmac = hash_hmac('sha256', $window, $this->macKey, true);
                $sidecar .= substr($hmac, 0, 10);
                $buffer = substr($buffer, self::CHUNK_SIZE);
            }
        }

        // Process remaining data (for small files or the final chunk)
        if (strlen($buffer) > 0) {
            $hmac = hash_hmac('sha256', $buffer, $this->macKey, true);
            $sidecar .= substr($hmac, 0, 10);
        }

        return $sidecar;
    }

    public function saveToFile(StreamInterface $encryptedStream, string $filePath): void
    {
        $sidecar = $this->generate($encryptedStream);
        file_put_contents($filePath, $sidecar);
    }
}
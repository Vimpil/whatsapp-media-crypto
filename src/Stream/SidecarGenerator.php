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

        // Read encrypted stream in fixed-size chunks
        while (!$this->stream->eof()) {
            $chunk = $this->stream->read($chunkSize);
            if ($chunk === '' || $chunk === false) {
                break;
            }

            // Compute HMAC-SHA256 for the chunk
            $sig = hash_hmac('sha256', $chunk, $this->macKey, true);
            $sidecar .= substr($sig, 0, 10);
        }

        // Write sidecar binary
        file_put_contents($outputPath, $sidecar);

        file_put_contents($logFile, "Sidecar generation completed. bytes=" . strlen($sidecar) . "\n", FILE_APPEND);
    }
}
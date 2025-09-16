<?php
namespace WhatsAppMedia\Stream;

use Psr\Http\Message\StreamInterface;
use GuzzleHttp\Psr7\StreamDecoratorTrait;

class EncryptingStreamWithSidecar implements StreamInterface
{
    use StreamDecoratorTrait;

    private const CHUNK_SIZE = 65536; // 64KB

    private string $cipherKey;
    private string $macKey;
    private string $currentIv;
    private string $encBuffer = '';
    private string $sidecar = '';
    private string $sidecarBuf = '';
    private $macCtx;
    private bool $finalized = false;

    public function __construct(StreamInterface $source, string $cipherKey, string $macKey, string $iv)
    {
        $this->stream = $source;
        $this->cipherKey = $cipherKey;
        $this->macKey = $macKey;
        $this->currentIv = $iv;

        // Основной HMAC для всего payload
        $this->macCtx = hash_init('sha256', HASH_HMAC, $this->macKey);
        hash_update($this->macCtx, $this->currentIv);
    }

    public function read($length)
    {
        $this->produceMore();
        $out = substr($this->encBuffer, 0, $length);
        $this->encBuffer = substr($this->encBuffer, strlen($out));
        return $out === false ? '' : $out;
    }

    public function eof()
    {
        $this->produceMore();
        return $this->finalized && $this->encBuffer === '';
    }

    public function getSidecar(): string
    {
        while (!$this->finalized) {
            $this->produceMore();
        }
        return $this->sidecar;
    }

    private function pkcs7_pad(string $data, int $blockSize = 16): string
    {
        $padLen = $blockSize - (strlen($data) % $blockSize);
        if ($padLen === 0) $padLen = $blockSize;
        return $data . str_repeat(chr($padLen), $padLen);
    }

    private function produceMore(): void
    {
        if ($this->finalized) return;

        while ((strlen($this->encBuffer) < 1) && !$this->finalized) {
            $chunk = $this->stream->read(self::CHUNK_SIZE);
            $isFinal = $this->stream->eof();
            if ($chunk === false) $chunk = '';

            // PKCS#7 padding только для последнего блока
            if ($isFinal) {
                $chunk = $this->pkcs7_pad($chunk, 16);
            }
            // AES-256-CBC шифрование
            $encrypted = openssl_encrypt($chunk, 'aes-256-cbc', $this->cipherKey, OPENSSL_RAW_DATA, $this->currentIv);
            if ($encrypted === false) throw new \RuntimeException('Encryption failed');

            // Use ZERO_PADDING to prevent OpenSSL from adding PKCS#7 (we pad manually on final)
            $flags = OPENSSL_RAW_DATA;
            if (defined('OPENSSL_ZERO_PADDING')) {
                $flags |= OPENSSL_ZERO_PADDING;
            }

            $encrypted = openssl_encrypt($chunk, 'aes-256-cbc', $this->cipherKey, $flags, $this->currentIv);

            $this->encBuffer .= $encrypted;
            // CBC: next IV is last 16 bytes
            // Общий HMAC для финального MAC
            hash_update($this->macCtx, $encrypted);

            // --- Sidecar ---
            // Update overall MAC

            while (strlen($this->sidecarBuf) >= self::CHUNK_SIZE + 16) {
                $window = substr($this->sidecarBuf, 0, self::CHUNK_SIZE + 16);
                $hmac = hash_hmac('sha256', $window, $this->macKey, true);
                $this->sidecar .= substr($hmac, 0, 10);
                $this->sidecarBuf = substr($this->sidecarBuf, self::CHUNK_SIZE);
            }

            // Финальный кусок для sidecar
            if ($isFinal && strlen($this->sidecarBuf) > 0) {
                $hmac = hash_hmac('sha256', $this->sidecarBuf, $this->macKey, true);
                $this->sidecar .= substr($hmac, 0, 10);
            }

            // Финальный 10-байтный MAC в конец зашифрованного видео
            if ($isFinal) {
                $finalMac = hash_final($this->macCtx, true);
                $this->encBuffer .= substr($finalMac, 0, 10);
                break;
            }
        }
    }
}

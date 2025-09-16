<?php
namespace WhatsAppMedia\Stream;

use Psr\Http\Message\StreamInterface;
use GuzzleHttp\Psr7\StreamDecoratorTrait;

class EncryptingStream implements StreamInterface {
    use StreamDecoratorTrait;

    const CHUNK_SIZE = 65536;
    private $cipherKey;
    private $macKey;
    private $currentIv;
    private $encBuffer = '';
    private $sidecar = '';
    private $sidecarBuf = '';
    private $macCtx;
    private $finalized = false;

    public function __construct(StreamInterface $source, $cipherKey, $macKey, $iv) {
        $this->stream = $source;
        $this->cipherKey = $cipherKey;
        $this->macKey = $macKey;
        $this->currentIv = $iv;
        $this->macCtx = hash_init('sha256', HASH_HMAC, $this->macKey);
        hash_update($this->macCtx, $this->currentIv);
    }

    public function read($length) {
        $this->produceMore();
        $out = substr($this->encBuffer, 0, $length);
        $this->encBuffer = substr($this->encBuffer, strlen($out));
        return $out === false ? '' : $out;
    }

    public function eof() {
        $this->produceMore();
        return $this->finalized && $this->encBuffer === '';
    }

    public function getSidecar() {
        while (!$this->finalized) $this->produceMore();
        return $this->sidecar;
    }

    public function getMacCtx() {
        return $this->macCtx;
    }

    private function pkcs7_pad(string $data, int $blockSize = 16): string {
        $padLen = $blockSize - (strlen($data) % $blockSize);
        if ($padLen === 0) $padLen = $blockSize;
        return $data . str_repeat(chr($padLen), $padLen);
    }

    private function produceMore() {
        if ($this->finalized) return;

        while ((strlen($this->encBuffer) < 1) && !$this->finalized) {
            $chunk = $this->stream->read(self::CHUNK_SIZE);
            $isFinal = $this->stream->eof();

            // If read returned false/empty and EOF, treat as empty final chunk
            if ($chunk === false) $chunk = '';

            // Choose flags: for intermediate chunks disable padding
            $flags = OPENSSL_RAW_DATA;
            $useZeroPadding = defined('OPENSSL_ZERO_PADDING');

            if (!$isFinal) {
                if ($useZeroPadding) {
                    $flags |= OPENSSL_ZERO_PADDING;
                }
            } else {
                $chunk = $this->pkcs7_pad($chunk, 16);

                if ($useZeroPadding) {
                    $flags |= OPENSSL_ZERO_PADDING;
                }
            }

            $encrypted = openssl_encrypt($chunk, 'aes-256-cbc', $this->cipherKey, $flags, $this->currentIv);
            if ($encrypted === false) {
                throw new \RuntimeException('Encryption failed');
            }

            $this->currentIv = substr($encrypted, -16);

            $this->encBuffer .= $encrypted;
            hash_update($this->macCtx, $encrypted);
            $this->sidecarBuf .= $encrypted;

            while (strlen($this->sidecarBuf) >= self::CHUNK_SIZE + 16) {
                $window = substr($this->sidecarBuf, 0, self::CHUNK_SIZE + 16);
                $sig = hash_hmac('sha256', $window, $this->macKey, true);
                $this->sidecar .= substr($sig, 0, 10);
                $this->sidecarBuf = substr($this->sidecarBuf, self::CHUNK_SIZE);
            }

            if ($isFinal) {
                $fullMac = hash_final($this->macCtx, true);
                $this->encBuffer .= substr($fullMac, 0, 10);

                if (strlen($this->sidecarBuf) > 0) {
                    $sig = hash_hmac('sha256', $this->sidecarBuf, $this->macKey, true);
                    $this->sidecar .= substr($sig, 0, 10);
                }

                $this->finalized = true;
                break;
            }
        }
    }
}
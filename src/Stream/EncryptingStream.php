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
                    // disable PKCS#7 padding: caller must ensure chunk length is multiple of 16 (it is)
                    $flags |= OPENSSL_ZERO_PADDING;
                }
            } else {
                // final chunk: apply PKCS#7 padding manually
                $chunk = $this->pkcs7_pad($chunk, 16);

                // IMPORTANT: we MANUALLY padded, so we MUST tell OpenSSL to NOT add its own padding.
                // That is, set OPENSSL_ZERO_PADDING too.
                if ($useZeroPadding) {
                    $flags |= OPENSSL_ZERO_PADDING;
                } else {
                    // if ZERO_PADDING not available (very old PHP builds) -- fallback:
                    // we cannot avoid double-padding reliably in streaming mode.
                    // Consider encrypting entire file at once or require PHP with OPENSSL_ZERO_PADDING.
                }
            }

            // Encrypt with flags (which now include ZERO_PADDING for both cases when available)
            $encrypted = openssl_encrypt($chunk, 'aes-256-cbc', $this->cipherKey, $flags, $this->currentIv);
            if ($encrypted === false) {
                throw new \RuntimeException('Encryption failed');
            }

            // Update IV from last 16 bytes of the encrypted chunk (CBC chaining)
            $this->currentIv = substr($encrypted, -16);

            // Append to enc buffer (to be read by caller)
            $this->encBuffer .= $encrypted;

            // Update global mac (HMAC context covers iv + ciphertext per spec, we initialized with IV earlier)
            hash_update($this->macCtx, $encrypted);

            // Sidecar: we must produce HMACs per 64KiB chunk of the ciphertext stream.
            // We can accumulate encrypted chunks into sidecar buffer and emit HMACs for full 64KiB pieces.
            $this->sidecarBuf .= $encrypted;

            // Emit sidecar entries for each full 64KiB block of ciphertext
            while (strlen($this->sidecarBuf) >= self::CHUNK_SIZE) {
                $window = substr($this->sidecarBuf, 0, self::CHUNK_SIZE); // exactly 65536 bytes
                $sig = hash_hmac('sha256', $window, $this->macKey, true);
                $this->sidecar .= substr($sig, 0, 10);
                // remove the emitted block from buffer
                $this->sidecarBuf = substr($this->sidecarBuf, self::CHUNK_SIZE);
            }

            if ($isFinal) {
                // finalize full file MAC (iv + all ciphertext)
                $fullMac = hash_final($this->macCtx, true);
                // append truncated mac (first 10 bytes) to the ciphertext buffer
                $this->encBuffer .= substr($fullMac, 0, 10);

                // Emit final sidecar entry for any remaining ciphertext
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
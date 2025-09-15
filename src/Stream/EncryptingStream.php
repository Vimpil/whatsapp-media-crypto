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
        // Truncate debug log at start
        file_put_contents(__DIR__ . '/encrypt_debug.log', '');
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

    private function produceMore() {
        if ($this->finalized) return;
        while ((strlen($this->encBuffer) < 1) && !$this->finalized) {
            $chunk = $this->stream->read(self::CHUNK_SIZE);
            $isFinal = $this->stream->eof();

            // Debug: Log IV and chunk info before encryption
            file_put_contents(__DIR__ . '/encrypt_debug.log',
                "[ENCRYPT] IV before: " . bin2hex($this->currentIv) . "\n" .
                "[ENCRYPT] Chunk size: " . strlen($chunk) . "\n" .
                "[ENCRYPT] Chunk first 16 bytes: " . bin2hex(substr($chunk, 0, 16)) . "\n",
                FILE_APPEND);

            $encrypted = openssl_encrypt($chunk, 'aes-256-cbc', $this->cipherKey, OPENSSL_RAW_DATA, $this->currentIv);
            $this->currentIv = substr($encrypted, -16);
            $this->encBuffer .= $encrypted;
            hash_update($this->macCtx, $encrypted);

            $this->sidecarBuf .= $encrypted;

            // Debug: Log IV after encryption and first bytes of encrypted chunk
            file_put_contents(__DIR__ . '/encrypt_debug.log',
                "[ENCRYPT] IV after: " . bin2hex($this->currentIv) . "\n" .
                "[ENCRYPT] Encrypted first 16 bytes: " . bin2hex(substr($encrypted, 0, 16)) . "\n",
                FILE_APPEND);

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

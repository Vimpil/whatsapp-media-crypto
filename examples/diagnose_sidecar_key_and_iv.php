    <?php
require __DIR__ . '/../vendor/autoload.php';

use WhatsAppMedia\MediaKey;

$sidecarPath = __DIR__ . '/../samples/VIDEO.sidecar';
$keyPath     = __DIR__ . '/../samples/VIDEO.key';

$mediaKey = file_get_contents($keyPath);
$parts = MediaKey::expand($mediaKey, 'VIDEO');

$sidecar = file_get_contents($sidecarPath);
$iv_sidecar = substr($sidecar, 0, 16);
$payload   = substr($sidecar, 16);

function bin2h($b){ return bin2hex($b); }

echo "IV from MediaKey::expand: ".bin2h($parts['iv'])."\n";
echo "IV from sidecar:        ".bin2h($iv_sidecar)."\n";
echo ($parts['iv'] === $iv_sidecar ? "IV MATCH\n" : "IV DIFFER\n");

$keys = [
  'cipherKey' => $parts['cipherKey'],
  'macKey'    => $parts['macKey'],
  'refKey'    => $parts['refKey'],
];

foreach ($keys as $name => $k) {
    $ok = false;
    $out = @openssl_decrypt($payload, 'aes-256-cbc', $k, OPENSSL_RAW_DATA, $iv_sidecar);
    if ($out !== false) {
        $len = strlen($out);
        if ($len > 0) {
            $pad = ord($out[$len-1]);
            if ($pad >=1 && $pad <= 16 && $pad <= $len) {
                $padBytes = substr($out, -$pad);
                if ($padBytes === str_repeat(chr($pad), $pad)) {
                    $ok = true;
                    $out = substr($out, 0, $len - $pad);
                }
            }
        }
    }
    echo "$name: ".($ok?"OK (valid PKCS7)":"FAIL")."\n";
    if ($ok) {
        echo "Decrypted first 32 bytes: ".substr(bin2h($out),0,64)."\n";
    }
}


<?php
namespace WhatsAppMedia;

class HKDF {
    public static function derive(string $ikm, string $info, int $length = 112, string $salt = ''): string {
        $salt = $salt === '' ? str_repeat("\0", 32) : $salt;
        $prk  = hash_hmac('sha256', $ikm, $salt, true);
        $t = '';
        $okm = '';
        $i = 1;
        while (strlen($okm) < $length) {
            $t = hash_hmac('sha256', $t . $info . chr($i), $prk, true);
            $okm .= $t;
            $i++;
        }
        return substr($okm, 0, $length);
    }
}

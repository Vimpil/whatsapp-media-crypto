<?php
namespace WhatsAppMedia;

class MediaKey {
    public static function expand(string $mediaKey, string $mediaType): array {
        $map = [
            'IMAGE' => 'WhatsApp Image Keys',
            'VIDEO' => 'WhatsApp Video Keys',
            'AUDIO' => 'WhatsApp Audio Keys',
            'DOCUMENT' => 'WhatsApp Document Keys',
        ];
        $info = $map[$mediaType] ?? 'WhatsApp Document Keys';
        $expanded = HKDF::derive($mediaKey, $info, 112);
        return [
            'iv' => substr($expanded, 0, 16),
            'cipherKey' => substr($expanded, 16, 32),
            'macKey' => substr($expanded, 48, 32),
            'refKey' => substr($expanded, 80, 32),
        ];
    }
}

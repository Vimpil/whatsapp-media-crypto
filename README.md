# WhatsApp Media Crypto

[![PHP >=7.4](https://img.shields.io/packagist/dependency-v/vimpil/whatsapp-media-crypto/php)](https://packagist.org/packages/vimpil/whatsapp-media-crypto)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)

An experimental PHP implementation of the WhatsApp media encryption scheme (HKDF, AES-CBC, HMAC) as PSR-7 stream decorators.

> **Status: experimental, not production-ready.**
>
> - **Known correctness issues:** behavior for data larger than 64 KiB is incorrect.
> - **Known security issue:** decrypted plaintext may be released before the MAC of the whole stream has been verified. Do not use it to process untrusted input.
> - **Not an official implementation.** It is an independent project, not affiliated with or endorsed by WhatsApp or Meta, and its compatibility with WhatsApp is not guaranteed.
> - **Not audited for security.** No stable release is planned, and `dev-master` may change without notice.
>
> Treat it as a learning and experimentation project.

## Requirements

- PHP 7.4+
- OpenSSL extension
- Composer

## Installation

Not recommended for production use (see the status notice above). For experimentation only:

```bash
composer require vimpil/whatsapp-media-crypto:dev-master
```

## Main Features

- PSR-7 compatible stream decorators
- Support for various media types (images, video, audio, documents)
- Stream-based API (see the known issue above for data over 64 KiB)
- Built-in sidecar generation for video and audio streaming
- Refactored `StreamFactory` with modular validation methods

## Project Structure

```
src/
├── Stream/
│   ├── AbstractCryptoStream.php    # Base class for cryptographic streams
│   ├── EncryptingStream.php        # Encryption implementation
│   └── DecryptingStream.php        # Decryption implementation
├── HKDF.php                        # HKDF implementation
├── MediaKey.php                    # Media key handling
└── StreamFactory.php               # Stream factory with modular validation
```

## Usage Examples

### Image Encryption

```php
use WhatsAppMedia\StreamFactory;
use GuzzleHttp\Psr7\Utils;

$source = Utils::streamFor(fopen('image.jpg', 'rb'));
$mediaKey = file_get_contents('image.key');

$encryptedStream = StreamFactory::createEncryptingStream(
    $source,
    $mediaKey,
    'IMAGE'
);
```

### Video/Audio Encryption with Sidecar Generation

```php
$source = Utils::streamFor(fopen('video.mp4', 'rb'));
$mediaKey = file_get_contents('video.key');

$encryptedStream = StreamFactory::createEncryptingStream(
    $source,
    $mediaKey,
    'VIDEO',
    true // enable sidecar generation
);

// Drain the encrypted stream first so that the sidecar is fully generated
while (!$encryptedStream->eof()) {
    $encryptedStream->read(8192);
}

// Get the sidecar after encryption
$sidecar = $encryptedStream->getSidecar();
```

### File Decryption

```php
$encrypted = Utils::streamFor(fopen('encrypted_file', 'rb'));
$mediaKey = file_get_contents('file.key');

$decryptedStream = StreamFactory::createDecryptingStream(
    $encrypted,
    $mediaKey,
    'VIDEO' // or 'AUDIO', 'IMAGE', 'DOCUMENT'
);
```

## Supported Media Types

| Type     | Description | Application Info         | Sidecar Support |
|----------|-------------|-------------------------|-----------------|
| IMAGE    | Images      | WhatsApp Image Keys     | No             |
| VIDEO    | Video       | WhatsApp Video Keys     | Yes            |
| AUDIO    | Audio       | WhatsApp Audio Keys     | Yes            |
| DOCUMENT | Documents   | WhatsApp Document Keys  | No             |

## Ready-to-Use Examples

The `examples/` directory contains ready-to-use scripts:

### Encryption
- `encrypt_image.php` - image encryption
- `encrypt_video.php` - video encryption
- `encrypt_audio.php` - audio encryption
- `encrypt_document.php` - document encryption
- `encrypt_video_streaming.php` - video encryption with sidecar
- `encrypt_audio_streaming.php` - audio encryption with sidecar

### Decryption
- `decrypt_image.php` - image decryption
- `decrypt_video.php` - video decryption
- `decrypt_audio.php` - audio decryption
- `decrypt_document.php` - document decryption

All examples include:
- File existence checks
- Output directory creation
- Error handling
- File size information

## Implementation Details

### Encryption

1. Generate or use existing mediaKey (32 bytes)
2. Expand key using HKDF with SHA-256 and type-specific application info
3. Split into components:
   - iv: first 16 bytes
   - cipherKey: next 32 bytes
   - macKey: next 32 bytes
   - refKey: remaining 32 bytes (not used)
4. AES-CBC encryption using cipherKey and iv
5. HMAC generation using macKey

### Sidecar Generation (for video and audio)

- Chunk size: 64KB
- Overlap: 16 bytes
- HMAC SHA-256 for each chunk, truncated to 10 bytes
- On-the-fly generation without additional reads from source stream

> Note: The 10-byte MAC truncation is WhatsApp-specific and is not an AEAD
> construction. It is implemented here for protocol compatibility and should
> not be reused as a generic cryptographic design.

## Testing

```bash
composer install
vendor/bin/phpunit
```

### Test Coverage

`tests/CryptoTest.php` contains PHPUnit tests for encryption/decryption round trips, sidecar generation, invalid MAC, empty stream, key length validation and unsupported types. These tests do not cover the known issues listed at the top of this file, and no coverage figure is claimed.

## Security

This library makes no security guarantees. See the status notice at the top of this file for the known issues.

## License

MIT License

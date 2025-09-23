# WhatsApp Media Crypto

A library for encrypting and decrypting media files using WhatsApp algorithms. Implements PSR-7 compatible stream decorators.

## Requirements

- PHP 7.4+
- OpenSSL extension
- Composer

## Installation

```bash
composer require your-vendor/whatsapp-media-crypto
```

## Main Features

- PSR-7 compatible stream decorators
- Support for various media types (images, video, audio, documents)
- Streaming processing of large files
- Built-in sidecar generation for video and audio streaming
- Strict typing and complete documentation
- 100% test coverage
- Refactored `StreamFactory` with modular validation methods
- Enhanced maintainability with detailed comments in core classes

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

## Testing

```bash
composer install
vendor/bin/phpunit
```

### Test Coverage

Tests cover all requirements:

1. Basic functionality:
   - Encryption/decryption for all media types
   - Correct handling of various file sizes
   - Verification against reference files

2. Sidecar generation (bonus task):
   - Generation for video and audio files
   - Verification against reference sidecars
   - Format and size validation

3. Edge cases:
   - Invalid MAC verification
   - Empty stream handling
   - Key length validation
   - Unsupported media type checks

## Security

- Input data validation
- MAC verification for each chunk
- Secure cryptographic primitive handling
- Proper error handling
- Data integrity verification

## License

MIT License

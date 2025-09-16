import sys
import os
import struct
from Crypto.Cipher import AES
from Crypto.Util.Padding import unpad

if len(sys.argv) < 3:
    print(f"Usage: python {sys.argv[0]} <sidecar_file> <key_file>")
    sys.exit(1)

sidecar_path = sys.argv[1]
key_path = sys.argv[2]

# Проверка файлов
if not os.path.exists(sidecar_path) or not os.path.exists(key_path):
    print("Sidecar file or key file does not exist.")
    sys.exit(1)

# Загрузка ключа (MediaKey / macKey)
with open(key_path, "rb") as f:
    key = f.read()
print(f"Loaded key ({len(key)} bytes)")

# Загрузка sidecar
with open(sidecar_path, "rb") as f:
    data = f.read()
print(f"Loaded sidecar ({len(data)} bytes)")

# Настройки WhatsApp
CHUNK_SIZE = 65536  # 64KB
HMAC_SIZE = 10      # первые 10 байт HMAC

offset = 0
block_index = 0

print("\n=== Sidecar Block Analysis ===")
while offset < len(data):
    block_index += 1
    remaining = len(data) - offset
    block_len = min(CHUNK_SIZE + 16, remaining)  # chunk + 16 для AES
    block = data[offset:offset + block_len]

    # IV для блока: первые 16 байт (часто для первого блока sidecar)
    iv = block[:16]
    payload = block[16:]

    # Показываем хекс
    print(f"\nBlock {block_index}:")
    print(f"  Offset: {offset}")
    print(f"  Block size: {len(block)}")
    print(f"  IV: {iv.hex()}")
    print(f"  Payload first 32 bytes: {payload[:32].hex()}")

    # Перемещение
    offset += block_len

from Crypto.Cipher import AES
from Crypto.Util.Padding import pad, unpad
import sys
import os

if len(sys.argv) < 3:
    print(f"Usage: python {sys.argv[0]} <sidecar_file> <key_file>")
    sys.exit(1)

sidecar_path = sys.argv[1]
key_path = sys.argv[2]

# Проверка файлов
if not os.path.exists(sidecar_path) or not os.path.exists(key_path):
    print("Sidecar file or key file does not exist.")
    sys.exit(1)

# Загружаем ключ
with open(key_path, "rb") as f:
    key = f.read()
print(f"Loaded key ({len(key)} bytes)")

# Загружаем sidecar
with open(sidecar_path, "rb") as f:
    data = f.read()
print(f"Loaded sidecar ({len(data)} bytes)")

# Предполагаем, что первые 16 байт = IV
iv = data[:16]
encrypted_payload = data[16:]

print(f"IV extracted ({len(iv)} bytes)")
print(f"Encrypted payload size: {len(encrypted_payload)} bytes")

# Check if the encrypted payload size is a multiple of 16
if len(encrypted_payload) % 16 != 0:
    print("Warning: Encrypted payload size is not a multiple of 16 bytes. Padding will be added temporarily.")
    encrypted_payload = pad(encrypted_payload, 16)

# Дешифровка AES-CBC
cipher = AES.new(key, AES.MODE_CBC, iv)
decrypted = cipher.decrypt(encrypted_payload)

# Попытка убрать PKCS#7 padding
try:
    decrypted = unpad(decrypted, 16)
    print("Removed PKCS#7 padding.")
except ValueError:
    print("Warning: Padding removal failed. Output may still contain padding.")

# Сохраняем расшифрованный payload
output_file = sidecar_path + ".payload.bin"
with open(output_file, "wb") as f:
    f.write(decrypted)

print(f"Decrypted payload saved as: {output_file}")

# Дополнительно — вывод первых 64 байт для анализа
print("First 64 bytes of decrypted payload (hex):")
print(decrypted[:64].hex())

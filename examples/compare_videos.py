import hashlib

def file_hash_len(path):
    h = hashlib.sha256()
    length = 0
    with open(path, "rb") as f:
        for chunk in iter(lambda: f.read(8192), b""):
            length += len(chunk)
            h.update(chunk)
    return h.hexdigest(), length

def compare_files(path1, path2, context=16):
    with open(path1, "rb") as f1, open(path2, "rb") as f2:
        offset = 0
        while True:
            b1 = f1.read(1)
            b2 = f2.read(1)
            if not b1 and not b2:
                print("✅ No differences found.")
                break
            if b1 != b2:
                print(f"Difference at offset {offset}: "
                      f"{b1.hex() if b1 else 'EOF'} vs {b2.hex() if b2 else 'EOF'}")
                # Show context
                f1.seek(max(0, offset - context))
                f2.seek(max(0, offset - context))
                ctx1 = f1.read(context * 2 + 1)
                ctx2 = f2.read(context * 2 + 1)
                print("File1 context:", ctx1.hex())
                print("File2 context:", ctx2.hex())
                break
            offset += 1

def compare_all_differences(path1, path2, max_diffs=10000):
    with open(path1, "rb") as f1, open(path2, "rb") as f2:
        offset = 0
        diffs = []
        while True:
            b1 = f1.read(1)
            b2 = f2.read(1)
            if not b1 and not b2:
                break
            if b1 != b2:
                diffs.append((offset, b1.hex() if b1 else 'EOF', b2.hex() if b2 else 'EOF'))
                if len(diffs) >= max_diffs:
                    break
            offset += 1
        if diffs:
            print(f"Found {len(diffs)} differences:")
#             for off, v1, v2 in diffs:
#                 print(f"Offset {off}: {v1} vs {v2}")
        else:
            print("✅ No differences found.")

def print_file_info(path):
    h = hashlib.sha256()
    length = 0
    with open(path, "rb") as f:
        for chunk in iter(lambda: f.read(8192), b""):
            length += len(chunk)
            h.update(chunk)
    print(f"{path}: {length} bytes, sha256={h.hexdigest()}")
    return length

def print_bytes(path, start, count):
    with open(path, "rb") as f:
        f.seek(start)
        data = f.read(count)
        print(f"{path} bytes {start}-{start+count-1}: {data.hex()}")

def print_differences(path1, path2, start, count):
    with open(path1, "rb") as f1, open(path2, "rb") as f2:
        f1.seek(start)
        f2.seek(start)
        for i in range(count):
            b1 = f1.read(1)
            b2 = f2.read(1)
            if not b1 and not b2:
                break
#             if b1 != b2:
#                 print(f"Offset {start+i}: {b1.hex() if b1 else 'EOF'} vs {b2.hex() if b2 else 'EOF'}")

def print_region(path, start, count):
    with open(path, "rb") as f:
        f.seek(start)
        data = f.read(count)
        print(f"{path} bytes {start}-{start+count-1}: {data.hex()}")

if __name__ == "__main__":
    file1 = "samples/VIDEO.original"
    file2 = "samples/VIDEO.generated.mine"

    print("File info:")
    len1 = print_file_info(file1)
    len2 = print_file_info(file2)

    print("\nFirst 100 bytes:")
    print_bytes(file1, 0, 100)
    print_bytes(file2, 0, 100)

    print("\nLast 100 bytes:")
    print_bytes(file1, max(0, len1-100), 100)
    print_bytes(file2, max(0, len2-100), 100)

    print("\nRegion around first difference (offset 65490-65590):")
    print_region(file1, 65490, 100)
    print_region(file2, 65490, 100)

    print("\nExtra bytes in generated file (offset 393736-393832):")
    print_region(file2, len1, len2-len1)

    print("\nFirst 10 differences:")
    print_differences(file1, file2, 0, 10000)

    print("\nLast 10 differences:")
    print_differences(file1, file2, max(0, min(len1, len2)-10000), 10000)

    hash1, len1 = file_hash_len(file1)
    hash2, len2 = file_hash_len(file2)

    print(f"{file1}: {len1} bytes, sha256={hash1}")
    print(f"{file2}: {len2} bytes, sha256={hash2}")

    if hash1 == hash2:
        print("✅ Files are identical (byte-for-byte).")
    else:
        print("⚠️ Files differ.")
        compare_files(file1, file2)
        print("\nAll differing bytes (first 100):")
        compare_all_differences(file1, file2)

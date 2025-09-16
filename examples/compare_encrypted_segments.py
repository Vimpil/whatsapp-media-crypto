import sys

def compare_segments(file1, file2, segment_size=65536):
    try:
        with open(file1, 'rb') as f1, open(file2, 'rb') as f2:
            data1 = f1.read(segment_size)
            data2 = f2.read(segment_size)

            if data1 == data2:
                print("The first 65536 bytes of both encrypted files are identical.")
            else:
                print("The first 65536 bytes of the encrypted files are different.")

                min_length = min(len(data1), len(data2))
                for i in range(min_length):
                    if data1[i] != data2[i]:
                        print(f"Difference at byte {i}: {data1[i]:02x} != {data2[i]:02x}")

                if len(data1) > min_length:
                    print(f"Extra data in {file1} starting at byte {min_length}:")
                    print(data1[min_length:])

                if len(data2) > min_length:
                    print(f"Extra data in {file2} starting at byte {min_length}:")
                    print(data2[min_length:])

    except FileNotFoundError as e:
        print(f"Error: {e}")
    except Exception as e:
        print(f"An unexpected error occurred: {e}")

if __name__ == "__main__":
    if len(sys.argv) != 3:
        print("Usage: python compare_encrypted_segments.py <file1> <file2>")
    else:
        compare_segments(sys.argv[1], sys.argv[2])

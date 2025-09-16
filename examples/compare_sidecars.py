import sys

def compare_sidecars(file1, file2):
    try:
        with open(file1, 'rb') as f1, open(file2, 'rb') as f2:
            data1 = f1.read()
            data2 = f2.read()

            if data1 == data2:
                print("The sidecar files are identical.")
            else:
                print("The sidecar files are different.")
    except FileNotFoundError as e:
        print(f"Error: {e}")
    except Exception as e:
        print(f"An unexpected error occurred: {e}")

if __name__ == "__main__":
    if len(sys.argv) != 3:
        print("Usage: python compare_sidecars.py <file1> <file2>")
    else:
        compare_sidecars(sys.argv[1], sys.argv[2])
# python examples/compare_sidecars.py samples/VIDEO.sidecar samples/VIDEO.sidecar.mine

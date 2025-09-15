<?php

$originalFile = __DIR__ . '/../samples/VIDEO.original';
$decryptedFile = __DIR__ . '/../samples/VIDEO.generated.mine';

if (!file_exists($originalFile) || !file_exists($decryptedFile)) {
    echo "One or both files do not exist.";
    exit(1);
}

$originalHash = hash_file('sha256', $originalFile);
$decryptedHash = hash_file('sha256', $decryptedFile);

if ($originalHash === $decryptedHash) {
    echo "The files match perfectly.";
} else {
    echo "The files do not match.";
    echo "\nOriginal Hash: $originalHash";
    echo "\nDecrypted Hash: $decryptedHash";
}


<?php
// Download one saved file by its archive file record number.
require __DIR__ . '/connect.php';
require __DIR__ . '/file_storage.php';

$fileId = filter_var(isset($_GET['id']) ? $_GET['id'] : null, FILTER_VALIDATE_INT);
if (!$fileId || $fileId < 1) {
    http_response_code(404);
    exit('File not found.');
}

$statement = $pdo->prepare(
    'SELECT original_name, stored_name
     FROM archive_files
     WHERE id = :id'
);
$statement->execute(['id' => $fileId]);
$file = $statement->fetch();
$filePath = $file ? archiveStoredFilePath($file['stored_name']) : null;

if (!$file || !$filePath || !is_file($filePath)) {
    http_response_code(404);
    exit('File not found.');
}

// Always send the stored file as a download instead of displaying it in the browser.
$downloadName = rawurlencode($file['original_name']);
header('Content-Type: application/octet-stream');
header('Content-Length: ' . filesize($filePath));
header('Content-Disposition: attachment; filename="archive-file"; filename*=UTF-8\'\'' . $downloadName);
header('X-Content-Type-Options: nosniff');
readfile($filePath);
exit;

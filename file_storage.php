<?php
// Uploaded files are kept outside the public htdocs folder.
function archiveUploadDirectory()
{
    return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'da_dsa_uploads';
}

// Check upload errors, file size, and file type before saving anything.
function collectArchiveUploads($fileField)
{
    if (!isset($fileField['name']) || !is_array($fileField['name'])) {
        return [];
    }

    $allowedExtensions = [
        'csv', 'doc', 'docx', 'gif', 'jpeg', 'jpg', 'pdf', 'png',
        'ppt', 'pptx', 'txt', 'webp', 'xls', 'xlsx', 'zip',
    ];
    $maximumFileSize = 10 * 1024 * 1024;
    $uploads = [];

    foreach ($fileField['name'] as $index => $submittedName) {
        $error = isset($fileField['error'][$index]) ? $fileField['error'][$index] : UPLOAD_ERR_NO_FILE;
        if ($error === UPLOAD_ERR_NO_FILE) {
            continue;
        }

        if ($error !== UPLOAD_ERR_OK) {
            throw new RuntimeException('A file could not be uploaded. Check the file size and try again.');
        }

        $size = isset($fileField['size'][$index]) ? (int) $fileField['size'][$index] : 0;
        if ($size < 1 || $size > $maximumFileSize) {
            throw new RuntimeException('Each file must be smaller than 10 MB.');
        }

        $originalName = basename(str_replace('\\', '/', (string) $submittedName));
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!in_array($extension, $allowedExtensions, true)) {
            throw new RuntimeException(
                'This file type is not allowed. Use PDF, office documents, text, CSV, common images, or ZIP files.'
            );
        }

        $temporaryPath = isset($fileField['tmp_name'][$index]) ? $fileField['tmp_name'][$index] : '';
        if ($originalName === '' || !is_uploaded_file($temporaryPath)) {
            throw new RuntimeException('The uploaded file was not valid. Please try again.');
        }

        $uploads[] = [
            'temporary_path' => $temporaryPath,
            'original_name' => $originalName,
            'extension' => $extension,
            'file_size' => $size,
        ];
    }

    if (count($uploads) > 5) {
        throw new RuntimeException('You can upload up to 5 files at a time.');
    }

    return $uploads;
}

// Move validated uploads to private storage using random filenames.
function storeArchiveUploads($uploads)
{
    if (count($uploads) === 0) {
        return [];
    }

    $directory = archiveUploadDirectory();
    if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
        throw new RuntimeException('The server could not create the file storage folder.');
    }

    $storedFiles = [];
    try {
        foreach ($uploads as $upload) {
            $storedName = bin2hex(random_bytes(16)) . '.' . $upload['extension'];
            $destination = $directory . DIRECTORY_SEPARATOR . $storedName;

            if (!move_uploaded_file($upload['temporary_path'], $destination)) {
                throw new RuntimeException('The server could not save an uploaded file.');
            }

            $storedFiles[] = [
                'original_name' => $upload['original_name'],
                'stored_name' => $storedName,
                'file_size' => $upload['file_size'],
            ];
        }
    } catch (Throwable $exception) {
        foreach ($storedFiles as $storedFile) {
            removeArchiveStoredFile($storedFile['stored_name']);
        }
        throw $exception;
    }

    return $storedFiles;
}

// Resolve only generated archive filenames inside the private storage folder.
function archiveStoredFilePath($storedName)
{
    if (!is_string($storedName) || !preg_match('/\A[a-f0-9]{32}\.[a-z0-9]{1,10}\z/', $storedName)) {
        return null;
    }

    return archiveUploadDirectory() . DIRECTORY_SEPARATOR . $storedName;
}

// Delete one stored file. A missing file is already in the desired state.
function removeArchiveStoredFile($storedName)
{
    $path = archiveStoredFilePath($storedName);
    return $path !== null && (!is_file($path) || unlink($path));
}

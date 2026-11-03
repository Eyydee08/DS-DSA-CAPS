<?php
session_start();

require __DIR__ . '/../config/database.php';
require __DIR__ . '/../dsa/merge_sort.php';
require __DIR__ . '/../file_storage.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function escapeArchiveValue($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function setArchiveMessage($message)
{
    $_SESSION['message'] = $message;
}

function findOrCreateArchiveCategory(PDO $pdo, $categoryName)
{
    $statement = $pdo->prepare('SELECT category_id FROM categories WHERE category_name = :category_name');
    $statement->execute(['category_name' => $categoryName]);
    $categoryId = $statement->fetchColumn();

    if ($categoryId !== false) {
        return (int) $categoryId;
    }

    $statement = $pdo->prepare('INSERT INTO categories (category_name) VALUES (:category_name)');
    $statement->execute(['category_name' => $categoryName]);
    return (int) $pdo->lastInsertId();
}

function refreshArchiveFileMetadata(PDO $pdo, $archiveId)
{
    $statement = $pdo->prepare(
        'SELECT stored_name, file_size
         FROM archive_files
         WHERE archive_id = :archive_id
         ORDER BY id ASC'
    );
    $statement->execute(['archive_id' => $archiveId]);
    $files = $statement->fetchAll();
    $primaryPath = count($files) > 0 ? $files[0]['stored_name'] : null;
    $totalSizeKb = 0;
    foreach ($files as $file) {
        $totalSizeKb += (int) $file['file_size'];
    }

    $statement = $pdo->prepare(
        'UPDATE archives
         SET file_path = :file_path, file_size_kb = :file_size_kb
         WHERE archive_id = :archive_id'
    );
    $statement->execute([
        'file_path' => $primaryPath,
        'file_size_kb' => round($totalSizeKb / 1024, 2),
        'archive_id' => $archiveId,
    ]);
}

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedToken = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    if (!hash_equals($_SESSION['csrf_token'], $postedToken)) {
        http_response_code(400);
        exit('The form expired or was invalid. Go back and try again.');
    }

    $action = isset($_POST['action']) ? $_POST['action'] : '';

    if ($action === 'save') {
        $id = isset($_POST['id']) ? filter_var($_POST['id'], FILTER_VALIDATE_INT) : null;
        $title = trim(isset($_POST['title']) ? $_POST['title'] : '');
        $category = trim(isset($_POST['category']) ? $_POST['category'] : '');
        $archiveYear = trim(isset($_POST['archive_year']) ? $_POST['archive_year'] : '');
        $description = trim(isset($_POST['description']) ? $_POST['description'] : '');

        if ($title === '' || $category === '' || $archiveYear === '' || $description === '') {
            setArchiveMessage('Please fill in every field.');
        } elseif (strlen($title) > 200 || strlen($category) > 100) {
            setArchiveMessage('The title or category is too long.');
        } elseif (!ctype_digit($archiveYear) || (int) $archiveYear < 1 || (int) $archiveYear > 9999) {
            setArchiveMessage('Please enter a year between 1 and 9999.');
        } elseif (isset($_POST['id']) && (!$id || $id < 1)) {
            setArchiveMessage('The archive entry number is invalid.');
        } else {
            try {
                $uploads = collectArchiveUploads(isset($_FILES['files']) ? $_FILES['files'] : []);
            } catch (RuntimeException $exception) {
                setArchiveMessage($exception->getMessage());
                header('Location: index.php');
                exit;
            }

            $storedFiles = storeArchiveUploads($uploads);
            try {
                $pdo->beginTransaction();
                $categoryId = findOrCreateArchiveCategory($pdo, $category);

                if ($id) {
                    $statement = $pdo->prepare(
                        'SELECT file_path FROM archives WHERE archive_id = :archive_id'
                    );
                    $statement->execute(['archive_id' => $id]);
                    $existingPath = $statement->fetchColumn();
                    if ($existingPath === false) {
                        throw new RuntimeException('The archive entry was not found.');
                    }

                    $statement = $pdo->prepare(
                        'SELECT COALESCE(SUM(file_size), 0)
                         FROM archive_files
                         WHERE archive_id = :archive_id'
                    );
                    $statement->execute(['archive_id' => $id]);
                    $totalSize = (int) $statement->fetchColumn();
                    foreach ($storedFiles as $storedFile) {
                        $totalSize += $storedFile['file_size'];
                    }
                    $filePath = $existingPath;
                    if (!$filePath && count($storedFiles) > 0) {
                        $filePath = $storedFiles[0]['stored_name'];
                    }

                    $statement = $pdo->prepare(
                        'UPDATE archives
                         SET title = :title, file_path = :file_path, file_size_kb = :file_size_kb,
                             category_id = :category_id, archive_year = :archive_year,
                             description = :description
                         WHERE archive_id = :archive_id'
                    );
                    $statement->execute([
                        'title' => $title,
                        'file_path' => $filePath,
                        'file_size_kb' => round($totalSize / 1024, 2),
                        'category_id' => $categoryId,
                        'archive_year' => (int) $archiveYear,
                        'description' => $description,
                        'archive_id' => $id,
                    ]);
                } else {
                    $totalSize = 0;
                    foreach ($storedFiles as $storedFile) {
                        $totalSize += $storedFile['file_size'];
                    }
                    $filePath = count($storedFiles) > 0 ? $storedFiles[0]['stored_name'] : null;

                    $statement = $pdo->prepare(
                        'INSERT INTO archives
                         (title, file_path, file_size_kb, category_id, archive_year, description)
                         VALUES (:title, :file_path, :file_size_kb, :category_id, :archive_year, :description)'
                    );
                    $statement->execute([
                        'title' => $title,
                        'file_path' => $filePath,
                        'file_size_kb' => round($totalSize / 1024, 2),
                        'category_id' => $categoryId,
                        'archive_year' => (int) $archiveYear,
                        'description' => $description,
                    ]);
                    $id = (int) $pdo->lastInsertId();
                }

                if (count($storedFiles) > 0) {
                    $fileStatement = $pdo->prepare(
                        'INSERT INTO archive_files (archive_id, original_name, stored_name, file_size)
                         VALUES (:archive_id, :original_name, :stored_name, :file_size)'
                    );
                    foreach ($storedFiles as $storedFile) {
                        $fileStatement->execute([
                            'archive_id' => $id,
                            'original_name' => $storedFile['original_name'],
                            'stored_name' => $storedFile['stored_name'],
                            'file_size' => $storedFile['file_size'],
                        ]);
                    }
                }
                $pdo->commit();
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                foreach ($storedFiles as $storedFile) {
                    removeArchiveStoredFile($storedFile['stored_name']);
                }
                throw $exception;
            }

            setArchiveMessage(isset($_POST['id']) ? 'Archive entry updated.' : 'Archive entry added.');
        }

        header('Location: index.php');
        exit;
    }

    if ($action === 'delete_file') {
        $fileId = filter_var(isset($_POST['file_id']) ? $_POST['file_id'] : null, FILTER_VALIDATE_INT);
        $itemId = filter_var(isset($_POST['id']) ? $_POST['id'] : null, FILTER_VALIDATE_INT);
        if (!$fileId || $fileId < 1 || !$itemId || $itemId < 1) {
            setArchiveMessage('The file or archive entry number is invalid.');
        } else {
            $statement = $pdo->prepare(
                'SELECT stored_name FROM archive_files
                 WHERE id = :file_id AND archive_id = :archive_id'
            );
            $statement->execute(['file_id' => $fileId, 'archive_id' => $itemId]);
            $file = $statement->fetch();

            if (!$file) {
                setArchiveMessage('The file was not found.');
            } else {
                $pdo->beginTransaction();
                $statement = $pdo->prepare(
                    'DELETE FROM archive_files WHERE id = :file_id AND archive_id = :archive_id'
                );
                $statement->execute(['file_id' => $fileId, 'archive_id' => $itemId]);
                refreshArchiveFileMetadata($pdo, $itemId);
                $pdo->commit();

                if (!removeArchiveStoredFile($file['stored_name'])) {
                    error_log('Could not remove archive file: ' . $file['stored_name']);
                    setArchiveMessage('The file record was removed, but the stored file could not be deleted.');
                } else {
                    setArchiveMessage('File removed from the archive.');
                }
            }
        }

        header('Location: index.php');
        exit;
    }

    if ($action === 'delete') {
        $id = filter_var(isset($_POST['id']) ? $_POST['id'] : null, FILTER_VALIDATE_INT);
        if (!$id || $id < 1) {
            setArchiveMessage('The archive entry number is invalid.');
        } else {
            $statement = $pdo->prepare('SELECT stored_name FROM archive_files WHERE archive_id = :archive_id');
            $statement->execute(['archive_id' => $id]);
            $storedNames = $statement->fetchAll(PDO::FETCH_COLUMN);

            $statement = $pdo->prepare('DELETE FROM archives WHERE archive_id = :archive_id');
            $statement->execute(['archive_id' => $id]);
            if ($statement->rowCount() > 0) {
                $filesRemoved = true;
                foreach ($storedNames as $storedName) {
                    if (!removeArchiveStoredFile($storedName)) {
                        error_log('Could not remove archive file: ' . $storedName);
                        $filesRemoved = false;
                    }
                }
                setArchiveMessage(
                    $filesRemoved
                        ? 'Archive entry deleted.'
                        : 'Archive entry deleted, but a stored file could not be removed.'
                );
            } else {
                setArchiveMessage('Archive entry was not found.');
            }
        }

        header('Location: index.php');
        exit;
    }
}

$editingItem = null;
if (isset($_GET['edit'])) {
    $editId = filter_var($_GET['edit'], FILTER_VALIDATE_INT);
    if ($editId && $editId > 0) {
        $statement = $pdo->prepare(
            'SELECT a.*, c.category_name
             FROM archives AS a
             INNER JOIN categories AS c ON c.category_id = a.category_id
             WHERE a.archive_id = :archive_id'
        );
        $statement->execute(['archive_id' => $editId]);
        $editingItem = $statement->fetch();
    }
}

$search = trim(isset($_GET['search']) ? $_GET['search'] : '');
$categoryId = null;
if (isset($_GET['category_id']) && $_GET['category_id'] !== '') {
    $validatedCategoryId = filter_var($_GET['category_id'], FILTER_VALIDATE_INT);
    $categoryId = $validatedCategoryId && $validatedCategoryId > 0 ? $validatedCategoryId : 0;
}

$conditions = [];
$parameters = [];
if ($search !== '') {
    $conditions[] = '(a.title LIKE :title_search OR a.description LIKE :description_search
                     OR c.category_name LIKE :category_search)';
    $searchValue = '%' . $search . '%';
    $parameters['title_search'] = $searchValue;
    $parameters['description_search'] = $searchValue;
    $parameters['category_search'] = $searchValue;
}
if ($categoryId !== null) {
    $conditions[] = 'a.category_id = :category_id';
    $parameters['category_id'] = $categoryId;
}

$sql = 'SELECT a.*, c.category_name
        FROM archives AS a
        INNER JOIN categories AS c ON c.category_id = a.category_id';
if (count($conditions) > 0) {
    $sql .= ' WHERE ' . implode(' AND ', $conditions);
}
$statement = $pdo->prepare($sql);
$statement->execute($parameters);
$archiveItems = mergeSortArchives($statement->fetchAll());

$statement = $pdo->prepare('SELECT category_id, category_name FROM categories ORDER BY category_name ASC');
$statement->execute();
$categories = $statement->fetchAll();

$filesByItem = [];
if (count($archiveItems) > 0) {
    $itemIds = array_map('intval', array_column($archiveItems, 'archive_id'));
    $placeholders = implode(',', array_fill(0, count($itemIds), '?'));
    $statement = $pdo->prepare(
        'SELECT id, archive_id, original_name, file_size
         FROM archive_files
         WHERE archive_id IN (' . $placeholders . ')
         ORDER BY uploaded_at DESC, id DESC'
    );
    $statement->execute($itemIds);
    foreach ($statement->fetchAll() as $file) {
        $filesByItem[$file['archive_id']][] = $file;
    }
}

$message = isset($_SESSION['message']) ? $_SESSION['message'] : '';
unset($_SESSION['message']);

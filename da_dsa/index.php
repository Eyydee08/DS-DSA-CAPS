<?php
// Start a session so the website can show messages and protect form actions.
session_start();

// Load the connection to the XAMPP database containing archive_items.
require __DIR__ . '/connect.php';
require __DIR__ . '/file_storage.php';

// Make a token used to check that update and delete forms came from this page.
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Escape text before showing it in the HTML page.
function escape($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

// Keep a message for the next page view after a form has been submitted.
function setMessage($message)
{
    $_SESSION['message'] = $message;
}

// Process add, edit, and delete forms before writing any page HTML.
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
            setMessage('Please fill in every field.');
        } elseif (!ctype_digit($archiveYear) || (int) $archiveYear < 1 || (int) $archiveYear > 9999) {
            setMessage('Please enter a year between 1 and 9999.');
        } elseif (isset($_POST['id']) && (!$id || $id < 1)) {
            setMessage('The archive entry number is invalid.');
        } else {
            try {
                $uploads = collectArchiveUploads(isset($_FILES['files']) ? $_FILES['files'] : []);
            } catch (RuntimeException $exception) {
                setMessage($exception->getMessage());
                header('Location: index.php');
                exit;
            }

            // Save the archive entry and its file records together.
            $storedFiles = storeArchiveUploads($uploads);
            try {
                $pdo->beginTransaction();
                if ($id) {
                    $statement = $pdo->prepare(
                        'UPDATE archive_items
                         SET title = :title, category = :category, archive_year = :archive_year,
                             description = :description
                         WHERE id = :id'
                    );
                    $statement->execute([
                        'title' => $title,
                        'category' => $category,
                        'archive_year' => (int) $archiveYear,
                        'description' => $description,
                        'id' => $id,
                    ]);
                    if ($statement->rowCount() === 0) {
                        $check = $pdo->prepare('SELECT id FROM archive_items WHERE id = :id');
                        $check->execute(['id' => $id]);
                        if (!$check->fetch()) {
                            throw new RuntimeException('The archive entry was not found.');
                        }
                    }
                } else {
                    $statement = $pdo->prepare(
                        'INSERT INTO archive_items (title, category, archive_year, description)
                         VALUES (:title, :category, :archive_year, :description)'
                    );
                    $statement->execute([
                        'title' => $title,
                        'category' => $category,
                        'archive_year' => (int) $archiveYear,
                        'description' => $description,
                    ]);
                    $id = (int) $pdo->lastInsertId();
                }

                if (count($storedFiles) > 0) {
                    $fileStatement = $pdo->prepare(
                        'INSERT INTO archive_files (archive_item_id, original_name, stored_name, file_size)
                         VALUES (:archive_item_id, :original_name, :stored_name, :file_size)'
                    );
                    foreach ($storedFiles as $storedFile) {
                        $fileStatement->execute([
                            'archive_item_id' => $id,
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

            setMessage($id && isset($_POST['id']) ? 'Archive entry updated.' : 'Archive entry added.');
        }

        header('Location: index.php');
        exit;
    }

    if ($action === 'delete_file') {
        $fileId = filter_var(isset($_POST['file_id']) ? $_POST['file_id'] : null, FILTER_VALIDATE_INT);
        $itemId = filter_var(isset($_POST['id']) ? $_POST['id'] : null, FILTER_VALIDATE_INT);
        if (!$fileId || $fileId < 1 || !$itemId || $itemId < 1) {
            setMessage('The file or archive entry number is invalid.');
        } else {
            $statement = $pdo->prepare(
                'SELECT stored_name FROM archive_files
                 WHERE id = :file_id AND archive_item_id = :item_id'
            );
            $statement->execute(['file_id' => $fileId, 'item_id' => $itemId]);
            $file = $statement->fetch();

            if (!$file) {
                setMessage('The file was not found.');
            } else {
                $statement = $pdo->prepare(
                    'DELETE FROM archive_files WHERE id = :file_id AND archive_item_id = :item_id'
                );
                $statement->execute(['file_id' => $fileId, 'item_id' => $itemId]);
                if (!removeArchiveStoredFile($file['stored_name'])) {
                    error_log('Could not remove archive file: ' . $file['stored_name']);
                    setMessage('The file record was removed, but the stored file could not be deleted.');
                } else {
                    setMessage('File removed from the archive.');
                }
            }
        }

        header('Location: index.php');
        exit;
    }

    if ($action === 'delete') {
        $id = filter_var(isset($_POST['id']) ? $_POST['id'] : null, FILTER_VALIDATE_INT);
        if (!$id || $id < 1) {
            setMessage('The archive entry number is invalid.');
        } else {
            $statement = $pdo->prepare('SELECT stored_name FROM archive_files WHERE archive_item_id = :id');
            $statement->execute(['id' => $id]);
            $storedNames = $statement->fetchAll(PDO::FETCH_COLUMN);

            $statement = $pdo->prepare('DELETE FROM archive_items WHERE id = :id');
            $statement->execute(['id' => $id]);
            if ($statement->rowCount() > 0) {
                $filesRemoved = true;
                foreach ($storedNames as $storedName) {
                    if (!removeArchiveStoredFile($storedName)) {
                        error_log('Could not remove archive file: ' . $storedName);
                        $filesRemoved = false;
                    }
                }
                setMessage($filesRemoved
                    ? 'Archive entry deleted.'
                    : 'Archive entry deleted, but a stored file could not be removed.');
            } else {
                setMessage('Archive entry was not found.');
            }
        }

        header('Location: index.php');
        exit;
    }
}

// Load the entry selected for editing, if there is one.
$editingItem = null;
if (isset($_GET['edit'])) {
    $editId = filter_var($_GET['edit'], FILTER_VALIDATE_INT);
    if ($editId && $editId > 0) {
        $statement = $pdo->prepare('SELECT * FROM archive_items WHERE id = :id');
        $statement->execute(['id' => $editId]);
        $editingItem = $statement->fetch();
    }
}

// Search titles, categories, and descriptions, or show every entry by default.
$search = trim(isset($_GET['search']) ? $_GET['search'] : '');
if ($search !== '') {
    $statement = $pdo->prepare(
        'SELECT * FROM archive_items
         WHERE title LIKE :title_search OR category LIKE :category_search
            OR description LIKE :description_search
         ORDER BY archive_year DESC, id DESC'
    );
    $searchValue = '%' . $search . '%';
    $statement->execute([
        'title_search' => $searchValue,
        'category_search' => $searchValue,
        'description_search' => $searchValue,
    ]);
} else {
    $statement = $pdo->query('SELECT * FROM archive_items ORDER BY archive_year DESC, id DESC');
}
$archiveItems = $statement->fetchAll();

// Load the files belonging to the records shown on this page.
$filesByItem = [];
if (count($archiveItems) > 0) {
    $itemIds = array_column($archiveItems, 'id');
    $placeholders = implode(',', array_fill(0, count($itemIds), '?'));
    $statement = $pdo->prepare(
        'SELECT id, archive_item_id, original_name, file_size
         FROM archive_files
         WHERE archive_item_id IN (' . $placeholders . ')
         ORDER BY uploaded_at DESC, id DESC'
    );
    $statement->execute($itemIds);
    foreach ($statement->fetchAll() as $file) {
        $filesByItem[$file['archive_item_id']][] = $file;
    }
}

// Read and clear a message created by the last form submission.
$message = isset($_SESSION['message']) ? $_SESSION['message'] : '';
unset($_SESSION['message']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Simple Website Archive</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <!-- The header introduces the archive website. -->
    <header>
        <h1>Website Archive</h1>
        <p>A simple place to keep and find archive records.</p>
        <a href="archive_files.php">View all attached files</a>
    </header>

    <main>
        <!-- This message area shows whether the last action worked. -->
        <?php if ($message !== ''): ?>
            <p class="message"><?= escape($message) ?></p>
        <?php endif; ?>

        <!-- This form adds a new record or fills in the selected record for editing. -->
        <section>
            <h2><?= $editingItem ? 'Edit an archive entry' : 'Add an archive entry' ?></h2>
            <form method="post" action="index.php" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= escape($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="action" value="save">
                <?php if ($editingItem): ?>
                    <input type="hidden" name="id" value="<?= escape($editingItem['id']) ?>">
                <?php endif; ?>

                <label for="title">Title</label>
                <input id="title" name="title" type="text" maxlength="200" required
                       value="<?= escape($editingItem ? $editingItem['title'] : '') ?>">

                <label for="category">Category</label>
                <input id="category" name="category" type="text" maxlength="100" required
                       value="<?= escape($editingItem ? $editingItem['category'] : '') ?>">

                <label for="archive_year">Year</label>
                <input id="archive_year" name="archive_year" type="number" min="1" max="9999" required
                       value="<?= escape($editingItem ? $editingItem['archive_year'] : '') ?>">

                <label for="description">Description</label>
                <textarea id="description" name="description" rows="4" required><?= escape(
                    $editingItem ? $editingItem['description'] : ''
                ) ?></textarea>

                <label for="files">Attach files (up to 5 files, 10 MB each)</label>
                <input id="files" name="files[]" type="file" multiple
                       accept=".csv,.doc,.docx,.gif,.jpeg,.jpg,.pdf,.png,.ppt,.pptx,.txt,.webp,.xls,.xlsx,.zip">

                <button type="submit"><?= $editingItem ? 'Save changes' : 'Add to archive' ?></button>
                <?php if ($editingItem): ?>
                    <a class="cancel-link" href="index.php">Cancel editing</a>
                <?php endif; ?>
            </form>
        </section>

        <!-- This search form filters the archive by its text fields. -->
        <section>
            <h2>Find an entry</h2>
            <form class="search-form" method="get" action="index.php">
                <label for="search">Search the archive</label>
                <input id="search" name="search" type="search" value="<?= escape($search) ?>">
                <button type="submit">Search</button>
                <?php if ($search !== ''): ?>
                    <a class="cancel-link" href="index.php">Show all entries</a>
                <?php endif; ?>
            </form>
        </section>

        <!-- This list displays saved records and provides edit and delete actions. -->
        <section>
            <h2>Archive entries</h2>
            <?php if (count($archiveItems) === 0): ?>
                <p>No archive entries found.</p>
            <?php else: ?>
                <?php foreach ($archiveItems as $item): ?>
                    <article class="archive-item">
                        <h3><?= escape($item['title']) ?></h3>
                        <p><strong>Category:</strong> <?= escape($item['category']) ?></p>
                        <p><strong>Year:</strong> <?= escape($item['archive_year']) ?></p>
                        <p><?= nl2br(escape($item['description'])) ?></p>
                        <?php if (!empty($filesByItem[$item['id']])): ?>
                            <h4>Attached files</h4>
                            <ul>
                                <?php foreach ($filesByItem[$item['id']] as $file): ?>
                                    <li>
                                        <a href="download.php?id=<?= escape($file['id']) ?>"><?= escape($file['original_name']) ?></a>
                                        (<?= number_format($file['file_size'] / 1024, 1) ?> KB)
                                        <form class="inline-form" method="post" action="index.php"
                                              onsubmit="return confirm('Remove this file?');">
                                            <input type="hidden" name="csrf_token" value="<?= escape($_SESSION['csrf_token']) ?>">
                                            <input type="hidden" name="action" value="delete_file">
                                            <input type="hidden" name="id" value="<?= escape($item['id']) ?>">
                                            <input type="hidden" name="file_id" value="<?= escape($file['id']) ?>">
                                            <button class="delete-button" type="submit">Remove file</button>
                                        </form>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                        <div class="actions">
                            <a href="index.php?edit=<?= escape($item['id']) ?>">Edit</a>
                            <form method="post" action="index.php" onsubmit="return confirm('Delete this entry?');">
                                <input type="hidden" name="csrf_token" value="<?= escape($_SESSION['csrf_token']) ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= escape($item['id']) ?>">
                                <button class="delete-button" type="submit">Delete</button>
                            </form>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>
    </main>

    <!-- The footer is a small closing note for the page. -->
    <footer>
        <p>Simple Website Archive</p>
    </footer>
</body>
</html>

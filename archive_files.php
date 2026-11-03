<?php
// Connect to website_archive and read the attachment records from archive_files.
require __DIR__ . '/config/database.php';

// Escape database values before displaying them in the page.
function escapeArchiveFileValue($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

// Show each file together with the archive entry it belongs to.
$statement = $pdo->prepare(
    'SELECT archive_files.id, archive_files.original_name, archive_files.file_size,
            archive_files.uploaded_at, archives.archive_id,
            archives.title AS archive_title
     FROM archive_files
     INNER JOIN archives ON archives.archive_id = archive_files.archive_id
     ORDER BY archive_files.uploaded_at DESC, archive_files.id DESC'
);
$statement->execute();
$archiveFiles = $statement->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Archive Files</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <!-- The header introduces the list of files saved in the database. -->
    <header>
        <h1>Archive Files</h1>
        <p>Files attached to archive entries.</p>
        <a href="index.php">Back to the archive</a>
    </header>

    <main>
        <!-- This section lists files retrieved from the archive_files table. -->
        <section>
            <h2>Saved files</h2>
            <?php if (count($archiveFiles) === 0): ?>
                <p>No files have been added to the archive yet.</p>
            <?php else: ?>
                <ul>
                    <?php foreach ($archiveFiles as $file): ?>
                        <li>
                            <a href="download.php?id=<?= escapeArchiveFileValue($file['id']) ?>">
                                <?= escapeArchiveFileValue($file['original_name']) ?>
                            </a>
                            — attached to
                            <a href="index.php?search=<?= rawurlencode($file['archive_title']) ?>">
                                <?= escapeArchiveFileValue($file['archive_title']) ?>
                            </a>
                            (<?= number_format($file['file_size'] / 1024, 1) ?> KB)
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </main>

    <!-- This footer links back to the main archive page. -->
    <footer>
        <a href="index.php">Back to the archive</a>
    </footer>
</body>
</html>

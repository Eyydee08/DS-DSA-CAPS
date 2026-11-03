<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Archive Application</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header>
        <h1>Archive Application</h1>
        <p>Store, search, and manage archived records.</p>
        <a href="archive_files.php">View all attached files</a>
    </header>

    <main>
        <?php if ($message !== ''): ?>
            <p class="message"><?= escapeArchiveValue($message) ?></p>
        <?php endif; ?>

        <section>
            <h2><?= $editingItem ? 'Edit an archive entry' : 'Add an archive entry' ?></h2>
            <form method="post" action="index.php" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= escapeArchiveValue($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="action" value="save">
                <?php if ($editingItem): ?>
                    <input type="hidden" name="id" value="<?= escapeArchiveValue($editingItem['archive_id']) ?>">
                <?php endif; ?>

                <label for="title">Title</label>
                <input id="title" name="title" type="text" maxlength="200" required
                       value="<?= escapeArchiveValue($editingItem ? $editingItem['title'] : '') ?>">

                <label for="category">Category</label>
                <input id="category" name="category" type="text" maxlength="100" required
                       list="category-options"
                       value="<?= escapeArchiveValue($editingItem ? $editingItem['category_name'] : '') ?>">
                <datalist id="category-options">
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= escapeArchiveValue($category['category_name']) ?>">
                    <?php endforeach; ?>
                </datalist>

                <label for="archive_year">Year</label>
                <input id="archive_year" name="archive_year" type="number" min="1" max="9999" required
                       value="<?= escapeArchiveValue($editingItem ? $editingItem['archive_year'] : '') ?>">

                <label for="description">Description</label>
                <textarea id="description" name="description" rows="4" required><?= escapeArchiveValue(
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

        <section>
            <h2>Find an entry</h2>
            <form class="search-form" method="get" action="index.php">
                <label for="search">Keyword</label>
                <input id="search" name="search" type="search" value="<?= escapeArchiveValue($search) ?>">

                <label for="category_id">Filter by category</label>
                <select id="category_id" name="category_id">
                    <option value="">All categories</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= escapeArchiveValue($category['category_id']) ?>"
                            <?= (string) $categoryId === (string) $category['category_id'] ? 'selected' : '' ?>>
                            <?= escapeArchiveValue($category['category_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <button type="submit">Search</button>
                <?php if ($search !== '' || $categoryId !== null): ?>
                    <a class="cancel-link" href="index.php">Show all entries</a>
                <?php endif; ?>
            </form>
        </section>

        <section>
            <h2>Archive entries</h2>
            <?php if (count($archiveItems) === 0): ?>
                <p>No archive entries found.</p>
            <?php else: ?>
                <?php foreach ($archiveItems as $item): ?>
                    <article class="archive-item">
                        <h3><?= escapeArchiveValue($item['title']) ?></h3>
                        <p><strong>Category:</strong> <?= escapeArchiveValue($item['category_name']) ?></p>
                        <p><strong>Year:</strong> <?= escapeArchiveValue($item['archive_year']) ?></p>
                        <p><strong>Total file size:</strong> <?= number_format((float) $item['file_size_kb'], 2) ?> KB</p>
                        <p><?= nl2br(escapeArchiveValue($item['description'])) ?></p>
                        <?php if (!empty($filesByItem[$item['archive_id']])): ?>
                            <h4>Attached files</h4>
                            <ul>
                                <?php foreach ($filesByItem[$item['archive_id']] as $file): ?>
                                    <li>
                                        <a href="download.php?id=<?= escapeArchiveValue($file['id']) ?>">
                                            <?= escapeArchiveValue($file['original_name']) ?>
                                        </a>
                                        (<?= number_format($file['file_size'] / 1024, 1) ?> KB)
                                        <form class="inline-form" method="post" action="index.php"
                                              onsubmit="return confirm('Remove this file?');">
                                            <input type="hidden" name="csrf_token"
                                                   value="<?= escapeArchiveValue($_SESSION['csrf_token']) ?>">
                                            <input type="hidden" name="action" value="delete_file">
                                            <input type="hidden" name="id"
                                                   value="<?= escapeArchiveValue($item['archive_id']) ?>">
                                            <input type="hidden" name="file_id"
                                                   value="<?= escapeArchiveValue($file['id']) ?>">
                                            <button class="delete-button" type="submit">Remove file</button>
                                        </form>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                        <div class="actions">
                            <a href="index.php?edit=<?= escapeArchiveValue($item['archive_id']) ?>">Edit</a>
                            <form method="post" action="index.php" onsubmit="return confirm('Delete this entry?');">
                                <input type="hidden" name="csrf_token"
                                       value="<?= escapeArchiveValue($_SESSION['csrf_token']) ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= escapeArchiveValue($item['archive_id']) ?>">
                                <button class="delete-button" type="submit">Delete</button>
                            </form>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>
    </main>

    <footer>
        <p>Secret Files ni Lars</p>
    </footer>
</body>
</html>

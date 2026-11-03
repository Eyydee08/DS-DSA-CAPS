# Simple Website Archive

A small PHP and MySQL archive catalog made for a local XAMPP installation. It
lets you add, search, edit, and delete archive entries.

## Run it with XAMPP

1. Copy this folder into `C:\xampp\htdocs\da_dsa` (or use this folder in place).
2. Open the XAMPP Control Panel and start **Apache** and **MySQL**.
3. Open `http://localhost/phpmyadmin`.
4. Choose **Import**, select the existing `database.sql` file (currently in the
   `unused` folder), and run the import. The script creates the `website_archive`
   database and its `archive_items` table.
5. In phpMyAdmin, import `archive_files.sql` into the same database. It creates the
   table used to connect uploaded files to archive entries.
6. Visit `http://localhost/da_dsa/`.
   To view every uploaded attachment, open `http://localhost/da_dsa/archive_files.php`.

The default connection settings in `connect.php` are for XAMPP's usual local MySQL
setup (`127.0.0.1`, user `root`, and no password). If your MySQL settings are
different, update the host, database, username, and password at the top of
`connect.php`. The connection selects the `website_archive` database; the
website reads and writes the `archive_items` and `archive_files` tables inside it.

## Attach files

When adding or editing an entry, select up to five files (10 MB each). The site
accepts PDF, Word, Excel, and PowerPoint documents, text and CSV files, common
image types, and ZIP files. Attachments are stored outside the public website
folder and downloaded through `download.php`. Deleting an entry also removes its
attachments.

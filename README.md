# Archive Web Application

A PHP and MySQL archive catalog demonstrating normalized relational storage,
prepared-statement database access, and in-memory MergeSort.

## Run with XAMPP

1. Place the project at `C:\xampp\htdocs\da_dsa`.
2. Start Apache and MySQL from the XAMPP Control Panel.
3. Import the root-level `schema.sql` in phpMyAdmin. It creates the
   `website_archive` database, its tables, and sample category/archive records.
4. Visit `http://localhost/da_dsa/`.

The default connection in `config/database.php` uses `127.0.0.1`, the `root`
account, and an empty password. Update those settings to match your local MySQL
installation if needed.

## Features

- Add, edit, search, and delete archive records.
- Filter records by category and keyword.
- Store up to five attachments per archive entry; uploaded files are stored
  outside the public web root and downloaded through `download.php`.
- New category names are added to the normalized `categories` table.
- All user-supplied SQL values use PDO prepared statements.

## Project structure

- `config/`: PDO connection
- `controllers/`: request handling and database operations
- `dsa/`: in-memory MergeSort implementation
- `views/`: archive page rendering
- `schema.sql`: database schema, constraints, and seed data
- `TECHNICAL-SUMMARY.md`: DSA workflow and complexity analysis

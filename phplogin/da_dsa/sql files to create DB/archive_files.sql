-- Add this table to the existing website_archive database for uploaded files.
USE website_archive;

CREATE TABLE IF NOT EXISTS archive_files (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    archive_item_id INT UNSIGNED NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    stored_name VARCHAR(100) NOT NULL,
    file_size BIGINT UNSIGNED NOT NULL,
    uploaded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY archive_files_item_id (archive_item_id),
    CONSTRAINT archive_files_item_fk
        FOREIGN KEY (archive_item_id) REFERENCES archive_items (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

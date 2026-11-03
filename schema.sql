CREATE DATABASE IF NOT EXISTS website_archive
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE website_archive;

CREATE TABLE IF NOT EXISTS categories (
    category_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    category_name VARCHAR(100) NOT NULL,
    PRIMARY KEY (category_id),
    UNIQUE KEY categories_name_unique (category_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS archives (
    archive_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(200) NOT NULL,
    file_path VARCHAR(255) NULL,
    file_size_kb DECIMAL(12, 2) UNSIGNED NOT NULL DEFAULT 0.00,
    category_id INT UNSIGNED NOT NULL,
    archive_year SMALLINT UNSIGNED NOT NULL,
    description TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (archive_id),
    KEY archives_category_id (category_id),
    CONSTRAINT archives_category_fk
        FOREIGN KEY (category_id) REFERENCES categories (category_id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS archive_files (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    archive_id INT UNSIGNED NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    stored_name VARCHAR(100) NOT NULL,
    file_size BIGINT UNSIGNED NOT NULL,
    uploaded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY archive_files_archive_id (archive_id),
    CONSTRAINT archive_files_archive_fk
        FOREIGN KEY (archive_id) REFERENCES archives (archive_id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO categories (category_id, category_name) VALUES
    (1, 'Documents'),
    (2, 'Images'),
    (3, 'Reports');

INSERT IGNORE INTO archives
    (archive_id, title, file_path, file_size_kb, category_id, archive_year, description)
VALUES
    (1, 'Sample project report', NULL, 0.00, 3, 2024, 'Example report archive entry.'),
    (2, 'Historical photographs', NULL, 0.00, 2, 2022, 'Example image archive entry.'),
    (3, 'Meeting notes', NULL, 0.00, 1, 2023, 'Example document archive entry.');

-- Create the database and table used by the Website Archive.
CREATE DATABASE IF NOT EXISTS website_archive
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE website_archive;

CREATE TABLE IF NOT EXISTS archive_items (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(200) NOT NULL,
    category VARCHAR(100) NOT NULL,
    archive_year SMALLINT UNSIGNED NOT NULL,
    description TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

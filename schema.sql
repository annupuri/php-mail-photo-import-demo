-- Schema for the photo uploader.
--
--   mysql -u root -p -e "CREATE DATABASE uploader DEFAULT CHARSET = utf8mb4;"
--   mysql -u root -p uploader < schema.sql
--
-- The application connects as the account configured in dao.php
-- (_DB_USER / _DB_PASS, "demo" / "demo" by default), so grant it access too:
--
--   GRANT ALL PRIVILEGES ON uploader.* TO 'demo'@'localhost' IDENTIFIED BY 'demo';
--
-- Upgrading a table that still has the old field1 / field2 columns:
--
--   ALTER TABLE images CHANGE field1 place VARCHAR(255) NOT NULL DEFAULT '';
--   ALTER TABLE images CHANGE field2 `comment` TEXT;

CREATE TABLE IF NOT EXISTS images (
    id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
    title     VARCHAR(255) NOT NULL DEFAULT '',  -- caption shown above the photo
    file_name VARCHAR(255) NOT NULL,             -- file written under _IMAGE_DIR
    user      VARCHAR(64)  NOT NULL,             -- owner (the login / mail subject)
    place     VARCHAR(255) NOT NULL DEFAULT '',  -- free text, rendered as a link
    `comment` TEXT,                              -- free-text note
    PRIMARY KEY (id),
    KEY idx_user (user)
) DEFAULT CHARSET = utf8mb4;

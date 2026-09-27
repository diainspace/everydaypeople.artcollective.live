CREATE TABLE schema_migrations (
    version VARCHAR(64) NOT NULL PRIMARY KEY,
    applied_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE administrators (
    id VARCHAR(64) NOT NULL PRIMARY KEY,
    username VARCHAR(100) NOT NULL,
    short_name VARCHAR(150) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    password_updated_at DATETIME(6) NOT NULL,
    UNIQUE KEY administrators_username_unique (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE site_settings (
    id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    time_zone VARCHAR(64) NOT NULL,
    posts_per_page SMALLINT UNSIGNED NOT NULL,
    CONSTRAINT site_settings_single_row CHECK (id = 1),
    CONSTRAINT site_settings_posts_per_page_positive CHECK (posts_per_page >= 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE categories (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    description TEXT NOT NULL,
    UNIQUE KEY categories_name_unique (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE posts (
    id VARCHAR(64) NOT NULL PRIMARY KEY,
    author_id VARCHAR(64) NOT NULL,
    category_id BIGINT UNSIGNED NULL,
    status VARCHAR(20) NOT NULL,
    published_at DATETIME(6) NOT NULL,
    expires_at DATETIME(6) NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    content LONGTEXT NOT NULL,
    cover_photo_src TEXT NULL,
    cover_photo_alt TEXT NULL,
    cover_photo_caption TEXT NULL,
    blog_photo_src TEXT NULL,
    blog_photo_alt TEXT NULL,
    blog_photo_caption TEXT NULL,
    permalink VARCHAR(191) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    UNIQUE KEY posts_permalink_unique (permalink),
    KEY posts_publication_lookup (status, published_at, expires_at),
    KEY posts_category_id_index (category_id),
    CONSTRAINT posts_author_fk FOREIGN KEY (author_id)
        REFERENCES administrators (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT posts_category_fk FOREIGN KEY (category_id)
        REFERENCES categories (id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT posts_status_allowed CHECK (status IN ('draft', 'published', 'archived')),
    CONSTRAINT posts_expiration_after_publication CHECK (expires_at IS NULL OR expires_at > published_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO schema_migrations (version) VALUES ('001_initial_schema');

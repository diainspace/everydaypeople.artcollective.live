CREATE TABLE website_content (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    content_key VARCHAR(100) NOT NULL,
    admin_label VARCHAR(180) NOT NULL,
    content_type VARCHAR(20) NOT NULL,
    content_value LONGTEXT NOT NULL,
    sort_order SMALLINT UNSIGNED NOT NULL,
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    UNIQUE KEY website_content_key_unique (content_key),
    CONSTRAINT website_content_type_allowed CHECK (content_type IN ('text', 'textarea', 'markdown'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO website_content (content_key, admin_label, content_type, content_value, sort_order) VALUES
    ('home_main_statement', 'Homepage — Main Statement', 'textarea', 'Art lives with people.', 10),
    ('home_supporting_text', 'Homepage — Supporting Text', 'textarea', 'Art, stories, and updates.', 20),
    ('home_belief_statement', 'Homepage — What We Believe', 'textarea', 'The work does not need permission to be direct, unfinished, difficult, joyful, or strange.', 30),
    ('about_title', 'About Page — Title', 'text', 'About', 40),
    ('about_body', 'About Page — Body', 'markdown', '', 50),
    ('footer_closing_statement', 'Footer — Closing Statement', 'textarea', 'Make room for the unfinished.', 60);

INSERT INTO schema_migrations (version) VALUES ('002_website_content');

ALTER TABLE categories
    ADD COLUMN updated_at DATETIME(6) NOT NULL
        DEFAULT CURRENT_TIMESTAMP(6)
        ON UPDATE CURRENT_TIMESTAMP(6);

INSERT INTO schema_migrations (version) VALUES ('005_manage_categories');

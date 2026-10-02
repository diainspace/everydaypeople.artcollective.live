ALTER TABLE website_content DROP CONSTRAINT website_content_type_allowed;
ALTER TABLE website_content
    ADD CONSTRAINT website_content_type_allowed
    CHECK (content_type IN ('text', 'textarea', 'markdown', 'toggle'));

UPDATE website_content
SET admin_label = 'Archive Panel Text', sort_order = 20
WHERE content_key = 'home_supporting_text';

UPDATE website_content
SET admin_label = 'Archive Button Text', sort_order = 40
WHERE content_key = 'home_archive_link_text';

UPDATE website_content SET sort_order = 80 WHERE content_key = 'home_about_label';
UPDATE website_content SET sort_order = 90 WHERE content_key = 'home_belief_statement';

INSERT INTO website_content (content_key, admin_label, content_type, content_value, sort_order) VALUES
    ('promote_archive', 'Promote Archive', 'toggle', '0', 15),
    ('alternate_promotion_text', 'Promotional Text', 'textarea', 'Art, stories, and updates.', 50),
    ('alternate_show_button', 'Show Button', 'toggle', '0', 60),
    ('alternate_button_text', 'Button Text', 'text', 'Learn more', 65),
    ('alternate_button_url', 'Button URL', 'text', '/about/', 70);

INSERT INTO schema_migrations (version) VALUES ('004_archive_promotion');

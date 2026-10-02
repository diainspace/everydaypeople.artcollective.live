UPDATE website_content SET admin_label = 'Main Statement', sort_order = 10 WHERE content_key = 'home_main_statement';
UPDATE website_content SET admin_label = 'Supporting Text', sort_order = 20 WHERE content_key = 'home_supporting_text';
UPDATE website_content SET admin_label = 'About Tagline (homepage)', sort_order = 40 WHERE content_key = 'home_belief_statement';
UPDATE website_content SET admin_label = 'Title', sort_order = 100 WHERE content_key = 'about_title';
UPDATE website_content SET admin_label = 'Body', sort_order = 110 WHERE content_key = 'about_body';
UPDATE website_content SET admin_label = 'Closing Statement', sort_order = 220 WHERE content_key = 'footer_closing_statement';

INSERT INTO website_content (content_key, admin_label, content_type, content_value, sort_order) VALUES
    ('home_about_label', 'About Section Label', 'text', 'What we believe', 30),
    ('home_archive_link_text', 'Archive Link Text', 'text', 'Enter the archive', 50),
    ('footer_collective_name', 'Collective Name', 'textarea', 'Everyday People\nArt Collective', 200),
    ('footer_collective_link', 'Collective Name Link', 'text', '/', 210),
    ('footer_supporting_text', 'Supporting Text', 'textarea', 'Art, stories, and updates.', 215),
    ('footer_copyright_statement', 'Copyright Statement', 'textarea', 'Everyday People Art Collective. All rights reserved. Reproduction without permission is prohibited.', 230);

INSERT INTO schema_migrations (version) VALUES ('003_expand_website_content');

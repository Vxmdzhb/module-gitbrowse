-- Git server: the admin page that connects the git browser to a gitweb
-- server, and its link in the admin panel's main menu.
--
-- Runs once (it is a migration), after migrate_zzzzz_gitbrowse_pages.sql.
-- `tools/module.php purge gitbrowse` forgets it, so the next
-- `tools/install.php --schema-only` puts it back.

INSERT IGNORE INTO `page` (url_id, i18n, file_name, template, controller, hidden, comments)
VALUES ('WORDING_ADMIN_CONFIG_GITBROWSE', 1, 'admin_config_gitbrowse', 1, 1, 0, 0);

UPDATE `page` SET `module` = 'gitbrowse'
 WHERE `module` = '' AND `file_name` = 'admin_config_gitbrowse';

-- Under the admin root: that ancestry gates it and puts its template in admin/.
INSERT IGNORE INTO `page_closure` (ancestor, descendant)
SELECT id, id FROM `page` WHERE url_id = 'WORDING_ADMIN_CONFIG_GITBROWSE';
INSERT IGNORE INTO `page_closure` (ancestor, descendant)
SELECT a.id, d.id FROM `page` a, `page` d
 WHERE a.url_id = 'WORDING_ADMIN' AND d.url_id = 'WORDING_ADMIN_CONFIG_GITBROWSE';

INSERT IGNORE INTO `page_meta` (page_id, title, description)
SELECT id, '', '' FROM `page` WHERE url_id = 'WORDING_ADMIN_CONFIG_GITBROWSE';

INSERT IGNORE INTO `page_robots` (page_id, `index`, follow)
SELECT id, 0, 0 FROM `page` WHERE url_id = 'WORDING_ADMIN_CONFIG_GITBROWSE';

-- ── Navbar link ──────────────────────────────────────────────────────────────
SET @gitadmin_page := (SELECT id FROM `page` WHERE url_id = 'WORDING_ADMIN_CONFIG_GITBROWSE' LIMIT 1);
SET @gitadmin_navbar := (SELECT id FROM `navbar` WHERE name = 'admin' LIMIT 1);
-- The main menu: the pin holding Users, else the alphabetical pin, else the
-- last pin (the rule migrate_zzz_admin_nav_pin_fix.sql uses).
SET @gitadmin_pin := COALESCE(
    (SELECT e.pin_id FROM `navbar_entry` e
       JOIN `navbar_pin` np ON np.id = e.pin_id
      WHERE np.navbar_id = @gitadmin_navbar AND e.name = 'WORDING_ADMIN_USERS' LIMIT 1),
    (SELECT id FROM `navbar_pin` WHERE navbar_id = @gitadmin_navbar AND sort_mode = 0 ORDER BY sort_order ASC, id ASC LIMIT 1),
    (SELECT id FROM `navbar_pin` WHERE navbar_id = @gitadmin_navbar ORDER BY sort_order DESC, id DESC LIMIT 1)
);
SET @gitadmin_existing := (
    SELECT ni.id FROM `navbar_internal` ni
      JOIN `navbar_entry` e  ON e.id = ni.id
      JOIN `navbar_pin`   np ON np.id = e.pin_id
     WHERE ni.page_id = @gitadmin_page AND np.navbar_id = @gitadmin_navbar LIMIT 1
);
INSERT INTO `navbar_entry_ids` (id)
SELECT NULL WHERE @gitadmin_page IS NOT NULL AND @gitadmin_pin IS NOT NULL AND @gitadmin_existing IS NULL;
SET @gitadmin_nav := COALESCE(@gitadmin_existing, LAST_INSERT_ID());
INSERT IGNORE INTO `navbar_entry` (id, pin_id, internal, name, i18n, active, sort_order)
SELECT @gitadmin_nav, @gitadmin_pin, 1, 'WORDING_ADMIN_CONFIG_GITBROWSE', 1, 1, 0
 WHERE @gitadmin_page IS NOT NULL AND @gitadmin_pin IS NOT NULL AND @gitadmin_nav IS NOT NULL;
INSERT IGNORE INTO `navbar_internal` (id, page_id)
SELECT @gitadmin_nav, @gitadmin_page
 WHERE @gitadmin_page IS NOT NULL AND @gitadmin_nav IS NOT NULL;

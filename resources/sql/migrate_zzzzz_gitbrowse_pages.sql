-- Git repositories: the module's page and its navbar link.
--
-- Runs once (it is a migration), after core's navbar layout, so the link
-- lands straight in the right pin. `tools/module.php purge gitbrowse` forgets it,
-- so the next `tools/install.php --schema-only` puts everything back.
-- Its link goes in the public navbar.

INSERT IGNORE INTO `page` (url_id, i18n, file_name, template, controller, hidden, comments)
VALUES ('WORDING_GITBROWSE', 1, 'git_browse', 1, 1, 0, 0);

UPDATE `page` SET `module` = 'gitbrowse'
 WHERE `module` = '' AND `file_name` = 'git_browse';

INSERT IGNORE INTO `page_closure` (ancestor, descendant)
SELECT id, id FROM `page` WHERE url_id = 'WORDING_GITBROWSE';

INSERT IGNORE INTO `page_meta` (page_id, title, description)
SELECT id, '', '' FROM `page` WHERE url_id = 'WORDING_GITBROWSE';

INSERT IGNORE INTO `page_robots` (page_id, `index`, follow)
SELECT id, 1, 1 FROM `page` WHERE url_id = 'WORDING_GITBROWSE';

-- ── Navbar link ──────────────────────────────────────────────────────────────
SET @gitbrowse_page := (SELECT id FROM `page` WHERE url_id = 'WORDING_GITBROWSE' LIMIT 1);
SET @gitbrowse_navbar := (SELECT id FROM `navbar` WHERE name = 'public' LIMIT 1);
-- The alphabetical pin, where every module's public link goes.
SET @gitbrowse_pin := (
    SELECT id FROM `navbar_pin` WHERE navbar_id = @gitbrowse_navbar AND sort_mode = 0
     ORDER BY sort_order ASC, id ASC LIMIT 1
);
SET @gitbrowse_existing := (
    SELECT ni.id FROM `navbar_internal` ni
      JOIN `navbar_entry` e  ON e.id = ni.id
      JOIN `navbar_pin`   np ON np.id = e.pin_id
     WHERE ni.page_id = @gitbrowse_page AND np.navbar_id = @gitbrowse_navbar LIMIT 1
);
INSERT INTO `navbar_entry_ids` (id)
SELECT NULL WHERE @gitbrowse_page IS NOT NULL AND @gitbrowse_pin IS NOT NULL AND @gitbrowse_existing IS NULL;
SET @gitbrowse_nav := COALESCE(@gitbrowse_existing, LAST_INSERT_ID());
INSERT IGNORE INTO `navbar_entry` (id, pin_id, internal, name, i18n, active, sort_order)
SELECT @gitbrowse_nav, @gitbrowse_pin, 1, 'WORDING_GITBROWSE', 1, 1, 0
 WHERE @gitbrowse_page IS NOT NULL AND @gitbrowse_pin IS NOT NULL AND @gitbrowse_nav IS NOT NULL;
INSERT IGNORE INTO `navbar_internal` (id, page_id)
SELECT @gitbrowse_nav, @gitbrowse_page
 WHERE @gitbrowse_page IS NOT NULL AND @gitbrowse_nav IS NOT NULL;

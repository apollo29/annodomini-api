-- Issue #196: Move base64 SVG icons out of /v6/sync payload
--
-- Adds icon_url column to game_set. Icons are then served as static SVG files
-- under https://<icon_base_url>/icons/{uid}.svg. Clients can cache them
-- independently of sync responses, cutting the sync payload by ~90%.
--
-- The existing `icon` column (base64 SVG) stays untouched during the transition
-- so older app versions continue to work. Once <5% of installs are on the old
-- version, a follow-up migration can drop the `icon` column.
--
-- Apply with one of:
--   * mysql -u <user> -p <db> < database/migrations/20260422_...sql
--   * public/migrate.php?key=... (browser, via schema_migrations tracking)
--   * phpMyAdmin -> SQL tab -> paste
--
-- Running this migration twice raises "Duplicate column name 'icon_url'"
-- which migrate.php handles gracefully as "already applied". MySQL 8.0 does
-- not support ADD COLUMN IF NOT EXISTS (MariaDB-only).

ALTER TABLE `game_set`
    ADD COLUMN `icon_url` VARCHAR(255) DEFAULT NULL AFTER `icon`;

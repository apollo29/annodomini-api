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
-- Apply with:
--   mysql -u <user> -p <db> < database/migrations/20260422_add_icon_url_to_game_set.sql
--
-- Idempotent check: only adds the column if it does not exist (MariaDB 10.2+ /
-- MySQL 8.0+). On older servers drop the IF NOT EXISTS and run once.

ALTER TABLE `game_set`
    ADD COLUMN IF NOT EXISTS `icon_url` VARCHAR(255) DEFAULT NULL AFTER `icon`;

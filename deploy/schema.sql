-- Schema for the Fugitive site's two tables, derived from the code that
-- reads and writes them:
--   feedback       <- www/feedback.php, www/management/*
--   server_events  <- www/server_stats.php, www/gamestats.php
--
-- Applied by install.sh. Safe to re-run.

CREATE TABLE IF NOT EXISTS feedback (
	id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
	user_name     VARCHAR(255) NOT NULL,
	description   TEXT         NOT NULL,
	-- Crash reports arrive gzipped and base64'd, and are stored decompressed.
	logs          LONGTEXT     DEFAULT NULL,
	-- 1 until an admin marks it read in /management.
	new           TINYINT(1)   NOT NULL DEFAULT 1,
	date_reported TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (id),
	KEY idx_feedback_new (new),
	KEY idx_feedback_date_reported (date_reported)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS server_events (
	id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
	server_id     VARCHAR(255) NOT NULL,
	version       VARCHAR(64)  NOT NULL,
	server_name   VARCHAR(255) NOT NULL,
	num_players   INT          NOT NULL DEFAULT 0,
	map_name      VARCHAR(255) NOT NULL,
	-- 'game_start' or 'game_end'.
	event_name    VARCHAR(64)  NOT NULL,
	-- JSON; for game_end it carries winning_team and game_length_s.
	event_data    TEXT         DEFAULT NULL,
	date_uploaded TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (id),
	KEY idx_events_event_name (event_name),
	KEY idx_events_date_uploaded (date_uploaded)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

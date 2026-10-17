-- Additive migration. Preserves every existing participant and registration rule.
CREATE TABLE IF NOT EXISTS campaign_visits (
 id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
 created_at_utc DATETIME NOT NULL,
 last_activity_utc DATETIME NOT NULL,
 source VARCHAR(24) NOT NULL,
 source_partner VARCHAR(40) NOT NULL DEFAULT '',
 device VARCHAR(12) NOT NULL,
 page_views INT UNSIGNED NOT NULL DEFAULT 0,
 KEY visits_date (created_at_utc)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS campaign_form_attempts (
 id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
 visit_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 started_at_utc DATETIME NOT NULL,
 last_activity_utc DATETIME NOT NULL,
 last_field VARCHAR(16) NOT NULL DEFAULT '',
 last_stage VARCHAR(24) NOT NULL DEFAULT 'dados',
 partner VARCHAR(40) NOT NULL DEFAULT '',
 submit_count INT UNSIGNED NOT NULL DEFAULT 0,
 confirmed_at_utc DATETIME NULL,
 registered_at_utc DATETIME NULL,
 success_screen_at_utc DATETIME NULL,
 KEY attempts_date (started_at_utc),
 KEY attempts_visit (visit_id),
 KEY attempts_activity (last_activity_utc)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS campaign_metric_errors (
 event_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
 visit_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 attempt_id VARCHAR(32) NOT NULL DEFAULT '',
 kind VARCHAR(24) NOT NULL,
 code VARCHAR(32) NOT NULL,
 field VARCHAR(16) NOT NULL DEFAULT '',
 created_at_utc DATETIME NOT NULL,
 KEY errors_date (created_at_utc)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS campaign_metric_receipts (
 event_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
 created_at_utc DATETIME NOT NULL,
 KEY receipts_date (created_at_utc)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS campaign_metric_limits (
 bucket CHAR(64) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
 attempts INT UNSIGNED NOT NULL DEFAULT 1,
 created_at_utc DATETIME NOT NULL,
 KEY limits_date (created_at_utc)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS campaign_metric_settings (
 name VARCHAR(40) PRIMARY KEY,
 value VARCHAR(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT IGNORE INTO campaign_metric_settings (name,value) VALUES ('measurement_started_at',UTC_TIMESTAMP());

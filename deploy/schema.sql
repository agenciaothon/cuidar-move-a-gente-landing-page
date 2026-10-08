-- Import only into the dedicated campaign database using Hostinger phpMyAdmin.
CREATE TABLE IF NOT EXISTS participants (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(100) NOT NULL,
  cpf CHAR(11) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  phone VARCHAR(11) NOT NULL,
  email VARCHAR(150) NOT NULL,
  city VARCHAR(80) NOT NULL,
  state CHAR(2) NOT NULL,
  partner VARCHAR(40) NOT NULL,
  adult_declared TINYINT NOT NULL,
  terms_version VARCHAR(40) NOT NULL,
  privacy_version VARCHAR(40) NOT NULL,
  created_at_utc DATETIME NOT NULL,
  UNIQUE KEY unique_participant_cpf (cpf),
  KEY participants_partner (partner),
  KEY participants_date (created_at_utc)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS registration_limits (
  bucket CHAR(64) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
  attempts INT UNSIGNED NOT NULL,
  created_at_utc DATETIME NOT NULL,
  KEY limits_created (created_at_utc)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

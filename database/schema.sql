CREATE DATABASE IF NOT EXISTS mat_ieee_landing
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE mat_ieee_landing;

CREATE TABLE IF NOT EXISTS quote_requests (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  first_name VARCHAR(100) NOT NULL,
  last_name VARCHAR(100) NOT NULL,
  work_email VARCHAR(255) NOT NULL,
  phone_number VARCHAR(50) NOT NULL,
  company VARCHAR(180) NOT NULL,
  job_title VARCHAR(160) NOT NULL,
  industry VARCHAR(160) NOT NULL,
  source VARCHAR(120) NOT NULL DEFAULT 'MAT IEEE Landing',
  ip_address VARCHAR(45) NULL,
  user_agent VARCHAR(512) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_quote_requests_created_at (created_at),
  KEY idx_quote_requests_work_email (work_email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


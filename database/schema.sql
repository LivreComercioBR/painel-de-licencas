CREATE TABLE licenses (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  license_code VARCHAR(64) NOT NULL UNIQUE,
  customer_name VARCHAR(160) NOT NULL,
  customer_email VARCHAR(190) NOT NULL,
  customer_document VARCHAR(32) NULL,
  plan VARCHAR(40) NOT NULL DEFAULT 'local_perpetual',
  status ENUM('available','activated','blocked') NOT NULL DEFAULT 'available',
  request_code VARCHAR(80) NULL UNIQUE,
  activation_payload MEDIUMTEXT NULL,
  activated_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE license_recovery_events (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  license_id INT UNSIGNED NOT NULL,
  request_nonce VARCHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX license_recovery_events_license_id_index (license_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE products (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(64) NOT NULL UNIQUE,
  name VARCHAR(160) NOT NULL,
  product_claim VARCHAR(160) NOT NULL,
  features_json TEXT NULL,
  license_model ENUM('perpetual','subscription') NOT NULL DEFAULT 'perpetual',
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO products (code, name, product_claim, features_json, license_model, status) VALUES
 ('oficina-pro-local', 'RMC Flow Oficina Pro — Edição Local', 'RMC Flow Oficina Pro', '{"vehicles":["car","motorcycle"],"offline":true}', 'perpetual', 'active'),
 ('comercio-local', 'RMC Flow Comércio Local', 'RMC Flow Comércio Local', '{"offline":true}', 'perpetual', 'active'),
 ('oficina-pro-cloud', 'RMC Flow Oficina Pro Cloud', 'RMC Flow Oficina Pro Cloud', NULL, 'subscription', 'active');

CREATE TABLE licenses (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  license_code VARCHAR(64) NOT NULL UNIQUE,
  product_code VARCHAR(64) NOT NULL DEFAULT 'oficina-pro-local',
  customer_name VARCHAR(160) NOT NULL,
  customer_email VARCHAR(190) NOT NULL,
  customer_document VARCHAR(32) NULL,
  plan VARCHAR(40) NOT NULL DEFAULT 'local_perpetual',
  status ENUM('available','activated','blocked') NOT NULL DEFAULT 'available',
  request_code VARCHAR(80) NULL UNIQUE,
  activation_payload MEDIUMTEXT NULL,
  activated_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY licenses_product_code_index (product_code),
  CONSTRAINT licenses_product_fk FOREIGN KEY (product_code) REFERENCES products(code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE license_recovery_events (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  license_id INT UNSIGNED NOT NULL,
  request_nonce VARCHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX license_recovery_events_license_id_index (license_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE subscription_plans (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_code VARCHAR(64) NOT NULL,
  code VARCHAR(64) NOT NULL,
  name VARCHAR(120) NOT NULL,
  interval_unit ENUM('day','week','month','year') NOT NULL DEFAULT 'year',
  interval_count SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  initial_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  renewal_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  currency CHAR(3) NOT NULL DEFAULT 'BRL',
  grace_days SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  features_json TEXT NULL,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY subscription_plans_product_code_code_unique (product_code, code),
  CONSTRAINT subscription_plans_product_fk FOREIGN KEY (product_code) REFERENCES products(code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE subscriptions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_code VARCHAR(64) NOT NULL,
  external_reference VARCHAR(160) NOT NULL,
  customer_name VARCHAR(160) NOT NULL,
  customer_email VARCHAR(190) NOT NULL,
  customer_document VARCHAR(32) NULL,
  plan_code VARCHAR(64) NOT NULL,
  provider VARCHAR(40) NULL,
  provider_customer_reference VARCHAR(160) NULL,
  provider_subscription_reference VARCHAR(160) NULL,
  status ENUM('pending','trial','active','past_due','expired','canceled') NOT NULL DEFAULT 'pending',
  starts_at DATETIME NULL,
  expires_at DATETIME NULL,
  grace_until DATETIME NULL,
  initial_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  renewal_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  currency CHAR(3) NOT NULL DEFAULT 'BRL',
  payment_status VARCHAR(32) NULL,
  last_payment_reference VARCHAR(160) NULL,
  paid_at DATETIME NULL,
  source_updated_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY subscriptions_product_external_unique (product_code, external_reference),
  KEY subscriptions_product_status_expiry_index (product_code, status, expires_at),
  CONSTRAINT subscriptions_product_fk FOREIGN KEY (product_code) REFERENCES products(code),
  CONSTRAINT subscriptions_plan_fk FOREIGN KEY (product_code, plan_code) REFERENCES subscription_plans(product_code, code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE subscription_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_code VARCHAR(64) NOT NULL,
  subscription_id BIGINT UNSIGNED NULL,
  provider VARCHAR(40) NOT NULL,
  source_event_id VARCHAR(120) NOT NULL,
  provider_payment_reference VARCHAR(160) NULL,
  event_type VARCHAR(64) NOT NULL,
  event_status VARCHAR(32) NULL,
  amount DECIMAL(12,2) NULL,
  currency CHAR(3) NULL,
  occurred_at DATETIME NULL,
  payload_sha256 CHAR(64) NOT NULL,
  received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY subscription_events_source_unique (product_code, provider, source_event_id),
  KEY subscription_events_payment_index (product_code, provider, provider_payment_reference),
  KEY subscription_events_subscription_index (subscription_id, received_at),
  CONSTRAINT subscription_events_product_fk FOREIGN KEY (product_code) REFERENCES products(code),
  CONSTRAINT subscription_events_subscription_fk FOREIGN KEY (subscription_id) REFERENCES subscriptions(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO subscription_plans
 (product_code, code, name, interval_unit, interval_count, initial_amount, renewal_amount, currency, grace_days, features_json, status)
VALUES
 ('oficina-pro-cloud', 'essential', 'Essencial', 'year', 1, 149.90, 80.00, 'BRL', 30, NULL, 'active'),
 ('oficina-pro-cloud', 'professional', 'Profissional', 'year', 1, 179.90, 80.00, 'BRL', 30, NULL, 'active');

-- RMC Flow Licensing Center v0.3.0
-- Run once on an existing panel database before deploying the new files.
CREATE TABLE IF NOT EXISTS products (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(64) NOT NULL UNIQUE,
  name VARCHAR(160) NOT NULL,
  license_model ENUM('perpetual','subscription') NOT NULL DEFAULT 'perpetual',
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO products (code, name, license_model, status) VALUES
 ('oficina-pro-local', 'RMC Flow Oficina Pro — Edição Local', 'perpetual', 'active'),
 ('comercio-local', 'RMC Flow Comércio Local', 'perpetual', 'active'),
 ('oficina-pro-cloud', 'RMC Flow Oficina Pro Cloud', 'subscription', 'active');

ALTER TABLE licenses
  ADD COLUMN product_code VARCHAR(64) NOT NULL DEFAULT 'oficina-pro-local' AFTER license_code,
  ADD INDEX licenses_product_code_index (product_code),
  ADD CONSTRAINT licenses_product_fk FOREIGN KEY (product_code) REFERENCES products(code);

CREATE TABLE IF NOT EXISTS subscription_plans (
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

CREATE TABLE IF NOT EXISTS subscriptions (
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

CREATE TABLE IF NOT EXISTS subscription_events (
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

INSERT IGNORE INTO subscription_plans
 (product_code, code, name, interval_unit, interval_count, initial_amount, renewal_amount, currency, grace_days, features_json, status)
VALUES
 ('oficina-pro-cloud', 'essential', 'Essencial', 'year', 1, 149.90, 80.00, 'BRL', 30, NULL, 'active'),
 ('oficina-pro-cloud', 'professional', 'Profissional', 'year', 1, 179.90, 80.00, 'BRL', 30, NULL, 'active');

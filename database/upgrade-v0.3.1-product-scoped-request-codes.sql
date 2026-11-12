-- RMC Flow Licensing Center v0.3.1
-- Run once on an existing panel database.
-- Request codes are generated per computer; the same computer may use
-- separate licenses for different products. Keep uniqueness within a product.
ALTER TABLE licenses
  DROP INDEX request_code,
  ADD UNIQUE KEY licenses_product_request_code_unique (product_code, request_code);

USE correspondencias;

CREATE TABLE IF NOT EXISTS mobile_user_devices (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  mobile_user_id BIGINT UNSIGNED NOT NULL,
  device_hash CHAR(64) NOT NULL,
  device_label VARCHAR(160) NOT NULL,
  platform VARCHAR(80) NULL,
  model VARCHAR(120) NULL,
  manufacturer VARCHAR(120) NULL,
  app_version VARCHAR(40) NULL,
  status ENUM('pending', 'approved', 'blocked') NOT NULL DEFAULT 'pending',
  approved_by_admin_id BIGINT UNSIGNED NULL,
  approved_at DATETIME NULL,
  last_login_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY mobile_user_devices_user_hash_unique (mobile_user_id, device_hash),
  KEY mobile_user_devices_status_idx (status),
  KEY mobile_user_devices_admin_idx (approved_by_admin_id),
  CONSTRAINT mobile_user_devices_user_fk
    FOREIGN KEY (mobile_user_id) REFERENCES mobile_users (id)
    ON DELETE CASCADE,
  CONSTRAINT mobile_user_devices_admin_fk
    FOREIGN KEY (approved_by_admin_id) REFERENCES admins (id)
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE OR REPLACE VIEW v_package_history AS
SELECT
  p.id,
  p.unit,
  p.unit_short,
  p.identification,
  p.status,
  p.received_at,
  d.receiver,
  d.signature_path,
  d.delivered_at,
  mu.name AS delivered_by_name
FROM packages p
LEFT JOIN package_deliveries d ON d.package_id = p.id
LEFT JOIN mobile_users mu ON mu.id = d.delivered_by_mobile_user_id
WHERE p.deleted_at IS NULL;

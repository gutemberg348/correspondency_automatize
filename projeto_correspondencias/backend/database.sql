CREATE DATABASE IF NOT EXISTS correspondencias
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE correspondencias;

SET NAMES utf8mb4;
SET time_zone = '-03:00';

CREATE TABLE IF NOT EXISTS admins (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(120) NOT NULL,
  username VARCHAR(80) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  last_login_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY admins_username_unique (username),
  KEY admins_active_idx (active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mobile_users (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(140) NOT NULL,
  username VARCHAR(80) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  validity_amount INT UNSIGNED NOT NULL DEFAULT 1,
  validity_unit ENUM('days', 'months') NOT NULL DEFAULT 'months',
  expires_at DATETIME NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_by_admin_id BIGINT UNSIGNED NULL,
  last_login_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY mobile_users_username_unique (username),
  KEY mobile_users_active_expires_idx (active, expires_at),
  KEY mobile_users_created_by_admin_idx (created_by_admin_id),
  CONSTRAINT mobile_users_created_by_admin_fk
    FOREIGN KEY (created_by_admin_id) REFERENCES admins (id)
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

CREATE TABLE IF NOT EXISTS packages (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  unit VARCHAR(120) NOT NULL,
  unit_short VARCHAR(40) NULL,
  identification TEXT NOT NULL,
  status ENUM('pendente', 'entregue', 'cancelada') NOT NULL DEFAULT 'pendente',
  received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_by_admin_id BIGINT UNSIGNED NULL,
  created_by_mobile_user_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  PRIMARY KEY (id),
  KEY packages_status_received_idx (status, received_at),
  KEY packages_unit_idx (unit),
  KEY packages_created_by_admin_idx (created_by_admin_id),
  KEY packages_created_by_mobile_user_idx (created_by_mobile_user_id),
  CONSTRAINT packages_created_by_admin_fk
    FOREIGN KEY (created_by_admin_id) REFERENCES admins (id)
    ON DELETE SET NULL,
  CONSTRAINT packages_created_by_mobile_user_fk
    FOREIGN KEY (created_by_mobile_user_id) REFERENCES mobile_users (id)
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS package_deliveries (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  package_id BIGINT UNSIGNED NOT NULL,
  receiver VARCHAR(140) NOT NULL,
  signature_data LONGTEXT NULL,
  signature_path VARCHAR(255) NULL,
  delivered_by_mobile_user_id BIGINT UNSIGNED NULL,
  delivered_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY package_deliveries_package_unique (package_id),
  KEY package_deliveries_delivered_at_idx (delivered_at),
  KEY package_deliveries_mobile_user_idx (delivered_by_mobile_user_id),
  CONSTRAINT package_deliveries_package_fk
    FOREIGN KEY (package_id) REFERENCES packages (id)
    ON DELETE CASCADE,
  CONSTRAINT package_deliveries_mobile_user_fk
    FOREIGN KEY (delivered_by_mobile_user_id) REFERENCES mobile_users (id)
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS package_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  package_id BIGINT UNSIGNED NOT NULL,
  event_type ENUM('created', 'delivered', 'cancelled', 'updated') NOT NULL,
  actor_type ENUM('admin', 'mobile_user', 'system') NOT NULL DEFAULT 'system',
  actor_id BIGINT UNSIGNED NULL,
  notes VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY package_events_package_idx (package_id),
  KEY package_events_type_created_idx (event_type, created_at),
  CONSTRAINT package_events_package_fk
    FOREIGN KEY (package_id) REFERENCES packages (id)
    ON DELETE CASCADE
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

CREATE OR REPLACE VIEW v_active_mobile_users AS
SELECT
  id,
  name,
  username,
  validity_amount,
  validity_unit,
  expires_at,
  active,
  created_at
FROM mobile_users
WHERE deleted_at IS NULL
  AND active = 1
  AND expires_at >= NOW();

INSERT INTO admins (id, name, username, password_hash, active)
VALUES
  (1, 'Administrador', 'admin', '$2y$10$9Wi4QUTEV7HUma9tI3xJSOORHL9nGHWxUslK1YS.CAh4NHg7BVvJ.', 1)
ON DUPLICATE KEY UPDATE
  name = VALUES(name),
  password_hash = VALUES(password_hash),
  active = 1;

INSERT IGNORE INTO mobile_users (
  id,
  name,
  username,
  password_hash,
  validity_amount,
  validity_unit,
  expires_at,
  active,
  created_by_admin_id
)
VALUES
  (1, 'Usuario Mobile Demo', 'mobile', 'mobile123', 1, 'months', DATE_ADD(NOW(), INTERVAL 1 MONTH), 1, 1);

INSERT IGNORE INTO packages (
  id,
  unit,
  unit_short,
  identification,
  status,
  received_at,
  created_by_admin_id
)
VALUES
  (1, 'Unidade C3', 'C3', 'A identificar - encomenda', 'pendente', '2026-04-19 01:07:00', 1);

INSERT IGNORE INTO package_events (id, package_id, event_type, actor_type, actor_id, notes, created_at)
VALUES
  (1, 1, 'created', 'admin', 1, 'Carga inicial de demonstracao', '2026-04-19 01:07:00');

USE correspondencias;

ALTER TABLE packages
  ADD COLUMN IF NOT EXISTS photo_data LONGTEXT NULL AFTER identification;

CREATE OR REPLACE VIEW v_package_history AS
SELECT
  p.id,
  p.unit,
  p.unit_short,
  p.identification,
  p.photo_data AS photo,
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

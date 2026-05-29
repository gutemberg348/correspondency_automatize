USE correspondencias;

ALTER TABLE mobile_user_devices
  ADD COLUMN IF NOT EXISTS phone VARCHAR(30) NULL AFTER device_label;

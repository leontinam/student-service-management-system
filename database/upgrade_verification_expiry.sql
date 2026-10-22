-- Add token expiry and give outstanding unverified accounts a 24-hour expiry window.
ALTER TABLE users
    ADD COLUMN verification_expires_at DATETIME DEFAULT NULL AFTER verification_token;

UPDATE users
SET verification_expires_at = DATE_ADD(NOW(), INTERVAL 24 HOUR)
WHERE is_verified = FALSE
  AND verification_token IS NOT NULL;

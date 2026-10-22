-- Add verification fields to an existing database, then mark existing accounts as verified.
ALTER TABLE users
    ADD COLUMN is_verified BOOLEAN NOT NULL DEFAULT FALSE AFTER role,
    ADD COLUMN verification_token CHAR(64) DEFAULT NULL UNIQUE AFTER is_verified;

UPDATE users
SET is_verified = TRUE;

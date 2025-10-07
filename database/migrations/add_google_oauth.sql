-- Migration: Add Google OAuth support
-- Date: 2025-10-07

USE mn;

-- Add google_id column to users table
ALTER TABLE users
ADD COLUMN google_id VARCHAR(255) UNIQUE NULL AFTER email,
ADD COLUMN name VARCHAR(255) NULL AFTER google_id,
ADD INDEX idx_google_id (google_id);

-- Make password_hash optional (nullable) for Google OAuth users
ALTER TABLE users
MODIFY COLUMN password_hash VARCHAR(255) NULL;

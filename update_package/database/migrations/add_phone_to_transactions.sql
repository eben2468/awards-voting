-- Migration: Add phone column to transactions table
-- This allows storing the phone number used for voting directly in the transactions table
-- Date: 2026-01-27

ALTER TABLE transactions ADD COLUMN phone VARCHAR(20) DEFAULT NULL AFTER category_id;

-- Add index for searching by phone
ALTER TABLE transactions ADD INDEX idx_phone (phone);

-- Update existing records to extract phone from metadata (if available)
UPDATE transactions 
SET phone = JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.phone'))
WHERE phone IS NULL AND JSON_EXTRACT(metadata, '$.phone') IS NOT NULL;

UPDATE transactions 
SET phone = JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.msisdn'))
WHERE phone IS NULL AND JSON_EXTRACT(metadata, '$.msisdn') IS NOT NULL;

-- Optimistic concurrency for accounts, matching the other aggregates. Existing rows start at version 0.
ALTER TABLE user_accounts ADD COLUMN version BIGINT NOT NULL DEFAULT 0 CHECK (version >= 0);

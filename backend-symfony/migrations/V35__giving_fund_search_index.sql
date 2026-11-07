CREATE EXTENSION IF NOT EXISTS pg_trgm;
CREATE INDEX giving_funds_name_trgm_idx ON giving_funds USING gin (name gin_trgm_ops);

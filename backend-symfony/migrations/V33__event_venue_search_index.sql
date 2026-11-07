CREATE EXTENSION IF NOT EXISTS pg_trgm;
CREATE INDEX event_venues_name_trgm_idx ON event_venues USING gin (name gin_trgm_ops);

CREATE INDEX event_definitions_title_trgm_idx ON event_definitions USING gin (title gin_trgm_ops);

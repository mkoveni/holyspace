-- People and household search use contains-style ILIKE, which a b-tree index cannot serve.
-- Trigram GIN indexes keep it fast as the directory grows. The indexed expression must match the query.
CREATE EXTENSION IF NOT EXISTS pg_trgm;
CREATE INDEX people_persons_fullname_trgm_idx ON people_persons USING gin ((first_name || ' ' || last_name) gin_trgm_ops);
CREATE INDEX people_households_name_trgm_idx ON people_households USING gin (name gin_trgm_ops);

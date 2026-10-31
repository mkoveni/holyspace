-- Existing immutable revisions retain their original shared-origin behaviour.
-- New revisions save the backend origin independently of browser return URLs.
ALTER TABLE payments_configuration_revisions ADD COLUMN backend_url VARCHAR(1000);

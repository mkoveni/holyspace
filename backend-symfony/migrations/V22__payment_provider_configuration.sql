CREATE TABLE payments_provider_settings (
 provider VARCHAR(10) PRIMARY KEY CHECK(provider IN ('payfast','yoco')),
 mode VARCHAR(4) NOT NULL DEFAULT 'test' CHECK(mode IN ('test','live')),enabled BOOLEAN NOT NULL DEFAULT FALSE,
 monthly BOOLEAN NOT NULL DEFAULT FALSE,managed BOOLEAN NOT NULL DEFAULT FALSE,version BIGINT NOT NULL DEFAULT 0 CHECK(version>=0),
 CHECK(NOT monthly OR provider='payfast')
);
INSERT INTO payments_provider_settings(provider) VALUES ('payfast'),('yoco');
CREATE TABLE payments_configuration_revisions (
 id UUID PRIMARY KEY,provider VARCHAR(10) NOT NULL REFERENCES payments_provider_settings(provider),mode VARCHAR(4) NOT NULL CHECK(mode IN ('test','live')),
 sequence BIGINT NOT NULL CHECK(sequence>0),public_url VARCHAR(1000) NOT NULL,ip_ranges VARCHAR(2000) NOT NULL,
 encrypted_credentials TEXT NOT NULL,credential_hash CHAR(64) NOT NULL,
 merchant_id_set BOOLEAN NOT NULL,merchant_key_set BOOLEAN NOT NULL,passphrase_set BOOLEAN NOT NULL,yoco_key_set BOOLEAN NOT NULL,webhook_secret_set BOOLEAN NOT NULL,
 created_by UUID NOT NULL,created_at TIMESTAMPTZ NOT NULL,UNIQUE(provider,mode,sequence),UNIQUE(id,provider,mode)
);
CREATE INDEX payments_configuration_latest_idx ON payments_configuration_revisions(provider,mode,sequence DESC);
CREATE TABLE payments_configuration_checks (
 id UUID PRIMARY KEY,revision_id UUID NOT NULL REFERENCES payments_configuration_revisions(id),successful BOOLEAN NOT NULL,
 message VARCHAR(500) NOT NULL,checked_by UUID NOT NULL,checked_at TIMESTAMPTZ NOT NULL
);
CREATE INDEX payments_configuration_check_idx ON payments_configuration_checks(revision_id,checked_at DESC,id);
CREATE TABLE payments_configuration_history (
 id UUID PRIMARY KEY,provider VARCHAR(10) NOT NULL REFERENCES payments_provider_settings(provider),mode VARCHAR(4) NOT NULL,
 version BIGINT NOT NULL,action VARCHAR(30) NOT NULL,note VARCHAR(1000) NOT NULL,changed_fields VARCHAR(1000) NOT NULL,
 actor_id UUID NOT NULL,occurred_at TIMESTAMPTZ NOT NULL
);
CREATE INDEX payments_configuration_history_idx ON payments_configuration_history(occurred_at DESC,id);
ALTER TABLE payments_orders ADD COLUMN configuration_id UUID;
ALTER TABLE payments_orders ADD CONSTRAINT payments_order_configuration FOREIGN KEY(configuration_id,provider,mode) REFERENCES payments_configuration_revisions(id,provider,mode);
INSERT INTO permissions(id,code,description) VALUES ('00000000-0000-0000-0000-000000000047','payments.configure','Configure encrypted payment providers and run connection checks');
INSERT INTO role_permissions(role_id,permission_id) SELECT r.id,p.id FROM roles r CROSS JOIN permissions p WHERE r.name='administrator' AND p.code='payments.configure';
-- [jooq ignore start]
CREATE TRIGGER payments_configuration_immutable BEFORE UPDATE OR DELETE ON payments_configuration_revisions FOR EACH ROW EXECUTE FUNCTION giving_reject_mutation();
CREATE TRIGGER payments_configuration_checks_immutable BEFORE UPDATE OR DELETE ON payments_configuration_checks FOR EACH ROW EXECUTE FUNCTION giving_reject_mutation();
CREATE TRIGGER payments_configuration_history_immutable BEFORE UPDATE OR DELETE ON payments_configuration_history FOR EACH ROW EXECUTE FUNCTION giving_reject_mutation();
CREATE OR REPLACE FUNCTION payments_guard_order() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
 IF TG_OP='DELETE' THEN RAISE EXCEPTION 'Payment orders cannot be removed' USING ERRCODE='23514'; END IF;
 IF (NEW.id,NEW.donor_id,NEW.fund_id,NEW.amount_minor,NEW.provider,NEW.mode,NEW.recurring,NEW.configuration_id,NEW.request_hash,NEW.created_by,NEW.created_at)
 IS DISTINCT FROM (OLD.id,OLD.donor_id,OLD.fund_id,OLD.amount_minor,OLD.provider,OLD.mode,OLD.recurring,OLD.configuration_id,OLD.request_hash,OLD.created_by,OLD.created_at)
 OR NEW.version<>OLD.version+1 THEN RAISE EXCEPTION 'Original payment intent cannot change' USING ERRCODE='23514'; END IF;
 RETURN NEW;
END $$;
-- [jooq ignore stop]

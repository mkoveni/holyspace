-- [jooq ignore start]
ALTER TABLE giving_history DROP CONSTRAINT giving_history_target_type_check;
ALTER TABLE giving_history ADD CONSTRAINT giving_history_target_type_check CHECK(target_type IN ('FUND','GIFT','PLEDGE','BATCH'));
-- [jooq ignore stop]
ALTER TABLE giving_gifts ADD CONSTRAINT giving_gift_identity_currency UNIQUE(id,donor_id,currency);
CREATE TABLE giving_pledges (
 id UUID PRIMARY KEY, donor_id UUID NOT NULL, fund_id UUID NOT NULL REFERENCES giving_funds(id),
 amount_minor BIGINT NOT NULL CHECK(amount_minor BETWEEN 1 AND 1000000000000),
 currency VARCHAR(3) NOT NULL CHECK(currency IN ('ZAR','USD','EUR','GBP')),
 starts_on DATE NOT NULL, ends_on DATE NOT NULL CHECK(ends_on>=starts_on),
 status VARCHAR(10) NOT NULL CHECK(status IN ('OPEN','CANCELLED')),version BIGINT NOT NULL DEFAULT 0 CHECK(version>=0),
 cancellation_reason VARCHAR(1000),request_hash CHAR(64) NOT NULL,created_by UUID NOT NULL,created_at TIMESTAMPTZ NOT NULL,
 UNIQUE(id,fund_id,donor_id,currency),CHECK(status<>'CANCELLED' OR (cancellation_reason IS NOT NULL AND length(trim(cancellation_reason))>0))
);
CREATE INDEX giving_pledge_donor_idx ON giving_pledges(donor_id,ends_on,id);
CREATE INDEX giving_pledge_status_idx ON giving_pledges(status,ends_on,id);
CREATE TABLE giving_pledge_assignments (
 id UUID PRIMARY KEY,pledge_id UUID NOT NULL,gift_id UUID NOT NULL,fund_id UUID NOT NULL,donor_id UUID NOT NULL,currency VARCHAR(3) NOT NULL,
 amount_minor BIGINT NOT NULL CHECK(amount_minor BETWEEN 1 AND 1000000000000),
 status VARCHAR(10) NOT NULL CHECK(status IN ('ACTIVE','CANCELLED')),version BIGINT NOT NULL DEFAULT 0 CHECK(version>=0),cancellation_reason VARCHAR(1000),cancelled_by UUID,cancelled_at TIMESTAMPTZ,
 request_hash CHAR(64) NOT NULL,created_by UUID NOT NULL,created_at TIMESTAMPTZ NOT NULL,
 FOREIGN KEY(pledge_id,fund_id,donor_id,currency) REFERENCES giving_pledges(id,fund_id,donor_id,currency),
 FOREIGN KEY(gift_id,donor_id,currency) REFERENCES giving_gifts(id,donor_id,currency),
 FOREIGN KEY(gift_id,fund_id) REFERENCES giving_allocations(gift_id,fund_id),
 CHECK(status<>'CANCELLED' OR (cancelled_by IS NOT NULL AND cancelled_at IS NOT NULL)),
 CHECK(status<>'CANCELLED' OR (cancellation_reason IS NOT NULL AND length(trim(cancellation_reason))>0))
);
CREATE INDEX giving_assignment_pledge_idx ON giving_pledge_assignments(pledge_id,status,id);
CREATE INDEX giving_assignment_gift_idx ON giving_pledge_assignments(gift_id,fund_id,status);
CREATE TABLE giving_acknowledgements (
 id UUID PRIMARY KEY,gift_id UUID NOT NULL UNIQUE REFERENCES giving_gifts(id),number BIGSERIAL NOT NULL UNIQUE,
 donor_name VARCHAR(300) NOT NULL,issued_on DATE NOT NULL,created_by UUID NOT NULL,created_at TIMESTAMPTZ NOT NULL,request_hash CHAR(64) NOT NULL
);
CREATE TABLE giving_acknowledgement_lines (
 acknowledgement_id UUID NOT NULL REFERENCES giving_acknowledgements(id),fund_id UUID NOT NULL REFERENCES giving_funds(id),
 fund_name VARCHAR(100) NOT NULL,amount_minor BIGINT NOT NULL CHECK(amount_minor>0),PRIMARY KEY(acknowledgement_id,fund_id)
);
CREATE TABLE giving_reconciliation_batches (
 id UUID PRIMARY KEY,reference VARCHAR(200) NOT NULL CHECK(length(trim(reference))>0),
 expected_minor BIGINT NOT NULL CHECK(expected_minor BETWEEN 1 AND 100000000000000),
 actual_minor BIGINT NOT NULL CHECK(actual_minor BETWEEN 1 AND 100000000000000),
 currency VARCHAR(3) NOT NULL CHECK(currency IN ('ZAR','USD','EUR','GBP')),reconciled_on DATE NOT NULL,
 reason VARCHAR(1000) NOT NULL,created_by UUID NOT NULL,created_at TIMESTAMPTZ NOT NULL,request_hash CHAR(64) NOT NULL,
 CHECK(expected_minor=actual_minor OR length(trim(reason))>0)
);
CREATE INDEX giving_batch_date_idx ON giving_reconciliation_batches(reconciled_on DESC,id);
CREATE TABLE giving_reconciliation_items (
 batch_id UUID NOT NULL REFERENCES giving_reconciliation_batches(id),gift_id UUID NOT NULL UNIQUE REFERENCES giving_gifts(id),
 PRIMARY KEY(batch_id,gift_id)
);
CREATE TABLE giving_reconciliation_reviews (
 id UUID PRIMARY KEY,batch_id UUID NOT NULL REFERENCES giving_reconciliation_batches(id),version BIGINT NOT NULL CHECK(version>0),
 outcome VARCHAR(20) NOT NULL CHECK(outcome IN ('FOLLOW_UP','EXPLAINED')),note VARCHAR(1000) NOT NULL CHECK(length(trim(note))>0),
 reversed_minor BIGINT NOT NULL CHECK(reversed_minor>=0),created_by UUID NOT NULL,created_at TIMESTAMPTZ NOT NULL,request_hash CHAR(64) NOT NULL,
 UNIQUE(batch_id,version)
);
INSERT INTO permissions(id,code,description) VALUES
 ('00000000-0000-0000-0000-000000000041','giving.acknowledge','Issue numbered gift acknowledgements'),
 ('00000000-0000-0000-0000-000000000042','giving.reconcile','Record and review reconciliation batches'),
 ('00000000-0000-0000-0000-000000000043','giving.export','Export the confidential Giving ledger');
INSERT INTO role_permissions(role_id,permission_id) SELECT r.id,p.id FROM roles r CROSS JOIN permissions p
 WHERE r.name='administrator' AND p.code IN ('giving.acknowledge','giving.reconcile','giving.export');
-- [jooq ignore start]
CREATE UNIQUE INDEX giving_batch_reference_unique ON giving_reconciliation_batches(lower(trim(reference)));
CREATE FUNCTION giving_guard_pledge_assignment() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE p giving_pledges%ROWTYPE; g giving_gifts%ROWTYPE; available NUMERIC; credited NUMERIC;
BEGIN
 SELECT * INTO g FROM giving_gifts WHERE id=NEW.gift_id FOR UPDATE;
 SELECT * INTO p FROM giving_pledges WHERE id=NEW.pledge_id FOR UPDATE;
 IF TG_OP='UPDATE' THEN
   IF (NEW.id,NEW.pledge_id,NEW.gift_id,NEW.fund_id,NEW.donor_id,NEW.currency,NEW.amount_minor,NEW.request_hash,NEW.created_by,NEW.created_at)
      IS DISTINCT FROM (OLD.id,OLD.pledge_id,OLD.gift_id,OLD.fund_id,OLD.donor_id,OLD.currency,OLD.amount_minor,OLD.request_hash,OLD.created_by,OLD.created_at)
      OR OLD.status<>'ACTIVE' OR NEW.status<>'CANCELLED' OR NEW.version<>OLD.version+1 THEN
     RAISE EXCEPTION 'Pledge assignment evidence cannot be replaced' USING ERRCODE='23514';
   END IF;
   RETURN NEW;
 END IF;
 IF p.status<>'OPEN' OR g.received_on<p.starts_on OR g.received_on>p.ends_on OR EXISTS(SELECT 1 FROM giving_reversals WHERE gift_id=g.id) THEN
   RAISE EXCEPTION 'Gift is not eligible for this pledge' USING ERRCODE='23514';
 END IF;
 SELECT a.amount_minor-COALESCE((SELECT sum(x.amount_minor) FROM giving_pledge_assignments x WHERE x.gift_id=g.id AND x.fund_id=NEW.fund_id AND x.status='ACTIVE'),0)
 INTO available FROM giving_allocations a WHERE a.gift_id=g.id AND a.fund_id=NEW.fund_id;
 SELECT COALESCE(sum(x.amount_minor),0) INTO credited FROM giving_pledge_assignments x WHERE x.pledge_id=p.id AND x.status='ACTIVE'
 AND NOT EXISTS(SELECT 1 FROM giving_reversals r WHERE r.gift_id=x.gift_id);
 IF NEW.amount_minor>available OR NEW.amount_minor+credited>p.amount_minor THEN
   RAISE EXCEPTION 'Pledge or gift allocation capacity exceeded' USING ERRCODE='23514';
 END IF;
 RETURN NEW;
END $$;
CREATE TRIGGER giving_assignment_guard BEFORE INSERT OR UPDATE ON giving_pledge_assignments FOR EACH ROW EXECUTE FUNCTION giving_guard_pledge_assignment();
CREATE TRIGGER giving_assignment_no_delete BEFORE DELETE ON giving_pledge_assignments FOR EACH ROW EXECUTE FUNCTION giving_reject_mutation();
CREATE TRIGGER giving_ack_immutable BEFORE UPDATE OR DELETE ON giving_acknowledgements FOR EACH ROW EXECUTE FUNCTION giving_reject_mutation();
CREATE TRIGGER giving_ack_lines_immutable BEFORE UPDATE OR DELETE ON giving_acknowledgement_lines FOR EACH ROW EXECUTE FUNCTION giving_reject_mutation();
CREATE TRIGGER giving_batch_immutable BEFORE UPDATE OR DELETE ON giving_reconciliation_batches FOR EACH ROW EXECUTE FUNCTION giving_reject_mutation();
CREATE TRIGGER giving_batch_items_immutable BEFORE UPDATE OR DELETE ON giving_reconciliation_items FOR EACH ROW EXECUTE FUNCTION giving_reject_mutation();
CREATE TRIGGER giving_review_immutable BEFORE UPDATE OR DELETE ON giving_reconciliation_reviews FOR EACH ROW EXECUTE FUNCTION giving_reject_mutation();
CREATE FUNCTION giving_guard_pledge() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
 IF (NEW.id,NEW.donor_id,NEW.fund_id,NEW.amount_minor,NEW.currency,NEW.starts_on,NEW.ends_on,NEW.request_hash,NEW.created_by,NEW.created_at)
 IS DISTINCT FROM (OLD.id,OLD.donor_id,OLD.fund_id,OLD.amount_minor,OLD.currency,OLD.starts_on,OLD.ends_on,OLD.request_hash,OLD.created_by,OLD.created_at)
 OR NEW.version<>OLD.version+1 OR (OLD.status='CANCELLED' AND NEW.status<>'CANCELLED') THEN
  RAISE EXCEPTION 'Commitment evidence cannot be replaced' USING ERRCODE='23514';
 END IF;
 RETURN NEW;
END $$;
CREATE TRIGGER giving_pledge_guard BEFORE UPDATE ON giving_pledges FOR EACH ROW EXECUTE FUNCTION giving_guard_pledge();
CREATE TRIGGER giving_pledge_no_delete BEFORE DELETE ON giving_pledges FOR EACH ROW EXECUTE FUNCTION giving_reject_mutation();
CREATE FUNCTION giving_check_acknowledgement() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE target UUID; source UUID;
BEGIN
 IF TG_TABLE_NAME='giving_acknowledgements' THEN target:=NEW.id; ELSE target:=NEW.acknowledgement_id; END IF;
 SELECT gift_id INTO source FROM giving_acknowledgements WHERE id=target;
 IF EXISTS(SELECT 1 FROM giving_reversals WHERE gift_id=source)
 OR (SELECT count(*) FROM giving_acknowledgement_lines WHERE acknowledgement_id=target)<>(SELECT count(*) FROM giving_allocations WHERE gift_id=source)
 OR EXISTS(SELECT 1 FROM giving_acknowledgement_lines l LEFT JOIN giving_allocations a ON a.gift_id=source AND a.fund_id=l.fund_id
 WHERE l.acknowledgement_id=target AND (a.gift_id IS NULL OR a.amount_minor<>l.amount_minor)) THEN
  RAISE EXCEPTION 'Acknowledgement must match the original gift allocations' USING ERRCODE='23514';
 END IF;
 RETURN NULL;
END $$;
CREATE CONSTRAINT TRIGGER giving_ack_balance AFTER INSERT ON giving_acknowledgements DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION giving_check_acknowledgement();
CREATE CONSTRAINT TRIGGER giving_ack_line_balance AFTER INSERT ON giving_acknowledgement_lines DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION giving_check_acknowledgement();
CREATE FUNCTION giving_check_reconciliation() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE target UUID; batch giving_reconciliation_batches%ROWTYPE; actual NUMERIC; entries INTEGER;
BEGIN
 IF TG_TABLE_NAME='giving_reconciliation_batches' THEN target:=NEW.id; ELSE target:=NEW.batch_id; END IF;
 SELECT * INTO batch FROM giving_reconciliation_batches WHERE id=target;
 SELECT sum(g.amount_minor),count(*) INTO actual,entries FROM giving_reconciliation_items i JOIN giving_gifts g ON g.id=i.gift_id WHERE i.batch_id=target;
 IF entries<1 OR entries>100 OR actual<>batch.actual_minor OR EXISTS(
 SELECT 1 FROM giving_reconciliation_items i JOIN giving_gifts g ON g.id=i.gift_id
 WHERE i.batch_id=target AND (g.currency<>batch.currency OR g.received_on>batch.reconciled_on OR EXISTS(SELECT 1 FROM giving_reversals r WHERE r.gift_id=g.id))) THEN
  RAISE EXCEPTION 'Reconciliation snapshot must match its eligible gifts' USING ERRCODE='23514';
 END IF;
 RETURN NULL;
END $$;
CREATE CONSTRAINT TRIGGER giving_batch_balance AFTER INSERT ON giving_reconciliation_batches DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION giving_check_reconciliation();
CREATE CONSTRAINT TRIGGER giving_batch_item_balance AFTER INSERT ON giving_reconciliation_items DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION giving_check_reconciliation();
-- [jooq ignore stop]

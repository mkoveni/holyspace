CREATE TABLE payments_orders (
 id UUID PRIMARY KEY, donor_id UUID NOT NULL, fund_id UUID NOT NULL, amount_minor BIGINT NOT NULL CHECK(amount_minor BETWEEN 500 AND 100000000),
 provider VARCHAR(10) NOT NULL CHECK(provider IN ('payfast','yoco')), mode VARCHAR(4) NOT NULL CHECK(mode IN ('test','live')),
 recurring BOOLEAN NOT NULL, status VARCHAR(20) NOT NULL CHECK(status IN ('PENDING','READY','PAID','FAILED')),
 checkout_id VARCHAR(200), checkout_url VARCHAR(1000), checkout_fields TEXT,
 subscription_status VARCHAR(20) NOT NULL CHECK(subscription_status IN ('NONE','PENDING','ACTIVE','PAUSED','LOCKED','CANCEL_REQUESTED','CANCELLED')),
 encrypted_token TEXT, token_hash CHAR(64), review_reason VARCHAR(1000), version BIGINT NOT NULL DEFAULT 0,
 request_hash CHAR(64) NOT NULL, created_by UUID NOT NULL, created_at TIMESTAMPTZ NOT NULL,
 CHECK(NOT recurring OR provider='payfast'),CHECK(recurring OR subscription_status='NONE'),
 UNIQUE(provider,mode,checkout_id),UNIQUE(provider,mode,token_hash)
);
CREATE INDEX payments_order_date_idx ON payments_orders(created_at DESC,id);
CREATE TABLE payments_charges (
 id UUID PRIMARY KEY, order_id UUID NOT NULL REFERENCES payments_orders(id),
 provider VARCHAR(10) NOT NULL, mode VARCHAR(4) NOT NULL, reference VARCHAR(200) NOT NULL,
 amount_minor BIGINT NOT NULL CHECK(amount_minor BETWEEN 1 AND 100000000), received_on DATE NOT NULL,
 gift_id UUID UNIQUE, created_at TIMESTAMPTZ NOT NULL, UNIQUE(provider,mode,reference)
);
CREATE INDEX payments_charge_order_idx ON payments_charges(order_id,created_at,id);
CREATE TABLE payments_refunds (
 id UUID PRIMARY KEY,order_id UUID NOT NULL REFERENCES payments_orders(id),charge_id UUID REFERENCES payments_charges(id),
 provider VARCHAR(10) NOT NULL,mode VARCHAR(4) NOT NULL,reference VARCHAR(200) NOT NULL,
 amount_minor BIGINT NOT NULL CHECK(amount_minor BETWEEN 1 AND 100000000),refunded_on DATE NOT NULL,
 applied BOOLEAN NOT NULL DEFAULT FALSE,reason VARCHAR(1000) NOT NULL,created_by UUID,created_at TIMESTAMPTZ NOT NULL,
 UNIQUE(provider,mode,reference)
);
CREATE INDEX payments_refund_order_idx ON payments_refunds(order_id,created_at,id);
CREATE TABLE payments_notifications (
 provider VARCHAR(10) NOT NULL,mode VARCHAR(4) NOT NULL,event_id VARCHAR(250) NOT NULL,
 order_id UUID NOT NULL REFERENCES payments_orders(id),fingerprint CHAR(64) NOT NULL,received_at TIMESTAMPTZ NOT NULL,
 PRIMARY KEY(provider,mode,event_id)
);
CREATE TABLE payments_reviews (
 id UUID PRIMARY KEY,order_id UUID NOT NULL REFERENCES payments_orders(id),charge_id UUID NOT NULL REFERENCES payments_charges(id),
 version BIGINT NOT NULL CHECK(version>0),settlement_reference VARCHAR(200) NOT NULL,
 fee_minor BIGINT NOT NULL CHECK(fee_minor>=0),bank_minor BIGINT NOT NULL CHECK(bank_minor>=0),refunded_minor BIGINT NOT NULL CHECK(refunded_minor>=0),
 note VARCHAR(1000) NOT NULL,created_by UUID NOT NULL,created_at TIMESTAMPTZ NOT NULL,request_hash CHAR(64) NOT NULL,
 UNIQUE(charge_id,version)
);
CREATE TABLE payments_history (
 id UUID PRIMARY KEY,order_id UUID NOT NULL REFERENCES payments_orders(id),action VARCHAR(40) NOT NULL,
 note VARCHAR(1000) NOT NULL,actor_id UUID,occurred_at TIMESTAMPTZ NOT NULL
);
CREATE INDEX payments_history_order_idx ON payments_history(order_id,occurred_at,id);
CREATE TABLE giving_payment_sources (
 gift_id UUID PRIMARY KEY REFERENCES giving_gifts(id),charge_id UUID NOT NULL UNIQUE,reference VARCHAR(200) NOT NULL UNIQUE
);
CREATE TABLE giving_payment_refunds (
 id UUID PRIMARY KEY,gift_id UUID NOT NULL REFERENCES giving_payment_sources(gift_id),fund_id UUID NOT NULL,
 amount_minor BIGINT NOT NULL CHECK(amount_minor>0),refunded_on DATE NOT NULL,reference VARCHAR(200) NOT NULL UNIQUE,
 created_by UUID NOT NULL,created_at TIMESTAMPTZ NOT NULL,
 FOREIGN KEY(gift_id,fund_id) REFERENCES giving_allocations(gift_id,fund_id)
);
CREATE INDEX giving_payment_refund_gift_idx ON giving_payment_refunds(gift_id,refunded_on,id);
INSERT INTO permissions(id,code,description) VALUES
 ('00000000-0000-0000-0000-000000000044','payments.manage','Create hosted giving checkouts and review verified payments'),
 ('00000000-0000-0000-0000-000000000045','payments.refund','Record provider-completed refunds'),
 ('00000000-0000-0000-0000-000000000046','payments.reconcile','Record payment settlement evidence and retry ledger posting');
INSERT INTO role_permissions(role_id,permission_id) SELECT r.id,p.id FROM roles r CROSS JOIN permissions p
 WHERE r.name='administrator' AND p.code IN ('payments.manage','payments.refund','payments.reconcile');
-- [jooq ignore start]
CREATE UNIQUE INDEX payments_monthly_unique ON payments_orders(donor_id,fund_id,provider,mode) WHERE recurring AND subscription_status<>'CANCELLED';
ALTER TABLE giving_gifts DROP CONSTRAINT giving_gifts_method_check;
ALTER TABLE giving_gifts ADD CONSTRAINT giving_gifts_method_check CHECK(method IN ('CASH','BANK','OTHER','ONLINE'));
CREATE TRIGGER giving_payment_source_immutable BEFORE UPDATE OR DELETE ON giving_payment_sources FOR EACH ROW EXECUTE FUNCTION giving_reject_mutation();
CREATE TRIGGER giving_payment_refund_immutable BEFORE UPDATE OR DELETE ON giving_payment_refunds FOR EACH ROW EXECUTE FUNCTION giving_reject_mutation();
CREATE TRIGGER payments_notification_immutable BEFORE UPDATE OR DELETE ON payments_notifications FOR EACH ROW EXECUTE FUNCTION giving_reject_mutation();
CREATE TRIGGER payments_history_immutable BEFORE UPDATE OR DELETE ON payments_history FOR EACH ROW EXECUTE FUNCTION giving_reject_mutation();
CREATE TRIGGER payments_review_immutable BEFORE UPDATE OR DELETE ON payments_reviews FOR EACH ROW EXECUTE FUNCTION giving_reject_mutation();
CREATE FUNCTION giving_guard_payment_refund() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE original giving_gifts%ROWTYPE; prior NUMERIC;
BEGIN
 SELECT * INTO original FROM giving_gifts WHERE id=NEW.gift_id FOR UPDATE;
 SELECT COALESCE(sum(amount_minor),0) INTO prior FROM giving_payment_refunds WHERE gift_id=NEW.gift_id;
 IF original.method<>'ONLINE' OR NEW.refunded_on<original.received_on OR prior+NEW.amount_minor>original.amount_minor
 OR EXISTS(SELECT 1 FROM giving_reversals WHERE gift_id=NEW.gift_id) THEN
 RAISE EXCEPTION 'Invalid online gift refund' USING ERRCODE='23514'; END IF;
 RETURN NEW;
END $$;
CREATE TRIGGER giving_payment_refund_guard BEFORE INSERT ON giving_payment_refunds FOR EACH ROW EXECUTE FUNCTION giving_guard_payment_refund();
CREATE FUNCTION giving_guard_online_reversal() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
 IF EXISTS(SELECT 1 FROM giving_payment_sources WHERE gift_id=NEW.gift_id) THEN
 RAISE EXCEPTION 'Online gifts require payment refunds' USING ERRCODE='23514'; END IF;
 RETURN NEW;
END $$;
CREATE TRIGGER giving_online_reversal_guard BEFORE INSERT ON giving_reversals FOR EACH ROW EXECUTE FUNCTION giving_guard_online_reversal();
-- [jooq ignore stop]

-- [jooq ignore start]
CREATE OR REPLACE FUNCTION giving_guard_pledge_assignment() RETURNS trigger LANGUAGE plpgsql AS $$
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
 SELECT a.amount_minor-COALESCE((SELECT sum(r.amount_minor) FROM giving_payment_refunds r WHERE r.gift_id=g.id),0)-COALESCE((SELECT sum(x.amount_minor) FROM giving_pledge_assignments x WHERE x.gift_id=g.id AND x.fund_id=NEW.fund_id AND x.status='ACTIVE'),0)
 INTO available FROM giving_allocations a WHERE a.gift_id=g.id AND a.fund_id=NEW.fund_id;
 SELECT COALESCE(sum(x.amount_minor),0) INTO credited FROM giving_pledge_assignments x WHERE x.pledge_id=p.id AND x.status='ACTIVE'
 AND NOT EXISTS(SELECT 1 FROM giving_reversals r WHERE r.gift_id=x.gift_id);
 IF NEW.amount_minor>available OR NEW.amount_minor+credited>p.amount_minor THEN
   RAISE EXCEPTION 'Pledge or gift allocation capacity exceeded' USING ERRCODE='23514';
 END IF;
 RETURN NEW;
END $$;
CREATE OR REPLACE FUNCTION giving_check_acknowledgement() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE target UUID; source UUID;
BEGIN
 IF TG_TABLE_NAME='giving_acknowledgements' THEN target:=NEW.id; ELSE target:=NEW.acknowledgement_id; END IF;
 SELECT gift_id INTO source FROM giving_acknowledgements WHERE id=target;
 IF EXISTS(SELECT 1 FROM giving_payment_refunds WHERE gift_id=source) OR EXISTS(SELECT 1 FROM giving_reversals WHERE gift_id=source)
 OR (SELECT count(*) FROM giving_acknowledgement_lines WHERE acknowledgement_id=target)<>(SELECT count(*) FROM giving_allocations WHERE gift_id=source)
 OR EXISTS(SELECT 1 FROM giving_acknowledgement_lines l LEFT JOIN giving_allocations a ON a.gift_id=source AND a.fund_id=l.fund_id
 WHERE l.acknowledgement_id=target AND (a.gift_id IS NULL OR a.amount_minor<>l.amount_minor)) THEN
  RAISE EXCEPTION 'Acknowledgement must match the original gift allocations' USING ERRCODE='23514';
 END IF;
 RETURN NULL;
END $$;
-- [jooq ignore stop]

-- [jooq ignore start]
CREATE FUNCTION payments_guard_charge() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
 IF TG_OP='DELETE' THEN RAISE EXCEPTION 'Charges cannot be removed' USING ERRCODE='23514'; END IF;
 IF (NEW.id,NEW.order_id,NEW.provider,NEW.mode,NEW.reference,NEW.amount_minor,NEW.received_on,NEW.created_at)
 IS DISTINCT FROM (OLD.id,OLD.order_id,OLD.provider,OLD.mode,OLD.reference,OLD.amount_minor,OLD.received_on,OLD.created_at)
 OR OLD.gift_id IS NOT NULL OR NEW.gift_id IS NULL THEN RAISE EXCEPTION 'Verified charge evidence cannot change' USING ERRCODE='23514'; END IF;
 RETURN NEW;
END $$;
CREATE TRIGGER payments_charge_guard BEFORE UPDATE OR DELETE ON payments_charges FOR EACH ROW EXECUTE FUNCTION payments_guard_charge();
CREATE FUNCTION payments_guard_refund() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
 IF TG_OP='DELETE' THEN RAISE EXCEPTION 'Refunds cannot be removed' USING ERRCODE='23514'; END IF;
 IF TG_OP='UPDATE' AND ((NEW.id,NEW.order_id,NEW.provider,NEW.mode,NEW.reference,NEW.amount_minor,NEW.refunded_on,NEW.reason,NEW.created_by,NEW.created_at)
 IS DISTINCT FROM (OLD.id,OLD.order_id,OLD.provider,OLD.mode,OLD.reference,OLD.amount_minor,OLD.refunded_on,OLD.reason,OLD.created_by,OLD.created_at)
 OR OLD.applied OR (OLD.charge_id IS NOT NULL AND NEW.charge_id IS DISTINCT FROM OLD.charge_id)) THEN RAISE EXCEPTION 'Verified refund evidence cannot change' USING ERRCODE='23514'; END IF;
 IF NEW.charge_id IS NOT NULL AND NOT EXISTS(SELECT 1 FROM payments_charges c WHERE c.id=NEW.charge_id AND c.order_id=NEW.order_id AND c.provider=NEW.provider AND c.mode=NEW.mode) THEN
 RAISE EXCEPTION 'Refund must match its original payment order' USING ERRCODE='23514'; END IF;
 RETURN NEW;
END $$;
CREATE TRIGGER payments_refund_guard BEFORE INSERT OR UPDATE OR DELETE ON payments_refunds FOR EACH ROW EXECUTE FUNCTION payments_guard_refund();
CREATE FUNCTION payments_guard_order() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
 IF TG_OP='DELETE' THEN RAISE EXCEPTION 'Payment orders cannot be removed' USING ERRCODE='23514'; END IF;
 IF (NEW.id,NEW.donor_id,NEW.fund_id,NEW.amount_minor,NEW.provider,NEW.mode,NEW.recurring,NEW.request_hash,NEW.created_by,NEW.created_at)
 IS DISTINCT FROM (OLD.id,OLD.donor_id,OLD.fund_id,OLD.amount_minor,OLD.provider,OLD.mode,OLD.recurring,OLD.request_hash,OLD.created_by,OLD.created_at)
 OR NEW.version<>OLD.version+1 THEN RAISE EXCEPTION 'Original payment intent cannot change' USING ERRCODE='23514'; END IF;
 RETURN NEW;
END $$;
CREATE TRIGGER payments_order_guard BEFORE UPDATE OR DELETE ON payments_orders FOR EACH ROW EXECUTE FUNCTION payments_guard_order();
-- [jooq ignore stop]

-- [jooq ignore start]
-- Provider references are case-sensitive; manual bank references retain their normalized uniqueness.
DROP INDEX giving_gift_reference_unique;
CREATE UNIQUE INDEX giving_gift_reference_unique ON giving_gifts(lower(trim(external_reference))) WHERE external_reference IS NOT NULL AND method<>'ONLINE';
CREATE UNIQUE INDEX giving_online_reference_unique ON giving_gifts(external_reference) WHERE method='ONLINE';
ALTER TABLE payments_orders ADD CONSTRAINT payments_token_pair CHECK((encrypted_token IS NULL)=(token_hash IS NULL));
CREATE FUNCTION giving_check_online_source() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
 IF NEW.method='ONLINE' AND NOT EXISTS(SELECT 1 FROM giving_payment_sources WHERE gift_id=NEW.id) THEN
 RAISE EXCEPTION 'Online gift requires trusted payment source evidence' USING ERRCODE='23514'; END IF;
 RETURN NULL;
END $$;
CREATE CONSTRAINT TRIGGER giving_online_source AFTER INSERT ON giving_gifts DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION giving_check_online_source();
-- [jooq ignore stop]

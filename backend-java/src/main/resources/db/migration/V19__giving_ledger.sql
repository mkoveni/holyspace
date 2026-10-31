CREATE TABLE giving_funds (
    id UUID PRIMARY KEY, name VARCHAR(100) NOT NULL CHECK(length(trim(name))>0),
    description VARCHAR(1000) NOT NULL, active BOOLEAN NOT NULL DEFAULT TRUE,
    version BIGINT NOT NULL DEFAULT 0 CHECK(version>=0), request_hash CHAR(64) NOT NULL
);
-- [jooq ignore start]
CREATE UNIQUE INDEX giving_fund_name_unique ON giving_funds(lower(trim(name)));
-- [jooq ignore stop]
CREATE TABLE giving_gifts (
    id UUID PRIMARY KEY, donor_id UUID, amount_minor BIGINT NOT NULL CHECK(amount_minor BETWEEN 1 AND 1000000000000),
    currency VARCHAR(3) NOT NULL CHECK(currency IN ('ZAR','USD','EUR','GBP')),
    received_on DATE NOT NULL, method VARCHAR(10) NOT NULL CHECK(method IN ('CASH','BANK','OTHER')),
    external_reference VARCHAR(200), request_hash CHAR(64) NOT NULL,
    created_by UUID NOT NULL, created_at TIMESTAMPTZ NOT NULL,
    CHECK(external_reference IS NULL OR length(trim(external_reference))>0)
);
-- [jooq ignore start]
CREATE UNIQUE INDEX giving_gift_reference_unique ON giving_gifts(lower(trim(external_reference))) WHERE external_reference IS NOT NULL;
-- [jooq ignore stop]
CREATE INDEX giving_gift_date_idx ON giving_gifts(received_on DESC,id DESC);
CREATE INDEX giving_gift_donor_idx ON giving_gifts(donor_id,received_on,id);
CREATE TABLE giving_allocations (
    gift_id UUID NOT NULL REFERENCES giving_gifts(id), fund_id UUID NOT NULL REFERENCES giving_funds(id),
    amount_minor BIGINT NOT NULL CHECK(amount_minor BETWEEN 1 AND 1000000000000), PRIMARY KEY(gift_id,fund_id)
);
CREATE INDEX giving_allocation_fund_idx ON giving_allocations(fund_id,gift_id);
CREATE TABLE giving_reversals (
    id UUID PRIMARY KEY, gift_id UUID NOT NULL UNIQUE REFERENCES giving_gifts(id),
    reversed_on DATE NOT NULL, reason VARCHAR(1000) NOT NULL CHECK(length(trim(reason))>0),
    request_hash CHAR(64) NOT NULL, created_by UUID NOT NULL, created_at TIMESTAMPTZ NOT NULL
);
CREATE INDEX giving_reversal_date_idx ON giving_reversals(reversed_on,gift_id);
CREATE TABLE giving_history (
    id UUID PRIMARY KEY, target_id UUID NOT NULL, target_type VARCHAR(10) NOT NULL CHECK(target_type IN ('FUND','GIFT')), action VARCHAR(30) NOT NULL,
    reason VARCHAR(1000) NOT NULL, actor_id UUID NOT NULL, occurred_at TIMESTAMPTZ NOT NULL
);
CREATE INDEX giving_history_target_idx ON giving_history(target_type,target_id,occurred_at,id);
INSERT INTO permissions(id,code,description) VALUES
 ('00000000-0000-0000-0000-000000000038','giving.read','View the confidential Giving ledger and fund totals'),
 ('00000000-0000-0000-0000-000000000039','giving.manage','Manage funds and record manual gifts'),
 ('00000000-0000-0000-0000-000000000040','giving.reverse','Record full linked gift reversals');
INSERT INTO role_permissions(role_id,permission_id) SELECT r.id,p.id FROM roles r CROSS JOIN permissions p
 WHERE r.name='administrator' AND p.code IN ('giving.read','giving.manage','giving.reverse');

-- [jooq ignore start]
-- Deferred checks cover the whole aggregate after header and allocation inserts.
CREATE FUNCTION giving_check_balance() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE target UUID; expected BIGINT; allocated NUMERIC;
BEGIN
    IF TG_TABLE_NAME='giving_gifts' THEN target:=NEW.id;
    ELSIF TG_OP='DELETE' THEN target:=OLD.gift_id;
    ELSE target:=NEW.gift_id; END IF;
    SELECT amount_minor INTO expected FROM giving_gifts WHERE id=target;
    SELECT COALESCE(sum(amount_minor),0) INTO allocated FROM giving_allocations WHERE gift_id=target;
    IF expected IS NOT NULL AND expected<>allocated THEN
        RAISE EXCEPTION 'Gift allocations must balance' USING ERRCODE='23514',CONSTRAINT='giving_balance';
    END IF;
    RETURN NULL;
END $$;
CREATE CONSTRAINT TRIGGER giving_header_balance AFTER INSERT ON giving_gifts DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION giving_check_balance();
CREATE CONSTRAINT TRIGGER giving_allocation_balance AFTER INSERT OR UPDATE OR DELETE ON giving_allocations DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION giving_check_balance();
CREATE FUNCTION giving_reject_mutation() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN RAISE EXCEPTION 'Posted Giving evidence is immutable' USING ERRCODE='23514',CONSTRAINT='giving_immutable'; END $$;
CREATE TRIGGER giving_gift_immutable BEFORE UPDATE OR DELETE ON giving_gifts FOR EACH ROW EXECUTE FUNCTION giving_reject_mutation();
CREATE TRIGGER giving_allocation_immutable BEFORE UPDATE OR DELETE ON giving_allocations FOR EACH ROW EXECUTE FUNCTION giving_reject_mutation();
CREATE TRIGGER giving_reversal_immutable BEFORE UPDATE OR DELETE ON giving_reversals FOR EACH ROW EXECUTE FUNCTION giving_reject_mutation();
CREATE TRIGGER giving_history_immutable BEFORE UPDATE OR DELETE ON giving_history FOR EACH ROW EXECUTE FUNCTION giving_reject_mutation();
-- [jooq ignore stop]

CREATE TABLE giving_donor_links (
    account_id UUID PRIMARY KEY,
    person_id UUID NOT NULL UNIQUE,
    active BOOLEAN NOT NULL,
    version BIGINT NOT NULL CHECK (version > 0),
    verified_by UUID NOT NULL,
    verified_at TIMESTAMPTZ NOT NULL
);
CREATE TABLE giving_donor_link_history (
    id UUID PRIMARY KEY,
    account_id UUID NOT NULL REFERENCES giving_donor_links(account_id),
    person_id UUID NOT NULL,
    active BOOLEAN NOT NULL,
    version BIGINT NOT NULL,
    actor_id UUID NOT NULL,
    evidence VARCHAR(1000) NOT NULL,
    occurred_at TIMESTAMPTZ NOT NULL,
    UNIQUE (account_id, version)
);
CREATE TABLE giving_online_funds (
    fund_id UUID PRIMARY KEY REFERENCES giving_funds(id),
    enabled BOOLEAN NOT NULL DEFAULT FALSE
);
ALTER TABLE payments_orders ADD COLUMN self_service BOOLEAN NOT NULL DEFAULT FALSE;
CREATE INDEX payments_donor_date_idx ON payments_orders(donor_id, created_at DESC, id);
-- [jooq ignore start]
CREATE TRIGGER giving_donor_link_history_immutable BEFORE UPDATE OR DELETE ON giving_donor_link_history
FOR EACH ROW EXECUTE FUNCTION giving_reject_mutation();
-- [jooq ignore stop]

CREATE TABLE identity_member_links (
    account_id UUID PRIMARY KEY REFERENCES user_accounts(id),
    person_id UUID NOT NULL UNIQUE,
    active BOOLEAN NOT NULL,
    version BIGINT NOT NULL CHECK (version > 0),
    verified_by UUID NOT NULL,
    verified_at TIMESTAMPTZ NOT NULL
);
CREATE TABLE identity_member_link_history (
    id UUID PRIMARY KEY,
    account_id UUID NOT NULL REFERENCES identity_member_links(account_id),
    person_id UUID NOT NULL,
    active BOOLEAN NOT NULL,
    version BIGINT NOT NULL,
    actor_id UUID NOT NULL,
    evidence VARCHAR(1000) NOT NULL,
    occurred_at TIMESTAMPTZ NOT NULL,
    UNIQUE (account_id, version)
);
ALTER TABLE account_sessions ADD COLUMN device_name VARCHAR(80) NOT NULL DEFAULT 'Web browser';
CREATE INDEX account_sessions_account_date_idx ON account_sessions(account_id,created_at DESC,id);
CREATE TABLE payments_mobile_checkouts (
    token_hash VARCHAR(64) PRIMARY KEY,
    order_id UUID NOT NULL REFERENCES payments_orders(id),
    account_id UUID NOT NULL,
    session_id UUID NOT NULL,
    encrypted_checkout TEXT NOT NULL,
    expires_at TIMESTAMPTZ NOT NULL,
    used_at TIMESTAMPTZ
);
CREATE INDEX payments_mobile_checkout_expiry_idx ON payments_mobile_checkouts(expires_at);
-- [jooq ignore start]
CREATE TRIGGER identity_member_link_history_immutable BEFORE UPDATE OR DELETE ON identity_member_link_history
FOR EACH ROW EXECUTE FUNCTION giving_reject_mutation();
-- [jooq ignore stop]

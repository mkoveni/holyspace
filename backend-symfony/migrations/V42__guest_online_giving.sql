-- Public (guest) online giving: a payment order may have no person. The giver's email is compulsory (confirmation),
-- stored encrypted; the return/status page is reached only through a random access token kept as a hash.
ALTER TABLE payments_orders ALTER COLUMN donor_id DROP NOT NULL;
ALTER TABLE payments_orders
    ADD COLUMN guest_name VARCHAR(200),
    ADD COLUMN guest_email_enc TEXT,
    ADD COLUMN guest_email_hash CHAR(64),
    ADD COLUMN guest_access_hash CHAR(64) UNIQUE;
ALTER TABLE payments_orders ADD CONSTRAINT payments_guest_shape CHECK (
    (donor_id IS NOT NULL AND guest_name IS NULL AND guest_email_enc IS NULL AND guest_email_hash IS NULL AND guest_access_hash IS NULL)
    OR (donor_id IS NULL AND NOT recurring AND NOT self_service
        AND guest_email_enc IS NOT NULL AND guest_email_hash IS NOT NULL AND guest_access_hash IS NOT NULL)
);
CREATE INDEX payments_guest_email_idx ON payments_orders(guest_email_hash, created_at DESC) WHERE guest_email_hash IS NOT NULL;

-- [jooq ignore start]
CREATE OR REPLACE FUNCTION payments_guard_order() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
 IF TG_OP='DELETE' THEN RAISE EXCEPTION 'Payment orders cannot be removed' USING ERRCODE='23514'; END IF;
 IF (NEW.id,NEW.donor_id,NEW.fund_id,NEW.amount_minor,NEW.provider,NEW.mode,NEW.recurring,NEW.configuration_id,NEW.request_hash,NEW.created_by,NEW.created_at,
     NEW.guest_name,NEW.guest_email_enc,NEW.guest_email_hash,NEW.guest_access_hash)
 IS DISTINCT FROM (OLD.id,OLD.donor_id,OLD.fund_id,OLD.amount_minor,OLD.provider,OLD.mode,OLD.recurring,OLD.configuration_id,OLD.request_hash,OLD.created_by,OLD.created_at,
     OLD.guest_name,OLD.guest_email_enc,OLD.guest_email_hash,OLD.guest_access_hash)
 OR NEW.version<>OLD.version+1 THEN RAISE EXCEPTION 'Original payment intent cannot change' USING ERRCODE='23514'; END IF;
 RETURN NEW;
END $$;
-- [jooq ignore stop]

-- The automatic confirmation of a verified guest payment. One per charge; the queued delivery is frozen to the
-- reviewed content, and its eligibility is re-judged from this row before every send.
CREATE TABLE payments_guest_confirmations (
    id UUID PRIMARY KEY,
    order_id UUID NOT NULL REFERENCES payments_orders(id),
    charge_id UUID NOT NULL UNIQUE REFERENCES payments_charges(id),
    gift_id UUID NOT NULL,
    delivery_id UUID NOT NULL UNIQUE,
    review_hash CHAR(64) NOT NULL,
    created_at TIMESTAMPTZ NOT NULL
);
CREATE INDEX payments_guest_confirmation_order_idx ON payments_guest_confirmations(order_id, created_at);
-- [jooq ignore start]
CREATE TRIGGER payments_guest_confirmation_immutable BEFORE UPDATE OR DELETE ON payments_guest_confirmations FOR EACH ROW EXECUTE FUNCTION giving_reject_mutation();
-- [jooq ignore stop]

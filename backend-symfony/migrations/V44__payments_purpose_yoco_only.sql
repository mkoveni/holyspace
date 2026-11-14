-- Payments becomes purpose-aware (GIVING today, TICKET next) and Yoco-only.
-- Pre-flight: the pilot never used Payfast. If any order is not Yoco or is recurring, stop and review the data.
DO $$
BEGIN
    IF EXISTS (SELECT 1 FROM payments_orders WHERE provider <> 'yoco' OR recurring) THEN
        RAISE EXCEPTION 'Payfast or recurring payment orders exist; review the pilot data before removing Payfast' USING ERRCODE = '23514';
    END IF;
END $$;

-- The Payfast settings head stays (configuration history and revisions are immutable evidence) but is switched off for good.
UPDATE payments_provider_settings SET enabled = FALSE, monthly = FALSE WHERE provider = 'payfast';

ALTER TABLE payments_orders DROP CONSTRAINT payments_orders_provider_check;
ALTER TABLE payments_orders ADD CONSTRAINT payments_orders_provider_check CHECK (provider = 'yoco');

ALTER TABLE payments_orders
    ADD COLUMN purpose VARCHAR(10) NOT NULL DEFAULT 'GIVING' CHECK (purpose IN ('GIVING', 'TICKET')),
    ADD COLUMN purpose_ref UUID;
ALTER TABLE payments_orders ALTER COLUMN fund_id DROP NOT NULL;
ALTER TABLE payments_orders ADD CONSTRAINT payments_purpose_shape CHECK (
    (purpose = 'GIVING' AND fund_id IS NOT NULL AND purpose_ref IS NULL)
    OR (purpose = 'TICKET' AND fund_id IS NULL AND purpose_ref IS NOT NULL AND donor_id IS NULL)
);
CREATE UNIQUE INDEX payments_purpose_ref_idx ON payments_orders(purpose, purpose_ref) WHERE purpose_ref IS NOT NULL;

-- [jooq ignore start]
CREATE OR REPLACE FUNCTION payments_guard_order() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
 IF TG_OP='DELETE' THEN RAISE EXCEPTION 'Payment orders cannot be removed' USING ERRCODE='23514'; END IF;
 IF (NEW.id,NEW.donor_id,NEW.fund_id,NEW.amount_minor,NEW.provider,NEW.mode,NEW.recurring,NEW.configuration_id,NEW.request_hash,NEW.created_by,NEW.created_at,
     NEW.guest_name,NEW.guest_email_enc,NEW.guest_email_hash,NEW.guest_access_hash,NEW.purpose,NEW.purpose_ref)
 IS DISTINCT FROM (OLD.id,OLD.donor_id,OLD.fund_id,OLD.amount_minor,OLD.provider,OLD.mode,OLD.recurring,OLD.configuration_id,OLD.request_hash,OLD.created_by,OLD.created_at,
     OLD.guest_name,OLD.guest_email_enc,OLD.guest_email_hash,OLD.guest_access_hash,OLD.purpose,OLD.purpose_ref)
 OR NEW.version<>OLD.version+1 THEN RAISE EXCEPTION 'Original payment intent cannot change' USING ERRCODE='23514'; END IF;
 RETURN NEW;
END $$;
-- [jooq ignore stop]

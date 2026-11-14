-- Phase 4 of docs/public-events-and-forms-design.md: resendable ticket emails, insert-only invoices and credit notes
-- (section 6) and refund selection (5.8). Door scanning needs no table: a ticket's check-in lives on event_tickets.

-- A buyer or staff member may ask for the tickets email again, so an order can have several (the newest is current).
ALTER TABLE event_ticket_emails DROP CONSTRAINT event_ticket_emails_order_id_key;
CREATE INDEX event_ticket_emails_order_idx ON event_ticket_emails(order_id, created_at DESC);

-- A void ticket whose credit_note_id is null is waiting for its refund; the refund that matches the sum of these prices
-- credits them (nothing is guessed). A full refund credits every remaining ticket.
ALTER TABLE event_tickets ADD COLUMN credit_note_id UUID;
-- A partial refund that matched no selection waits here until staff choose the tickets it covers.
ALTER TABLE event_ticket_orders ADD COLUMN unmatched_refund_minor BIGINT NOT NULL DEFAULT 0 CHECK (unmatched_refund_minor >= 0);

-- The issuer's details, versioned by effective date. Rows are never edited: a change is a new row, so documents already
-- issued keep the details (and the VAT status) they were issued with.
CREATE TABLE event_invoice_settings (
    id UUID PRIMARY KEY,
    effective_from DATE NOT NULL UNIQUE,
    legal_name VARCHAR(150) NOT NULL,
    address VARCHAR(300) NOT NULL,
    registration_number VARCHAR(40) NOT NULL,
    npo_number VARCHAR(40) NOT NULL,
    vat_number VARCHAR(20) NOT NULL,
    vat_registered BOOLEAN NOT NULL,
    vat_rate_bp INTEGER NOT NULL CHECK (vat_rate_bp BETWEEN 0 AND 5000),
    created_at TIMESTAMPTZ NOT NULL,
    CHECK (NOT vat_registered OR vat_number <> '')
);
INSERT INTO event_invoice_settings (id, effective_from, legal_name, address, registration_number, npo_number, vat_number, vat_registered, vat_rate_bp, created_at)
VALUES ('20000000-0000-0000-0000-000000000001', DATE '2000-01-01', 'Divine Community Space', '', '', '', '', FALSE, 1500, CURRENT_TIMESTAMP);

-- Gapless numbers: the counter moves in the same transaction as the document, so a rollback gives the number back.
CREATE TABLE event_invoice_counters (
    series VARCHAR(3) PRIMARY KEY CHECK (series IN ('INV', 'CN')),
    last_number BIGINT NOT NULL CHECK (last_number >= 0)
);
INSERT INTO event_invoice_counters (series, last_number) VALUES ('INV', 0), ('CN', 0);

CREATE TABLE event_invoices (
    id UUID PRIMARY KEY,
    number VARCHAR(20) NOT NULL UNIQUE,
    title VARCHAR(20) NOT NULL CHECK (title IN ('Invoice', 'Tax Invoice')),
    ticket_order_id UUID NOT NULL UNIQUE REFERENCES event_ticket_orders(id),
    settings_id UUID NOT NULL REFERENCES event_invoice_settings(id),
    issued_on DATE NOT NULL,
    buyer_name VARCHAR(100) NOT NULL,
    buyer_address VARCHAR(300) NOT NULL DEFAULT '',
    buyer_vat_number VARCHAR(20) NOT NULL DEFAULT '',
    event_title VARCHAR(200) NOT NULL,
    subtotal_minor BIGINT NOT NULL CHECK (subtotal_minor >= 0),
    fee_minor BIGINT NOT NULL CHECK (fee_minor >= 0),
    vat_minor BIGINT NOT NULL CHECK (vat_minor >= 0),
    total_minor BIGINT NOT NULL CHECK (total_minor = subtotal_minor + fee_minor),
    vat_rate_bp INTEGER NOT NULL CHECK (vat_rate_bp BETWEEN 0 AND 5000),
    created_at TIMESTAMPTZ NOT NULL
);
CREATE TABLE event_invoice_lines (
    id UUID PRIMARY KEY,
    invoice_id UUID NOT NULL REFERENCES event_invoices(id),
    position INTEGER NOT NULL CHECK (position >= 0),
    description VARCHAR(300) NOT NULL,
    quantity INTEGER NOT NULL CHECK (quantity > 0),
    unit_minor BIGINT NOT NULL CHECK (unit_minor >= 0),
    line_minor BIGINT NOT NULL CHECK (line_minor = quantity * unit_minor),
    UNIQUE (invoice_id, position)
);
CREATE TABLE event_credit_notes (
    id UUID PRIMARY KEY,
    number VARCHAR(20) NOT NULL UNIQUE,
    invoice_id UUID NOT NULL REFERENCES event_invoices(id),
    issued_on DATE NOT NULL,
    total_minor BIGINT NOT NULL CHECK (total_minor > 0),
    vat_minor BIGINT NOT NULL CHECK (vat_minor >= 0),
    created_at TIMESTAMPTZ NOT NULL
);
CREATE INDEX event_credit_notes_invoice_idx ON event_credit_notes(invoice_id);
CREATE TABLE event_credit_note_lines (
    id UUID PRIMARY KEY,
    credit_note_id UUID NOT NULL REFERENCES event_credit_notes(id),
    position INTEGER NOT NULL CHECK (position >= 0),
    description VARCHAR(300) NOT NULL,
    line_minor BIGINT NOT NULL CHECK (line_minor > 0),
    UNIQUE (credit_note_id, position)
);

-- [jooq ignore start]
CREATE TRIGGER event_invoice_settings_immutable BEFORE UPDATE OR DELETE ON event_invoice_settings FOR EACH ROW EXECUTE FUNCTION giving_reject_mutation();
CREATE TRIGGER event_invoices_immutable BEFORE UPDATE OR DELETE ON event_invoices FOR EACH ROW EXECUTE FUNCTION giving_reject_mutation();
CREATE TRIGGER event_invoice_lines_immutable BEFORE UPDATE OR DELETE ON event_invoice_lines FOR EACH ROW EXECUTE FUNCTION giving_reject_mutation();
CREATE TRIGGER event_credit_notes_immutable BEFORE UPDATE OR DELETE ON event_credit_notes FOR EACH ROW EXECUTE FUNCTION giving_reject_mutation();
CREATE TRIGGER event_credit_note_lines_immutable BEFORE UPDATE OR DELETE ON event_credit_note_lines FOR EACH ROW EXECUTE FUNCTION giving_reject_mutation();
-- [jooq ignore stop]

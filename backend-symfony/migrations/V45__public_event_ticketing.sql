-- Public paid events (docs/public-events-and-forms-design.md section 5). A buyer is never a person: there is deliberately
-- no person_id anywhere below. The ticket order id is also the payments order id (purpose TICKET, purpose_ref = the same id).

-- Processing-fee estimate shown to the buyer as its own line (D4, D6). One row, versioned; the rate is a setting, not a constant.
CREATE TABLE event_ticket_settings (
    id SMALLINT PRIMARY KEY CHECK (id = 1),
    fee_rate_ppm INTEGER NOT NULL CHECK (fee_rate_ppm BETWEEN 0 AND 200000),
    fee_fixed_minor BIGINT NOT NULL CHECK (fee_fixed_minor BETWEEN 0 AND 100000),
    provider_vat_ppm INTEGER NOT NULL CHECK (provider_vat_ppm BETWEEN 0 AND 500000),
    hold_minutes INTEGER NOT NULL CHECK (hold_minutes BETWEEN 5 AND 60),
    version BIGINT NOT NULL CHECK (version >= 0)
);
-- Yoco Core: 2.95 % of the charge plus Yoco's 15 % VAT on its fee; seats are held for 15 minutes (A5).
INSERT INTO event_ticket_settings (id, fee_rate_ppm, fee_fixed_minor, provider_vat_ppm, hold_minutes, version) VALUES (1, 29500, 0, 150000, 15, 0);

CREATE TABLE event_public_sales (
    occurrence_id UUID PRIMARY KEY REFERENCES event_occurrences(id),
    enabled BOOLEAN NOT NULL,
    sales_open_at TIMESTAMPTZ,
    sales_close_at TIMESTAMPTZ,
    max_per_order INTEGER NOT NULL CHECK (max_per_order BETWEEN 1 AND 50),
    version BIGINT NOT NULL CHECK (version >= 0),
    CHECK (sales_open_at IS NULL OR sales_close_at IS NULL OR sales_close_at > sales_open_at)
);

CREATE TABLE event_ticket_types (
    id UUID PRIMARY KEY,
    occurrence_id UUID NOT NULL REFERENCES event_occurrences(id),
    name VARCHAR(80) NOT NULL,
    description VARCHAR(300) NOT NULL DEFAULT '',
    price_minor BIGINT NOT NULL CHECK (price_minor BETWEEN 0 AND 100000000),
    quantity_cap INTEGER CHECK (quantity_cap BETWEEN 1 AND 10000),
    sort_order INTEGER NOT NULL DEFAULT 0,
    active BOOLEAN NOT NULL DEFAULT TRUE,
    version BIGINT NOT NULL CHECK (version >= 0),
    UNIQUE (occurrence_id, name)
);
CREATE INDEX event_ticket_types_occurrence_idx ON event_ticket_types(occurrence_id, sort_order, id);

CREATE TABLE event_ticket_orders (
    id UUID PRIMARY KEY,
    occurrence_id UUID NOT NULL REFERENCES event_occurrences(id),
    status VARCHAR(24) NOT NULL CHECK (status IN ('RESERVED', 'PAID', 'EXPIRED', 'RELEASED', 'LATE_PAID_REVIEW', 'PARTIALLY_REFUNDED', 'REFUNDED', 'REFUND_REVIEW')),
    buyer_name VARCHAR(100) NOT NULL,
    buyer_email_enc TEXT NOT NULL,
    buyer_email_hash CHAR(64) NOT NULL,
    access_hash CHAR(64) NOT NULL UNIQUE,
    wallet_hash CHAR(64) NOT NULL UNIQUE,
    request_hash CHAR(64) NOT NULL,
    subtotal_minor BIGINT NOT NULL CHECK (subtotal_minor >= 0),
    fee_minor BIGINT NOT NULL CHECK (fee_minor >= 0),
    total_minor BIGINT NOT NULL CHECK (total_minor = subtotal_minor + fee_minor),
    expires_at TIMESTAMPTZ NOT NULL,
    created_at TIMESTAMPTZ NOT NULL,
    paid_at TIMESTAMPTZ,
    version BIGINT NOT NULL CHECK (version >= 0)
);
CREATE INDEX event_ticket_orders_occurrence_idx ON event_ticket_orders(occurrence_id, status, expires_at);
CREATE INDEX event_ticket_orders_email_idx ON event_ticket_orders(buyer_email_hash, created_at);

CREATE TABLE event_ticket_order_lines (
    id UUID PRIMARY KEY,
    order_id UUID NOT NULL REFERENCES event_ticket_orders(id),
    ticket_type_id UUID NOT NULL REFERENCES event_ticket_types(id),
    type_name_snapshot VARCHAR(80) NOT NULL,
    quantity INTEGER NOT NULL CHECK (quantity BETWEEN 1 AND 50),
    unit_price_minor BIGINT NOT NULL CHECK (unit_price_minor >= 0),
    UNIQUE (order_id, ticket_type_id)
);
CREATE INDEX event_ticket_order_lines_type_idx ON event_ticket_order_lines(ticket_type_id);

CREATE TABLE event_tickets (
    id UUID PRIMARY KEY,
    order_id UUID NOT NULL REFERENCES event_ticket_orders(id),
    line_id UUID NOT NULL REFERENCES event_ticket_order_lines(id),
    code_hash CHAR(64) NOT NULL UNIQUE,
    holder_name VARCHAR(100) NOT NULL,
    status VARCHAR(12) NOT NULL CHECK (status IN ('ISSUED', 'CHECKED_IN', 'VOID')),
    checked_in_at TIMESTAMPTZ,
    version BIGINT NOT NULL CHECK (version >= 0),
    CHECK ((status = 'CHECKED_IN') = (checked_in_at IS NOT NULL) OR status = 'VOID')
);
CREATE INDEX event_tickets_order_idx ON event_tickets(order_id);

INSERT INTO permissions(id, code, description) VALUES
    ('00000000-0000-0000-0000-00000000004f', 'events.tickets.read', 'See public ticket orders and counts'),
    ('00000000-0000-0000-0000-000000000050', 'events.tickets.contacts', 'See ticket buyers'' contact details'),
    ('00000000-0000-0000-0000-000000000051', 'events.tickets.manage', 'Configure public ticket sales, ticket types and the processing-fee estimate'),
    ('00000000-0000-0000-0000-000000000052', 'events.tickets.scan', 'Scan tickets at the door');
INSERT INTO role_permissions(role_id, permission_id)
    SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
    WHERE r.name = 'administrator' AND p.code IN ('events.tickets.read', 'events.tickets.contacts', 'events.tickets.manage', 'events.tickets.scan');
